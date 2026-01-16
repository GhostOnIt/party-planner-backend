<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class AssignTrialToExistingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:assign-trial 
                            {--dry-run : Show what would be done without making changes}
                            {--force : Force assignment even if user already has a subscription}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign trial subscription to existing users who do not have one';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔍 Recherche des utilisateurs sans abonnement...');

        // Get users without account-level subscriptions
        $query = User::query()
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->whereNull('event_id');
            });

        if (!$force) {
            // Only get users who don't have any account-level subscription
            $users = $query->get();
        } else {
            // Get all users (will skip those with active subscriptions in the loop)
            $users = User::all();
        }

        $totalUsers = $users->count();
        $this->info("📊 {$totalUsers} utilisateur(s) trouvé(s)");

        if ($totalUsers === 0) {
            $this->info('✅ Tous les utilisateurs ont déjà un abonnement.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY-RUN activé - aucune modification ne sera effectuée');
        }

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $assigned = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Check if user already has an active subscription (if not forcing)
                if (!$force) {
                    $existingSubscription = $subscriptionService->getUserActiveSubscription($user);
                    if ($existingSubscription) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                }

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  [DRY-RUN] Attribuer l'essai à: {$user->email} (ID: {$user->id})");
                    $assigned++;
                } else {
                    $subscription = $subscriptionService->createTrialSubscription($user);
                    
                    if ($subscription) {
                        $assigned++;
                    } else {
                        $skipped++;
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("  ❌ Erreur pour {$user->email}: {$e->getMessage()}");
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
                ['✅ Essais attribués', $assigned],
                ['⏭️  Ignorés (déjà abonné)', $skipped],
                ['❌ Erreurs', $errors],
                ['📊 Total', $totalUsers],
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

