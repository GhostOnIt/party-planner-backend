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

<x-mail::button :url="$invitationsUrl" color="primary">
Voir mes invitations
</x-mail::button>

Si vous ne connaissez pas {{ $inviter->name }} ou n'avez pas sollicité cette invitation, vous pouvez ignorer cet email.

Cordialement,<br>
L'équipe {{ config('app.name') }}

<x-mail::subcopy>
Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :

**Voir mes invitations :** {{ $invitationsUrl }}
</x-mail::subcopy>
</x-mail::message>
