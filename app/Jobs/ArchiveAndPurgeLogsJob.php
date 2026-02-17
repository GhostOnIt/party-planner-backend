<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveAndPurgeLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Nombre de jours de rétention en base SQL.
     */
    protected int $retentionDays;

    /**
     * Nombre de logs à traiter par batch.
     */
    protected int $batchSize;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $retentionDays = 30,
        int $batchSize = 500
    ) {
        $this->retentionDays = $retentionDays;
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     *
     * Processus en 2 étapes :
     * 1. Archiver vers S3 les logs non encore archivés (s3_key = null) et âgés de + de X jours
     * 2. Purger de la base SQL les logs déjà archivés (s3_key != null) et âgés de + de X jours
     */
    public function handle(): void
    {
        Log::info('ArchiveAndPurgeLogsJob started', [
            'retention_days' => $this->retentionDays,
            'batch_size' => $this->batchSize,
        ]);

        $archivedCount = $this->archiveToS3();
        $purgedCount = $this->purgeFromDatabase();

        Log::info('ArchiveAndPurgeLogsJob completed', [
            'archived_to_s3' => $archivedCount,
            'purged_from_db' => $purgedCount,
        ]);
    }

    /**
     * Étape 1 : Archiver vers S3 les logs qui n'ont pas encore de s3_key.
     */
    protected function archiveToS3(): int
    {
        $totalArchived = 0;
        $disk = Storage::disk('s3-logs');

        ActivityLog::eligibleForArchival($this->retentionDays)
            ->chunkById($this->batchSize, function ($logs) use ($disk, &$totalArchived) {
                foreach ($logs as $log) {
                    try {
                        $s3Key = $this->writeLogToS3($disk, $log);

                        if ($s3Key) {
                            $log->update([
                                's3_key' => $s3Key,
                                's3_archived_at' => now(),
                            ]);
                            $totalArchived++;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to archive activity log to S3', [
                            'log_id' => $log->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $totalArchived;
    }

    /**
     * Étape 2 : Purger les logs SQL déjà archivés et âgés de + de X jours.
     */
    protected function purgeFromDatabase(): int
    {
        $totalPurged = 0;

        $query = ActivityLog::eligibleForPurge($this->retentionDays);
        $totalPurged = $query->count();

        if ($totalPurged > 0) {
            ActivityLog::eligibleForPurge($this->retentionDays)
                ->chunkById($this->batchSize, function ($logs) {
                    $ids = $logs->pluck('id')->toArray();
                    ActivityLog::whereIn('id', $ids)->delete();
                });
        }

        return $totalPurged;
    }

    /**
     * Écrire un log individuel en JSON sur S3.
     */
    protected function writeLogToS3($disk, ActivityLog $log): ?string
    {
        $uuid = Str::uuid()->toString();
        $date = $log->created_at;

        $s3Key = sprintf(
            '%s/%s/%s/%s.json',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
            $uuid
        );

        $payload = [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'actor_type' => $log->actor_type,
            'action' => $log->action,
            'model_type' => $log->model_type,
            'model_id' => $log->model_id,
            'description' => $log->description,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'source' => $log->source,
            'page_url' => $log->page_url,
            'session_id' => $log->session_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at?->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];

        $jsonContent = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $disk->put($s3Key, $jsonContent);

        return $s3Key;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ArchiveAndPurgeLogsJob failed', [
            'error' => $exception->getMessage(),
            'retention_days' => $this->retentionDays,
        ]);
    }
}
