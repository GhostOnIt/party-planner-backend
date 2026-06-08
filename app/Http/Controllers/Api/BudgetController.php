<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\StoreBudgetItemRequest;
use App\Http\Requests\Budget\UpdateBudgetItemRequest;
use App\Models\BudgetItem;
use App\Models\BudgetItemPayment;
use App\Models\BudgetPaymentAttachment;
use App\Models\Event;
use App\Services\BudgetService;
use App\Services\EventReadCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService,
        protected EventReadCacheService $eventReadCacheService
    ) {}

    /**
     * Display the budget for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewAny', [BudgetItem::class, $event]);

        $query = $event->budgetItems()->with('payments.attachments');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by payment status
        if ($request->filled('paid')) {
            $query->where('paid', $request->boolean('paid'));
        }

        $items = $query->orderBy('category')->orderBy('name')->get();
        $stats = $this->eventReadCacheService->rememberBudgetStats(
            $event,
            fn () => $this->budgetService->getStatistics($event)
        );
        $byCategory = $this->eventReadCacheService->rememberBudgetByCategory(
            $event,
            fn () => $this->budgetService->getByCategory($event)->toArray()
        );

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
        $this->authorize('viewAny', [BudgetItem::class, $event]);

        $stats = $this->eventReadCacheService->rememberBudgetStats(
            $event,
            fn () => $this->budgetService->getStatistics($event)
        );
        $byCategory = $this->eventReadCacheService->rememberBudgetByCategory(
            $event,
            fn () => $this->budgetService->getByCategory($event)->toArray()
        );

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
        $this->authorize('view', $item);
        $this->ensureItemBelongsToEvent($event, $item);

        return response()->json($item->load('payments.attachments'));
    }

    /**
     * Store a newly created budget item.
     */
    public function store(StoreBudgetItemRequest $request, Event $event): JsonResponse
    {
        $this->authorize('create', [BudgetItem::class, $event]);

        $item = $this->budgetService->create($event, $request->validated());
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($item, 201);
    }

    /**
     * Update the specified budget item.
     */
    public function update(UpdateBudgetItemRequest $request, Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('update', $item);
        $this->ensureItemBelongsToEvent($event, $item);

        $item = $this->budgetService->update($item, $request->validated());
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($item);
    }

    /**
     * Remove the specified budget item.
     */
    public function destroy(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('delete', $item);
        $this->ensureItemBelongsToEvent($event, $item);

        $this->budgetService->delete($item);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json(null, 204);
    }

    /**
     * Mark an item as paid.
     */
    public function markPaid(Request $request, Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensureItemBelongsToEvent($event, $item);

        $request->validate([
            'payment_date' => ['nullable', 'date'],
        ]);

        $paymentDate = $request->input('payment_date');
        $item = $this->budgetService->markAsPaid($item, $paymentDate);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($item);
    }

    /**
     * Mark an item as unpaid.
     */
    public function markUnpaid(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensureItemBelongsToEvent($event, $item);

        $item = $this->budgetService->markAsUnpaid($item);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($item);
    }

    public function payments(Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('view', $item);
        $this->ensureItemBelongsToEvent($event, $item);

        return response()->json($item->payments()->with('attachments')->latest('payment_date')->get());
    }

    public function storePayment(Request $request, Event $event, BudgetItem $item): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensureItemBelongsToEvent($event, $item);

        $data = $request->validate($this->paymentRules());
        $payment = $this->budgetService->createPayment($item, $data, $request->user());
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($payment, 201);
    }

    public function updatePayment(Request $request, Event $event, BudgetItem $item, BudgetItemPayment $payment): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensurePaymentBelongsToItem($event, $item, $payment);

        $data = $request->validate($this->paymentRules(required: false));
        $payment = $this->budgetService->updatePayment($payment, $data);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($payment);
    }

    public function destroyPayment(Event $event, BudgetItem $item, BudgetItemPayment $payment): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensurePaymentBelongsToItem($event, $item, $payment);

        $this->budgetService->deletePayment($payment);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json(null, 204);
    }

    public function storePaymentAttachment(Request $request, Event $event, BudgetItem $item, BudgetItemPayment $payment): JsonResponse
    {
        $this->authorize('manageBudget', $event);
        $this->ensurePaymentBelongsToItem($event, $item, $payment);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $attachment = $this->budgetService->attachPaymentFile($payment, $request->file('file'), $request->user());
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json($attachment, 201);
    }

    public function paymentAttachmentSignedUrl(
        Event $event,
        BudgetItem $item,
        BudgetItemPayment $payment,
        BudgetPaymentAttachment $attachment
    ): JsonResponse {
        $this->authorize('view', $item);
        $this->ensureAttachmentBelongsToPayment($event, $item, $payment, $attachment);

        $url = $this->budgetService->getAttachmentSignedUrl($attachment);

        abort_if($url === null, 503, 'Le preview du justificatif est indisponible.');

        return response()->json([
            'url' => $url,
            'expires_in_minutes' => 15,
        ]);
    }

    public function destroyPaymentAttachment(
        Event $event,
        BudgetItem $item,
        BudgetItemPayment $payment,
        BudgetPaymentAttachment $attachment
    ): JsonResponse {
        $this->authorize('manageBudget', $event);
        $this->ensureAttachmentBelongsToPayment($event, $item, $payment, $attachment);

        $this->budgetService->deleteAttachment($attachment);
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json(null, 204);
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
        $this->eventReadCacheService->invalidateEvent($event);

        return response()->json([
            'message' => "{$count} items updated",
            'count' => $count,
        ]);
    }

    protected function paymentRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'amount' => [$presence, 'numeric', 'min:1', 'max:999999999.99'],
            'payment_date' => [$presence, 'date'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function ensureItemBelongsToEvent(Event $event, BudgetItem $item): void
    {
        abort_unless((string) $item->event_id === (string) $event->id, 404);
    }

    protected function ensurePaymentBelongsToItem(Event $event, BudgetItem $item, BudgetItemPayment $payment): void
    {
        $this->ensureItemBelongsToEvent($event, $item);

        abort_unless(
            (string) $payment->event_id === (string) $event->id
            && (string) $payment->budget_item_id === (string) $item->id,
            404
        );
    }

    protected function ensureAttachmentBelongsToPayment(
        Event $event,
        BudgetItem $item,
        BudgetItemPayment $payment,
        BudgetPaymentAttachment $attachment
    ): void {
        $this->ensurePaymentBelongsToItem($event, $item, $payment);

        abort_unless(
            (string) $attachment->event_id === (string) $event->id
            && (string) $attachment->budget_item_id === (string) $item->id
            && (string) $attachment->budget_item_payment_id === (string) $payment->id,
            404
        );
    }
}
