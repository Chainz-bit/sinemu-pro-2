@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'invalid-feedback d-block list-unstyled small text-danger mt-1 mb-0']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
