<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchLogsRequest;
use App\Models\ActivityLog;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityLogController extends Controller
{
    public function __construct(
        protected ActivityService $activityService
    ) {}

    /**
     * Recevoir un batch d'événements frontend (navigation, UI interactions).
     *
     * POST /api/activity-logs/batch
     */
    public function storeBatch(StoreBatchLogsRequest $request): JsonResponse
    {
        $user = $request->user();
        $events = $request->validated('events');

        $count = $this->activityService->logFrontendBatch($events, $user);

        return response()->json([
            'message' => "{$count} événements enregistrés.",
            'count' => $count,
        ], 201);
    }

    /**
     * Lister les logs d'activité avec filtres et pagination (admin uniquement).
     *
     * GET /api/admin/activity-logs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => $request->input('user_id', $request->input('admin_id')),
            'actor_type' => $request->input('actor_type'),
            'source' => $request->input('source'),
            'action' => $request->input('action'),
            'model_type' => $request->input('model_type'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'search' => $request->input('search'),
            'session_id' => $request->input('session_id'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_dir' => $request->input('sort_dir', 'desc'),
        ];

        $perPage = $request->input('per_page', 15);
        $logs = $this->activityService->getActivityLogs($filters, $perPage);

        return response()->json($logs);
    }

    /**
     * Statistiques des logs d'activité (admin uniquement).
     *
     * GET /api/admin/activity-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $actorType = $request->input('actor_type');
        $source = $request->input('source');

        $stats = $this->activityService->getActivityStats($actorType, $source);

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Détail d'un log d'activité (admin uniquement).
     *
     * GET /api/admin/activity-logs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $log = ActivityLog::with('user')->findOrFail($id);

        return response()->json([
            'log' => $log,
        ]);
    }

    /**
     * Générer un lien temporaire vers un log archivé en S3.
     *
     * GET /api/admin/activity-logs/{id}/s3-url
     */
    public function s3Url(int $id): JsonResponse
    {
        $log = ActivityLog::findOrFail($id);

        if (!$log->s3_key) {
            return response()->json([
                'message' => 'Ce log n\'est pas encore archivé sur S3.',
            ], 404);
        }

        $disk = Storage::disk('s3-logs');

        if (!$disk->exists($log->s3_key)) {
            return response()->json([
                'message' => 'Le fichier S3 n\'a pas été trouvé.',
            ], 404);
        }

        $url = $disk->temporaryUrl($log->s3_key, now()->addMinutes(30));

        return response()->json([
            'url' => $url,
            's3_key' => $log->s3_key,
            'expires_at' => now()->addMinutes(30)->toISOString(),
        ]);
    }

    /**
     * Exporter les logs archivés en S3 pour une période donnée.
     *
     * GET /api/admin/activity-logs/export
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $disk = Storage::disk('s3-logs');

        $files = [];
        $current = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);

        while ($current->lte($end)) {
            $prefix = $current->format('Y/m/d');
            $dayFiles = $disk->files($prefix);

            foreach ($dayFiles as $file) {
                $files[] = [
                    's3_key' => $file,
                    'date' => $current->format('Y-m-d'),
                    'url' => $disk->temporaryUrl($file, now()->addHours(1)),
                ];
            }

            $current->addDay();
        }

        return response()->json([
            'files' => $files,
            'total' => count($files),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'expires_at' => now()->addHours(1)->toISOString(),
        ]);
    }
}
