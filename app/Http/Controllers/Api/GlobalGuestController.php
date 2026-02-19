<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\SendCampaignEmailJob;
use Illuminate\Pagination\LengthAwarePaginator;

class GlobalGuestController extends Controller
{
    public function __construct(
        protected GuestService $guestService
    ) {}

    /**
     * Get all guests for the authenticated user across all events.
     * Deduplicated by email/phone, prioritizing the most recent interaction.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Get IDs of events the user can access (owner or accepted collaborator only)
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');
        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique()->values();

        // 2. Early return if user has no accessible events
        if ($eventIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->input('per_page', 20),
                    'total' => 0,
                ],
                'stats' => ['total' => 0, 'with_email' => 0, 'with_phone' => 0],
            ]);
        }

        // 3. Build the base query - restrict to user's events only
        $query = Guest::query()
            ->with(['event:id,title,date'])
            ->whereIn('event_id', $eventIds);

        // 4. Apply Filters
        
        // Event Filter
        if ($request->filled('event_id') && $request->event_id !== 'all') {
            $query->where('event_id', $request->event_id);
        }

        // RSVP Status Filter
        if ($request->filled('rsvp_status') && $request->rsvp_status !== 'all') {
            $query->where('rsvp_status', $request->rsvp_status);
        }

        // Date Range Filter (based on Event Date)
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('event', function ($q) use ($request) {
                if ($request->filled('date_from')) {
                    $q->whereDate('date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->whereDate('date', '<=', $request->date_to);
                }
            });
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim(Str::lower((string) $request->input('search')));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE ?", [$like])
                      ->orWhereRaw("LOWER(COALESCE(phone, '')) LIKE ?", [$like]);
                });
            }
        }

        // 5. Fetch all matching guests (ordered by created_at desc for deduplication priority)
        $allGuests = $query->orderBy('created_at', 'desc')->get();

        // 6. Deduplicate in PHP
        // Group by email (if exists), then phone (if exists), else unique ID
        $uniqueGuests = $allGuests->unique(function ($guest) {
            if (!empty($guest->email)) {
                return 'email:' . strtolower($guest->email);
            }
            if (!empty($guest->phone)) {
                return 'phone:' . $guest->phone;
            }
            return 'id:' . $guest->id;
        });

        // 7. Manual Pagination
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $offset = ($page - 1) * $perPage;

        $items = $uniqueGuests->slice($offset, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $uniqueGuests->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // 8. Calculate Stats (on the full unique set)
        $stats = [
            'total' => $uniqueGuests->count(),
            'with_email' => $uniqueGuests->whereNotNull('email')->count(),
            'with_phone' => $uniqueGuests->whereNotNull('phone')->count(),
        ];

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Export guests to CSV/Excel/PDF.
     * Currently supports CSV.
     */
    public function export(Request $request)
    {
        // Reuse the index logic to get the filtered, deduplicated list
        // Note: For export, we might want ALL results, not paginated.
        
        $user = $request->user();
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');
        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique()->values();

        if ($eventIds->isEmpty()) {
            return response()->streamDownload(function () {
                echo "\xEF\xBB\xBF" . "Nom,Email,Téléphone,Événement,Date Événement,Statut,Dernière interaction\n";
            }, 'invites_export.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $query = Guest::query()
            ->with(['event:id,title,date'])
            ->whereIn('event_id', $eventIds);

        // Apply same filters as index...
        if ($request->filled('event_id') && $request->event_id !== 'all') {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('rsvp_status') && $request->rsvp_status !== 'all') {
            $query->where('rsvp_status', $request->rsvp_status);
        }
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('event', function ($q) use ($request) {
                if ($request->filled('date_from')) $q->whereDate('date', '>=', $request->date_from);
                if ($request->filled('date_to')) $q->whereDate('date', '<=', $request->date_to);
            });
        }
        if ($request->filled('search')) {
            $search = trim(Str::lower((string) $request->input('search')));
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE ?", [$like])
                      ->orWhereRaw("LOWER(COALESCE(phone, '')) LIKE ?", [$like]);
                });
            }
        }

        $allGuests = $query->orderBy('created_at', 'desc')->get();
        $uniqueGuests = $allGuests->unique(function ($guest) {
            if (!empty($guest->email)) return 'email:' . strtolower($guest->email);
            if (!empty($guest->phone)) return 'phone:' . $guest->phone;
            return 'id:' . $guest->id;
        });

        // Generate CSV
        $csv = "Nom,Email,Téléphone,Événement,Date Événement,Statut,Dernière interaction\n";
        
        foreach ($uniqueGuests as $guest) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $guest->name),
                $guest->email ?? '',
                $guest->phone ?? '',
                str_replace('"', '""', $guest->event->title ?? ''),
                $guest->event->date ? $guest->event->date->format('d/m/Y') : '',
                $guest->rsvp_status,
                $guest->created_at->format('d/m/Y')
            );
        }

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF" . $csv; // UTF-8 BOM
        }, 'invites_export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Send a campaign message to selected guests.
     */
    public function sendCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'guest_ids' => 'required|array',
            'guest_ids.*' => 'exists:guests,id',
        ]);

        $guestIds = $request->input('guest_ids');
        $subject = $request->input('subject');
        $message = $request->input('message');

        // Fetch guests ensuring they belong to user's events only
        $user = $request->user();
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');
        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique()->values();

        $guests = $eventIds->isEmpty()
            ? collect()
            : Guest::whereIn('id', $guestIds)
                ->whereIn('event_id', $eventIds)
                ->whereNotNull('email') // Only email supported for now
                ->get();

        $count = 0;
        foreach ($guests as $guest) {
            SendCampaignEmailJob::dispatch($guest, $subject, $message);
            $count++;
        }

        return response()->json([
            'message' => "Campagne envoyée à {$count} invité(s).",
            'count' => $count
        ]);
    }
}
