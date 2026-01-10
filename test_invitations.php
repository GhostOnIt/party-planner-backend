<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\CollaboratorController;
use App\Services\CollaboratorService;
use Illuminate\Http\Request;

echo "🧪 Testing pendingInvitations API...\n";

$user = \App\Models\User::first();
if (!$user) {
    echo "❌ No users found in database\n";
    exit(1);
}

echo "✅ Found user: {$user->email}\n";

// Create mock request with user resolver
$request = new Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Create controller
$service = app(CollaboratorService::class);
$controller = new CollaboratorController($service);

// Call the method
try {
    $response = $controller->pendingInvitations($request);
    $data = json_decode($response->getContent(), true);

    echo "✅ API call successful\n";
    echo "📊 Found " . count($data['invitations']) . " invitations\n";

    if (count($data['invitations']) > 0) {
        $first = $data['invitations'][0];
        echo "🔍 Sample invitation structure:\n";
        echo "   - id: {$first['id']}\n";
        echo "   - event_id: {$first['event_id']}\n";
        echo "   - inviter_id: {$first['inviter_id']}\n";
        echo "   - status: {$first['status']}\n";
        echo "   - roles: " . json_encode($first['roles']) . "\n";
        echo "   - has event: " . (isset($first['event']) ? 'yes' : 'no') . "\n";
        echo "   - has inviter: " . (isset($first['inviter']) ? 'yes' : 'no') . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "📍 File: {$e->getFile()}:{$e->getLine()}\n";
    echo "🔍 Trace:\n{$e->getTraceAsString()}\n";
}

echo "\n🎯 Test completed!\n";
