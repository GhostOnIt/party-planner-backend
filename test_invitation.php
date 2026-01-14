<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Event;
use App\Services\CollaboratorService;

// Create test user
$user = User::factory()->create([
    'email' => 'test-collab-' . time() . '@example.com',
    'name' => 'Test Collaborator'
]);

echo "✅ Created test user: {$user->email} (ID: {$user->id})\n";

// Get first event
$event = Event::first();
if (!$event) {
    echo "❌ No events found\n";
    exit(1);
}

echo "✅ Found event: {$event->title} (ID: {$event->id})\n";

// Test invitation
$service = new CollaboratorService();

try {
    $collaborator = $service->inviteByEmailWithRoles(
        $event,
        $user->email,
        ['coordinator', 'guest_manager']
    );

    echo "✅ Invitation successful!\n";
    echo "   Collaborator ID: {$collaborator->id}\n";
    echo "   Roles: " . implode(', ', $collaborator->getRoleValues()) . "\n";

} catch (Exception $e) {
    echo "❌ Invitation failed: {$e->getMessage()}\n";
}
