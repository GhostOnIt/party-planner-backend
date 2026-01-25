<?php

namespace Database\Seeders;

use App\Models\CommunicationSpot;
use Illuminate\Database\Seeder;

class CommunicationSpotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default login/register page advertisements
        $loginAds = [
            [
                'type' => 'banner',
                'title' => 'Organisez des événements inoubliables',
                'description' => 'Créez, planifiez et gérez vos événements avec une simplicité déconcertante. De l\'anniversaire intime au mariage grandiose.',
                'image' => 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=1920&h=1080&fit=crop',
                'is_active' => true,
                'display_locations' => ['login'],
                'priority' => 1,
                'views' => 0,
                'clicks' => 0,
            ],
            [
                'type' => 'banner',
                'title' => 'Gérez vos invités en un clic',
                'description' => 'Suivez les confirmations, gérez les restrictions alimentaires et communiquez avec vos invités en temps réel.',
                'image' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=1920&h=1080&fit=crop',
                'is_active' => true,
                'display_locations' => ['login'],
                'priority' => 2,
                'views' => 0,
                'clicks' => 0,
            ],
            [
                'type' => 'banner',
                'title' => 'Capturez chaque moment',
                'description' => 'Partagez des photos et créez des albums collaboratifs avec tous vos invités pour des souvenirs éternels.',
                'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=1920&h=1080&fit=crop',
                'is_active' => true,
                'display_locations' => ['login'],
                'priority' => 3,
                'views' => 0,
                'clicks' => 0,
            ],
        ];

        foreach ($loginAds as $ad) {
            CommunicationSpot::updateOrCreate(
                ['title' => $ad['title']],
                $ad
            );
        }

        $this->command->info('Communication spots seeded successfully!');
    }
}
