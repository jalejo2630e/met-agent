@php
    $mailBranding = $mailBranding ?? \App\Support\MailBranding::data();
@endphp
<x-mail::message>
# {{ $heading }}

@foreach ($bodyLines as $line)
{{ $line }}

@endforeach

@if(! empty($actionUrl) && ! empty($actionText))
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endif

{{ __('Regards') }},<br>
{{ $mailBranding['display_name'] ?? config('app.name') }}
</x-mail::message>
