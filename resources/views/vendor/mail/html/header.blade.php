@props(['url'])
@php
    $logoUrl = config('mail.logo_url');
    if (!$logoUrl && file_exists(public_path('images/logo.png'))) {
        $logoUrl = url('images/logo.png');
    } elseif (!$logoUrl && file_exists(public_path('images/logo.svg'))) {
        $logoUrl = url('images/logo.svg');
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url ?? config('app.url') }}" style="display: inline-block;">
@if($logoUrl)
    <img src="{{ $logoUrl }}" class="logo" alt="{{ config('app.name') }}" style="max-width: 200px; height: auto;">
@else
    <span style="color: #4F46E5; font-size: 24px; font-weight: 800; text-decoration: none;">{!! $slot !!}</span>
@endif
</a>
</td>
</tr>
