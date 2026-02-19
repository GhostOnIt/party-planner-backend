<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Helpers\StorageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\DuplicateEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Jobs\NotifyGuestsOfStatusChangeJob;
use App\Models\Event;
use App\Models\EventCreationInvitation;
use App\Models\User;
use App\Notifications\EventCreatedForUserNotification;
use App\Services\EventService;
use App\Services\EventStatusService;
use App\Services\PermissionService;
use App\Services\PhotoService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    protected PhotoService $photoService;
    protected PermissionService $permissionService;
    protected QuotaService $quotaService;
    protected SubscriptionService $subscriptionService;
    protected EventService $eventService;
    protected EventStatusService $eventStatusService;

    public function __construct(
        PhotoService $photoService,
        PermissionService $permissionService,
        QuotaService $quotaService,
        SubscriptionService $subscriptionService,
        EventService $eventService,
        EventStatusService $eventStatusService
    ) {
        $this->photoService = $photoService;
        $this->permissionService = $permissionService;
        $this->quotaService = $quotaService;
        $this->subscriptionService = $subscriptionService;
        $this->eventService = $eventService;
        $this->eventStatusService = $eventStatusService;
    }

    /**
     * Display a listing of events.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // All users see: own events + collaborator events + events with pending claim (admin created for them)
        $query = Event::where(function ($q) use ($user) {
            $q->where('user_id', $user->id) // Events created by user
              ->orWhereHas('collaborators', function ($collaboratorQuery) use ($user) {
                  $collaboratorQuery->where('user_id', $user->id)
                                   ->whereNotNull('accepted_at'); // Events where user is accepted collaborator
              })
              ->orWhereHas('eventCreationInvitations', function ($invQuery) use ($user) {
                  $invQuery->where('email', $user->email); // Pending events to claim
              });
        });

        // Filter by upcoming events only (date >= today)
        if ($request->boolean('upcoming')) {
            $query->where('date', '>=', now()->startOfDay());
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Search by title
        if ($search = $request->input('search')) {
            $query->where('title', 'ilike', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'date');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->input('per_page', 10);
        $events = $query
            ->with([
                'user:id,name,avatar',
                'coverPhoto:id,event_id,url,thumbnail_url',
                'featuredPhoto:id,event_id,url,thumbnail_url',
                'eventCreationInvitations' => function ($q) use ($user) {
                    $q->where('email', $user->email)->select('event_id', 'token');
                }
            ])
            ->withCount([
                'guests',
                'guests as guests_confirmed_count' => function ($query) {
                    $query->where('rsvp_status', 'accepted');
                },
                'guests as guests_declined_count' => function ($query) {
                    $query->where('rsvp_status', 'declined');
                },
                'guests as guests_pending_count' => function ($query) {
                    $query->where('rsvp_status', 'pending');
                },
                'tasks',
                'tasks as tasks_completed_count' => function ($query) {
                    $query->where('status', 'completed');
                }
            ])
            ->withSum('budgetItems as budget_spent', 'actual_cost')
            ->paginate($perPage);

        // Add pending_claim and claim_token for events with invitation
        $events->getCollection()->transform(function ($event) use ($user) {
            $invitation = $event->eventCreationInvitations->first();
            if ($invitation && strtolower($invitation->email) === strtolower($user->email)) {
                $event->pending_claim = true;
                $event->claim_token = $invitation->token;
            } else {
                $event->pending_claim = false;
            }
            $event->unsetRelation('eventCreationInvitations');
            return $event;
        });

        return response()->json($events);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $user = $request->user();
        $owner = $user;

        // Admin may create an event for another user by providing email
        $ownerEmail = null;
        if ($user->isAdmin() && $request->filled('owner_email')) {
            $ownerEmail = strtolower(trim($request->input('owner_email')));
            $targetUser = User::where('email', $ownerEmail)->first();
            if ($targetUser) {
                $owner = $targetUser;
                // L'admin ne peut pas créer d'événement pour un utilisateur sans abonnement actif ou dont le quota est à 0
                if (!$this->quotaService->canCreateEvent($owner)) {
                    $quota = $this->quotaService->getCreationsQuota($owner);
                    $subscription = $this->subscriptionService->getUserActiveSubscription($owner);
                    $message = !$subscription
                        ? "Impossible de créer un événement pour cet utilisateur : il n'a pas d'abonnement actif."
                        : "Impossible de créer un événement pour cet utilisateur : son quota de création est atteint (0 événement disponible).";

                    return response()->json([
                        'message' => $message,
                        'error' => !$subscription ? 'target_no_subscription' : 'target_quota_exceeded',
                        'quota' => $quota,
                    ], 403);
                }
            }
            // If no user found, event will be created for admin; pending invitation + email sent
        }

        // Les admins n'ont pas besoin d'abonnement pour créer un événement pour eux-mêmes
        // Vérifier le quota avant de créer l'événement (sauf pour les admins qui créent pour eux-mêmes)
        if (!$user->isAdmin() && !$this->quotaService->canCreateEvent($user)) {
 
            $quota = $this->quotaService->getCreationsQuota($user);
            $subscription = $this->subscriptionService->getUserActiveSubscription($user);
            
            // Distinguer "pas d'abonnement" vs "quota atteint"
            if (!$subscription) {
                // Pas d'abonnement actif
                return response()->json([
                    'message' => 'Vous devez souscrire à un plan pour créer un événement.',
                    'error' => 'no_subscription',
                    'quota' => $quota,
                    'actions' => [
                        'subscribe' => [
                            'label' => 'Voir les plans',
                            'url' => '/plans',
                        ],
                    ],
                ], 403);
            } else {
                // Abonnement actif mais quota atteint
                return response()->json([
                    'message' => 'Quota de création d\'événements atteint.',
                    'error' => 'quota_exceeded',
                    'quota' => $quota,
                    'actions' => [
                        'upgrade' => [
                            'label' => 'Passer à un plan supérieur',
                            'url' => '/plans',
                        ],
                        'topup' => [
                            'label' => 'Acheter des crédits supplémentaires',
                            'url' => '/top-up',
                        ],
                    ],
                ], 403);
            }
        }

        $validated = $request->validated();
        $coverPhoto = $request->file('cover_photo');
        // Récupérer template_id depuis les données validées
        // Si présent et > 0, on l'utilise
        // Si présent et null, on ne fait pas d'auto-application
        // Si absent, on laisse null pour l'auto-application
        $templateId = $validated['template_id'] ?? null;
        $hasUserCoverPhoto = $request->hasFile('cover_photo') && $coverPhoto && $coverPhoto->isValid();

        // Debug: Log file information
        if ($coverPhoto) {
            Log::info('Cover photo received', [
                'has_file' => $request->hasFile('cover_photo'),
                'is_valid' => $coverPhoto->isValid(),
                'size' => $coverPhoto->getSize(),
                'mime_type' => $coverPhoto->getMimeType(),
                'original_name' => $coverPhoto->getClientOriginalName(),
                'extension' => $coverPhoto->getClientOriginalExtension(),
            ]);
        } else {
            Log::info('No cover photo in request', [
                'has_file' => $request->hasFile('cover_photo'),
                'all_files' => array_keys($request->allFiles()),
            ]);
        }

        // Retirer cover_photo, template_id et owner_email des données validées
        unset($validated['cover_photo']);
        unset($validated['template_id']);
        unset($validated['owner_email']);
        
        // Indiquer si l'utilisateur a fourni une photo de couverture
        $validated['_has_cover_photo'] = $hasUserCoverPhoto;

        // Créer l'événement et consommer le quota dans une transaction pour garantir la cohérence
        $finalTemplateId = ($request->has('template_id') && $templateId !== null && $templateId !== '')
            ? (int) $templateId
            : -1;

        $creatingForAnotherUser = $owner->id !== $user->id;

        try {
            $event = DB::transaction(function () use ($owner, $user, $validated, $finalTemplateId, $creatingForAnotherUser) {
                $ev = $this->eventService->create($owner, $validated, $finalTemplateId);

                // Consommer un crédit pour le propriétaire (owner)
                // Si admin crée pour lui-même : pas d'abonnement requis, consume peut échouer → on continue
                // Si création pour un autre user : consume DOIT réussir (quota vérifié avant)
                $consumed = $this->quotaService->consumeCreation($owner);

                if ($creatingForAnotherUser && !$consumed) {
                    Log::error('Event creation: consumeCreation failed for target user', [
                        'owner_id' => $owner->id,
                        'event_id' => $ev->id,
                    ]);
                    throw new \RuntimeException(
                        'Impossible de mettre à jour le quota de création. L\'événement n\'a pas été créé.'
                    );
                }

                return $ev;
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'quota_consumption_failed',
            ], 500);
        }

        // If admin created the event for another user, notify that user by email
        if ($owner->id !== $user->id) {
            $owner->notify(new EventCreatedForUserNotification($event, $user));
        }
        // If admin created for non-registered email: create pending invitation and send email
        if ($ownerEmail && $owner->id === $user->id) {
            app(\App\Services\EventCreationInvitationService::class)
                ->createPendingInvitation($event, $ownerEmail, $user);
        }

        // Récupérer l'URL de la photo de couverture du template si elle existe
        $templateCoverPhotoUrl = null;
        if ($finalTemplateId > 0) {
            $template = \App\Models\EventTemplate::find($finalTemplateId);
            if ($template && $template->cover_photo_url) {
                $templateCoverPhotoUrl = $template->cover_photo_url;
            }
        }

        // Si une photo de couverture est fournie par l'utilisateur, l'uploader et la marquer comme featured
        // Sinon, si le template a une photo de couverture, l'utiliser
        if ($hasUserCoverPhoto && $coverPhoto) {
            try {
                // Vérifier que le fichier est valide
                if (!$coverPhoto->isValid()) {
                    return response()->json([
                        'message' => 'Le fichier photo de couverture n\'est pas valide.',
                        'errors' => [
                            'cover_photo' => ['Le fichier photo de couverture n\'est pas valide.']
                        ]
                    ], 422);
                }

                $photo = $this->photoService->upload(
                    $event,
                    $coverPhoto,
                    $request->user(),
                    'event_photo'
                );
                
                // Marquer la photo comme featured (photo de couverture)
                $this->photoService->setAsFeatured($photo);
            } catch (\Exception $e) {
                Log::error('Cover photo upload failed', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'message' => 'L\'upload de la photo de couverture a échoué. Veuillez réessayer.',
                    'errors' => [
                        'cover_photo' => ['L\'upload de la photo de couverture a échoué. Veuillez réessayer.']
                    ]
                ], 422);
            }
        } elseif ($templateCoverPhotoUrl && !$hasUserCoverPhoto) {
            try {
                $sourcePath = StorageHelper::urlToPath($templateCoverPhotoUrl);
                $destinationPath = "events/{$event->id}/photos/" . basename($sourcePath);
                $sourceDisk = StorageHelper::diskForUrl($templateCoverPhotoUrl);
                $destDisk = StorageHelper::disk();

                if ($sourcePath && $sourceDisk->exists($sourcePath)) {
                    $fileContent = $sourceDisk->get($sourcePath);
                    $destDisk->put($destinationPath, $fileContent);

                    $photoUrl = StorageHelper::url($destinationPath);
                    
                    // Créer l'entrée Photo pour l'événement
                    $photo = $event->photos()->create([
                        'uploaded_by_user_id' => $request->user()->id,
                        'type' => 'event_photo',
                        'url' => $photoUrl,
                        'thumbnail_url' => $photoUrl, // Will be updated by job if needed
                        'is_featured' => true,
                    ]);
                    
                    $this->photoService->setAsFeatured($photo);
                }
            } catch (\Exception $e) {
                Log::error('Template cover photo copy failed', [
                    'event_id' => $event->id,
                    'template_photo_url' => $templateCoverPhotoUrl,
                    'error' => $e->getMessage(),
                ]);
                // Ne pas échouer la création de l'événement si la copie de la photo échoue
            }
        }

        // Charger les relations nécessaires
        $event->load([
            'coverPhoto:id,event_id,url,thumbnail_url',
            'featuredPhoto:id,event_id,url,thumbnail_url'
        ]);

        // Ajouter les infos de quota à la réponse
        $quotaInfo = $this->quotaService->getCreationsQuota($request->user());

        return response()->json([
            'event' => $event,
            'quota' => $quotaInfo,
        ], 201);
    }

    /**
     * Duplicate an event with optional overrides and options (include guests, tasks, budget).
     */
    public function duplicate(DuplicateEventRequest $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$this->quotaService->canCreateEvent($user)) {
            $quota = $this->quotaService->getCreationsQuota($user);
            $subscription = $this->subscriptionService->getUserActiveSubscription($user);
            if (!$subscription) {
                return response()->json([
                    'message' => 'Vous devez souscrire à un plan pour créer un événement.',
                    'error' => 'no_subscription',
                    'quota' => $quota,
                ], 403);
            }
            return response()->json([
                'message' => 'Quota de création d\'événements atteint.',
                'error' => 'quota_exceeded',
                'quota' => $quota,
            ], 403);
        }

        $validated = $request->validated();
        $overrides = [
            'title' => $validated['title'],
            'type' => $validated['type'] ?? $event->type,
            'date' => isset($validated['date']) ? $validated['date'] : null,
            'time' => $validated['time'] ?? $event->time?->format('H:i'),
            'location' => $validated['location'] ?? $event->location,
            'description' => $validated['description'] ?? $event->description,
            'theme' => $validated['theme'] ?? $event->theme,
            'expected_guests_count' => $validated['expected_guests_count'] ?? $event->expected_guests_count,
            'duplicate_guests' => $validated['include_guests'] ?? false,
            'duplicate_tasks' => $validated['include_tasks'] ?? true,
            'duplicate_budget' => $validated['include_budget'] ?? true,
            'duplicate_collaborators' => $validated['include_collaborators'] ?? false,
        ];

        $newEvent = $this->eventService->duplicate($event, $user, $overrides);

        if (!$user->isAdmin()) {
            $this->quotaService->consumeCreation($user);
        }

        $newEvent->load([
            'coverPhoto:id,event_id,url,thumbnail_url',
            'featuredPhoto:id,event_id,url,thumbnail_url',
        ]);

        return response()->json($newEvent, 201);
    }

    /**
     * Display the specified event.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $user = $request->user();

        // Check if this is a pending claim event (user must claim before any action)
        $invitation = EventCreationInvitation::where('event_id', $event->id)
            ->where('email', $user->email)
            ->first();

        if ($invitation) {
            // Return event with requires_claim so frontend shows claim screen (no other actions allowed)
            $event->load(['user:id,name,avatar', 'coverPhoto:id,event_id,url,thumbnail_url']);
            $event->loadCount(['guests', 'tasks']);
            $event->requires_claim = true;
            $event->claim_token = $invitation->token;

            return response()->json($event);
        }

        // Charger les relations avec les statistiques
        $event->load([
            'user:id,name,avatar',
            'coverPhoto:id,event_id,url,thumbnail_url',
            'featuredPhoto:id,event_id,url,thumbnail_url',
            'collaborators.user:id,name,avatar',
        ]);

        // Ajouter les compteurs de statistiques
        $event->loadCount([
            'guests',
            'guests as guests_confirmed_count' => function ($query) {
                $query->where('rsvp_status', 'accepted');
            },
            'guests as guests_declined_count' => function ($query) {
                $query->where('rsvp_status', 'declined');
            },
            'guests as guests_pending_count' => function ($query) {
                $query->where('rsvp_status', 'pending');
            },
            'tasks',
            'tasks as tasks_completed_count' => function ($query) {
                $query->where('status', 'completed');
            },
            'budgetItems',
            'collaborators',
        ]);

        // Somme des coûts réels (dépensé) et des coûts estimés des lignes de budget
        $event->loadSum('budgetItems as budget_spent', 'actual_cost');
        $event->loadSum('budgetItems as budget_items_estimated', 'estimated_cost');

        return response()->json($event);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:mariage,anniversaire,baby_shower,soiree,brunch,autre',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date',
            'time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'estimated_budget' => 'nullable|numeric|min:0',
            'theme' => 'nullable|string|max:255',
            'expected_guests_count' => 'nullable|integer|min:1',
            'status' => 'sometimes|required|in:upcoming,ongoing,completed,cancelled',
        ]);

        // Validate status transition rules (for all users including admins)
        if (isset($validated['status'])) {
            $newStatus = EventStatus::tryFrom($validated['status']);
            if ($newStatus) {
                if (!$this->eventStatusService->canTransitionTo($event, $newStatus)) {
                    $message = $this->eventStatusService->getTransitionErrorMessage($event, $newStatus);

                    return response()->json(['message' => $message], 422);
                }
            }
        }

        $previousStatus = $event->status;

        $event->update($validated);

        // Dispatch delayed job to notify guests (2 min) when status actually changed
        if (isset($validated['status']) && $previousStatus !== $validated['status']) {
            NotifyGuestsOfStatusChangeJob::dispatch($event->id, $validated['status'])
                ->delay(now()->addMinutes(2));
        }

        return response()->json($event);
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(null, 204);
    }



    /**
     * Get public event details (limited).
     */
    public function publicShow(Event $event): JsonResponse
    {
        return response()->json([
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'date' => $event->date,
            'time' => $event->time,
            'location' => $event->location,
            'theme' => $event->theme,
        ]);
    }

    /**
     * Get user permissions for an event.
     */
    public function getPermissions(Event $event): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'permissions' => $this->permissionService->getUserPermissions($user, $event),
            'role' => $this->getUserRole($user, $event),
            'is_owner' => $event->user_id === $user->id,
            'can_manage' => $this->permissionService->userCan($user, $event, 'collaborators.invite'),
            'can_invite' => $this->permissionService->userCan($user, $event, 'collaborators.invite'),
            'can_edit_roles' => $this->permissionService->userCan($user, $event, 'collaborators.edit_roles'),
            'can_remove_collaborators' => $this->permissionService->userCan($user, $event, 'collaborators.remove'),
            'can_create_custom_roles' => $this->permissionService->userCan($user, $event, 'collaborators.invite'), // Same as can_invite for now
        ]);
    }

    /**
     * Get user role for an event.
     */
    private function getUserRole($user, Event $event): string
    {
        if ($event->user_id === $user->id) {
            return 'owner';
        }

        $collaborator = $event->collaborators()->where('user_id', $user->id)->first();

        return $collaborator ? ($collaborator->effective_role ?? 'none') : 'none';
    }
}
