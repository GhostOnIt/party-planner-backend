<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function __construct(
        protected GuestService $guestService
    ) {}
    /**
     * Display a listing of guests for an event.
     */

     public function index(Request $request, Event $event): JsonResponse
{
    $this->authorize('view', $event);

    $query = $event->guests();

    // Filter by RSVP status
    if ($request->filled('rsvp_status')) {
        $query->where('rsvp_status', $request->rsvp_status);
    }

    // Search by name, email, or phone
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // Order by name
    $query->orderBy('name');

    // Pagination with per_page support
    $perPage = $request->input('per_page', 20);
    $guests = $query->paginate($perPage);

    // Get stats if needed (optional, can be removed if stats come from separate endpoint)
    $stats = $this->guestService->getStatistics($event);

    return response()->json([
        'data' => $guests->items(),
        'meta' => [
            'current_page' => $guests->currentPage(),
            'last_page' => $guests->lastPage(),
            'per_page' => $guests->perPage(),
            'total' => $guests->total(),
        ],
        'stats' => $stats,
    ]);
}
     
    /**
     * Store a newly created guest.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('guests')->where(function ($query) use ($event) {
                    return $query->where('event_id', $event->id)
                        ->whereNotNull('email');
                }),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('guests')->where(function ($query) use ($event) {
                    return $query->where('event_id', $event->id)
                        ->whereNotNull('phone');
                }),
            ],
            'notes' => 'nullable|string',
            'send_invitation' => 'sometimes|boolean',
        ], [
            'email.unique' => 'Cet email est déjà utilisé pour un invité de cet événement.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé pour un invité de cet événement.',
        ]);

        $sendInvitation = $request->boolean('send_invitation', true);
        unset($validated['send_invitation']);

        $guest = $this->guestService->create($event, $validated, $sendInvitation);

        return response()->json($guest, 201);
    }

    /**
     * Display the specified guest.
     */
    public function show(Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json($guest);
    }

    /**
     * Update the specified guest.
     */
    public function update(Request $request, Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('guests')->where(function ($query) use ($event) {
                    return $query->where('event_id', $event->id)
                        ->whereNotNull('email');
                })->ignore($guest->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('guests')->where(function ($query) use ($event) {
                    return $query->where('event_id', $event->id)
                        ->whereNotNull('phone');
                })->ignore($guest->id),
            ],
            'rsvp_status' => 'sometimes|required|in:pending,accepted,declined,maybe',
            'notes' => 'nullable|string',
        ], [
            'email.unique' => 'Cet email est déjà utilisé pour un invité de cet événement.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé pour un invité de cet événement.',
        ]);

        $guest->update($validated);

        return response()->json($guest);
    }

    /**
     * Remove the specified guest.
     */
    public function destroy(Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('update', $event);

        $guest->delete();

        return response()->json(null, 204);
    }

    /**
     * Get guest statistics for an event.
     */
    public function statistics(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $stats = $this->guestService->getStatistics($event);
        $canAddMore = $this->guestService->canAddGuest($event);
        $remainingSlots = $this->guestService->getRemainingSlots($event);

        return response()->json([
            'statistics' => $stats,
            'can_add_more' => $canAddMore,
            'remaining_slots' => $remainingSlots,
        ]);
    }

    /**
     * Import guests from CSV/Excel file.
     */
    public function import(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'skip_duplicates' => 'sometimes|boolean',
            'delimiter' => ['sometimes', 'string', 'max:1', function ($attribute, $value, $fail) {
                if (!in_array($value, [',', ';', "\t"])) {
                    $fail('Le délimiteur doit être une virgule, un point-virgule ou une tabulation.');
                }
            }],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $options = [
            'skip_duplicates' => $validated['skip_duplicates'] ?? true,
            'delimiter' => $validated['delimiter'] ?? ',',
        ];

        // Handle Excel files
        if (in_array($extension, ['xlsx', 'xls'])) {
            $results = $this->guestService->importFromExcel($event, $file, $options);
        } else {
            $results = $this->guestService->importFromCsv($event, $file, $options);
        }

        $statusCode = empty($results['errors']) ? 200 : 207;

        return response()->json([
            'message' => "Import terminé: {$results['imported']} invité(s) importé(s), {$results['skipped']} ignoré(s).",
            'data' => $results,
        ], $statusCode);
    }

    /**
     * Download import template.
     */
    public function downloadTemplate(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $format = $request->query('format', 'csv');

        if ($format === 'csv') {
            $content = "nom,email,telephone,notes,statut\nJean Dupont,jean@example.com,+33612345678,Ami de la famille,pending\nMarie Martin,marie@example.com,,Collègue,";

            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, 'template_invites.csv', [
                'Content-Type' => 'text/csv',
            ]);
        }

        // For Excel, return CSV with proper encoding
        $content = "nom,email,telephone,notes,statut\nJean Dupont,jean@example.com,+33612345678,Ami de la famille,pending\nMarie Martin,marie@example.com,,Collègue,";

        return response()->streamDownload(function () use ($content) {
            echo "\xEF\xBB\xBF" . $content; // UTF-8 BOM for Excel
        }, 'template_invites.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Preview import data before confirming.
     */
    public function previewImport(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'delimiter' => ['sometimes', 'string', 'max:1', function ($attribute, $value, $fail) {
                if (!in_array($value, [',', ';', "\t"])) {
                    $fail('Le délimiteur doit être une virgule, un point-virgule ou une tabulation.');
                }
            }],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $options = [
            'delimiter' => $validated['delimiter'] ?? ',',
        ];

        if (in_array($extension, ['xlsx', 'xls'])) {
            $preview = $this->guestService->previewExcelImport($file, $options);
        } else {
            $preview = $this->guestService->previewCsvImport($file, $options);
        }

        // Check for duplicates
        $existingEmails = $event->guests()->whereNotNull('email')->pluck('email')->toArray();
        $existingNames = $event->guests()->pluck('name')->toArray();

        foreach ($preview['rows'] as &$row) {
            $row['is_duplicate'] = false;
            if (!empty($row['email']) && in_array($row['email'], $existingEmails)) {
                $row['is_duplicate'] = true;
            } elseif (!empty($row['name']) && in_array($row['name'], $existingNames)) {
                $row['is_duplicate'] = true;
            }
        }

        return response()->json($preview);
    }
}
