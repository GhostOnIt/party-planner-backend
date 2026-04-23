<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending {--minutes=10 : Age minimal (minutes) avant expiration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire les paiements pending trop anciens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $minutes = $minutes > 0 ? $minutes : 10;
        $threshold = Carbon::now()->subMinutes($minutes);

        $payments = Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $threshold)
            ->with('subscription')
            ->get();

        $expiredCount = 0;
        foreach ($payments as $payment) {
            $payment->markAsFailedByTimeout();
            $expiredCount++;
        }

        $this->info(sprintf(
            'Paiements expirés: %d (seuil: %d minutes).',
            $expiredCount,
            $minutes
        ));

        return self::SUCCESS;
    }
}
