<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Collaborator;
use Illuminate\Support\Facades\DB;

echo "🔍 Debugging SendCollaborationInvitationJob failures\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Check for failed jobs in queue
$failedJobs = DB::table('failed_jobs')
    ->where('queue', 'default')
    ->where('payload', 'like', '%SendCollaborationInvitationJob%')
    ->orderBy('failed_at', 'desc')
    ->limit(5)
    ->get();

if ($failedJobs->isEmpty()) {
    echo "✅ No failed SendCollaborationInvitationJob found in failed_jobs table\n";
} else {
    echo "❌ Found {$failedJobs->count()} failed jobs:\n";
    foreach ($failedJobs as $job) {
        echo "   - ID: {$job->id}, Failed at: {$job->failed_at}\n";
        echo "     Exception: " . substr($job->exception, 0, 100) . "...\n";
    }
}

// Check pending jobs
$pendingJobs = DB::table('jobs')
    ->where('queue', 'default')
    ->where('payload', 'like', '%SendCollaborationInvitationJob%')
    ->get();

if ($pendingJobs->isEmpty()) {
    echo "✅ No pending SendCollaborationInvitationJob in queue\n";
} else {
    echo "📋 Found {$pendingJobs->count()} pending jobs:\n";
    foreach ($pendingJobs as $job) {
        // Try to decode the payload to see the collaborator ID
        $payload = json_decode($job->payload, true);
        if ($payload && isset($payload['data']['command'])) {
            $command = unserialize($payload['data']['command']);
            if ($command && property_exists($command, 'collaborator')) {
                $collaboratorId = isset($command->collaborator->id) ? $command->collaborator->id : 'unknown';
                echo "   - Job ID: {$job->id}, Collaborator ID: {$collaboratorId}\n";

                // Check if collaborator exists
                if (is_numeric($collaboratorId)) {
                    $collaborator = Collaborator::find($collaboratorId);
                    if ($collaborator) {
                        echo "     ✅ Collaborator exists\n";
                        echo "     📧 Email: " . ($collaborator->user->email ?? 'no email') . "\n";
                        echo "     ✅ Accepted: " . ($collaborator->isAccepted() ? 'yes' : 'no') . "\n";
                    } else {
                        echo "     ❌ Collaborator no longer exists!\n";
                    }
                }
            }
        }
    }
}

// Clear failed jobs for this type
if (!$failedJobs->isEmpty()) {
    echo "\n🧹 Clearing failed SendCollaborationInvitationJob...\n";
    DB::table('failed_jobs')
        ->where('queue', 'default')
        ->where('payload', 'like', '%SendCollaborationInvitationJob%')
        ->delete();
    echo "✅ Failed jobs cleared\n";
}

echo "\n🎯 Summary:\n";
echo "- Check if collaborators exist when jobs are processed\n";
echo "- Ensure relations are loaded before dispatching jobs\n";
echo "- Consider adding more robust error handling\n";
