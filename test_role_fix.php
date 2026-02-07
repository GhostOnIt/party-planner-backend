<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Create test data
    $user = App\Models\User::factory()->create();
    $event = App\Models\Event::factory()->create(['user_id' => $user->id]);

    // Test creating collaborator with guest_manager role
    $collaborator = App\Models\Collaborator::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'role' => 'guest_manager'
    ]);

    echo "✅ Successfully created collaborator with guest_manager role!\n";
    echo "Role: " . $collaborator->role . "\n";

    // Test other new roles
    $testRoles = ['coordinator', 'planner', 'accountant', 'supervisor', 'reporter'];

    foreach ($testRoles as $role) {
        $testCollaborator = App\Models\Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => App\Models\User::factory()->create()->id,
            'role' => $role
        ]);
        echo "✅ Successfully created collaborator with {$role} role!\n";
    }

    echo "\n🎉 All role constraint tests passed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
