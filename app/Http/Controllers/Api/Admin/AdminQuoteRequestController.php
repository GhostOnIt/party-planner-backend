<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\QuoteRequestsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddQuoteNoteRequest;
use App\Http\Requests\Admin\AssignQuoteRequestRequest;
use App\Http\Requests\Admin\ListQuoteRequestsRequest;
use App\Http\Requests\Admin\ScheduleQuoteCallRequest;
use App\Http\Requests\Admin\UpdateQuoteOutcomeRequest;
use App\Http\Requests\Admin\UpdateQuoteStageRequest;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestStage;
use App\Models\User;
use App\Services\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminQuoteRequestController extends Controller
{
    public function __construct(protected QuoteRequestService $quoteRequestService) {}

    public function index(ListQuoteRequestsRequest $request): JsonResponse
    {
        $stages = $this->quoteRequestService->ensureWorkflowStages();
        $firstStage = $stages->firstWhere('slug', 'pending_processing') ?? $stages->first();
        if ($firstStage) {
            QuoteRequest::query()
                ->whereNull('current_stage_id')
                ->update([
                    'current_stage_id' => $firstStage->id,
                    'last_stage_changed_at' => now(),
                ]);
        }

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
            ->withCount('offers');

        if ($request->filled('stage_id')) {
            $query->where('current_stage_id', $request->string('stage_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->string('outcome'));
        }

        if ($request->filled('assigned_admin_id')) {
            $query->where('assigned_admin_id', $request->string('assigned_admin_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('budget_min')) {
            $query->where('budget_estimate', '>=', (int) $request->input('budget_min'));
        }

        if ($request->filled('budget_max')) {
            $query->where('budget_estimate', '<=', (int) $request->input('budget_max'));
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

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

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
                'offers.creator:id,name',
            ]),
        ]);
    }

    public function updateStage(UpdateQuoteStageRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $stage = QuoteRequestStage::findOrFail($request->validated('stage_id'));
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

    public function assign(AssignQuoteRequestRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validated();

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

    public function addNote(AddQuoteNoteRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $activity = $this->quoteRequestService->logActivity(
            $quoteRequest,
            'note_added',
            $request->validated('note'),
            null,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Note ajoutée.',
            'data' => $activity->load('user:id,name,email'),
        ], 201);
    }

    public function scheduleCall(ScheduleQuoteCallRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $quoteRequest->update([
            'call_scheduled_at' => $request->validated('call_scheduled_at'),
        ]);

        $this->quoteRequestService->logActivity(
            $quoteRequest,
            'call_scheduled',
            'Call de qualification planifié.',
            ['call_scheduled_at' => $request->validated('call_scheduled_at')],
            $request->user()->id
        );
        $this->quoteRequestService->notifyCustomerCallScheduled($quoteRequest->fresh());

        return response()->json([
            'message' => 'Call planifié.',
            'data' => $quoteRequest->fresh()->load(['currentStage', 'assignedAdmin']),
        ]);
    }

    public function updateOutcome(UpdateQuoteOutcomeRequest $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $validated = $request->validated();
        $closedStage = QuoteRequestStage::query()->where('slug', 'closed')->first();

        $quoteRequest->update([
            'outcome' => $validated['outcome'],
            'outcome_note' => $validated['outcome_note'] ?? null,
            'status' => 'closed',
            'current_stage_id' => $closedStage?->id ?? $quoteRequest->current_stage_id,
            'last_stage_changed_at' => now(),
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

    public function export(ListQuoteRequestsRequest $request): BinaryFileResponse
    {
        $filters = $request->only([
            'search', 'stage_id', 'status', 'outcome', 'assigned_admin_id',
            'date_from', 'date_to', 'budget_min', 'budget_max',
        ]);

        return Excel::download(
            new QuoteRequestsExport($filters),
            'demandes-business-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
