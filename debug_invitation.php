<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\User;
use App\Services\CollaboratorService;

$email = $argv[1] ?? 'roroboss06@gmail.com';
$eventId = $argv[2] ?? null; // Si passé en paramètre

echo "🔍 Diagnostic d'invitation pour l'email: {$email}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$user = User::where('email', $email)->first();

// Try case-insensitive search if exact match fails
if (!$user) {
    $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
    if ($user) {
        echo "⚠️  Utilisateur trouvé avec casse différente: {$user->email}\n";
    }
}

if (!$user) {
    echo "❌ PROBLÈME: Utilisateur non trouvé avec l'email: {$email}\n";
    echo "\n💡 Utilisateurs disponibles en base:\n";
    $users = User::select('email', 'name')->limit(10)->get();
    foreach ($users as $u) {
        echo "   - {$u->email} ({$u->name})\n";
    }
    echo "\n🔧 SOLUTION: L'utilisateur doit d'abord créer un compte sur la plateforme.\n";
    exit(1);
}

echo "✅ Utilisateur trouvé: ID {$user->id}, Nom: {$user->name}, Email: {$user->email}\n";

// Get the first event or the specified event
if ($eventId) {
    $event = Event::find($eventId);
} else {
    $event = Event::first();
}

if (!$event) {
    echo "❌ ERREUR: Aucun événement trouvé en base de données\n";
    exit(1);
}

echo "✅ Événement trouvé: ID {$event->id}, Titre: {$event->title}\n";

// Check if already a collaborator
$collaboratorService = new CollaboratorService();
$isCollaborator = $collaboratorService->isCollaborator($event, $user);

if ($isCollaborator) {
    echo "❌ PROBLÈME: L'utilisateur est déjà collaborateur sur cet événement\n";
    echo "🔧 SOLUTION: Impossible d'inviter quelqu'un qui est déjà collaborateur.\n";
    exit(1);
}

echo "✅ L'utilisateur n'est pas encore collaborateur\n";

// Check if it's the owner
if ($event->user_id === $user->id) {
    echo "❌ PROBLÈME: L'utilisateur est le propriétaire de l'événement\n";
    echo "🔧 SOLUTION: Le propriétaire ne peut pas s'inviter lui-même.\n";
    exit(1);
}

echo "✅ L'utilisateur n'est pas le propriétaire\n";

// Check subscription
$canAddCollaborator = $collaboratorService->canAddCollaborator($event);
if (!$canAddCollaborator) {
    echo "❌ PROBLÈME: L'événement n'a pas d'abonnement actif\n";
    $subscription = $event->subscription;
    if ($subscription) {
        echo "   Statut abonnement: {$subscription->payment_status}\n";
        echo "   Date expiration: {$subscription->expires_at}\n";
    } else {
        echo "   Aucun abonnement trouvé\n";
    }
    echo "🔧 SOLUTION: Un abonnement actif est requis pour inviter des collaborateurs.\n";
    exit(1);
}

echo "✅ L'événement a un abonnement actif\n";

echo "\n🎉 DIAGNOSTIC RÉUSSI: Tous les contrôles sont passés !\n";
echo "💡 Si l'invitation échoue encore, vérifiez les logs du serveur pour plus de détails.\n";