<?php

use App\Http\Controllers\Api\AdminFaqController;
use App\Http\Controllers\Api\AdminPlanController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CollaboratorController;
use App\Http\Controllers\Api\CustomRoleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\EventTemplateController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store']);
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
        Route::post('/reset-password', [NewPasswordController::class, 'store']);
    });

    // OTP routes (public)
    Route::prefix('otp')->group(function () {
        Route::post('/send', [OtpController::class, 'send'])->middleware('throttle:5,1');
        Route::post('/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('/resend', [OtpController::class, 'resend'])->middleware('throttle:3,1');
        Route::post('/reset-password', [OtpController::class, 'resetPassword']);
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1');
        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('api.verify-email');
        Route::put('/password', [PasswordController::class, 'update']);
    });
});

/*
|--------------------------------------------------------------------------
| API Routes - Public
|--------------------------------------------------------------------------
*/

// Public invitation endpoints
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/respond', [InvitationController::class, 'respond']);

// Public event details (limited)
Route::get('/events/{event}/public', [EventController::class, 'publicShow']);

// Public photo upload routes (no auth required, token validated)
Route::prefix('events/{event}/photos/public/{token}')->group(function () {
    Route::get('/', [PhotoController::class, 'publicIndex']);
    Route::post('/', [PhotoController::class, 'publicStore']);
    Route::post('/download-multiple', [PhotoController::class, 'publicDownloadMultiple']);
});

/*
|--------------------------------------------------------------------------
| API Routes - Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Current authenticated user
    Route::get('/user', function () {
        return request()->user();
    });

    // User profile management
    Route::get('/user/profile', [ProfileController::class, 'show']);
    Route::post('/user/profile', [ProfileController::class, 'update']);
    Route::delete('/user/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::delete('/user', [ProfileController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    // Specific routes must be defined BEFORE the resource route to avoid conflicts
    Route::get('events/upcoming', [DashboardController::class, 'upcoming']);
    Route::get('events/{event}/permissions', [EventController::class, 'getPermissions']);
    Route::apiResource('events', EventController::class);

    /*
    |--------------------------------------------------------------------------
    | Guests (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::get('events/{event}/guests/statistics', [GuestController::class, 'statistics']);
    Route::post('events/{event}/guests/import', [GuestController::class, 'import']);
    Route::post('events/{event}/guests/preview-import', [GuestController::class, 'previewImport']);
    Route::get('guests/import-template', [GuestController::class, 'downloadTemplate']);
    Route::get('events/{event}/guests/{guest}/invitation-details', [GuestController::class, 'getInvitationDetails']);
    Route::post('events/{event}/guests/{guest}/send-invitation', [GuestController::class, 'sendInvitation']);
    Route::post('events/{event}/guests/{guest}/send-reminder', [GuestController::class, 'sendReminder']);
    Route::post('events/{event}/guests/{guest}/check-in', [GuestController::class, 'checkIn']);
    Route::post('events/{event}/guests/{guest}/undo-check-in', [GuestController::class, 'undoCheckIn']);
    Route::apiResource('events.guests', GuestController::class);

    /*
    |--------------------------------------------------------------------------
    | Tasks (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('events.tasks', TaskController::class);

    /*
    |--------------------------------------------------------------------------
    | Budget (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/budget')->group(function () {
        Route::get('/', [BudgetController::class, 'index']);
        Route::get('/statistics', [BudgetController::class, 'statistics']);
        Route::post('/items', [BudgetController::class, 'store']);
        Route::get('/items/{item}', [BudgetController::class, 'show']);
        Route::put('/items/{item}', [BudgetController::class, 'update']);
        Route::delete('/items/{item}', [BudgetController::class, 'destroy']);
        Route::post('/items/{item}/mark-paid', [BudgetController::class, 'markPaid']);
        Route::post('/items/{item}/mark-unpaid', [BudgetController::class, 'markUnpaid']);
        Route::post('/bulk-update', [BudgetController::class, 'bulkUpdate']);
    });

    /*
    |--------------------------------------------------------------------------
    | Photos (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/photos')->group(function () {
        // Routes statiques d'abord
        Route::get('/', [PhotoController::class, 'index']);
        Route::post('/', [PhotoController::class, 'store']);
        Route::get('/statistics', [PhotoController::class, 'statistics']);
        Route::post('/bulk-delete', [PhotoController::class, 'bulkDelete']);
        Route::post('/bulk-download', [PhotoController::class, 'bulkDownload']);
        Route::post('/bulk-update-type', [PhotoController::class, 'bulkUpdateType']);

        // Routes avec paramètre {photo} ensuite
        Route::get('/{photo}', [PhotoController::class, 'show']);
        Route::get('/{photo}/download', [PhotoController::class, 'download']);
        Route::put('/{photo}', [PhotoController::class, 'update']);
        Route::delete('/{photo}', [PhotoController::class, 'destroy']);
        Route::post('/{photo}/toggle-featured', [PhotoController::class, 'toggleFeatured']);
        Route::post('/{photo}/set-featured', [PhotoController::class, 'setFeatured']);
    });

    /*
    |--------------------------------------------------------------------------
    | Collaborators (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/collaborators')->group(function () {
        Route::get('/', [CollaboratorController::class, 'index']);
        Route::get('/statistics', [CollaboratorController::class, 'statistics']);
        Route::post('/', [CollaboratorController::class, 'store']);
        Route::put('/{user}', [CollaboratorController::class, 'update']);
        Route::delete('/{user}', [CollaboratorController::class, 'destroy']);
        Route::post('/accept', [CollaboratorController::class, 'accept']);
        Route::post('/decline', [CollaboratorController::class, 'decline']);
        Route::post('/leave', [CollaboratorController::class, 'leave']);
        Route::post('/{user}/resend', [CollaboratorController::class, 'resendInvitation']);
    });

    /*
    |--------------------------------------------------------------------------
    | Custom Roles (nested under events)
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/roles')->group(function () {
        Route::get('/', [CustomRoleController::class, 'index']);
        Route::post('/', [CustomRoleController::class, 'store']);
        Route::put('/{role}', [CustomRoleController::class, 'update']);
        Route::delete('/{role}', [CustomRoleController::class, 'destroy']);
    });

// Permissions endpoint (not nested under events)
Route::get('/permissions', [CustomRoleController::class, 'permissions']);

// Available roles endpoint
Route::get('/roles/available', [CustomRoleController::class, 'availableRoles']);

    /*
    |--------------------------------------------------------------------------
    | User Invitations (invitations à collaborer reçues)
    |--------------------------------------------------------------------------
    */
    Route::get('/user/invitations', [CollaboratorController::class, 'pendingInvitations']);
    Route::post('/user/invitations/{id}/accept', [CollaboratorController::class, 'acceptInvitationById']);
    Route::post('/user/invitations/{id}/reject', [CollaboratorController::class, 'rejectInvitationById']);

    /*
    |--------------------------------------------------------------------------
    | User Collaborations (événements où je collabore)
    |--------------------------------------------------------------------------
    */
    Route::get('/user/collaborations', [CollaboratorController::class, 'myCollaborations']);
    Route::delete('/user/collaborations/{eventId}', [CollaboratorController::class, 'leaveByEventId']);

    // Legacy routes (backwards compatibility)
    Route::get('/collaborations', [CollaboratorController::class, 'myCollaborations']);
    Route::get('/collaborations/pending', [CollaboratorController::class, 'pendingInvitations']);

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/recent', [NotificationController::class, 'recent']);
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        Route::post('/bulk-delete', [NotificationController::class, 'bulkDelete']);
        Route::delete('/clear-read', [NotificationController::class, 'clearRead']);
        Route::get('/settings', [NotificationController::class, 'settings']);
        Route::put('/settings', [NotificationController::class, 'updateSettings']);
    });

    /*
    |--------------------------------------------------------------------------
    | Device Tokens (for push notifications)
    |--------------------------------------------------------------------------
    */
    Route::post('/user/device-tokens', [NotificationController::class, 'registerDeviceToken']);

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::post('/upgrade', [SubscriptionController::class, 'upgrade']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/renew', [SubscriptionController::class, 'renew']);
        Route::get('/calculate-price', [SubscriptionController::class, 'calculatePrice']);
        Route::get('/check-limits', [SubscriptionController::class, 'checkLimits']);
    });

    // Account-level subscription endpoints
    Route::get('/user/subscription', [SubscriptionController::class, 'current']);
    Route::get('/user/quota', [SubscriptionController::class, 'quota']);
    Route::get('/user/entitlements', [SubscriptionController::class, 'entitlements']);
    Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);

    // Event-level entitlements (for collaborators)
    Route::get('/events/{event}/entitlements', [SubscriptionController::class, 'eventEntitlements']);

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::post('/mtn/initiate', [PaymentController::class, 'initiateMtn']);
        Route::post('/airtel/initiate', [PaymentController::class, 'initiateAirtel']);
        Route::get('/{payment}/status', [PaymentController::class, 'status']);
        Route::get('/{payment}/poll', [PaymentController::class, 'poll']);
        Route::post('/{payment}/retry', [PaymentController::class, 'retry']);
    });

    /*
    |--------------------------------------------------------------------------
    | Dashboard & Statistics
    |--------------------------------------------------------------------------
    */
    Route::get('/events/{event}/dashboard', [DashboardController::class, 'eventDashboardData']);
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData']);
    Route::get('/dashboard/user-stats', [DashboardController::class, 'userStats']);
    Route::get('/dashboard/urgent-tasks', [DashboardController::class, 'urgentTasks']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/confirmations', [DashboardController::class, 'confirmations']);
    Route::get('/dashboard/events-by-type', [DashboardController::class, 'eventsByType']);
    Route::get('/activities/recent', [DashboardController::class, 'recentActivity']);
    Route::get('/search', [DashboardController::class, 'search']);

    /*
    |--------------------------------------------------------------------------
    | Event Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('templates')->group(function () {
        Route::get('/', [EventTemplateController::class, 'index']);
        Route::get('/{template}', [EventTemplateController::class, 'show']);
        Route::get('/type/{type}', [EventTemplateController::class, 'byType']);
        Route::get('/{template}/preview', [EventTemplateController::class, 'preview']);
        Route::get('/themes/{type}', [EventTemplateController::class, 'themes']);
    });
    Route::post('/events/{event}/templates/{template}/apply', [EventTemplateController::class, 'apply']);

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    */
    Route::prefix('events/{event}/exports')->group(function () {
        Route::get('/guests/csv', [ExportController::class, 'exportGuestsCsv']);
        Route::get('/guests/pdf', [ExportController::class, 'exportGuestsPdf']);
        Route::get('/guests/xlsx', [ExportController::class, 'exportGuestsXlsx']);
        Route::get('/budget/csv', [ExportController::class, 'exportBudgetCsv']);
        Route::get('/budget/pdf', [ExportController::class, 'exportBudgetPdf']);
        Route::get('/budget/xlsx', [ExportController::class, 'exportBudgetXlsx']);
        Route::get('/tasks/csv', [ExportController::class, 'exportTasksCsv']);
        Route::get('/tasks/xlsx', [ExportController::class, 'exportTasksXlsx']);
        Route::get('/report/pdf', [ExportController::class, 'exportReport']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (require admin role)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard & Statistics
        Route::get('/stats', [DashboardController::class, 'adminStats']);
        Route::get('/dashboard/stats', [DashboardController::class, 'adminDashboardStats']);
        Route::get('/chart-data', [DashboardController::class, 'adminChartData']);
        Route::get('/subscriptions/distribution', [DashboardController::class, 'adminPlanDistribution']);

        // Users Management
        Route::get('/users', [DashboardController::class, 'adminUsers']);
        Route::get('/users/{user}', [DashboardController::class, 'adminUserShow']);
        Route::put('/users/{user}', [DashboardController::class, 'adminUserUpdateRole']);
        Route::put('/users/{user}/role', [DashboardController::class, 'adminUserUpdateRole']);
        Route::post('/users/{user}/toggle-active', [DashboardController::class, 'adminUserToggleActive']);
        Route::post('/users/{user}/send-password-reset', [DashboardController::class, 'adminUserSendPasswordReset']);

        // Events Management
        Route::get('/events', [DashboardController::class, 'adminEvents']);

        // Payments Management
        Route::get('/payments', [DashboardController::class, 'adminPayments']);
        Route::post('/payments/{payment}/refund', [DashboardController::class, 'adminPaymentRefund']);

        // Subscriptions Management
        Route::get('/subscriptions', [DashboardController::class, 'adminSubscriptions']);
        Route::post('/subscriptions/{subscription}/extend', [DashboardController::class, 'adminSubscriptionExtend']);
        Route::post('/subscriptions/{subscription}/change-plan', [DashboardController::class, 'adminSubscriptionChangePlan']);
        Route::post('/subscriptions/{subscription}/cancel', [DashboardController::class, 'adminSubscriptionCancel']);

        // Plans Management (Dynamic Subscription Plans)
        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::get('/plans/{plan}', [AdminPlanController::class, 'show']);
        Route::put('/plans/{plan}', [AdminPlanController::class, 'update']);
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);
        Route::post('/plans/{plan}/toggle-active', [AdminPlanController::class, 'toggleActive']);

        // Activity Logs
        Route::get('/activity', [DashboardController::class, 'adminRecentActivity']);
        Route::get('/activity-logs', [DashboardController::class, 'adminActivityLogs']);
        Route::get('/activity-logs/stats', [DashboardController::class, 'adminActivityStats']);

        // Templates Management
        Route::get('/templates', [EventTemplateController::class, 'adminIndex']);
        Route::post('/templates', [EventTemplateController::class, 'store']);
        Route::put('/templates/{template}', [EventTemplateController::class, 'update']);
        Route::delete('/templates/{template}', [EventTemplateController::class, 'destroy']);
        Route::post('/templates/{template}/toggle-active', [EventTemplateController::class, 'toggleActive']);

        // FAQs Management
        Route::get('/faqs', [AdminFaqController::class, 'index']);
        Route::post('/faqs', [AdminFaqController::class, 'store']);
        Route::put('/faqs/{faq}', [AdminFaqController::class, 'update']);
        Route::delete('/faqs/{faq}', [AdminFaqController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Public Plans (for pricing page)
    |--------------------------------------------------------------------------
    */
    Route::get('/plans', [AdminPlanController::class, 'publicIndex']);
    Route::get('/plans/trial/available', [AdminPlanController::class, 'getAvailableTrial']);

    /*
    |--------------------------------------------------------------------------
    | Public FAQs
    |--------------------------------------------------------------------------
    */
    Route::get('/faqs', [FaqController::class, 'index']);
});
