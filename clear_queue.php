<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🧹 Clearing queue and failed jobs for SendCollaborationInvitationJob\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Clear pending jobs
$pendingCount = DB::table('jobs')
    ->where('queue', 'default')
    ->where('payload', 'like', '%SendCollaborationInvitationJob%')
    ->delete();

echo "✅ Cleared {$pendingCount} pending jobs\n";

// Clear failed jobs
$failedCount = DB::table('failed_jobs')
    ->where('queue', 'default')
    ->where('payload', 'like', '%SendCollaborationInvitationJob%')
    ->delete();

echo "✅ Cleared {$failedCount} failed jobs\n";

echo "\n🎯 Queue cleared! New invitation jobs should work correctly.\n";
echo "💡 Test by creating a new collaborator invitation.\n";
