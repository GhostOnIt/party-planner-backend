<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

class UpdateEventsMaxGuestsAllowed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-max-guests-allowed 
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update max_guests_allowed, max_collaborators_allowed, and max_photos_allowed for existing events based on their creation context';

    /**
     * Execute the console command.
     */
    public function handle(EntitlementService $entitlementService): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Recherche des événements sans limites définies...');

        // Get events without max_guests_allowed, max_collaborators_allowed, or max_photos_allowed
        $events = Event::where(function ($query) {
                $query->whereNull('max_guests_allowed')
                      ->orWhereNull('max_collaborators_allowed')
                      ->orWhereNull('max_photos_allowed');
            })
            ->with('user')
            ->get();

        $totalEvents = $events->count();
        $this->info("📊 {$totalEvents} événement(s) trouvé(s)");

        if ($totalEvents === 0) {
            $this->info('✅ Tous les événements ont déjà max_guests_allowed défini.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY-RUN activé - aucune modification ne sera effectuée');
        }

        $bar = $this->output->createProgressBar($totalEvents);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($events as $event) {
            try {
                $user = $event->user;
                
                // Get current subscription limits (or free tier)
                $updateData = [];
                
                if ($event->max_guests_allowed === null) {
                    $updateData['max_guests_allowed'] = $entitlementService->limit($user, 'guests.max_per_event');
                }
                if ($event->max_collaborators_allowed === null) {
                    $updateData['max_collaborators_allowed'] = $entitlementService->limit($user, 'collaborators.max_per_event');
                }
                if ($event->max_photos_allowed === null) {
                    $updateData['max_photos_allowed'] = $entitlementService->limit($user, 'photos.max_per_event');
                }
                if ($event->features_enabled === null) {
                    $entitlements = $entitlementService->getEffectiveEntitlements($user);
                    $updateData['features_enabled'] = array_filter($entitlements['features'] ?? [], fn($value) => $value === true);
                }

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  [DRY-RUN] Événement #{$event->id} ({$event->title}):");
                    foreach ($updateData as $key => $value) {
                        $this->line("    - {$key}: {$value}");
                    }
                    $updated++;
                } else {
                    $event->update($updateData);
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("  ❌ Erreur pour événement #{$event->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📈 Résumé:');
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['✅ Événements mis à jour', $updated],
                ['⏭️  Ignorés', $skipped],
                ['❌ Erreurs', $errors],
                ['📊 Total', $totalEvents],
            ]
        );

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY-RUN: Aucune modification réelle effectuée.');
            $this->info('💡 Exécutez sans --dry-run pour appliquer les changements.');
        } else {
            $this->info('✅ Commande terminée avec succès!');
        }

        return Command::SUCCESS;
    }
}

