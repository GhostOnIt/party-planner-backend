<?php

namespace App\Http\Controllers;

use App\Exports\BudgetExport;
use App\Exports\GuestsExport;
use App\Exports\TasksExport;
use App\Models\Event;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Show export options page.
     */
    public function index(Event $event)
    {
        $this->authorize('view', $event);

        $stats = [
            'guests_count' => $event->guests()->count(),
            'tasks_count' => $event->tasks()->count(),
            'budget_items_count' => $event->budgetItems()->count(),
        ];

        return view('exports.index', compact('event', 'stats'));
    }

    /**
     * Export guests to CSV.
     */
    public function exportGuestsCsv(Event $event, Request $request): StreamedResponse
    {
        $this->authorize('view', $event);

        $filters = $this->extractFilters($request);

        return $this->exportService->exportGuestsToCsv($event, $filters);
    }

    /**
     * Export guests to PDF.
     */
    public function exportGuestsPdf(Event $event, Request $request): Response
    {
        $this->authorize('view', $event);

        $filters = $this->extractFilters($request);

        return $this->exportService->exportGuestsToPdf($event, $filters);
    }

    /**
     * Export guests to Excel.
     */
    public function exportGuestsXlsx(Event $event, Request $request): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $filters = $this->extractFilters($request);
        $filename = Str::slug($event->title) . '-invites-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new GuestsExport($event, $filters), $filename);
    }

    /**
     * Extract filters from request.
     */
    protected function extractFilters(Request $request): array
    {
        $filters = [];

        // RSVP status filter (can be multiple)
        if ($request->filled('rsvp_status')) {
            $statuses = is_array($request->rsvp_status)
                ? $request->rsvp_status
                : explode(',', $request->rsvp_status);
            $filters['rsvp_status'] = array_filter($statuses);
        }

        // Check-in filter
        if ($request->filled('checked_in')) {
            $filters['checked_in'] = $request->boolean('checked_in');
        }

        // Invitation sent filter
        if ($request->filled('invitation_sent')) {
            $filters['invitation_sent'] = $request->boolean('invitation_sent');
        }

        return $filters;
    }

    /**
     * Export budget to CSV.
     */
    public function exportBudgetCsv(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        return $this->exportService->exportBudgetToCsv($event);
    }

    /**
     * Export budget to PDF.
     */
    public function exportBudgetPdf(Event $event): Response
    {
        $this->authorize('view', $event);

        return $this->exportService->exportBudgetToPdf($event);
    }

    /**
     * Export budget to Excel.
     */
    public function exportBudgetXlsx(Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $filename = Str::slug($event->title) . '-budget-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new BudgetExport($event), $filename);
    }

    /**
     * Export tasks to CSV.
     */
    public function exportTasksCsv(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        return $this->exportService->exportTasksToCsv($event);
    }

    /**
     * Export tasks to Excel.
     */
    public function exportTasksXlsx(Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $filename = Str::slug($event->title) . '-taches-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new TasksExport($event), $filename);
    }

    /**
     * Export full event report to PDF.
     */
    public function exportReport(Event $event): Response
    {
        $this->authorize('view', $event);

        return $this->exportService->exportEventReportPdf($event);
    }
}
