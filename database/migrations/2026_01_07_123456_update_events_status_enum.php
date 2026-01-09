<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate existing data to new status values
        $today = now()->startOfDay();
        $now = now();
        
        // Migrate draft, planning, confirmed to upcoming or completed based on date
        // Events in the future -> upcoming
        DB::table('events')
            ->whereIn('status', ['draft', 'planning', 'confirmed'])
            ->where('date', '>', $today)
            ->update(['status' => 'upcoming']);
        
        // Events in the past -> completed
        DB::table('events')
            ->whereIn('status', ['draft', 'planning', 'confirmed'])
            ->where('date', '<', $today)
            ->update(['status' => 'completed']);
        
        // Events happening today - need to check time
        // If time is null -> ongoing (all day event)
        // If time is set and hasn't passed -> ongoing
        // If time is set and has passed -> completed
        $eventsToday = DB::table('events')
            ->whereIn('status', ['draft', 'planning', 'confirmed'])
            ->whereDate('date', $today)
            ->get();
        
        foreach ($eventsToday as $event) {
            if (is_null($event->time)) {
                // No time set, consider it ongoing for the whole day
                DB::table('events')->where('id', $event->id)->update(['status' => 'ongoing']);
            } else {
                // Time is set, check if it has passed
                $eventDateTime = \Carbon\Carbon::parse($event->date . ' ' . $event->time);
                if ($eventDateTime >= $now) {
                    DB::table('events')->where('id', $event->id)->update(['status' => 'ongoing']);
                } else {
                    DB::table('events')->where('id', $event->id)->update(['status' => 'completed']);
                }
            }
        }
        
        // Update the enum column type
        // Change from enum to string first, then back to enum with new values
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 20)->default('upcoming')->change();
        });
        
        // For databases that support it, we can add a check constraint
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
             $constraintExists = DB::select("SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'events_status_check' AND table_name = 'events'");
            if (!empty($constraintExists)) {
                DB::statement("ALTER TABLE events DROP CONSTRAINT events_status_check");
            }
            DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('upcoming', 'ongoing', 'completed', 'cancelled'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrate data back to old status values
        // Map: upcoming -> confirmed, ongoing -> confirmed, completed -> completed, cancelled -> cancelled
        DB::table('events')
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->update(['status' => 'confirmed']);
        
        // Remove check constraint if exists
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE events DROP CONSTRAINT IF EXISTS events_status_check");
        }
        
        // Restore the old enum
        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'planning', 'confirmed', 'completed', 'cancelled'])->default('draft')->change();
        });
    }
};
