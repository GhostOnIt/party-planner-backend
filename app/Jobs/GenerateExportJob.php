<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Event $event,
        public User $user,
        public string $exportType,
        public string $format = 'pdf'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        try {
            $filename = $this->generateFilename();
            $path = "exports/{$this->user->id}/{$filename}";

            $content = match ($this->exportType) {
                'guests' => $this->generateGuestsExport($exportService),
                'budget' => $this->generateBudgetExport($exportService),
                'tasks' => $this->generateTasksExport(),
                'report' => $this->generateReportExport(),
                default => throw new \InvalidArgumentException("Type d'export invalide: {$this->exportType}"),
            };

            Storage::disk('local')->put($path, $content);

            $this->user->notify(new ExportReadyNotification(
                $this->event,
                $this->exportType,
                $this->format,
                $path
            ));

            Log::info("GenerateExportJob: Export {$this->exportType} généré pour l'événement {$this->event->id}");
        } catch (\Exception $e) {
            Log::error("GenerateExportJob: Échec de génération - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate filename for export.
     */
    protected function generateFilename(): string
    {
        $slug = Str::slug($this->event->title);
        $date = now()->format('Y-m-d_His');

        return "{$slug}-{$this->exportType}-{$date}.{$this->format}";
    }

    /**
     * Generate guests export content.
     */
    protected function generateGuestsExport(ExportService $exportService): string
    {
        $guests = $this->event->guests()->orderBy('name')->get();

        $stats = [
            'total' => $guests->count(),
            'accepted' => $guests->where('rsvp_status', 'accepted')->count(),
            'declined' => $guests->where('rsvp_status', 'declined')->count(),
            'pending' => $guests->where('rsvp_status', 'pending')->count(),
            'maybe' => $guests->where('rsvp_status', 'maybe')->count(),
            'checked_in' => $guests->where('checked_in', true)->count(),
        ];

        $pdf = Pdf::loadView('exports.guests-pdf', [
            'event' => $this->event,
            'guests' => $guests,
            'stats' => $stats,
            'generatedAt' => now(),
        ]);

        return $pdf->output();
    }

    /**
     * Generate budget export content.
     */
    protected function generateBudgetExport(ExportService $exportService): string
    {
        $items = $this->event->budgetItems()->orderBy('category')->get();

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
            'event' => $this->event,
            'items' => $items,
            'stats' => $stats,
            'generatedAt' => now(),
        ]);

        return $pdf->output();
    }

    /**
     * Generate tasks export content.
     */
    protected function generateTasksExport(): string
    {
        $tasks = $this->event->tasks()->with('assignedUser')->orderBy('due_date')->get();

        $stats = [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'todo' => $tasks->where('status', 'todo')->count(),
        ];

        $pdf = Pdf::loadView('exports.tasks-pdf', [
            'event' => $this->event,
            'tasks' => $tasks,
            'stats' => $stats,
            'generatedAt' => now(),
        ]);

        return $pdf->output();
    }

    /**
     * Generate full event report content.
     */
    protected function generateReportExport(): string
    {
        $this->event->load(['guests', 'tasks', 'budgetItems', 'photos', 'collaborators.user', 'user']);

        $guestStats = [
            'total' => $this->event->guests->count(),
            'accepted' => $this->event->guests->where('rsvp_status', 'accepted')->count(),
            'declined' => $this->event->guests->where('rsvp_status', 'declined')->count(),
            'pending' => $this->event->guests->where('rsvp_status', 'pending')->count(),
            'checked_in' => $this->event->guests->where('checked_in', true)->count(),
        ];

        $taskStats = [
            'total' => $this->event->tasks->count(),
            'completed' => $this->event->tasks->where('status', 'completed')->count(),
            'in_progress' => $this->event->tasks->where('status', 'in_progress')->count(),
            'todo' => $this->event->tasks->where('status', 'todo')->count(),
        ];

        $budgetStats = [
            'total_estimated' => $this->event->budgetItems->sum('estimated_cost'),
            'total_actual' => $this->event->budgetItems->sum('actual_cost'),
            'total_paid' => $this->event->budgetItems->where('paid', true)->sum('actual_cost'),
        ];

        $pdf = Pdf::loadView('exports.event-report-pdf', [
            'event' => $this->event,
            'guestStats' => $guestStats,
            'taskStats' => $taskStats,
            'budgetStats' => $budgetStats,
            'generatedAt' => now(),
        ]);

        return $pdf->output();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateExportJob: Job échoué pour l'événement {$this->event->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'export',
            'event:' . $this->event->id,
            'user:' . $this->user->id,
            'type:' . $this->exportType,
        ];
    }
}
