<?php

namespace App\Listeners;

use App\Events\EventCreated;
use App\Services\CustomRoleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateSystemRolesForEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private CustomRoleService $customRoleService
    ) {}

    /**
     * Handle the event.
     * System roles are global (from enum); no DB rows are created per event.
     */
    public function handle(EventCreated $event): void
    {
        // No-op: custom roles are user-scoped and managed in settings only
    }
}
