@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col gap-1.5">
    <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ $title }}</h1>
    @if ($description)
        <p class="text-sm text-mute">{{ $description }}</p>
    @endif
</div>
