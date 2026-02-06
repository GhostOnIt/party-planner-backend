<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunicationSpot;
use App\Models\CommunicationSpotVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunicationSpotController extends Controller
{
    /**
     * List all communication spots (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $query = CommunicationSpot::query();

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->input('status');
            $now = now();

            switch ($status) {
                case 'active':
                    $query->where('is_active', true)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
                        })
                        ->where(function ($q) use ($now) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
                        });
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'scheduled':
                    $query->where('is_active', true)
                        ->where('start_date', '>', $now);
                    break;
                case 'expired':
                    $query->where('end_date', '<', $now);
                    break;
            }
        }

        // Filter by location
        if ($request->has('location')) {
            $query->whereJsonContains('display_locations', $request->input('location'));
        }

        // Search
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('poll_question', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $spots = $query->orderBy('priority', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($spots->items())->map(fn($spot) => $spot->toApiResponse()),
            'current_page' => $spots->currentPage(),
            'last_page' => $spots->lastPage(),
            'per_page' => $spots->perPage(),
            'total' => $spots->total(),
            'from' => $spots->firstItem(),
            'to' => $spots->lastItem(),
        ]);
    }

    /**
     * Get a single spot (admin).
     */
    public function show(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        return response()->json($spot->toApiResponse());
    }

    /**
     * Create a new spot (admin).
     */
    public function store(Request $request): JsonResponse
    {
        // Parse JSON strings from FormData
        $data = $request->all();
        $jsonFields = ['primaryButton', 'secondaryButton', 'pollOptions', 'displayLocations', 'targetRoles'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }
        
        // Handle boolean from FormData
        if (isset($data['isActive'])) {
            $data['isActive'] = filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN);
        }
        
        $request->merge($data);

        $validated = $request->validate([
            'type' => 'required|in:banner,poll',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'badge' => 'nullable|string|max:50',
            'badgeType' => 'nullable|in:live,new,promo',
            'primaryButton' => 'nullable|array',
            'primaryButton.label' => 'nullable|string|max:100',
            'primaryButton.href' => 'nullable|string|max:500',
            'secondaryButton' => 'nullable|array',
            'secondaryButton.label' => 'nullable|string|max:100',
            'secondaryButton.href' => 'nullable|string|max:500',
            'pollQuestion' => 'nullable|string|max:500',
            'pollOptions' => 'nullable|array',
            'pollOptions.*.label' => 'nullable|string|max:200',
            'isActive' => 'boolean',
            'displayLocations' => 'required|array',
            'displayLocations.*' => 'in:login,dashboard',
            'priority' => 'nullable|integer|min:0',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'targetRoles' => 'nullable|array',
            'targetRoles.*' => 'string',
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('communication-spots', 'public');
        }

        // Build poll options with IDs
        $pollOptions = null;
        if (isset($validated['pollOptions'])) {
            $pollOptions = collect($validated['pollOptions'])
                ->filter(fn($opt) => !empty($opt['label']))
                ->map(fn($opt) => [
                    'id' => (string) Str::uuid(),
                    'label' => $opt['label'],
                ])
                ->values()
                ->toArray();
        }

        // Filter empty buttons
        $primaryButton = null;
        if (!empty($validated['primaryButton']['label'])) {
            $primaryButton = $validated['primaryButton'];
        }

        $secondaryButton = null;
        if (!empty($validated['secondaryButton']['label'])) {
            $secondaryButton = $validated['secondaryButton'];
        }

        // Les sondages ne peuvent pas être affichés sur la page de connexion
        $displayLocations = $validated['displayLocations'];
        if ($validated['type'] === 'poll') {
            $displayLocations = array_values(array_filter($displayLocations, fn($loc) => $loc !== 'login'));
            if (empty($displayLocations)) {
                $displayLocations = ['dashboard'];
            }
        }

        $spot = CommunicationSpot::create([
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'image' => $imagePath ? Storage::url($imagePath) : null,
            'badge' => $validated['badge'] ?? null,
            'badge_type' => $validated['badgeType'] ?? 'new',
            'primary_button' => $primaryButton,
            'secondary_button' => $secondaryButton,
            'poll_question' => $validated['pollQuestion'] ?? null,
            'poll_options' => $pollOptions,
            'is_active' => $validated['isActive'] ?? false,
            'display_locations' => $displayLocations,
            'priority' => $validated['priority'] ?? 0,
            'start_date' => $validated['startDate'] ?? null,
            'end_date' => $validated['endDate'] ?? null,
            'target_roles' => $validated['targetRoles'] ?? null,
            'votes' => [],
        ]);

        return response()->json($spot->toApiResponse(), 201);
    }

    /**
     * Update a spot (admin).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        // Parse JSON strings from FormData
        $data = $request->all();
        $jsonFields = ['primaryButton', 'secondaryButton', 'pollOptions', 'displayLocations', 'targetRoles'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }
        
        // Handle boolean from FormData
        if (isset($data['isActive'])) {
            $data['isActive'] = filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Remove image from data if it's a string URL (existing image, not a new upload)
        if (isset($data['image']) && is_string($data['image']) && !$request->hasFile('image')) {
            unset($data['image']);
        }
        
        $request->merge($data);

        // Build validation rules - only validate image if it's a file upload
        $rules = [
            'type' => 'sometimes|in:banner,poll',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'badge' => 'nullable|string|max:50',
            'badgeType' => 'nullable|in:live,new,promo',
            'primaryButton' => 'nullable|array',
            'primaryButton.label' => 'nullable|string|max:100',
            'primaryButton.href' => 'nullable|string|max:500',
            'secondaryButton' => 'nullable|array',
            'secondaryButton.label' => 'nullable|string|max:100',
            'secondaryButton.href' => 'nullable|string|max:500',
            'pollQuestion' => 'nullable|string|max:500',
            'pollOptions' => 'nullable|array',
            'pollOptions.*.label' => 'nullable|string|max:200',
            'isActive' => 'boolean',
            'displayLocations' => 'sometimes|array',
            'displayLocations.*' => 'in:login,dashboard',
            'priority' => 'nullable|integer|min:0',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
            'targetRoles' => 'nullable|array',
            'targetRoles.*' => 'string',
        ];
        
        // Only validate image if a file is being uploaded
        if ($request->hasFile('image')) {
            $rules['image'] = 'nullable|image|max:5120';
        }
        
        $validated = $request->validate($rules);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($spot->image) {
                $oldPath = str_replace('/storage/', '', $spot->image);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store('communication-spots', 'public');
            $spot->image = Storage::url($imagePath);
        }

        // Update fields
        if (isset($validated['type'])) {
            $spot->type = $validated['type'];
        }
        if (array_key_exists('title', $validated)) {
            $spot->title = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $spot->description = $validated['description'];
        }
        if (array_key_exists('badge', $validated)) {
            $spot->badge = $validated['badge'];
        }
        if (isset($validated['badgeType'])) {
            $spot->badge_type = $validated['badgeType'];
        }

        // Filter empty buttons
        if (array_key_exists('primaryButton', $validated)) {
            $spot->primary_button = !empty($validated['primaryButton']['label']) 
                ? $validated['primaryButton'] 
                : null;
        }
        if (array_key_exists('secondaryButton', $validated)) {
            $spot->secondary_button = !empty($validated['secondaryButton']['label']) 
                ? $validated['secondaryButton'] 
                : null;
        }

        if (array_key_exists('pollQuestion', $validated)) {
            $spot->poll_question = $validated['pollQuestion'];
        }

        // Build poll options with IDs (preserve existing IDs if possible)
        if (isset($validated['pollOptions'])) {
            $existingOptions = collect($spot->poll_options ?? []);
            $pollOptions = collect($validated['pollOptions'])
                ->filter(fn($opt) => !empty($opt['label']))
                ->map(function ($opt) use ($existingOptions) {
                    // Try to find existing option with same label
                    $existing = $existingOptions->firstWhere('label', $opt['label']);
                    return [
                        'id' => $existing['id'] ?? (string) Str::uuid(),
                        'label' => $opt['label'],
                    ];
                })
                ->values()
                ->toArray();
            $spot->poll_options = $pollOptions;
        }

        if (isset($validated['isActive'])) {
            $spot->is_active = $validated['isActive'];
        }
        if (isset($validated['displayLocations'])) {
            $locations = $validated['displayLocations'];
            // Les sondages ne peuvent pas être affichés sur la page de connexion
            if ($spot->type === 'poll') {
                $locations = array_values(array_filter($locations, fn($loc) => $loc !== 'login'));
                if (empty($locations)) {
                    $locations = ['dashboard'];
                }
            }
            $spot->display_locations = $locations;
        }
        if (isset($validated['priority'])) {
            $spot->priority = $validated['priority'];
        }
        if (array_key_exists('startDate', $validated)) {
            $spot->start_date = $validated['startDate'];
        }
        if (array_key_exists('endDate', $validated)) {
            $spot->end_date = $validated['endDate'];
        }
        if (array_key_exists('targetRoles', $validated)) {
            $spot->target_roles = $validated['targetRoles'];
        }

        $spot->save();

        return response()->json($spot->toApiResponse());
    }

    /**
     * Delete a spot (admin).
     */
    public function destroy(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        // Delete image if exists
        if ($spot->image) {
            $path = str_replace('/storage/', '', $spot->image);
            Storage::disk('public')->delete($path);
        }

        $spot->delete();

        return response()->json(['message' => 'Spot supprimé avec succès']);
    }

    /**
     * Toggle spot active status (admin).
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        $validated = $request->validate([
            'isActive' => 'required|boolean',
        ]);

        $spot->update(['is_active' => $validated['isActive']]);

        return response()->json($spot->toApiResponse());
    }

    /**
     * Get poll results (admin).
     */
    public function results(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        if ($spot->type !== 'poll') {
            return response()->json(['error' => 'Ce spot n\'est pas un sondage'], 400);
        }

        $votes = $spot->votes ?? [];
        $totalVotes = array_sum($votes);

        $options = collect($spot->poll_options ?? [])->map(function ($option) use ($votes, $totalVotes) {
            $optionVotes = $votes[$option['id']] ?? 0;
            return [
                'id' => $option['id'],
                'label' => $option['label'],
                'votes' => $optionVotes,
                'percentage' => $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'spotId' => (string) $spot->id,
            'question' => $spot->poll_question,
            'options' => $options,
            'totalVotes' => $totalVotes,
            'closedAt' => $spot->end_date?->isPast() ? $spot->end_date->toIso8601String() : null,
        ]);
    }

    /**
     * Reset poll votes (admin).
     */
    public function resetVotes(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        if ($spot->type !== 'poll') {
            return response()->json(['error' => 'Ce spot n\'est pas un sondage'], 400);
        }

        $spot->update(['votes' => []]);
        $spot->userVotes()->delete();

        return response()->json(['message' => 'Votes réinitialisés avec succès']);
    }

    /**
     * Close a poll (admin).
     */
    public function close(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        if ($spot->type !== 'poll') {
            return response()->json(['error' => 'Ce spot n\'est pas un sondage'], 400);
        }

        $spot->update([
            'end_date' => now(),
            'is_active' => false,
        ]);

        return response()->json($spot->toApiResponse());
    }

    /**
     * Export poll results (admin).
     */
    public function export(int $id): JsonResponse
    {
        $spot = CommunicationSpot::findOrFail($id);

        if ($spot->type !== 'poll') {
            return response()->json(['error' => 'Ce spot n\'est pas un sondage'], 400);
        }

        $votes = $spot->votes ?? [];
        $totalVotes = array_sum($votes);

        $data = [
            'question' => $spot->poll_question,
            'totalVotes' => $totalVotes,
            'createdAt' => $spot->created_at->toIso8601String(),
            'closedAt' => $spot->end_date?->toIso8601String(),
            'options' => collect($spot->poll_options ?? [])->map(function ($option) use ($votes, $totalVotes) {
                $optionVotes = $votes[$option['id']] ?? 0;
                return [
                    'label' => $option['label'],
                    'votes' => $optionVotes,
                    'percentage' => $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 1) : 0,
                ];
            }),
        ];

        return response()->json($data);
    }

    /**
     * Get active spots for a location (public/authenticated).
     */
    public function active(Request $request): JsonResponse
    {
        try {
            $location = $request->input('location', 'dashboard');
            $user = $request->user();
            // Convert enum to string if present
            $userRole = $user?->role?->value ?? $user?->role;
            if ($userRole && !is_string($userRole)) {
                $userRole = (string) $userRole;
            }

            $query = CommunicationSpot::activeForLocation($location, $userRole);
            // Les sondages ne s'affichent pas sur la page de connexion
            if ($location === 'login') {
                $query->where('type', '!=', 'poll');
            }
            $spots = $query->get();

            $spotsData = $spots->map(function ($spot) use ($user) {
                $data = $spot->toApiResponse();

                // Add user's vote status for polls
                if ($spot->type === 'poll' && $user) {
                    $userVote = $spot->getUserVote($user->id);
                    $data['hasVoted'] = (bool) $userVote;
                    $data['userVoteOptionId'] = $userVote?->option_id;

                    // Add vote counts if user has voted
                    if ($userVote) {
                        $votes = $spot->votes ?? [];
                        $totalVotes = array_sum($votes);
                        $data['pollOptions'] = collect($spot->poll_options ?? [])->map(function ($option) use ($votes, $totalVotes) {
                            return [
                                'id' => $option['id'],
                                'label' => $option['label'],
                                'votes' => $votes[$option['id']] ?? 0,
                                'percentage' => $totalVotes > 0 ? round(($votes[$option['id']] ?? 0) / $totalVotes * 100, 1) : 0,
                            ];
                        });
                    }
                }

                return $data;
            });

            return response()->json(['data' => $spotsData]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Communication spots active error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty data if table doesn't exist yet
            return response()->json(['data' => []]);
        }
    }

    /**
     * Track a view (public/authenticated).
     */
    public function trackView(Request $request, int $id): JsonResponse
    {
        $spot = CommunicationSpot::find($id);

        if ($spot) {
            $spot->incrementViews();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Track a click (public/authenticated).
     */
    public function trackClick(Request $request, int $id): JsonResponse
    {
        $spot = CommunicationSpot::find($id);

        if ($spot) {
            $spot->incrementClicks();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Vote on a poll (authenticated).
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $spot = CommunicationSpot::findOrFail($id);

        if ($spot->type !== 'poll') {
            return response()->json([
                'success' => false,
                'message' => 'Ce spot n\'est pas un sondage',
                'hasVoted' => false,
            ], 400);
        }

        $validated = $request->validate([
            'optionId' => 'required|string',
        ]);

        // Verify option exists
        $optionExists = collect($spot->poll_options ?? [])
            ->contains(fn($opt) => $opt['id'] === $validated['optionId']);

        if (!$optionExists) {
            return response()->json([
                'success' => false,
                'message' => 'Option invalide',
                'hasVoted' => false,
            ], 400);
        }

        $userVote = $spot->getUserVote($user->id);

        if ($userVote) {
            // Changer de vote : retirer l'ancien, enregistrer le nouveau
            if ($userVote->option_id === $validated['optionId']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Vote déjà enregistré pour cette option',
                    'hasVoted' => true,
                ]);
            }
            $spot->removeVote($userVote->option_id);
            $userVote->update(['option_id' => $validated['optionId']]);
            $spot->recordVote($validated['optionId']);
        } else {
            // Premier vote
            CommunicationSpotVote::create([
                'spot_id' => $spot->id,
                'user_id' => $user->id,
                'option_id' => $validated['optionId'],
            ]);
            $spot->recordVote($validated['optionId']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vote enregistré avec succès',
            'hasVoted' => true,
        ]);
    }
}
