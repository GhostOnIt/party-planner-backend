<?php

namespace Database\Seeders;

use App\Models\QuoteRequestStage;
use Illuminate\Database\Seeder;

class QuoteRequestStageSeeder extends Seeder
{
    public function run(): void
    {
        $workflowStages = [
            ['name' => 'En attente de traitement', 'slug' => 'pending_processing', 'sort_order' => 0, 'is_system' => true],
            ['name' => 'Assignée à un admin', 'slug' => 'assigned_admin', 'sort_order' => 1, 'is_system' => true],
            ['name' => 'Call programmé', 'slug' => 'call_scheduled', 'sort_order' => 2, 'is_system' => true],
            ['name' => 'Offre personnalisée créée', 'slug' => 'custom_offer_created', 'sort_order' => 3, 'is_system' => true],
            ['name' => 'Clôturée', 'slug' => 'closed', 'sort_order' => 4, 'is_system' => true],
        ];

        // Disable old/legacy system stages to keep a single business workflow.
        QuoteRequestStage::query()->where('is_system', true)->update(['is_active' => false]);

        foreach ($workflowStages as $stage) {
            QuoteRequestStage::updateOrCreate(
                ['slug' => $stage['slug']],
                $stage + ['is_active' => true]
            );
        }
    }
}
