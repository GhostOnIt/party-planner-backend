<?php

namespace App\Console\Commands;

use App\Models\CustomOffer;
use App\Services\QuoteRequestService;
use Illuminate\Console\Command;

class ExpireCustomOffers extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Expire les offres personnalisées dont la date de validité est dépassée';

    public function handle(QuoteRequestService $quoteRequestService): int
    {
        $expired = CustomOffer::query()
            ->where('status', 'sent')
            ->where('expires_at', '<', now())
            ->with('quoteRequest')
            ->get();

        foreach ($expired as $offer) {
            $offer->update(['status' => 'expired']);

            if ($offer->quoteRequest) {
                $quoteRequestService->logActivity(
                    $offer->quoteRequest,
                    'offer_expired',
                    "Offre \"{$offer->title}\" expirée automatiquement.",
                    ['offer_id' => $offer->id]
                );
            }
        }

        $count = $expired->count();
        $this->info("{$count} offre(s) expirée(s).");

        return self::SUCCESS;
    }
}
