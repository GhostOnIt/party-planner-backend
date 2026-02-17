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

class StoreActivityLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param array $logData Les données du log à enregistrer
     */
    public function __construct(
        public array $logData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 1. Insérer en base de données
            $activityLog = ActivityLog::create($this->logData);

            // 2. Écrire le fichier JSON sur S3
            $s3Key = $this->writeToS3($activityLog);

            // 3. Mettre à jour le log avec la clé S3
            if ($s3Key) {
                $activityLog->update(['s3_key' => $s3Key]);
            }
        } catch (\Exception $e) {
            Log::error('StoreActivityLogJob failed', [
                'error' => $e->getMessage(),
                'log_data' => $this->logData,
            ]);

            throw $e;
        }
    }

    /**
     * Écrire le log en tant que fichier JSON individuel sur S3.
     *
     * Structure : {year}/{month}/{day}/{uuid}.json
     * Exemple : 2026/02/17/a1b2c3d4-e5f6-7890-abcd-ef1234567890.json
     */
    protected function writeToS3(ActivityLog $activityLog): ?string
    {
        try {
            $disk = Storage::disk('s3-logs');

            $date = $activityLog->created_at;
            $uuid = Str::uuid()->toString();

            $s3Key = sprintf(
                '%s/%s/%s/%s.json',
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
                $uuid
            );

            $jsonContent = json_encode($this->buildS3Payload($activityLog), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $disk->put($s3Key, $jsonContent);

            return $s3Key;
        } catch (\Exception $e) {
            Log::warning('Failed to write activity log to S3', [
                'error' => $e->getMessage(),
                'log_id' => $activityLog->id,
            ]);

            return null;
        }
    }

    /**
     * Construire le payload JSON pour S3.
     */
    protected function buildS3Payload(ActivityLog $activityLog): array
    {
        return [
            'id' => $activityLog->id,
            'user_id' => $activityLog->user_id,
            'actor_type' => $activityLog->actor_type,
            'action' => $activityLog->action,
            'model_type' => $activityLog->model_type,
            'model_id' => $activityLog->model_id,
            'description' => $activityLog->description,
            'old_values' => $activityLog->old_values,
            'new_values' => $activityLog->new_values,
            'ip_address' => $activityLog->ip_address,
            'user_agent' => $activityLog->user_agent,
            'source' => $activityLog->source,
            'page_url' => $activityLog->page_url,
            'session_id' => $activityLog->session_id,
            'metadata' => $activityLog->metadata,
            'created_at' => $activityLog->created_at?->toISOString(),
            'updated_at' => $activityLog->updated_at?->toISOString(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('StoreActivityLogJob permanently failed', [
            'error' => $exception->getMessage(),
            'log_data' => $this->logData,
        ]);
    }
}
