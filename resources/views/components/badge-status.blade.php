@props([
    'status',
    'type' => null,
    'size' => 'sm',
    'label' => null,
])

<x-status-badge :status="$status" :type="$type" :size="$size" :label="$label" {{ $attributes }}>
    {{ $slot }}
</x-status-badge>
