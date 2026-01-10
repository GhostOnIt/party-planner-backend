<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Event;
use App\Services\CollaboratorService;
use App\Mail\CollaborationInvitationMail;

$user = User::factory()->create([
    'email' => 'test-multi-role-' . time() . '@example.com',
    'name' => 'Test Multi Role'
]);

$event = Event::first();

if (!$event) {
    echo "❌ No event found\n";
    exit(1);
}

$service = new CollaboratorService();
$collaborator = $service->inviteByEmailWithRoles($event, $user->email, ['coordinator', 'planner', 'guest_manager']);

if (!$collaborator) {
    echo "❌ Failed to create collaborator\n";
    exit(1);
}

$collaborator->load('collaboratorRoles');

echo "✅ Collaborator created with roles: " . implode(', ', $collaborator->getRoleValues()) . "\n";
echo "✅ Effective role names: " . implode(', ', $collaborator->getEffectiveRoleNames()) . "\n";

// Test email generation
$mail = new CollaborationInvitationMail($collaborator);
$content = $mail->content();

echo "✅ Email content generated successfully\n";
echo "📧 Role label in email: " . $content->with['roleLabel'] . "\n";
