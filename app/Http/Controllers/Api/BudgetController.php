<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\StoreBudgetItemRequest;
use App\Http\Requests\Budget\UpdateBudgetItemRequest;
use App\Models\BudgetItem;
use App\Models\Event;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService
    ) {}

    /**
     * Display the budget for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $query = $event->budgetItems();

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by payment status
        if ($request->filled('paid')) {
            $query->where('paid', $request->boolean('paid'));
        }

        $items = $query->orderBy('category')->orderBy('name')->get();
        $stats = $this->budgetService->getStatistics($event);
        $byCategory = $this->budgetService->getByCategory($event);

        return response()->json([
            'items' => $items,
            'stats' => $stats,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Get budget statistics.
     */
    public function statistics(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $stats = $this->budgetService->getStatistics($event);
        $byCategory = $this->budgetService->getByCategory($event);

        return response()->json([
            'stats' => $stats,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Show a single budget item.
     */
    public function show(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json($item);
    }

    /**
     * Store a newly created budget item.
     */
    public function store(StoreBudgetItemRequest $request, Event $event): JsonResponse
    {
        $item = $this->budgetService->create($event, $request->validated());

        return response()->json($item, 201);
    }

    /**
     * Update the specified budget item.
     */
    public function update(UpdateBudgetItemRequest $request, Event $event, BudgetItem $item): JsonResponse
    {
        $item = $this->budgetService->update($item, $request->validated());

        return response()->json($item);
    }

    /**
     * Remove the specified budget item.
     */
    public function destroy(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);

        $this->budgetService->delete($item);

        return response()->json(null, 204);
    }

    /**
     * Mark an item as paid.
     */
    public function markPaid(Request $request, Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);

        $paymentDate = $request->input('payment_date');
        $item = $this->budgetService->markAsPaid($item, $paymentDate);

        return response()->json($item);
    }

    /**
     * Mark an item as unpaid.
     */
    public function markUnpaid(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);

        $item = $this->budgetService->markAsUnpaid($item);

        return response()->json($item);
    }

    /**
     * Bulk update items.
     */
    public function bulkUpdate(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageBudget', $event);

        $request->validate([
            'items' => 'required|array',
            'items.*' => 'exists:budget_items,id',
            'action' => 'required|in:mark_paid,mark_unpaid,delete',
        ]);

        $items = BudgetItem::whereIn('id', $request->items)
            ->where('event_id', $event->id)
            ->get();

        $count = 0;
        foreach ($items as $item) {
            switch ($request->action) {
                case 'mark_paid':
                    $this->budgetService->markAsPaid($item);
                    break;
                case 'mark_unpaid':
                    $this->budgetService->markAsUnpaid($item);
                    break;
                case 'delete':
                    $this->budgetService->delete($item);
                    break;
            }
            $count++;
        }

        return response()->json([
            'message' => "{$count} items updated",
            'count' => $count,
        ]);
    }
}
