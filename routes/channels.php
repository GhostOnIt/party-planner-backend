<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private channel for user-specific notifications.
 * User can only listen to their own notifications.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

/**
 * Private channel for user notifications.
 */
Broadcast::channel('users.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

/**
 * Private channel for event updates.
 * Only event owner or collaborators can listen.
 */
Broadcast::channel('events.{eventId}', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    if (!$event) {
        return false;
    }

    return $event->canBeViewedBy($user);
});

/**
 * Private channel for event guests updates.
 * Only event owner or collaborators can listen.
 */
Broadcast::channel('events.{eventId}.guests', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    if (!$event) {
        return false;
    }

    return $event->canBeViewedBy($user);
});

/**
 * Private channel for event tasks updates.
 * Only event owner or collaborators can listen.
 */
Broadcast::channel('events.{eventId}.tasks', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    if (!$event) {
        return false;
    }

    return $event->canBeViewedBy($user);
});

/**
 * Private channel for event budget updates.
 * Only event owner or collaborators can listen.
 */
Broadcast::channel('events.{eventId}.budget', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    if (!$event) {
        return false;
    }

    return $event->canBeViewedBy($user);
});

/**
 * Private channel for payment status updates.
 * Only the payment owner can listen.
 */
Broadcast::channel('payments.{paymentId}', function (User $user, int $paymentId) {
    $payment = \App\Models\Payment::with('subscription')->find($paymentId);

    if (!$payment) {
        return false;
    }

    return $payment->subscription->user_id === $user->id;
});

/**
 * Presence channel for event collaboration.
 * Shows who is currently viewing/editing the event.
 */
Broadcast::channel('events.{eventId}.presence', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    if (!$event || !$event->canBeViewedBy($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
    ];
});

/**
 * Admin channel for system-wide updates.
 * Only admins can listen.
 */
Broadcast::channel('admin', function (User $user) {
    return $user->isAdmin();
});

/**
 * Admin channel for activity logs.
 */
Broadcast::channel('admin.activity', function (User $user) {
    return $user->isAdmin();
});
