<?php

namespace App\Services;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export guests to CSV.
     */
    public function exportGuestsToCsv(Event $event, array $filters = []): StreamedResponse
    {
        $filename = Str::slug($event->title) . '-invites-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $guests = $this->applyFilters($event->guests(), $filters)->orderBy('name')->get();

        $callback = function () use ($guests) {
            $file = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($file, [
                'Nom',
                'Email',
                'Téléphone',
                'Statut RSVP',
                'Notes',
                'Check-in',
                'Invitation envoyée',
                'Date de réponse',
            ], ';');

            // Data
            foreach ($guests as $guest) {
                fputcsv($file, [
                    $guest->name,
                    $guest->email,
                    $guest->phone,
                    $this->getRsvpLabel($guest->rsvp_status),
                    $guest->notes,
                    $guest->checked_in ? 'Oui' : 'Non',
                    $guest->invitation_sent_at?->format('d/m/Y H:i') ?? '',
                    $guest->responded_at?->format('d/m/Y H:i') ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export guests to PDF.
     */
    public function exportGuestsToPdf(Event $event, array $filters = []): Response
    {
        $guests = $this->applyFilters($event->guests(), $filters)->orderBy('name')->get();

        $stats = [
            'total' => $guests->count(),
            'accepted' => $guests->where('rsvp_status', 'accepted')->count(),
            'declined' => $guests->where('rsvp_status', 'declined')->count(),
            'pending' => $guests->where('rsvp_status', 'pending')->count(),
            'maybe' => $guests->where('rsvp_status', 'maybe')->count(),
            'checked_in' => $guests->where('checked_in', true)->count(),
        ];

        $pdf = Pdf::loadView('exports.guests-pdf', [
            'event' => $event,
            'guests' => $guests,
            'stats' => $stats,
            'generatedAt' => now(),
        ]);

        $filename = Str::slug($event->title) . '-invites-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Apply filters to guests query.
     */
    protected function applyFilters($query, array $filters)
    {
        // Filter by RSVP status
        if (!empty($filters['rsvp_status']) && is_array($filters['rsvp_status'])) {
            $query->whereIn('rsvp_status', $filters['rsvp_status']);
        }

        // Filter by check-in status
        if (isset($filters['checked_in'])) {
            $query->where('checked_in', $filters['checked_in']);
        }

        // Filter by invitation sent status
        if (isset($filters['invitation_sent'])) {
            if ($filters['invitation_sent']) {
                $query->whereNotNull('invitation_sent_at');
            } else {
                $query->whereNull('invitation_sent_at');
            }
        }

        return $query;
    }

    /**
     * Export budget to CSV.
     */
    public function exportBudgetToCsv(Event $event): StreamedResponse
    {
        $filename = Str::slug($event->title) . '-budget-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $items = $event->budgetItems()->orderBy('category')->get();

        $callback = function () use ($items, $event) {
            $file = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($file, [
                'Catégorie',
                'Nom',
                'Coût estimé',
                'Coût réel',
                'Payé',
                'Date de paiement',
                'Notes',
            ], ';');

            // Data
            foreach ($items as $item) {
                fputcsv($file, [
                    $this->getCategoryLabel($item->category),
                    $item->name,
                    number_format($item->estimated_cost, 2, ',', ' '),
                    number_format($item->actual_cost ?? 0, 2, ',', ' '),
                    $item->paid ? 'Oui' : 'Non',
                    $item->payment_date?->format('d/m/Y') ?? '',
                    $item->notes,
                ], ';');
            }

            // Summary row
            fputcsv($file, [], ';');
            fputcsv($file, [
                'TOTAL',
                '',
                number_format($items->sum('estimated_cost'), 2, ',', ' '),
                number_format($items->sum('actual_cost'), 2, ',', ' '),
                '',
                '',
                '',
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export budget to PDF.
     */
    public function exportBudgetToPdf(Event $event): Response
    {
        $items = $event->budgetItems()->orderBy('category')->get();

        $stats = [
            'total_estimated' => $items->sum('estimated_cost'),
            'total_actual' => $items->sum('actual_cost'),
            'total_paid' => $items->where('paid', true)->sum('actual_cost'),
            'total_unpaid' => $items->where('paid', false)->sum('actual_cost'),
            'items_count' => $items->count(),
            'by_category' => $items->groupBy('category')->map(fn($group) => [
                'estimated' => $group->sum('estimated_cost'),
                'actual' => $group->sum('actual_cost'),
                'count' => $group->count(),
            ])->toArray(),
        ];

        $pdf = Pdf::loadView('exports.budget-pdf', [
            'event' => $event,
            'items' => $items,
            'stats' => $stats,
            'generatedAt' => now(),
        ]);

        $filename = Str::slug($event->title) . '-budget-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export full event report to PDF.
     */
    public function exportEventReportPdf(Event $event): Response
    {
        $event->load(['guests', 'tasks', 'budgetItems', 'photos', 'collaborators.user', 'user']);

        $guestStats = [
            'total' => $event->guests->count(),
            'accepted' => $event->guests->where('rsvp_status', 'accepted')->count(),
            'declined' => $event->guests->where('rsvp_status', 'declined')->count(),
            'pending' => $event->guests->where('rsvp_status', 'pending')->count(),
            'checked_in' => $event->guests->where('checked_in', true)->count(),
        ];

        $taskStats = [
            'total' => $event->tasks->count(),
            'completed' => $event->tasks->where('status', 'completed')->count(),
            'in_progress' => $event->tasks->where('status', 'in_progress')->count(),
            'todo' => $event->tasks->where('status', 'todo')->count(),
        ];

        $budgetStats = [
            'total_estimated' => $event->budgetItems->sum('estimated_cost'),
            'total_actual' => $event->budgetItems->sum('actual_cost'),
            'total_paid' => $event->budgetItems->where('paid', true)->sum('actual_cost'),
        ];

        $pdf = Pdf::loadView('exports.event-report-pdf', [
            'event' => $event,
            'guestStats' => $guestStats,
            'taskStats' => $taskStats,
            'budgetStats' => $budgetStats,
            'generatedAt' => now(),
        ]);

        $filename = Str::slug($event->title) . '-rapport-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export tasks to CSV.
     */
    public function exportTasksToCsv(Event $event): StreamedResponse
    {
        $filename = Str::slug($event->title) . '-taches-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $tasks = $event->tasks()->with('assignee')->orderBy('due_date')->get();

        $callback = function () use ($tasks) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'Titre',
                'Description',
                'Statut',
                'Priorité',
                'Assigné à',
                'Date d\'échéance',
                'Complété le',
            ], ';');

            foreach ($tasks as $task) {
                fputcsv($file, [
                    $task->title,
                    $task->description,
                    $this->getStatusLabel($task->status),
                    $this->getPriorityLabel($task->priority),
                    $task->assignee?->name ?? '',
                    $task->due_date?->format('d/m/Y') ?? '',
                    $task->completed_at?->format('d/m/Y H:i') ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get RSVP status label.
     */
    protected function getRsvpLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'accepted' => 'Accepté',
            'declined' => 'Décliné',
            'maybe' => 'Peut-être',
            default => $status,
        };
    }

    /**
     * Get category label.
     */
    protected function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'location' => 'Lieu',
            'catering' => 'Traiteur',
            'decoration' => 'Décoration',
            'entertainment' => 'Animation',
            'photography' => 'Photographie',
            'transportation' => 'Transport',
            'other' => 'Autre',
            default => $category,
        };
    }

    /**
     * Get task status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'todo' => 'À faire',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $status,
        };
    }

    /**
     * Get priority label.
     */
    protected function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            default => $priority,
        };
    }
}
