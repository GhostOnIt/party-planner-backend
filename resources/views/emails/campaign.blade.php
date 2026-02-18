<x-mail::message>
# {{ $subject }}

{{ $content }}

<x-mail::button :url="config('app.frontend_url')">
Accéder à la plateforme
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
