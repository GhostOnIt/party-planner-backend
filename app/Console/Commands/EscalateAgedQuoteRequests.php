<?php

namespace App\Console\Commands;

use App\Models\QuoteRequest;
use App\Services\QuoteRequestService;
use Illuminate\Console\Command;

class EscalateAgedQuoteRequests extends Command
{
    protected $signature = 'quote-requests:escalate-aged {--days=7 : Seuil (en jours) sans activité pour déclencher l\'escalade}';

    protected $description = 'Flag les demandes Business open sans activité depuis N jours et notifie l\'admin responsable';

    public function handle(QuoteRequestService $quoteRequestService): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $requests = QuoteRequest::query()
            ->where('status', 'open')
            ->with([
                'assignedAdmin',
                'activities' => fn ($q) => $q->latest()->limit(1),
            ])
            ->get()
            ->filter(function (QuoteRequest $request) use ($threshold) {
                $lastActivity = $request->activities->first();

                // Si la dernière activité est déjà 'stale_flagged' récente, on ne re-flag pas.
                if ($lastActivity && $lastActivity->activity_type === 'stale_flagged'
                    && $lastActivity->created_at->isAfter($threshold)) {
                    return false;
                }

                $lastTouchedAt = $lastActivity?->created_at ?? $request->created_at;

                return $lastTouchedAt->isBefore($threshold);
            });

        foreach ($requests as $request) {
            $lastActivity = $request->activities->first();
            $lastTouchedAt = $lastActivity?->created_at ?? $request->created_at;
            $daysSince = (int) floor($lastTouchedAt->diffInDays(now()));

            $quoteRequestService->logActivity(
                $request,
                'stale_flagged',
                "Demande sans activité depuis {$daysSince} jour(s) — escalade automatique.",
                ['days_since_last_activity' => $daysSince]
            );

            $quoteRequestService->notifyAdminsStaleRequest($request, $daysSince);
        }

        $count = $requests->count();
        $this->info("{$count} demande(s) escaladée(s).");

        return self::SUCCESS;
    }
}
