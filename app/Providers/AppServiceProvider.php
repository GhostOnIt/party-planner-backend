<?php

namespace App\Providers;

use App\Events\EventCreated;
use App\Listeners\CreateSystemRolesForEvent;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\AdminPolicy;
use App\Policies\CollaboratorPolicy;
use App\Policies\EventPolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Collaborator::class, CollaboratorPolicy::class);

        // Register admin gates
        Gate::define('admin.access', [AdminPolicy::class, 'access']);
        Gate::define('admin.viewDashboard', [AdminPolicy::class, 'viewDashboard']);
        Gate::define('admin.manageUsers', [AdminPolicy::class, 'manageUsers']);
        Gate::define('admin.viewAllEvents', [AdminPolicy::class, 'viewAllEvents']);
        Gate::define('admin.viewAllPayments', [AdminPolicy::class, 'viewAllPayments']);
        Gate::define('admin.viewAllSubscriptions', [AdminPolicy::class, 'viewAllSubscriptions']);
        Gate::define('admin.viewActivityLogs', [AdminPolicy::class, 'viewActivityLogs']);
        Gate::define('admin.manageTemplates', [AdminPolicy::class, 'manageTemplates']);

        // Register event listeners
        EventFacade::listen(
            EventCreated::class,
            CreateSystemRolesForEvent::class
        );

        // Register observers
        User::observe(UserObserver::class);
    }
}
