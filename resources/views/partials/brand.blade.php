@php
    $name = $store['storeName'] ?? 'TechPhone';
    $split = strripos($name, 'phone');
@endphp
<a class="brand {{ $class ?? '' }}" href="{{ route('home') }}">
    @if (! empty($store['logo']))
        <img class="brand-logo" src="{{ media_url($store['logo']) }}" alt="{{ $name }}">
    @else
        <span class="brand-mark">{{ mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: 'T' }}</span>
    @endif
    <span>@if ($split > 0){{ substr($name, 0, $split) }}<span>{{ substr($name, $split) }}</span>@else{{ $name }}@endif</span>
</a>
