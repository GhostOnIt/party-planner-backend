<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Models\Event;
use App\Services\PermissionService;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    protected PhotoService $photoService;
    protected PermissionService $permissionService;

    public function __construct(PhotoService $photoService, PermissionService $permissionService)
    {
        $this->photoService = $photoService;
        $this->permissionService = $permissionService;
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
        $validated = $request->validated();
        $coverPhoto = $request->file('cover_photo');

        // Retirer cover_photo des données validées car ce n'est pas un champ du modèle Event
        unset($validated['cover_photo']);

        // Créer l'événement
        $event = $request->user()->events()->create($validated);

        // Si une photo de couverture est fournie, l'uploader et la marquer comme featured
        if ($coverPhoto) {
            $photo = $this->photoService->upload(
                $event,
                $coverPhoto,
                $request->user(),
                'event_photo'
            );
            
            // Marquer la photo comme featured (photo de couverture)
            $this->photoService->setAsFeatured($photo);
        }

        // Charger les relations nécessaires
        $event->load([
            'coverPhoto:id,event_id,url,thumbnail_url',
            'featuredPhoto:id,event_id,url,thumbnail_url'
        ]);

        return response()->json($event, 201);
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
