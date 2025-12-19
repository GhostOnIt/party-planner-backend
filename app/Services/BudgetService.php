<?php

namespace App\Services;

use App\Enums\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class BudgetService
{
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
        $items = $event->budgetItems;

        $totalEstimated = $items->sum('estimated_cost');
        $totalActual = $items->sum('actual_cost');
        $totalPaid = $items->where('paid', true)->sum('actual_cost');
        $totalUnpaid = $items->where('paid', false)->sum('actual_cost');

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
            'paid_items_count' => $items->where('paid', true)->count(),
            'unpaid_items_count' => $items->where('paid', false)->count(),
            'is_over_budget' => $totalActual > $eventBudget && $eventBudget > 0,
            'is_over_estimated' => $totalActual > $totalEstimated,
        ];
    }

    /**
     * Get budget breakdown by category.
     */
    public function getByCategory(Event $event): Collection
    {
        $items = $event->budgetItems;

        return $items->groupBy('category')->map(function ($categoryItems, $category) {
            return [
                'category' => $category,
                'label' => BudgetCategory::tryFrom($category)?->label() ?? ucfirst($category),
                'items' => $categoryItems,
                'estimated' => $categoryItems->sum('estimated_cost'),
                'actual' => $categoryItems->sum('actual_cost'),
                'paid' => $categoryItems->where('paid', true)->sum('actual_cost'),
                'unpaid' => $categoryItems->where('paid', false)->sum('actual_cost'),
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
        $item->update([
            'paid' => true,
            'payment_date' => $paymentDate ?? now()->toDateString(),
        ]);

        return $item->fresh();
    }

    /**
     * Mark an item as unpaid.
     */
    public function markAsUnpaid(BudgetItem $item): BudgetItem
    {
        $item->update([
            'paid' => false,
            'payment_date' => null,
        ]);

        return $item->fresh();
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

        $csv = "Catégorie,Nom,Estimé,Réel,Payé,Date paiement,Notes\n";

        foreach ($items as $item) {
            $csv .= implode(',', [
                '"' . $item->category_label . '"',
                '"' . str_replace('"', '""', $item->name) . '"',
                $item->estimated_cost ?? '',
                $item->actual_cost ?? '',
                $item->paid ? 'Oui' : 'Non',
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
