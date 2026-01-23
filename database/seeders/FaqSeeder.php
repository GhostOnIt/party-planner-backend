<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Comment fonctionne la facturation ?',
                'answer' => "Chaque plan est facturé mensuellement. Vous bénéficiez de toutes les fonctionnalités incluses pendant la durée de votre abonnement. L'essai gratuit est disponible une seule fois par compte et peut être activé depuis cette page.",
                'order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Puis-je changer de plan ?',
                'answer' => 'Oui, vous pouvez passer à un plan supérieur à tout moment. La différence de prix sera calculée au prorata de la durée restante.',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'Quels modes de paiement acceptez-vous ?',
                'answer' => 'Nous acceptons les paiements via MTN Mobile Money et Airtel Money. Le paiement est sécurisé et confirmé instantanément.',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Que se passe-t-il à la fin de mon abonnement ?',
                'answer' => "À la fin de votre abonnement, vous conservez l'accès en lecture à vos données mais ne pouvez plus ajouter d'invités ou modifier l'événement. Vous pouvez renouveler à tout moment.",
                'order' => 4,
                'is_active' => true,
            ],
            [
                'question' => 'Puis-je annuler mon abonnement à tout moment ?',
                'answer' => "Oui, vous pouvez annuler votre abonnement à tout moment depuis les paramètres de votre compte. Aucun frais d'annulation ne sera appliqué.",
                'order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faqData) {
            Faq::updateOrCreate(
                ['question' => $faqData['question']],
                $faqData
            );
        }
    }
}
