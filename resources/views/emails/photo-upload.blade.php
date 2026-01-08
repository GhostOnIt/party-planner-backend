<x-mail::message>
# Partagez vos photos !

Bonjour **{{ $guest->name }}**,

Merci d'avoir participé à l'événement **{{ $event->title }}** !

Nous aimerions créer une galerie souvenir collective avec toutes les photos de l'événement. Vous pouvez maintenant partager vos meilleurs moments en uploadant vos photos.

@if($event->date)
**Date de l'événement :** {{ $event->date->format('d/m/Y') }}
@if($event->time)
 à {{ $event->time->format('H:i') }}
@endif
@endif

@if($event->location)
**Lieu :** {{ $event->location }}
@endif

Pour partager vos photos, cliquez sur le bouton ci-dessous :

<x-mail::button :url="$uploadUrl">
Partager mes photos
</x-mail::button>

Vous pourrez :
- Voir toutes les photos de l'événement
- Uploader vos propres photos
- Télécharger les photos que vous souhaitez

Merci pour votre contribution !<br>
{{ config('app.name') }}

<x-mail::subcopy>
Si vous ne parvenez pas à cliquer sur le bouton, copiez et collez l'URL suivante dans votre navigateur : [{{ $uploadUrl }}]({{ $uploadUrl }})
</x-mail::subcopy>
</x-mail::message>

