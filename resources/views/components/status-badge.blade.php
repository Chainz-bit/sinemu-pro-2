@props([
    'status',
    'type' => null,
    'size' => 'sm',
    'label' => null,
])

@php
    $resolved = \App\Support\StatusBadgePresenter::resolve($status, $type);
    $displayLabel = $label ?? $resolved['label'];
    $badgeClasses = \App\Support\StatusBadgePresenter::badgeClasses($status, $type, $size);
@endphp

<span {{ $attributes->merge(['class' => $badgeClasses]) }}>
    @if(trim((string) $slot) !== '')
        {{ $slot }}
    @else
        {{ $displayLabel }}
    @endif
</span>
