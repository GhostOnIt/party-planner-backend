<?php

namespace App\Http\Controllers;

use App\Http\Requests\Budget\StoreBudgetItemRequest;
use App\Http\Requests\Budget\UpdateBudgetItemRequest;
use App\Models\BudgetItem;
use App\Models\Event;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService
    ) {}

    /**
     * Display the budget for an event.
     */
    public function index(Request $request, Event $event): View
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

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $items = $query->orderBy('category')->orderBy('name')->get();
        $byCategory = $this->budgetService->getByCategory($event);
        $stats = $this->budgetService->getStatistics($event);
        $overBudgetItems = $this->budgetService->getOverBudgetItems($event);
        $unpaidItems = $this->budgetService->getUnpaidItems($event);

        return view('events.budget.index', compact(
            'event',
            'items',
            'byCategory',
            'stats',
            'overBudgetItems',
            'unpaidItems'
        ));
    }

    /**
     * Show form to create a new budget item.
     */
    public function create(Event $event): View
    {
        $this->authorize('manageBudget', $event);

        $categories = \App\Enums\BudgetCategory::options();

        return view('events.budget.create', compact('event', 'categories'));
    }

    /**
     * Store a newly created budget item.
     */
    public function store(StoreBudgetItemRequest $request, Event $event): RedirectResponse
    {
        $item = $this->budgetService->create($event, $request->validated());

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', 'Élément de budget ajouté avec succès.');
    }

    /**
     * Show form to edit a budget item.
     */
    public function edit(Event $event, BudgetItem $item): View
    {
        $this->authorize('manageBudget', $event);

        $categories = \App\Enums\BudgetCategory::options();

        return view('events.budget.edit', compact('event', 'item', 'categories'));
    }

    /**
     * Update the specified budget item.
     */
    public function update(UpdateBudgetItemRequest $request, Event $event, BudgetItem $item): RedirectResponse
    {
        $this->budgetService->update($item, $request->validated());

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', 'Élément de budget mis à jour avec succès.');
    }

    /**
     * Remove the specified budget item.
     */
    public function destroy(Event $event, BudgetItem $item): RedirectResponse
    {
        $this->authorize('manageBudget', $event);

        $this->budgetService->delete($item);

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', 'Élément de budget supprimé avec succès.');
    }

    /**
     * Mark an item as paid.
     */
    public function markPaid(Request $request, Event $event, BudgetItem $item): RedirectResponse
    {
        $this->authorize('manageBudget', $event);

        $paymentDate = $request->input('payment_date');

        $this->budgetService->markAsPaid($item, $paymentDate);

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', 'Élément marqué comme payé.');
    }

    /**
     * Mark an item as unpaid.
     */
    public function markUnpaid(Event $event, BudgetItem $item): RedirectResponse
    {
        $this->authorize('manageBudget', $event);

        $this->budgetService->markAsUnpaid($item);

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', 'Élément marqué comme non payé.');
    }

    /**
     * Toggle paid status (for quick actions).
     */
    public function togglePaid(Event $event, BudgetItem $item): RedirectResponse
    {
        $this->authorize('manageBudget', $event);

        if ($item->paid) {
            $this->budgetService->markAsUnpaid($item);
            $message = 'Élément marqué comme non payé.';
        } else {
            $this->budgetService->markAsPaid($item);
            $message = 'Élément marqué comme payé.';
        }

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', $message);
    }

    /**
     * Export budget to PDF.
     */
    public function exportPdf(Event $event): Response
    {
        $this->authorize('export', $event);

        $pdf = $this->budgetService->generatePdf($event);
        $filename = "budget-{$event->title}-" . now()->format('Y-m-d') . ".pdf";

        return $pdf->download($filename);
    }

    /**
     * Export budget to CSV.
     */
    public function exportCsv(Event $event): Response
    {
        $this->authorize('export', $event);

        $csv = $this->budgetService->exportToCsv($event);
        $filename = "budget-{$event->title}-" . now()->format('Y-m-d') . ".csv";

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get budget statistics (JSON for AJAX).
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
     * Duplicate budget from another event.
     */
    public function duplicate(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageBudget', $event);

        $request->validate([
            'source_event_id' => 'required|exists:events,id',
        ]);

        $sourceEvent = Event::findOrFail($request->source_event_id);
        $this->authorize('view', $sourceEvent);

        $count = $this->budgetService->duplicateFromEvent($sourceEvent, $event);

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', "{$count} éléments de budget importés avec succès.");
    }

    /**
     * Bulk update items (mark multiple as paid/unpaid).
     */
    public function bulkUpdate(Request $request, Event $event): RedirectResponse
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

        $actionLabel = match ($request->action) {
            'mark_paid' => 'marqués comme payés',
            'mark_unpaid' => 'marqués comme non payés',
            'delete' => 'supprimés',
        };

        return redirect()
            ->route('events.budget.index', $event)
            ->with('success', "{$count} éléments {$actionLabel}.");
    }
}
