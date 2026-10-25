@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-2xl bg-leaf-soft px-4 py-3 text-sm font-medium text-leaf']) }}>
        {{ $status }}
    </div>
@endif
