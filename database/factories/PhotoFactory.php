<?php

namespace Database\Factories;

use App\Enums\PhotoType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = fake()->randomElement([800, 1024, 1280, 1920]);
        $height = fake()->randomElement([600, 768, 960, 1080]);

        return [
            'event_id' => Event::factory(),
            'uploaded_by_user_id' => User::factory(),
            'type' => fake()->randomElement(PhotoType::cases())->value,
            'url' => "https://picsum.photos/{$width}/{$height}?random=" . fake()->unique()->numberBetween(1, 10000),
            'thumbnail_url' => "https://picsum.photos/300/200?random=" . fake()->unique()->numberBetween(1, 10000),
            'description' => fake()->optional(0.3)->sentence(),
            'is_featured' => fake()->boolean(10),
        ];
    }

    /**
     * Indicate that the photo is a moodboard photo.
     */
    public function moodboard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PhotoType::MOODBOARD->value,
        ]);
    }

    /**
     * Indicate that the photo is an event photo.
     */
    public function eventPhoto(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PhotoType::EVENT_PHOTO->value,
        ]);
    }

    /**
     * Indicate that the photo is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
