<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Jobs\SendBulkInvitationsJob;
use App\Jobs\SendInvitationJob;
use App\Jobs\SendReminderJob;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}
    /**
     * Create a new guest for an event.
     */
    public function create(Event $event, array $data, bool $sendInvitation = true): Guest
    {
        return DB::transaction(function () use ($event, $data, $sendInvitation) {
            $guest = $event->guests()->create($data);

            // Create invitation with token
            $this->createInvitation($guest);

            if($sendInvitation && !empty($guest->email)) {
                $this->sendInvitation($guest);
            }
            return $guest->fresh(['invitation']);
        });
    }

    /**
     * Update a guest.
     */
    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($data);

        return $guest->fresh();
    }

    /**
     * Delete a guest and their invitation.
     */
    public function delete(Guest $guest): void
    {
        DB::transaction(function () use ($guest) {
            $guest->invitation?->delete();
            $guest->delete();
        });
    }

    /**
     * Import guests from CSV file.
     */
    public function importFromCsv(Event $event, UploadedFile $file, array $options = []): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            $results['errors'][] = 'Impossible d\'ouvrir le fichier.';
            return $results;
        }

        // Read header row
        $headers = fgetcsv($handle, 0, $options['delimiter'] ?? ',');

        if (!$headers) {
            fclose($handle);
            $results['errors'][] = 'Le fichier est vide ou mal formaté.';
            return $results;
        }

        // Normalize headers
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Map column names
        $columnMap = $this->getColumnMap($headers);

        if (!isset($columnMap['name'])) {
            fclose($handle);
            $results['errors'][] = 'La colonne "nom" est requise.';
            return $results;
        }

        $rowNumber = 1;

        DB::transaction(function () use ($handle, $event, $columnMap, $options, &$results, &$rowNumber) {
            while (($row = fgetcsv($handle, 0, $options['delimiter'] ?? ',')) !== false) {
                $rowNumber++;

                try {
                    $guestData = $this->parseRowData($row, $columnMap);

                    if (empty($guestData['name'])) {
                        $results['skipped']++;
                        continue;
                    }

                    // Check for duplicates if option is set
                    if ($options['skip_duplicates'] ?? true) {
                        $existingGuest = $event->guests()
                            ->where(function ($query) use ($guestData) {
                                $query->where('name', $guestData['name']);
                                if (!empty($guestData['email'])) {
                                    $query->orWhere('email', $guestData['email']);
                                }
                            })
                            ->first();

                        if ($existingGuest) {
                            $results['skipped']++;
                            continue;
                        }
                    }

                    $guest = $event->guests()->create($guestData);
                    $this->createInvitation($guest);

                    $results['imported']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Ligne {$rowNumber}: " . $e->getMessage();
                }
            }
        });

        fclose($handle);

        return $results;
    }

    /**
     * Parse CSV row data into guest data array.
     */
    protected function parseRowData(array $row, array $columnMap): array
    {
        $data = [
            'rsvp_status' => RsvpStatus::PENDING->value,
        ];

        foreach ($columnMap as $field => $index) {
            if (isset($row[$index])) {
                $value = trim($row[$index]);

                if ($field === 'rsvp_status') {
                    $value = $this->normalizeRsvpStatus($value);
                }

                $data[$field] = $value ?: null;
            }
        }

        return $data;
    }

    /**
     * Get column map from headers.
     */
    protected function getColumnMap(array $headers): array
    {
        $map = [];

        $aliases = [
            'name' => ['nom', 'name', 'guest', 'invité', 'invite', 'nom complet', 'full name'],
            'email' => ['email', 'e-mail', 'mail', 'courriel', 'adresse email'],
            'phone' => ['phone', 'téléphone', 'telephone', 'tel', 'mobile', 'numéro'],
            'notes' => ['notes', 'note', 'commentaire', 'comment', 'remarque'],
            'rsvp_status' => ['rsvp', 'status', 'statut', 'réponse', 'reponse', 'confirmation'],
        ];

        foreach ($aliases as $field => $possibleNames) {
            foreach ($headers as $index => $header) {
                if (in_array($header, $possibleNames)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Normalize RSVP status from import.
     */
    protected function normalizeRsvpStatus(string $value): string
    {
        $value = strtolower($value);

        $statusMap = [
            'oui' => RsvpStatus::ACCEPTED->value,
            'yes' => RsvpStatus::ACCEPTED->value,
            'accepté' => RsvpStatus::ACCEPTED->value,
            'accepte' => RsvpStatus::ACCEPTED->value,
            'confirmed' => RsvpStatus::ACCEPTED->value,
            'confirmé' => RsvpStatus::ACCEPTED->value,
            'non' => RsvpStatus::DECLINED->value,
            'no' => RsvpStatus::DECLINED->value,
            'refusé' => RsvpStatus::DECLINED->value,
            'refuse' => RsvpStatus::DECLINED->value,
            'declined' => RsvpStatus::DECLINED->value,
            'peut-être' => RsvpStatus::MAYBE->value,
            'maybe' => RsvpStatus::MAYBE->value,
            'incertain' => RsvpStatus::MAYBE->value,
        ];

        return $statusMap[$value] ?? RsvpStatus::PENDING->value;
    }

    /**
     * Create an invitation for a guest.
     */
    public function createInvitation(Guest $guest, ?string $customMessage = null): Invitation
    {
        return $guest->invitation()->create([
            'event_id' => $guest->event_id,
            'token' => Str::random(config('partyplanner.invitations.token_length', 32)),
            'template_id' => config('partyplanner.invitations.default_template'),
            'custom_message' => $customMessage,
        ]);
    }

    /**
     * Regenerate invitation token.
     */
    public function regenerateToken(Guest $guest): Invitation
    {
        $invitation = $guest->invitation;

        if (!$invitation) {
            return $this->createInvitation($guest);
        }

        $invitation->update([
            'token' => Str::random(config('partyplanner.invitations.token_length', 32)),
            'sent_at' => null,
            'opened_at' => null,
            'responded_at' => null,
        ]);

        return $invitation->fresh();
    }

    /**
     * Send invitation to a single guest.
     * If invitation was already sent, sends a reminder instead.
     */
    public function sendInvitation(Guest $guest, ?string $customMessage = null): array
    {
        // Check if guest has email
        if (empty($guest->email)) {
            throw new \InvalidArgumentException('L\'invité n\'a pas d\'adresse email.');
        }

        // Check if invitation was already sent
        if ($guest->invitation_sent_at) {
            // Send reminder instead
            // Check if guest hasn't already responded (accepted or declined)
            if (in_array($guest->rsvp_status, [RsvpStatus::ACCEPTED->value, RsvpStatus::DECLINED->value])) {
                throw new \InvalidArgumentException('L\'invité a déjà répondu, pas besoin de rappel.');
            }

            // Dispatch the reminder job
            // With QUEUE_CONNECTION=database, jobs are queued and processed by the worker
            SendReminderJob::dispatch($guest);

            return [
                'type' => 'reminder',
                'message' => 'Rappel envoyé avec succès.',
            ];
        }

        // Send new invitation
        // Ensure invitation exists
        $invitation = $guest->invitation ?? $this->createInvitation($guest, $customMessage);

        if ($customMessage && $invitation->custom_message !== $customMessage) {
            $invitation->update(['custom_message' => $customMessage]);
        }

        // Dispatch the invitation job
        // With QUEUE_CONNECTION=database, jobs are queued and processed by the worker
        SendInvitationJob::dispatch($guest);

        return [
            'type' => 'invitation',
            'message' => 'Invitation envoyée avec succès.',
        ];
    }

    /**
     * Send invitations to multiple guests.
     * For guests who already have invitations, sends reminders instead.
     */
    public function sendBulkInvitations(Event $event, ?Collection $guests = null, ?string $customMessage = null): array
    {
        // Get all guests with email (not just those without invitations)
        $guestsToProcess = $guests ?? $event->guests()
            ->whereNotNull('email')
            ->get();

        if ($guestsToProcess->isEmpty()) {
            return [
                'invitations' => 0,
                'reminders' => 0,
                'total' => 0,
            ];
        }

        $invitationCount = 0;
        $reminderCount = 0;

        foreach ($guestsToProcess as $guest) {
            try {
                // Check if invitation was already sent
                if ($guest->invitation_sent_at) {
                    // Send reminder if guest hasn't responded (accepted or declined)
                    if (!in_array($guest->rsvp_status, [RsvpStatus::ACCEPTED->value, RsvpStatus::DECLINED->value])) {
                        // Dispatch the reminder job
                        SendReminderJob::dispatch($guest);
                        $reminderCount++;
                    }
                } else {
                    // Send new invitation
                    // Ensure invitation exists
                    if (!$guest->invitation) {
                        $this->createInvitation($guest, $customMessage);
                    }

                    // Dispatch the invitation job
                    SendInvitationJob::dispatch($guest);
                    $invitationCount++;
                }
            } catch (\Exception $e) {
                // Log error but continue with other guests
                \Log::error("Error sending invitation/reminder to guest {$guest->id}: " . $e->getMessage());
            }
        }

        return [
            'invitations' => $invitationCount,
            'reminders' => $reminderCount,
            'total' => $invitationCount + $reminderCount,
        ];
    }

    /**
     * Send reminder to a single guest.
     */
    public function sendReminder(Guest $guest): void
    {
        // Check if guest has email
        if (empty($guest->email)) {
            throw new \InvalidArgumentException('L\'invité n\'a pas d\'adresse email.');
        }

        // Check if invitation was sent
        if (!$guest->invitation_sent_at) {
            throw new \InvalidArgumentException('L\'invitation n\'a pas encore été envoyée.');
        }

        // Check if guest hasn't already responded (accepted or declined)
        if (in_array($guest->rsvp_status, [RsvpStatus::ACCEPTED->value, RsvpStatus::DECLINED->value])) {
            throw new \InvalidArgumentException('L\'invité a déjà répondu, pas besoin de rappel.');
        }

        // Dispatch the reminder job
        // With QUEUE_CONNECTION=database, jobs are queued and processed by the worker
        SendReminderJob::dispatch($guest);
    }

    /**
     * Send reminder to guests who haven't responded.
     */
    public function sendReminders(Event $event): int
    {
        $guestsToRemind = $event->guests()
            ->whereIn('rsvp_status', [RsvpStatus::PENDING->value, RsvpStatus::MAYBE->value])
            ->whereNotNull('invitation_sent_at')
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<', now()->subDays(7));
            })
            ->get();

        foreach ($guestsToRemind as $guest) {
            SendReminderJob::dispatch($guest);
        }

        return $guestsToRemind->count();
    }

    /**
     * Check in a guest.
     */
    public function checkIn(Guest $guest): Guest
    {
        // Only allow check-in for guests with pending, accepted, or maybe status
        if (!in_array($guest->rsvp_status, ['pending', 'accepted', 'maybe'])) {
            throw new \InvalidArgumentException('Seuls les invités avec un statut "en attente", "accepté" ou "peut-être" peuvent être enregistrés.');
        }

        $wasCheckedIn = $guest->checked_in;

        $guest->update([
            'checked_in' => true,
            'checked_in_at' => now(),
        ]);

        // Refresh to get updated photo_upload_token if it was auto-generated
        $guest->refresh();

        // Send photo upload link email if guest has email and wasn't already checked in
        if (!$wasCheckedIn && !empty($guest->email) && !empty($guest->photo_upload_token)) {
            // Dispatch the photo upload link job
            // With QUEUE_CONNECTION=database, jobs are queued and processed by the worker
            \App\Jobs\SendPhotoUploadLinkJob::dispatch($guest);
        }

        return $guest;
    }

    /**
     * Undo check-in.
     */
    public function undoCheckIn(Guest $guest): Guest
    {
        $guest->update([
            'checked_in' => false,
            'checked_in_at' => null,
        ]);

        return $guest->fresh();
    }

    /**
     * Update RSVP status.
     */
    public function updateRsvpStatus(Guest $guest, RsvpStatus $status): Guest
    {
        $guest->update(['rsvp_status' => $status->value]);

        // Update invitation responded_at
        if ($guest->invitation && !$guest->invitation->responded_at) {
            $guest->invitation->update(['responded_at' => now()]);
        }

        return $guest->fresh();
    }

    /**
     * Get guest statistics for an event.
     */
    public function getStatistics(Event $event): array
    {
        $guests = $event->guests;

        return [
            'total' => $guests->count(),
            'by_status' => [
                'accepted' => $guests->where('rsvp_status', RsvpStatus::ACCEPTED->value)->count(),
                'declined' => $guests->where('rsvp_status', RsvpStatus::DECLINED->value)->count(),
                'pending' => $guests->where('rsvp_status', RsvpStatus::PENDING->value)->count(),
                'maybe' => $guests->where('rsvp_status', RsvpStatus::MAYBE->value)->count(),
            ],
            'invitations' => [
                'sent' => $guests->whereNotNull('invitation_sent_at')->count(),
                'not_sent' => $guests->whereNull('invitation_sent_at')->count(),
            ],
            'check_in' => [
                'checked_in' => $guests->where('checked_in', true)->count(),
                'not_checked_in' => $guests->where('checked_in', false)->count(),
            ],
            'with_email' => $guests->whereNotNull('email')->count(),
            'without_email' => $guests->whereNull('email')->count(),
        ];
    }

    /**
     * Check if event can add more guests.
     * Uses max_guests_allowed stored on the event (set at creation time).
     * This allows events created during an active subscription to keep their limits
     * even after the subscription expires.
     */
    public function canAddGuest(Event $event): bool
    {
        $currentCount = $event->guests()->count();

        // If event has max_guests_allowed set (from when it was created), use that
        if ($event->max_guests_allowed !== null) {
            return $currentCount < $event->max_guests_allowed;
        }

        // Fallback: check current subscription (for backward compatibility with old events)
        $subscription = $this->subscriptionService->getUserActiveSubscription($event->user);

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan) {
                $maxGuests = $plan->getGuestsLimit();
                return $currentCount < $maxGuests;
            }
        }

        // Free tier fallback
        $maxGuests = config('partyplanner.free_tier.max_guests', 10);
        return $currentCount < $maxGuests;
    }

    /**
     * Get remaining guest slots.
     * Uses max_guests_allowed stored on the event.
     */
    public function getRemainingSlots(Event $event): int
    {
        $currentCount = $event->guests()->count();

        // If event has max_guests_allowed set, use that
        if ($event->max_guests_allowed !== null) {
            return max(0, $event->max_guests_allowed - $currentCount);
        }

        // Fallback: check current subscription (for backward compatibility)
        $subscription = $this->subscriptionService->getUserActiveSubscription($event->user);

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan) {
                $maxGuests = $plan->getGuestsLimit();
                return max(0, $maxGuests - $currentCount);
            }
        }

        // Free tier fallback
        $maxGuests = config('partyplanner.free_tier.max_guests', 10);
        return max(0, $maxGuests - $currentCount);
    }

    /**
     * Export guests to CSV.
     */
    public function exportToCsv(Event $event): string
    {
        $guests = $event->guests()->with('invitation')->get();

        $csv = "Nom,Email,Téléphone,Statut RSVP,Invitation envoyée,Enregistré,Notes\n";

        foreach ($guests as $guest) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $guest->name),
                $guest->email ?? '',
                $guest->phone ?? '',
                RsvpStatus::tryFrom($guest->rsvp_status)?->label() ?? $guest->rsvp_status,
                $guest->invitation_sent_at?->format('d/m/Y H:i') ?? 'Non',
                $guest->checked_in ? 'Oui' : 'Non',
                str_replace('"', '""', $guest->notes ?? '')
            );
        }

        return $csv;
    }

    /**
     * Preview CSV import data.
     */
    public function previewCsvImport(UploadedFile $file, array $options = []): array
    {
        $preview = [
            'headers' => [],
            'rows' => [],
            'total_rows' => 0,
            'errors' => [],
        ];

        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            $preview['errors'][] = 'Impossible d\'ouvrir le fichier.';
            return $preview;
        }

        // Read header row
        $headers = fgetcsv($handle, 0, $options['delimiter'] ?? ',');

        if (!$headers) {
            fclose($handle);
            $preview['errors'][] = 'Le fichier est vide ou mal formaté.';
            return $preview;
        }

        // Normalize headers
        $normalizedHeaders = array_map(fn($h) => strtolower(trim($h)), $headers);
        $columnMap = $this->getColumnMap($normalizedHeaders);

        if (!isset($columnMap['name'])) {
            $preview['errors'][] = 'La colonne "nom" est requise.';
        }

        $preview['headers'] = $headers;

        // Read up to 100 rows for preview
        $rowNumber = 0;
        while (($row = fgetcsv($handle, 0, $options['delimiter'] ?? ',')) !== false && $rowNumber < 100) {
            $rowNumber++;
            $preview['total_rows']++;

            $guestData = $this->parseRowData($row, $columnMap);
            $guestData['_raw'] = $row;

            $preview['rows'][] = $guestData;
        }

        // Count remaining rows
        while (fgetcsv($handle, 0, $options['delimiter'] ?? ',') !== false) {
            $preview['total_rows']++;
        }

        fclose($handle);

        return $preview;
    }

    /**
     * Import guests from Excel file.
     */
    public function importFromExcel(Event $event, UploadedFile $file, array $options = []): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                $results['errors'][] = 'Le fichier est vide.';
                return $results;
            }

            // First row is headers
            $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
            $columnMap = $this->getColumnMap($headers);

            if (!isset($columnMap['name'])) {
                $results['errors'][] = 'La colonne "nom" est requise.';
                return $results;
            }

            DB::transaction(function () use ($rows, $event, $columnMap, $options, &$results) {
                // Skip header row
                for ($i = 1; $i < count($rows); $i++) {
                    $rowNumber = $i + 1;

                    try {
                        $guestData = $this->parseRowData($rows[$i], $columnMap);

                        if (empty($guestData['name'])) {
                            $results['skipped']++;
                            continue;
                        }

                        // Check for duplicates
                        if ($options['skip_duplicates'] ?? true) {
                            $existingGuest = $event->guests()
                                ->where(function ($query) use ($guestData) {
                                    $query->where('name', $guestData['name']);
                                    if (!empty($guestData['email'])) {
                                        $query->orWhere('email', $guestData['email']);
                                    }
                                })
                                ->first();

                            if ($existingGuest) {
                                $results['skipped']++;
                                continue;
                            }
                        }

                        $guest = $event->guests()->create($guestData);
                        $this->createInvitation($guest);

                        $results['imported']++;
                    } catch (\Exception $e) {
                        $results['errors'][] = "Ligne {$rowNumber}: " . $e->getMessage();
                    }
                }
            });
        } catch (\Exception $e) {
            $results['errors'][] = 'Erreur lors de la lecture du fichier Excel: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Preview Excel import data.
     */
    public function previewExcelImport(UploadedFile $file, array $options = []): array
    {
        $preview = [
            'headers' => [],
            'rows' => [],
            'total_rows' => 0,
            'errors' => [],
        ];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                $preview['errors'][] = 'Le fichier est vide.';
                return $preview;
            }

            // First row is headers
            $headers = $rows[0];
            $normalizedHeaders = array_map(fn($h) => strtolower(trim($h ?? '')), $headers);
            $columnMap = $this->getColumnMap($normalizedHeaders);

            if (!isset($columnMap['name'])) {
                $preview['errors'][] = 'La colonne "nom" est requise.';
            }

            $preview['headers'] = $headers;
            $preview['total_rows'] = count($rows) - 1;

            // Read up to 100 rows for preview
            $maxRows = min(count($rows), 101);
            for ($i = 1; $i < $maxRows; $i++) {
                $guestData = $this->parseRowData($rows[$i], $columnMap);
                $guestData['_raw'] = $rows[$i];
                $preview['rows'][] = $guestData;
            }
        } catch (\Exception $e) {
            $preview['errors'][] = 'Erreur lors de la lecture du fichier Excel: ' . $e->getMessage();
        }

        return $preview;
    }




  
}
