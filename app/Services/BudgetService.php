<?php

namespace App\Services;

use App\Enums\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetItemPayment;
use App\Models\BudgetPaymentAttachment;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function __construct(
        protected S3Service $s3Service
    ) {}

    /**
     * Create a new budget item.
     */
    public function create(Event $event, array $data): BudgetItem
    {
        $item = $event->budgetItems()->create($data);

        $this->checkBudgetAlerts($event);

        return $item;
    }

    /**
     * Update a budget item.
     */
    public function update(BudgetItem $item, array $data): BudgetItem
    {
        $item->update($data);

        $this->checkBudgetAlerts($item->event);

        return $item->fresh();
    }

    /**
     * Delete a budget item.
     */
    public function delete(BudgetItem $item): bool
    {
        return $item->delete();
    }

    /**
     * Get budget statistics for an event.
     */
    public function getStatistics(Event $event): array
    {
        $items = $event->budgetItems()->with('payments.attachments')->get();

        $totalEstimated = $items->sum('estimated_cost');
        $totalActual = $items->sum('actual_cost');
        $totalPaid = $items->sum('total_paid');
        $totalUnpaid = max($totalActual - $totalPaid, 0);

        $eventBudget = $event->estimated_budget ?? 0;
        $budgetRemaining = $eventBudget - $totalActual;
        $budgetUsedPercent = $eventBudget > 0 ? ($totalActual / $eventBudget) * 100 : 0;

        $variance = $totalActual - $totalEstimated;
        $variancePercent = $totalEstimated > 0 ? ($variance / $totalEstimated) * 100 : 0;

        return [
            'total_estimated' => $totalEstimated,
            'total_actual' => $totalActual,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
            'event_budget' => $eventBudget,
            'budget_remaining' => $budgetRemaining,
            'budget_used_percent' => round($budgetUsedPercent, 2),
            'variance' => $variance,
            'variance_percent' => round($variancePercent, 2),
            'items_count' => $items->count(),
            'paid_items_count' => $items->where('payment_status', 'paid')->count(),
            'partially_paid_items_count' => $items->where('payment_status', 'partially_paid')->count(),
            'unpaid_items_count' => $items->where('payment_status', 'unpaid')->count(),
            'payment_proofs_count' => $items->sum('attachments_count'),
            'missing_proof_paid_items_count' => $items
                ->filter(fn ($item) => $item->payment_status === 'paid' && $item->attachments_count === 0)
                ->count(),
            'is_over_budget' => $totalActual > $eventBudget && $eventBudget > 0,
            'is_over_estimated' => $totalActual > $totalEstimated,
        ];
    }

    /**
     * Get budget breakdown by category.
     */
    public function getByCategory(Event $event): Collection
    {
        $items = $event->budgetItems()->with('payments.attachments')->get();

        return $items->groupBy('category')->map(function ($categoryItems, $category) {
            return [
                'category' => $category,
                'label' => BudgetCategory::tryFrom($category)?->label() ?? ucfirst($category),
                'items' => $categoryItems,
                'estimated' => $categoryItems->sum('estimated_cost'),
                'actual' => $categoryItems->sum('actual_cost'),
                'paid' => $categoryItems->sum('total_paid'),
                'unpaid' => max($categoryItems->sum('actual_cost') - $categoryItems->sum('total_paid'), 0),
                'variance' => $categoryItems->sum('actual_cost') - $categoryItems->sum('estimated_cost'),
                'count' => $categoryItems->count(),
            ];
        })->sortBy('label');
    }

    /**
     * Get items that are over budget.
     */
    public function getOverBudgetItems(Event $event): Collection
    {
        return $event->budgetItems->filter(function ($item) {
            return $item->isOverBudget();
        });
    }

    /**
     * Get unpaid items.
     */
    public function getUnpaidItems(Event $event): Collection
    {
        return $event->budgetItems()
            ->where('paid', false)
            ->whereNotNull('actual_cost')
            ->where('actual_cost', '>', 0)
            ->orderBy('category')
            ->get();
    }

    /**
     * Get items due for payment (based on event date proximity).
     */
    public function getItemsDueForPayment(Event $event, int $daysBeforeEvent = 7): Collection
    {
        if (!$event->date || $event->date->isPast()) {
            return collect();
        }

        $daysUntilEvent = now()->diffInDays($event->date, false);

        if ($daysUntilEvent > $daysBeforeEvent) {
            return collect();
        }

        return $this->getUnpaidItems($event);
    }

    /**
     * Check and send budget alerts.
     */
    public function checkBudgetAlerts(Event $event): void
    {
        $stats = $this->getStatistics($event);
        $threshold = config('partyplanner.notifications.budget_alert_threshold', 90);

        // Alert if budget usage exceeds threshold
        if ($stats['budget_used_percent'] >= $threshold && $stats['event_budget'] > 0) {
            $this->sendBudgetAlert(
                $event,
                'budget_threshold',
                "Attention : Vous avez utilisé {$stats['budget_used_percent']}% de votre budget pour l'événement \"{$event->title}\"."
            );
        }

        // Alert if over budget
        if ($stats['is_over_budget']) {
            $overAmount = abs($stats['budget_remaining']);
            $this->sendBudgetAlert(
                $event,
                'over_budget',
                "Alerte : Vous avez dépassé votre budget de " . number_format($overAmount, 0, ',', ' ') . " FCFA pour l'événement \"{$event->title}\"."
            );
        }
    }

    /**
     * Send a budget alert notification.
     */
    protected function sendBudgetAlert(Event $event, string $alertType, string $message): void
    {
        // Check if we already sent this alert recently (within 24 hours)
        $existingAlert = Notification::where('user_id', $event->user_id)
            ->where('event_id', $event->id)
            ->where('type', 'budget_alert')
            ->where('title', 'like', "%{$alertType}%")
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($existingAlert) {
            return;
        }

        Notification::create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'type' => 'budget_alert',
            'title' => "Alerte budget ({$alertType})",
            'message' => $message,
            'sent_via' => 'push',
        ]);
    }

    /**
     * Apply budget items from a template.
     */
    public function applyFromTemplate(Event $event, array $templateBudgetCategories): int
    {
        $created = 0;

        foreach ($templateBudgetCategories as $item) {
            if (!isset($item['name']) || !isset($item['category'])) {
                continue;
            }

            // Skip if item already exists
            $exists = $event->budgetItems()
                ->where('name', $item['name'])
                ->where('category', $item['category'])
                ->exists();

            if (!$exists) {
                $event->budgetItems()->create([
                    'category' => $item['category'],
                    'name' => $item['name'],
                    'estimated_cost' => $item['estimated_cost'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Mark an item as paid.
     */
    public function markAsPaid(BudgetItem $item, ?string $paymentDate = null): BudgetItem
    {
        $remainingAmount = $item->remaining_amount;

        if ($remainingAmount > 0) {
            $this->createPayment($item, [
                'amount' => $remainingAmount,
                'payment_date' => $paymentDate ?? now()->toDateString(),
                'notes' => 'Paiement du solde restant',
            ]);
        }

        $item->update([
            'paid' => true,
            'payment_date' => $paymentDate ?? now()->toDateString(),
        ]);

        return $item->fresh(['payments.attachments']);
    }

    /**
     * Mark an item as unpaid.
     */
    public function markAsUnpaid(BudgetItem $item): BudgetItem
    {
        $item->payments()->each(function (BudgetItemPayment $payment) {
            $this->deletePayment($payment);
        });

        $item->update([
            'actual_cost' => null,
            'paid' => false,
            'payment_date' => null,
        ]);

        return $item->fresh(['payments.attachments']);
    }

    public function createPayment(BudgetItem $item, array $data, ?User $user = null): BudgetItemPayment
    {
        $item->refresh();

        if ($item->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'amount' => 'Cette ligne budgétaire est déjà payée.',
            ]);
        }

        $payment = $item->payments()->create([
            'event_id' => $item->event_id,
            'created_by' => $user?->id,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncPaymentState($item);

        return $payment->fresh(['attachments']);
    }

    public function updatePayment(BudgetItemPayment $payment, array $data): BudgetItemPayment
    {
        $payment->update($data);
        $this->syncPaymentState($payment->budgetItem);

        return $payment->fresh(['attachments']);
    }

    public function deletePayment(BudgetItemPayment $payment): bool
    {
        $item = $payment->budgetItem;

        $payment->attachments->each(fn (BudgetPaymentAttachment $attachment) => $this->deleteAttachment($attachment));
        $deleted = $payment->delete();
        $this->syncPaymentState($item);

        return $deleted;
    }

    public function attachPaymentFile(BudgetItemPayment $payment, UploadedFile $file, ?User $user = null): BudgetPaymentAttachment
    {
        $item = $payment->budgetItem;
        $upload = $this->s3Service->uploadBudgetPaymentAttachment(
            (string) $payment->event_id,
            (string) $payment->budget_item_id,
            (string) $payment->id,
            $file
        );

        return $payment->attachments()->create([
            'budget_item_id' => $payment->budget_item_id,
            'event_id' => $payment->event_id,
            'uploaded_by' => $user?->id,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            's3_path' => $upload['path'],
        ]);
    }

    public function deleteAttachment(BudgetPaymentAttachment $attachment): bool
    {
        $this->s3Service->delete($attachment->s3_path);

        return $attachment->delete();
    }

    public function getAttachmentSignedUrl(BudgetPaymentAttachment $attachment, int $minutes = 15): ?string
    {
        return $this->s3Service->getSignedUrl($attachment->s3_path, $minutes);
    }

    protected function syncPaymentState(BudgetItem $item): void
    {
        $item->refresh();
        $totalPaid = (float) $item->payments()->sum('amount');
        $estimatedCost = (float) ($item->estimated_cost ?? 0);
        $newActualCost = $totalPaid > 0 ? $totalPaid : 0;

        $targetAmount = $newActualCost > 0 ? $newActualCost : $estimatedCost;
        if ($newActualCost > 0 && $newActualCost < $estimatedCost && $totalPaid < $estimatedCost) {
            $targetAmount = $estimatedCost;
        }

        $isPaid = $totalPaid > 0 && ($targetAmount <= 0 || $totalPaid >= $targetAmount);

        $item->update([
            'actual_cost' => $newActualCost > 0 ? $newActualCost : null,
            'paid' => $isPaid,
            'payment_date' => $isPaid
                ? $item->payments()->latest('payment_date')->value('payment_date')
                : null,
        ]);
    }

    /**
     * Duplicate budget from another event.
     */
    public function duplicateFromEvent(Event $sourceEvent, Event $targetEvent): int
    {
        $created = 0;

        foreach ($sourceEvent->budgetItems as $item) {
            $targetEvent->budgetItems()->create([
                'category' => $item->category,
                'name' => $item->name,
                'estimated_cost' => $item->estimated_cost,
                'notes' => $item->notes,
                // Reset actual_cost and paid status
                'actual_cost' => null,
                'paid' => false,
                'payment_date' => null,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Generate PDF report.
     */
    public function generatePdf(Event $event): \Barryvdh\DomPDF\PDF
    {
        $items = $event->budgetItems()
            ->with('payments.attachments')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $byCategory = $this->getByCategory($event);
        $stats = $this->getStatistics($event);

        return Pdf::loadView('events.budget.pdf', compact('event', 'items', 'byCategory', 'stats'))
            ->setPaper(config('partyplanner.exports.pdf.paper_size', 'a4'))
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Export budget to CSV.
     */
    public function exportToCsv(Event $event): string
    {
        $items = $event->budgetItems()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $csv = "Catégorie,Nom,Estimé,Réel,Montant payé,Reste à payer,Statut paiement,Justificatifs,Date paiement,Notes\n";

        foreach ($items as $item) {
            $csv .= implode(',', [
                '"' . $item->category_label . '"',
                '"' . str_replace('"', '""', $item->name) . '"',
                $item->estimated_cost ?? '',
                $item->actual_cost ?? '',
                $item->total_paid,
                $item->remaining_amount,
                match ($item->payment_status) {
                    'paid' => 'Payé',
                    'partially_paid' => 'Partiel',
                    default => 'Non payé',
                },
                $item->attachments_count,
                $item->payment_date?->format('d/m/Y') ?? '',
                '"' . str_replace('"', '""', $item->notes ?? '') . '"',
            ]) . "\n";
        }

        $stats = $this->getStatistics($event);
        $csv .= "\n";
        $csv .= "\"Total estimé\",,{$stats['total_estimated']},,,,\n";
        $csv .= "\"Total réel\",,,{$stats['total_actual']},,,\n";
        $csv .= "\"Total payé\",,,{$stats['total_paid']},,,\n";
        $csv .= "\"Total impayé\",,,{$stats['total_unpaid']},,,\n";

        return $csv;
    }

    /**
     * Get budget summary for dashboard.
     */
    public function getDashboardSummary(User $user): array
    {
        $events = $user->events()->with('budgetItems')->get();

        $totalEstimated = 0;
        $totalActual = 0;
        $totalPaid = 0;
        $eventsOverBudget = 0;

        foreach ($events as $event) {
            $totalEstimated += $event->budgetItems->sum('estimated_cost');
            $totalActual += $event->budgetItems->sum('actual_cost');
            $totalPaid += $event->budgetItems->where('paid', true)->sum('actual_cost');

            if ($event->estimated_budget && $event->budgetItems->sum('actual_cost') > $event->estimated_budget) {
                $eventsOverBudget++;
            }
        }

        return [
            'total_estimated' => $totalEstimated,
            'total_actual' => $totalActual,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalActual - $totalPaid,
            'events_over_budget' => $eventsOverBudget,
        ];
    }
}
