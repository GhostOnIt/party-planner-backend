<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Models\Event;
use App\Services\PermissionService;
use App\Services\PhotoService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    protected PhotoService $photoService;
    protected PermissionService $permissionService;
    protected QuotaService $quotaService;
    protected SubscriptionService $subscriptionService;

    public function __construct(
        PhotoService $photoService, 
        PermissionService $permissionService,
        QuotaService $quotaService,
        SubscriptionService $subscriptionService
    ) {
        $this->photoService = $photoService;
        $this->permissionService = $permissionService;
        $this->quotaService = $quotaService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of events.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // All users see their own events + events where they are collaborators
        $query = Event::where(function ($q) use ($user) {
            $q->where('user_id', $user->id) // Events created by user
              ->orWhereHas('collaborators', function ($collaboratorQuery) use ($user) {
                  $collaboratorQuery->where('user_id', $user->id)
                                   ->whereNotNull('accepted_at'); // Events where user is accepted collaborator
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
                'user:id,name',
                'coverPhoto:id,event_id,url,thumbnail_url',
                'featuredPhoto:id,event_id,url,thumbnail_url'
            ])
            ->withCount(['guests', 'tasks'])
            ->paginate($perPage);

        return response()->json($events);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier le quota avant de créer l'événement
        if (!$this->quotaService->canCreateEvent($user)) {
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

        // Retirer cover_photo des données validées car ce n'est pas un champ du modèle Event
        unset($validated['cover_photo']);

        // Créer l'événement
        $event = $user->events()->create($validated);

        // Consommer un crédit de création d'événement
        $this->quotaService->consumeCreation($user);

        // Si une photo de couverture est fournie, l'uploader et la marquer comme featured
        if ($coverPhoto) {
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
                    'message' => 'L\'upload de la photo de couverture a échoué.',
                    'errors' => [
                        'cover_photo' => ['L\'upload de la photo de couverture a échoué.']
                    ]
                ], 422);
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
     * Display the specified event.
     */
    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load([
            'guests',
            'tasks',
            'budgetItems',
            'photos',
            'coverPhoto',
            'featuredPhoto',
            'collaborators.user',
        ]);

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

        // Prevent manual changes to ongoing or completed status (except for admins or cancelled)
        // These statuses should be managed automatically by the scheduled command
        if (isset($validated['status'])) {
            $user = $request->user();
            $newStatus = $validated['status'];
            
            // Allow admins to set any status
            if (!$user->isAdmin()) {
                // Regular users can only set to upcoming or cancelled manually
                // ongoing and completed are managed automatically
                if (in_array($newStatus, ['ongoing', 'completed']) && $event->status !== 'cancelled') {
                    unset($validated['status']);
                }
            }
        }

        $event->update($validated);

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
