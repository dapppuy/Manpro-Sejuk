@props([
    'title' => null,
    'mitra' => false,
])

<x-layouts::auth.simple :title="$title" :mitra="$mitra">
    {{ $slot }}
    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-layouts::auth.simple>
