@php
    $user = auth()->user();
@endphp

@if ($user?->isTechnician())
    <x-layouts::mitra :title="$title ?? null">
        {{ $slot }}
    </x-layouts::mitra>
@elseif ($user?->isAdmin())
    <x-layouts::admin :title="$title ?? null">
        {{ $slot }}
    </x-layouts::admin>
@else
    <x-layouts::customer :title="$title ?? null">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
            {{ $slot }}
        </div>
    </x-layouts::customer>
@endif
