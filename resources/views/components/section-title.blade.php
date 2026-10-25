@props([
    'icon',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex items-start gap-3') }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-frost text-ice">
        <flux:icon :icon="$icon" class="size-5" />
    </span>
    <div>
        <h2 class="font-bold">{{ $title }}</h2>
        @if ($description)
            <p class="mt-0.5 text-sm text-mute">{{ $description }}</p>
        @endif
    </div>
</div>
