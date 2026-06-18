<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlanService::class);
    }

    public function test_public_catalog_only_contains_commercial_plans(): void
    {
        foreach ([
            ['name' => 'Gratuit', 'slug' => 'gratuit', 'price' => 0, 'is_trial' => false],
            ['name' => 'Essai Gratuit', 'slug' => 'essai-gratuit', 'price' => 0, 'is_trial' => true],
            ['name' => 'Starter', 'slug' => 'starter', 'price' => 3500, 'is_trial' => false],
            ['name' => 'PRO', 'slug' => 'pro', 'price' => 9900, 'is_trial' => false],
            ['name' => 'Business', 'slug' => 'business', 'price' => 0, 'is_trial' => false],
        ] as $index => $plan) {
            Plan::create([
                ...$plan,
                'duration_days' => 30,
                'is_active' => true,
                'sort_order' => $index + 1,
                'limits' => [],
                'features' => [],
            ]);
        }

        $slugs = $this->service->getPublicCatalog()->pluck('slug')->all();

        $this->assertSame(['starter', 'pro', 'business'], $slugs);
    }

    public function test_trial_is_available_for_new_user_inside_activation_window(): void
    {
        $trial = Plan::create([
            'name' => 'Essai Gratuit',
            'slug' => 'essai-gratuit',
            'price' => 0,
            'duration_days' => 14,
            'is_trial' => true,
            'is_one_time_use' => true,
            'is_active' => true,
            'sort_order' => 1,
            'limits' => [],
            'features' => [],
        ]);
        $user = User::factory()->create(['created_at' => now()->subDays(9)]);

        $availableTrial = $this->service->getAvailableTrialPlan($user);

        $this->assertNotNull($availableTrial);
        $this->assertSame($trial->id, $availableTrial->id);
    }

    public function test_trial_is_not_available_after_activation_window(): void
    {
        Plan::create([
            'name' => 'Essai Gratuit',
            'slug' => 'essai-gratuit',
            'price' => 0,
            'duration_days' => 14,
            'is_trial' => true,
            'is_one_time_use' => true,
            'is_active' => true,
            'sort_order' => 1,
            'limits' => [],
            'features' => [],
        ]);
        $user = User::factory()->create(['created_at' => now()->subDays(11)]);

        $this->assertNull($this->service->getAvailableTrialPlan($user));
    }
}
