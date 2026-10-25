@props([
    'tone' => 'white',
    'icon' => null,
    'iconClass' => 'text-ice',
    'title' => null,
    'sub' => null,
])

<div {{ $attributes->class(['rounded-3xl p-5 ring-1 sm:p-7', 'bg-frost ring-ice/20' => $tone === 'frost', 'bg-white ring-line' => $tone !== 'frost']) }}>
    @if ($title)
        <div class="flex gap-4">
            @if ($icon)
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-paper ring-1 ring-line">
                    <flux:icon :icon="$icon" class="size-5 {{ $iconClass }}" />
                </span>
            @endif
            <div class="min-w-0">
                <h2 class="text-xl font-extrabold tracking-tight sm:text-2xl">{{ $title }}</h2>
                @if ($sub)
                    <p class="mt-1 text-ink-soft">{{ $sub }}</p>
                @endif
            </div>
        </div>
    @endif
    {{ $slot }}
</div>
