<x-mail::message>
# {{ $notification->title }}

Bonjour {{ $user->name }},

{{ $notification->message }}

@if($event)
## Détails de l'événement

- **Titre :** {{ $event->title }}
@if($event->date)
- **Date :** {{ $event->date->format('d/m/Y') }}
@endif
@if($event->location)
- **Lieu :** {{ $event->location }}
@endif
@endif

<x-mail::button :url="$actionUrl">
Voir les détails
</x-mail::button>

Cordialement,<br>
L'équipe {{ config('app.name') }}

<x-mail::subcopy>
Vous recevez cet email car vous avez activé les notifications par email. Vous pouvez modifier vos préférences dans les [paramètres de notification]({{ route('notifications.settings') }}).
</x-mail::subcopy>
</x-mail::message>
