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

---
## Check-in (jour de l'événement)

@if(!empty($checkInQrEmbedded))
Le portier pourra scanner ce QR code pour effectuer le check-in.

<div style="text-align:center; margin: 14px 0;">
  <img
    src="{{ $message->embedData($checkInQrEmbedded['binary'], $checkInQrEmbedded['filename'], $checkInQrEmbedded['mime']) }}"
    alt="QR code de check-in"
    width="180"
    height="180"
    style="display:block; margin:0 auto;"
  />
</div>

Si le QR ne se scanne pas, vous pouvez aussi ouvrir ce lien :
@else
Pour le check-in le jour de l'événement, ouvrez ce lien (le QR n’a pas pu être généré automatiquement) :
@endif
[{{ $checkInUrl }}]({{ $checkInUrl }})
</x-mail::message>
