<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventReadCacheService
{
    private const EVENT_STATS_TTL = 30;
    private const USER_TTL = 60;
    private const PLANS_TTL = 60;

    public function eventSummaryStats(Event $event): array
    {
        return Cache::remember($this->eventSummaryKey($event->id), self::EVENT_STATS_TTL, function () use ($event) {
            $guestStats = DB::table('guests')
                ->where('event_id', $event->id)
                ->selectRaw(
                    "COUNT(*) as total,
                    SUM(CASE WHEN rsvp_status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN rsvp_status = 'declined' THEN 1 ELSE 0 END) as declined,
                    SUM(CASE WHEN rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending"
                )
                ->first();

            $taskStats = DB::table('tasks')
                ->where('event_id', $event->id)
                ->selectRaw(
                    "COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
                )
                ->first();

            $budgetStats = DB::table('budget_items')
                ->where('event_id', $event->id)
                ->selectRaw(
                    "COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN paid = true THEN actual_cost ELSE 0 END), 0) as spent,
                    COALESCE(SUM(estimated_cost), 0) as estimated"
                )
                ->first();

            $collaboratorsCount = DB::table('collaborators')
                ->where('event_id', $event->id)
                ->count();

            return [
                'guests_count' => (int) ($guestStats->total ?? 0),
                'guests_confirmed_count' => (int) ($guestStats->accepted ?? 0),
                'guests_declined_count' => (int) ($guestStats->declined ?? 0),
                'guests_pending_count' => (int) ($guestStats->pending ?? 0),
                'tasks_count' => (int) ($taskStats->total ?? 0),
                'tasks_completed_count' => (int) ($taskStats->completed ?? 0),
                'budget_items_count' => (int) ($budgetStats->total ?? 0),
                'collaborators_count' => (int) $collaboratorsCount,
                'budget_spent' => (float) ($budgetStats->spent ?? 0),
                'budget_items_estimated' => (float) ($budgetStats->estimated ?? 0),
            ];
        });
    }

    public function rememberGuestStats(Event $event, callable $callback): array
    {
        return Cache::remember($this->guestStatsKey($event->id), self::EVENT_STATS_TTL, $callback);
    }

    public function rememberBudgetStats(Event $event, callable $callback): array
    {
        return Cache::remember($this->budgetStatsKey($event->id), self::EVENT_STATS_TTL, $callback);
    }

    public function rememberBudgetByCategory(Event $event, callable $callback): array
    {
        return Cache::remember($this->budgetByCategoryKey($event->id), self::EVENT_STATS_TTL, $callback);
    }

    public function rememberPermissions(User $user, Event $event, callable $callback): array
    {
        return Cache::remember($this->permissionsKey($event->id, $user->id), self::USER_TTL, $callback);
    }

    public function rememberQuota(User $user, callable $callback): array
    {
        return Cache::remember($this->quotaKey($user->id), self::USER_TTL, $callback);
    }

    public function rememberEntitlements(User $user, callable $callback): array
    {
        return Cache::remember($this->entitlementsKey($user->id), self::USER_TTL, $callback);
    }

    public function rememberPublicPlans(?User $user, callable $callback, string $country = 'COG'): array
    {
        $userKey = $user?->id ?? 'guest';
        $country = strtoupper($country);
        $version = Cache::get('plans:public:version', 1);

        return Cache::remember("plans:public:v{$version}:{$userKey}:{$country}", self::PLANS_TTL, $callback);
    }

    public function invalidateEvent(Event|string $event): void
    {
        $eventId = $event instanceof Event ? $event->id : $event;

        Cache::forget($this->eventSummaryKey($eventId));
        Cache::forget($this->guestStatsKey($eventId));
        Cache::forget($this->budgetStatsKey($eventId));
        Cache::forget($this->budgetByCategoryKey($eventId));
    }

    public function invalidateUser(User|string $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        Cache::forget($this->quotaKey($userId));
        Cache::forget($this->entitlementsKey($userId));
        Cache::forget("plans:public:v" . Cache::get('plans:public:version', 1) . ":{$userId}");
    }

    public function invalidatePermissions(Event|string $event, User|string|null $user = null): void
    {
        if ($user === null) {
            return;
        }

        $eventId = $event instanceof Event ? $event->id : $event;
        $userId = $user instanceof User ? $user->id : $user;

        Cache::forget($this->permissionsKey($eventId, $userId));
    }

    public function invalidatePublicPlans(?User $user = null): void
    {
        if ($user === null) {
            Cache::forever('plans:public:version', (int) Cache::get('plans:public:version', 1) + 1);
        }

        if ($user) {
            Cache::forget("plans:public:v" . Cache::get('plans:public:version', 1) . ":{$user->id}");
        }

        Cache::forget('plans:public:v' . Cache::get('plans:public:version', 1) . ':guest');
    }

    private function eventSummaryKey(string $eventId): string
    {
        return "event:{$eventId}:summary_stats";
    }

    private function guestStatsKey(string $eventId): string
    {
        return "event:{$eventId}:guest_stats";
    }

    private function budgetStatsKey(string $eventId): string
    {
        return "event:{$eventId}:budget_stats";
    }

    private function budgetByCategoryKey(string $eventId): string
    {
        return "event:{$eventId}:budget_by_category";
    }

    private function permissionsKey(string $eventId, string $userId): string
    {
        return "event:{$eventId}:user:{$userId}:permissions";
    }

    private function quotaKey(string $userId): string
    {
        return "user:{$userId}:quota";
    }

    private function entitlementsKey(string $userId): string
    {
        return "user:{$userId}:entitlements";
    }
}
