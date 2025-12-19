<x-mail::message>
# Vous êtes invité(e) !

Bonjour **{{ $guest->name }}**,

Vous êtes cordialement invité(e) à l'événement suivant :

## {{ $event->title }}

@if($event->date)
**Date :** {{ $event->date->format('d/m/Y') }}
@if($event->time)
 à {{ $event->time->format('H:i') }}
@endif
@endif

@if($event->location)
**Lieu :** {{ $event->location }}
@endif

@if($customMessage)
---
*Message de l'organisateur :*

{{ $customMessage }}

---
@endif

Pour confirmer votre présence, veuillez cliquer sur le bouton ci-dessous :

<x-mail::button :url="$invitationUrl">
Répondre à l'invitation
</x-mail::button>

Si vous avez des questions, n'hésitez pas à contacter l'organisateur.

Merci,<br>
{{ config('app.name') }}

<x-mail::subcopy>
Si vous ne parvenez pas à cliquer sur le bouton, copiez et collez l'URL suivante dans votre navigateur : [{{ $invitationUrl }}]({{ $invitationUrl }})
</x-mail::subcopy>
</x-mail::message>
