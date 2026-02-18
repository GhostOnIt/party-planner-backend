<?php

use App\Http\Controllers\Webhooks\MobileMoneyWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\TwilioWebhookController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Status Route
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
        'status' => 'operational',
        'documentation' => url('/api/documentation'),
    ]);
})->name('api.status');

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('health');

/*
|--------------------------------------------------------------------------
| Prometheus Metrics
|--------------------------------------------------------------------------
|
| L'endpoint /metrics est automatiquement enregistré par le package
| spatie/laravel-prometheus via la configuration dans config/prometheus.php
|
| Sécurité :
| - Restriction IP configurée via config/prometheus.php (allowed_ips)
| - Middleware AllowIps appliqué automatiquement
| - Pour production, ajoutez les IPs de votre serveur Prometheus dans allowed_ips
|
*/

/*
|--------------------------------------------------------------------------
| API Documentation (Swagger UI)
|--------------------------------------------------------------------------
*/

Route::get('/api/documentation', function () {
    return view('api-docs');
})->name('api.documentation');

Route::get('/api/docs/openapi.yaml', function () {
    $path = base_path('docs/openapi.yaml');

    if (!file_exists($path)) {
        abort(404, 'OpenAPI specification not found');
    }

    // Lire le contenu du fichier
    $content = file_get_contents($path);

    // Remplacer ${API_URL} par l'URL réelle de l'application
    // Utiliser l'URL de la requête actuelle pour inclure automatiquement le port
    $apiUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
    // Fallback sur config('app.url') si nécessaire
    if (empty($apiUrl) || $apiUrl === 'http://' || $apiUrl === 'https://') {
        $apiUrl = rtrim(config('app.url'), '/');
    }
    $content = str_replace('${API_URL}', $apiUrl, $content);

    return response($content, 200, [
        'Content-Type' => 'application/x-yaml',
        'Access-Control-Allow-Origin' => '*',
    ]);
})->name('api.docs.openapi');

/*
|--------------------------------------------------------------------------
| Webhooks (public - called by external services)
|--------------------------------------------------------------------------
*/

// Mobile Money callbacks
Route::post('/webhooks/mtn', [MobileMoneyWebhookController::class, 'handleMtn'])->name('webhooks.mtn');
Route::post('/webhooks/airtel', [MobileMoneyWebhookController::class, 'handleAirtel'])->name('webhooks.airtel');

// Stripe webhooks
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

// Twilio webhooks
Route::prefix('webhooks/twilio')->name('webhooks.twilio.')->group(function () {
    Route::post('/sms/status', [TwilioWebhookController::class, 'handleSmsStatus'])->name('sms.status');
    Route::post('/sms/incoming', [TwilioWebhookController::class, 'handleIncomingSms'])->name('sms.incoming');
    Route::post('/whatsapp/status', [TwilioWebhookController::class, 'handleWhatsAppStatus'])->name('whatsapp.status');
    Route::post('/whatsapp/incoming', [TwilioWebhookController::class, 'handleIncomingWhatsApp'])->name('whatsapp.incoming');
});

// Legacy payment callbacks (kept for backwards compatibility)
Route::post('/payments/mtn/callback', [MobileMoneyWebhookController::class, 'handleMtn'])->name('payments.mtn.callback');
Route::post('/payments/airtel/callback', [MobileMoneyWebhookController::class, 'handleAirtel'])->name('payments.airtel.callback');
