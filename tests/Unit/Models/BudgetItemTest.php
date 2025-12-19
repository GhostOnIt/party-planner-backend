<?php

namespace Tests\Unit\Models;

use App\Models\BudgetItem;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_item_belongs_to_event(): void
    {
        $event = Event::factory()->create();
        $item = BudgetItem::factory()->create(['event_id' => $event->id]);

        $this->assertInstanceOf(Event::class, $item->event);
        $this->assertEquals($event->id, $item->event->id);
    }

    public function test_budget_item_is_over_budget(): void
    {
        $item = BudgetItem::factory()->overBudget()->create();

        $this->assertTrue($item->isOverBudget());
    }

    public function test_budget_item_is_not_over_budget(): void
    {
        $item = BudgetItem::factory()->create([
            'estimated_cost' => 100000,
            'actual_cost' => 90000,
        ]);

        $this->assertFalse($item->isOverBudget());
    }

    public function test_budget_item_difference_negative_when_under_budget(): void
    {
        $item = BudgetItem::factory()->create([
            'estimated_cost' => 100000,
            'actual_cost' => 80000,
        ]);

        // difference = actual - estimated = 80000 - 100000 = -20000
        $this->assertEquals(-20000, $item->difference);
    }

    public function test_budget_item_difference_positive_when_over_budget(): void
    {
        $item = BudgetItem::factory()->create([
            'estimated_cost' => 100000,
            'actual_cost' => 120000,
        ]);

        // difference = actual - estimated = 120000 - 100000 = 20000
        $this->assertEquals(20000, $item->difference);
    }

    public function test_budget_item_paid_status(): void
    {
        $paidItem = BudgetItem::factory()->paid()->create();
        $unpaidItem = BudgetItem::factory()->unpaid()->create();

        $this->assertTrue($paidItem->paid);
        $this->assertFalse($unpaidItem->paid);
    }
}
