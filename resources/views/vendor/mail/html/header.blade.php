@props(['url'])
@php
    $mailBranding = $mailBranding ?? \App\Support\MailBranding::data();
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (! empty($mailBranding['logo_url']))
<img src="{{ $mailBranding['logo_url'] }}" class="logo" alt="{{ $mailBranding['display_name'] }}">
@elseif (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
