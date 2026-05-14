@props([
    'id',
    'label' => 'Aksi',
    'triggerClass' => '',
    'menuClass' => '',
])

<button type="button" class="row-menu-trigger {{ $triggerClass }}" data-menu-target="{{ $id }}" aria-label="{{ $label }}" aria-controls="{{ $id }}">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 5.5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3zm0 5a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3z" fill="currentColor"/>
    </svg>
</button>
<div class="row-menu {{ $menuClass }}" id="{{ $id }}" role="menu">
    {{ $slot }}
</div>
