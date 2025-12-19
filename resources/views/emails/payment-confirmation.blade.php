<x-mail::message>
# Paiement confirmé

Bonjour {{ $user->name }},

Nous avons bien reçu votre paiement. Merci pour votre confiance !

## Détails du paiement

- **Montant :** {{ $payment->formatted_amount }}
- **Méthode :** {{ $payment->payment_method_label }}
- **Référence :** {{ $payment->transaction_reference }}
- **Date :** {{ $payment->updated_at->format('d/m/Y à H:i') }}

## Détails de l'abonnement

- **Événement :** {{ $event->title }}
- **Plan :** {{ $subscription->plan_label }}
- **Invités inclus :** {{ $subscription->guest_count }}
- **Valide jusqu'au :** {{ $subscription->expires_at?->format('d/m/Y') ?? 'Illimité' }}

<x-mail::button :url="route('events.show', $event->id)">
Accéder à votre événement
</x-mail::button>

Vous pouvez consulter votre historique de paiements dans votre espace personnel.

Cordialement,<br>
L'équipe {{ config('app.name') }}

<x-mail::subcopy>
En cas de question, n'hésitez pas à nous contacter. Référence de transaction : {{ $payment->transaction_reference }}
</x-mail::subcopy>
</x-mail::message>
