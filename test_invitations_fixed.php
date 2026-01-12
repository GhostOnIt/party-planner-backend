<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\CollaboratorController;
use App\Services\CollaboratorService;
use Illuminate\Http\Request;

echo "🧪 Testing fixed pendingInvitations API...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

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
        echo "🔍 Sample invitation validation:\n";
        echo "   - created_at: " . ($first['created_at'] ?? 'null') . "\n";
        echo "   - event exists: " . (isset($first['event']) ? 'yes' : 'no') . "\n";
        echo "   - inviter exists: " . (isset($first['inviter']) ? 'yes' : 'no') . "\n";
        echo "   - inviter name: " . ($first['inviter']['name'] ?? 'null') . "\n";
        echo "   - event title: " . ($first['event']['title'] ?? 'null') . "\n";
        echo "   - event date: " . ($first['event']['date'] ?? 'null') . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "📍 File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n🎯 Test completed!\n";

