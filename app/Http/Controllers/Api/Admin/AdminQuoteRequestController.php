<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestStage;
use App\Models\User;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuoteRequestController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $query = QuoteRequest::query()
            ->with([
                'currentStage',
                'assignedAdmin:id,name,email',
                'user:id,name,email',
                'plan:id,name,slug',
                'activities' => function ($q) {
                    $q->latest()->limit(20);
                },
            ])
            ->latest();

        if ($request->filled('stage_id')) {
            $query->where('current_stage_id', $request->string('stage_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_code', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->paginate(min((int) $request->input('per_page', 20), 100)),
        ]);
    }

    public function show(QuoteRequest $quoteRequest): JsonResponse
    {
        return response()->json([
            'data' => $quoteRequest->load([
                'currentStage',
                'assignedAdmin:id,name,email',
                'user:id,name,email,phone',
                'plan:id,name,slug',
                'activities.user:id,name,email',
            ]),
        ]);
    }

    public function updateStage(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'exists:quote_request_stages,id'],
        ]);

        $stage = QuoteRequestStage::findOrFail($validated['stage_id']);
        $quoteRequest->update([
            'current_stage_id' => $stage->id,
            'last_stage_changed_at' => now(),
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'stage_changed',
            "Passage à l'étape {$stage->name}.",
            ['stage_id' => $stage->id, 'stage_slug' => $stage->slug],
            $request->user()->id
        );
        $this->quoteRequestService->notifyCustomerUpdate(
            $quoteRequest,
            'Mise à jour de votre demande Business',
            "Votre demande {$quoteRequest->tracking_code} est maintenant à l'étape: {$stage->name}."
        );

        return response()->json([
            'message' => 'Étape mise à jour.',
            'data' => $quoteRequest->fresh()->load(['currentStage', 'assignedAdmin']),
        ]);
    }

    public function assign(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'assigned_admin_id' => ['nullable', 'exists:users,id'],
        ]);

        if (!empty($validated['assigned_admin_id'])) {
            $admin = User::query()->findOrFail($validated['assigned_admin_id']);
            if (!$admin->isAdmin()) {
                return response()->json(['message' => 'Le collaborateur assigné doit être administrateur.'], 422);
            }
        }

        $quoteRequest->update([
            'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'assigned',
            'Responsable assigné à la demande.',
            ['assigned_admin_id' => $validated['assigned_admin_id'] ?? null],
            $request->user()->id
        );

        return response()->json([
            'message' => 'Assignation mise à jour.',
            'data' => $quoteRequest->fresh()->load('assignedAdmin:id,name,email'),
        ]);
    }

    public function addNote(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $activity = $this->quoteRequestService->logActivity(
            $quoteRequest,
            'note_added',
            $validated['note'],
            null,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Note ajoutée.',
            'data' => $activity->load('user:id,name,email'),
        ], 201);
    }

    public function scheduleCall(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'call_scheduled_at' => ['required', 'date'],
        ]);

        $quoteRequest->update([
            'call_scheduled_at' => $validated['call_scheduled_at'],
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'call_scheduled',
            'Call de qualification planifié.',
            ['call_scheduled_at' => $validated['call_scheduled_at']],
            $request->user()->id
        );
        $this->quoteRequestService->notifyCustomerCallScheduled($quoteRequest->fresh());

        return response()->json([
            'message' => 'Call planifié.',
            'data' => $quoteRequest->fresh()->load(['currentStage', 'assignedAdmin']),
        ]);
    }

    public function updateOutcome(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:offer_sent,won,lost'],
            'outcome_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quoteRequest->update([
            'outcome' => $validated['outcome'],
            'outcome_note' => $validated['outcome_note'] ?? null,
            'status' => in_array($validated['outcome'], ['won', 'lost'], true) ? 'closed' : 'open',
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'outcome_updated',
            'Issue commerciale mise à jour.',
            $validated,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Issue mise à jour.',
            'data' => $quoteRequest->fresh()->load(['currentStage', 'assignedAdmin']),
        ]);
    }
}
