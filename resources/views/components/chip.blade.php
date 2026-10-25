@props([
    'active' => false,
    'icon' => null,
])

<button type="button" {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-semibold transition',
    'bg-ink text-white' => $active,
    'bg-white text-ink-soft ring-1 ring-line hover:ring-ink-soft' => ! $active,
]) }} aria-pressed="{{ $active ? 'true' : 'false' }}">
    @if ($icon) <flux:icon :icon="$icon" class="size-4" /> @endif
    {{ $slot }}
</button>
