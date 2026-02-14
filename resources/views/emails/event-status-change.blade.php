<x-mail::message>
# Changement de statut - {{ $event->title }}

Bonjour **{{ $guest->name }}**,

L'organisateur vous informe du changement de statut de l'événement suivant :

## {{ $event->title }}

**Nouveau statut :** {{ $status->label() }}

@if($event->date)
**Date :** {{ $event->date->format('d/m/Y') }}
@if($event->time)
 à {{ $event->time->format('H:i') }}
@endif
@endif

@if($event->location)
**Lieu :** {{ $event->location }}
@endif

---

@switch($status->value)
    @case('cancelled')
L'événement a été annulé. Nous vous remercions de votre intérêt et espérons vous revoir bientôt.
        @break
    @case('ongoing')
L'événement a commencé ! Nous espérons vous voir bientôt.
        @break
    @case('completed')
L'événement est maintenant terminé. Merci d'y avoir participé !
        @break
    @case('upcoming')
L'événement est à nouveau prévu. À bientôt !
        @break
    @default
Un changement a été effectué sur cet événement.
@endswitch

@if($invitationUrl)
<x-mail::button :url="$invitationUrl">
Voir l'invitation
</x-mail::button>
@endif

Pour toute question, contactez l'organisateur.

Merci,<br>
{{ config('app.name') }}

@if($invitationUrl)
<x-mail::subcopy>
Si vous ne parvenez pas à cliquer sur le bouton : [{{ $invitationUrl }}]({{ $invitationUrl }})
</x-mail::subcopy>
@endif
</x-mail::message>
