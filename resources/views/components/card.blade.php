@props([
    'padding' => 'p-5 sm:p-6',
    'as' => 'div',
])

<{{ $as }} {{ $attributes->class(['rounded-3xl bg-white ring-1 ring-line', $padding]) }}>
    {{ $slot }}
</{{ $as }}>
