<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class DebugPlansCommand extends Command
{
    protected $signature = 'plans:debug {--user= : User ID to check} {--fix : Fix is_one_time_use on trial plans}';
    protected $description = 'Debug plan visibility and one-time-use issues';

    public function handle(): void
    {
        $this->info('=== Plans Debug ===');
        $this->newLine();

        // List all plans with their is_one_time_use status
        $this->info('📋 All Plans:');
        $plans = Plan::all();
        $this->table(
            ['ID', 'Name', 'Slug', 'is_trial', 'is_one_time_use', 'is_active', 'Price'],
            $plans->map(fn($p) => [
                $p->id,
                $p->name,
                $p->slug,
                $p->is_trial ? '✅' : '❌',
                $p->is_one_time_use ? '✅' : '❌',
                $p->is_active ? '✅' : '❌',
                $p->price . ' FCFA',
            ])
        );

        // Fix is_one_time_use if requested
        if ($this->option('fix')) {
            $this->newLine();
            $this->info('🔧 Fixing trial plans to have is_one_time_use = true...');
            $updated = Plan::where('is_trial', true)
                ->where(function ($q) {
                    $q->where('is_one_time_use', false)
                      ->orWhereNull('is_one_time_use');
                })
                ->update(['is_one_time_use' => true]);
            $this->info("Updated {$updated} plan(s).");
        }

        // Check specific user
        $userId = $this->option('user');
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User {$userId} not found.");
                return;
            }

            $this->newLine();
            $this->info("👤 User: {$user->name} (ID: {$user->id})");

            // List all subscriptions for this user
            $subscriptions = $user->subscriptions()->with('plan')->get();
            
            $this->newLine();
            $this->info("📜 User's Subscriptions ({$subscriptions->count()}):");
            
            if ($subscriptions->isEmpty()) {
                $this->warn("No subscriptions found.");
            } else {
                $this->table(
                    ['ID', 'Plan ID', 'Plan Name', 'Event ID', 'Status', 'Payment', 'Expires At'],
                    $subscriptions->map(fn($s) => [
                        $s->id,
                        $s->plan_id ?? 'NULL',
                        $s->plan?->name ?? $s->plan_type ?? 'Unknown',
                        $s->event_id ?? 'Account-level',
                        $s->status ?? '-',
                        $s->payment_status,
                        $s->expires_at?->format('Y-m-d') ?? '-',
                    ])
                );
            }

            // Check which plans should be visible
            $this->newLine();
            $this->info("👁️ Plans visibility for this user:");
            
            foreach ($plans->where('is_active', true) as $plan) {
                $hasUsed = $user->subscriptions()
                    ->where('plan_id', $plan->id)
                    ->exists();
                
                $shouldShow = !$plan->is_one_time_use || !$hasUsed;
                
                $icon = $shouldShow ? '✅' : '❌';
                $reason = '';
                
                if (!$shouldShow) {
                    $reason = " (is_one_time_use={$plan->is_one_time_use}, hasUsed={$hasUsed})";
                }
                
                $this->line("{$icon} {$plan->name} (ID: {$plan->id}){$reason}");
            }

            // Check for subscriptions without plan_id that might be trials
            $trialWithoutPlanId = $user->subscriptions()
                ->whereNull('plan_id')
                ->where(function ($q) {
                    $q->where('status', 'trial')
                      ->orWhere('plan_type', 'trial');
                })
                ->get();

            if ($trialWithoutPlanId->isNotEmpty()) {
                $this->newLine();
                $this->warn("⚠️ Found {$trialWithoutPlanId->count()} trial subscription(s) WITHOUT plan_id:");
                $this->table(
                    ['ID', 'Plan Type', 'Status', 'Event ID'],
                    $trialWithoutPlanId->map(fn($s) => [
                        $s->id,
                        $s->plan_type ?? '-',
                        $s->status ?? '-',
                        $s->event_id ?? 'Account-level',
                    ])
                );
                $this->warn("These subscriptions need to be linked to the trial plan for proper filtering!");
            }
        }

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('  - Run with --fix to set is_one_time_use=true on all trial plans');
        $this->line('  - Run with --user=ID to check a specific user');
        $this->line('  - Ensure the PlanSeeder has been run: php artisan db:seed --class=PlanSeeder');
    }
}

