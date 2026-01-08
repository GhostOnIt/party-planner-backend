<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateEventStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update event statuses automatically based on date and time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Updating event statuses...');

        $now = now();
        $today = now()->startOfDay();
        
        // Get all events that are not cancelled (cancelled events are protected)
        $events = Event::where('status', '!=', EventStatus::CANCELLED->value)
            ->get();

        $updated = 0;
        $upcoming = 0;
        $ongoing = 0;
        $completed = 0;

        foreach ($events as $event) {
            $newStatus = $this->calculateStatus($event, $now, $today);
            
            if ($newStatus !== $event->status) {
                $event->update(['status' => $newStatus]);
                $updated++;
                
                match ($newStatus) {
                    EventStatus::UPCOMING->value => $upcoming++,
                    EventStatus::ONGOING->value => $ongoing++,
                    EventStatus::COMPLETED->value => $completed++,
                    default => null,
                };
            }
        }

        $this->info("Updated {$updated} event(s):");
        $this->line("  - Upcoming: {$upcoming}");
        $this->line("  - Ongoing: {$ongoing}");
        $this->line("  - Completed: {$completed}");

        return Command::SUCCESS;
    }

    /**
     * Calculate the appropriate status for an event based on its date and time.
     */
    private function calculateStatus(Event $event, $now, $today): string
    {
        // If event is cancelled, it stays cancelled (protected status)
        if ($event->status === EventStatus::CANCELLED->value) {
            return EventStatus::CANCELLED->value;
        }

        $eventDate = $event->date;
        
        // If event date is in the past
        if ($eventDate < $today) {
            return EventStatus::COMPLETED->value;
        }

        // If event date is in the future
        if ($eventDate > $today) {
            return EventStatus::UPCOMING->value;
        }

        // Event is today - check time
        if ($eventDate->isSameDay($today)) {
            if (is_null($event->time)) {
                // No time set, consider it ongoing for the whole day
                return EventStatus::ONGOING->value;
            }

            // Time is set, check if it has passed
            $eventDateTime = \Carbon\Carbon::parse($event->date->format('Y-m-d') . ' ' . $event->time);
            
            if ($eventDateTime < $now) {
                return EventStatus::COMPLETED->value;
            } else {
                return EventStatus::ONGOING->value;
            }
        }

        // Default to upcoming if we can't determine
        return EventStatus::UPCOMING->value;
    }
}
