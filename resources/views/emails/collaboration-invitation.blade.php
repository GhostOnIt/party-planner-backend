<x-mail::message>
# Invitation à collaborer

Bonjour {{ $invitee->name }},

**{{ $inviter->name }}** vous invite à collaborer sur l'événement **"{{ $event->title }}"**.

## Détails de l'événement

- **Titre :** {{ $event->title }}
- **Type :** {{ ucfirst($event->type) }}
@if($event->date)
- **Date :** {{ $event->date->format('d/m/Y') }}
@endif
@if($event->location)
- **Lieu :** {{ $event->location }}
@endif

## Votre rôle

Vous êtes invité(e) en tant que **{{ $roleLabel }}**.

Vous pourrez accéder à l'événement selon les permissions associées à votre rôle.

<x-mail::button :url="$acceptUrl" color="success">
Accepter l'invitation
</x-mail::button>

<x-mail::button :url="$declineUrl" color="gray">
Décliner
</x-mail::button>

Si vous ne connaissez pas {{ $inviter->name }} ou n'avez pas sollicité cette invitation, vous pouvez ignorer cet email.

Cordialement,<br>
L'équipe {{ config('app.name') }}

<x-mail::subcopy>
Si les boutons ne fonctionnent pas, copiez et collez ces liens dans votre navigateur :

**Accepter :** {{ $acceptUrl }}

**Décliner :** {{ $declineUrl }}
</x-mail::subcopy>
</x-mail::message>
