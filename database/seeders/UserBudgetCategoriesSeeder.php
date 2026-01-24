<?php

namespace Database\Seeders;

use App\Enums\BudgetCategory;
use App\Models\User;
use App\Models\UserBudgetCategory;
use Illuminate\Database\Seeder;

class UserBudgetCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultCategories = [
            [
                'slug' => BudgetCategory::LOCATION->value,
                'name' => BudgetCategory::LOCATION->label(),
                'color' => BudgetCategory::LOCATION->color(),
                'order' => 1,
            ],
            [
                'slug' => BudgetCategory::CATERING->value,
                'name' => BudgetCategory::CATERING->label(),
                'color' => BudgetCategory::CATERING->color(),
                'order' => 2,
            ],
            [
                'slug' => BudgetCategory::DECORATION->value,
                'name' => BudgetCategory::DECORATION->label(),
                'color' => BudgetCategory::DECORATION->color(),
                'order' => 3,
            ],
            [
                'slug' => BudgetCategory::ENTERTAINMENT->value,
                'name' => BudgetCategory::ENTERTAINMENT->label(),
                'color' => BudgetCategory::ENTERTAINMENT->color(),
                'order' => 4,
            ],
            [
                'slug' => BudgetCategory::PHOTOGRAPHY->value,
                'name' => BudgetCategory::PHOTOGRAPHY->label(),
                'color' => BudgetCategory::PHOTOGRAPHY->color(),
                'order' => 5,
            ],
            [
                'slug' => BudgetCategory::TRANSPORTATION->value,
                'name' => BudgetCategory::TRANSPORTATION->label(),
                'color' => BudgetCategory::TRANSPORTATION->color(),
                'order' => 6,
            ],
            [
                'slug' => BudgetCategory::OTHER->value,
                'name' => BudgetCategory::OTHER->label(),
                'color' => BudgetCategory::OTHER->color(),
                'order' => 7,
            ],
        ];

        // Create default categories for all existing users
        User::chunk(100, function ($users) use ($defaultCategories) {
            foreach ($users as $user) {
                // Check if user already has categories
                if ($user->budgetCategories()->count() === 0) {
                    foreach ($defaultCategories as $category) {
                        UserBudgetCategory::create([
                            'user_id' => $user->id,
                            'slug' => $category['slug'],
                            'name' => $category['name'],
                            'color' => $category['color'],
                            'is_default' => true,
                            'order' => $category['order'],
                        ]);
                    }
                }
            }
        });
    }
}
