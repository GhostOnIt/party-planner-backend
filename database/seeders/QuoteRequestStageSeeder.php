<?php

namespace Database\Seeders;

use App\Models\QuoteRequestStage;
use Illuminate\Database\Seeder;

class QuoteRequestStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Nouvelle', 'slug' => 'new', 'sort_order' => 0, 'is_system' => true],
            ['name' => 'Qualifiée', 'slug' => 'qualified', 'sort_order' => 1, 'is_system' => true],
            ['name' => 'Call planifié', 'slug' => 'call_scheduled', 'sort_order' => 2, 'is_system' => true],
            ['name' => 'Offre envoyée', 'slug' => 'offer_sent', 'sort_order' => 3, 'is_system' => true],
            ['name' => 'Gagnée', 'slug' => 'won', 'sort_order' => 4, 'is_system' => true],
            ['name' => 'Perdue', 'slug' => 'lost', 'sort_order' => 5, 'is_system' => true],
        ];

        foreach ($stages as $stage) {
            QuoteRequestStage::updateOrCreate(
                ['slug' => $stage['slug']],
                $stage + ['is_active' => true]
            );
        }
    }
}
