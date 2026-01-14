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
     */
    public function handle(EventCreated $event): void
    {
        $this->customRoleService->createSystemRolesForEvent($event->event);
    }
}
