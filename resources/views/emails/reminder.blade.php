<x-mail::message>
# Rappel : Votre réponse est attendue

Bonjour **{{ $guest->name }}**,

Nous n'avons pas encore reçu votre réponse pour l'événement suivant :

## {{ $event->title }}

@if($event->date)
**Date :** {{ $event->date->format('d/m/Y') }}
@if($event->time)
 à {{ $event->time->format('H:i') }}
@endif

@php
    $daysUntil = now()->diffInDays($event->date, false);
@endphp

@if($daysUntil > 0)
*L'événement a lieu dans {{ $daysUntil }} jour(s).*
@elseif($daysUntil === 0)
*L'événement a lieu aujourd'hui !*
@endif
@endif

@if($event->location)
**Lieu :** {{ $event->location }}
@endif

---

Votre réponse est importante pour l'organisation de cet événement. Merci de confirmer votre présence dès que possible.

<x-mail::button :url="$invitationUrl">
Répondre maintenant
</x-mail::button>

Si vous avez des questions, n'hésitez pas à contacter l'organisateur.

Merci,<br>
{{ config('app.name') }}

<x-mail::subcopy>
Si vous ne parvenez pas à cliquer sur le bouton, copiez et collez l'URL suivante dans votre navigateur : [{{ $invitationUrl }}]({{ $invitationUrl }})
</x-mail::subcopy>
</x-mail::message>
