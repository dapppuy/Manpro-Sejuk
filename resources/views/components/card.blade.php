@props([
    'as' => 'div',
])

<{{ $as }} {{ $attributes->class('rounded-2xl bg-white ring-1 ring-line') }}>
    {{ $slot }}
</{{ $as }}>
