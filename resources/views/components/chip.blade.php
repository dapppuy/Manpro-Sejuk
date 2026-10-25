@props([
    'active' => false,
    'invalid' => false,
])

<button type="button" {{ $attributes->class([
    'rounded-full px-3.5 py-2 text-sm font-medium transition',
    'bg-ink text-white' => $active,
    'bg-white text-ink-soft ring-1 ring-inset hover:ring-ink/30' => ! $active,
    'ring-coral/50' => ! $active && $invalid,
    'ring-line' => ! $active && ! $invalid,
]) }} aria-pressed="{{ $active ? 'true' : 'false' }}">
    {{ $slot }}
</button>
