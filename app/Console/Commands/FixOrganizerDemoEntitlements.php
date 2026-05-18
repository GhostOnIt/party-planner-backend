<?php

namespace App\Console\Commands;

use Database\Seeders\OrganizerDemoSeeder;
use Illuminate\Console\Command;

class FixOrganizerDemoEntitlements extends Command
{
    protected $signature = 'demo:fix-organizer-entitlements';

    protected $description = 'Active les droits Pro (features_enabled + abonnement compte) pour les événements démo organisateur';

    public function handle(): int
    {
        $this->info('Réparation des entitlements démo organisateur…');

        (new OrganizerDemoSeeder)->repairExistingDemoData();

        $this->info('Terminé. Rechargez la page événement (Ctrl+F5) ou reconnectez-vous.');

        return Command::SUCCESS;
    }
}
