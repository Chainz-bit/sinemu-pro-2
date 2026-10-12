@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success py-2 small mb-3']) }}>
        {{ $status }}
    </div>
@endif
