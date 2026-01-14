<?php

$logFile = 'storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist\n";
    exit(1);
}

$lines = file($logFile);

// Look for SendCollaborationInvitationJob errors
$jobErrors = [];
foreach ($lines as $line) {
    if (strpos($line, 'SendCollaborationInvitationJob') !== false) {
        $jobErrors[] = $line;
    }
}

echo "SendCollaborationInvitationJob errors:\n";
echo str_repeat("=", 50) . "\n";

if (empty($jobErrors)) {
    echo "No SendCollaborationInvitationJob errors found in logs.\n";
} else {
    foreach (array_slice($jobErrors, -10) as $error) { // Show last 10 job errors
        echo $error;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";

// Also show the very last lines in case of other errors
$lastLines = array_slice($lines, -20);
echo "Last 20 lines of Laravel log:\n";
echo str_repeat("=", 50) . "\n";

foreach ($lastLines as $line) {
    echo $line;
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Log check completed.\n";
