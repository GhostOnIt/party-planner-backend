<x-mail::message>
# Un événement a été créé pour vous

**{{ $admin->name }}** (administrateur) a créé un événement pour vous sur {{ config('app.name') }}.

## Détails de l'événement

- **Titre :** {{ $event->title }}
- **Type :** {{ ucfirst($event->type) }}
@if($event->date)
- **Date :** {{ \Carbon\Carbon::parse($event->date)->format('d/m/Y') }}
@endif
@if($event->location)
- **Lieu :** {{ $event->location }}
@endif

Pour accéder à cet événement, créez un compte ou connectez-vous avec l'adresse **{{ $invitation->email }}**.

<x-mail::button :url="$claimUrl" color="primary">
Accéder à l'événement
</x-mail::button>

Si vous ne connaissez pas {{ $admin->name }} ou n'avez pas sollicité cet événement, vous pouvez ignorer cet email.

Cordialement,<br>
L'équipe {{ config('app.name') }}

<x-mail::subcopy>
Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :

**Accéder à l'événement :** {{ $claimUrl }}
</x-mail::subcopy>
</x-mail::message>
