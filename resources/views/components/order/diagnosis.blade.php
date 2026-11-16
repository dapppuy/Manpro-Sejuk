@props(['diagnosis', 'title' => 'Hasil Cek AC Pintar'])

@php
    /** @var \App\Models\Diagnosis $diagnosis */
    $causes = collect($diagnosis->possible_causes ?? [])->take(3);
    $urgency = ['darurat' => 'bg-coral text-white', 'tinggi' => 'bg-coral-soft text-coral', 'sedang' => 'bg-sun-soft text-sun-ink', 'rendah' => 'bg-leaf-soft text-leaf'][strtolower((string) $diagnosis->urgency_level)] ?? 'bg-paper text-ink-soft';
@endphp

<x-card {{ $attributes->class('p-5 sm:p-6') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
            <flux:icon.sparkles class="mt-1 size-5 shrink-0 text-ice" />
            <div>
                <h2 class="text-lg font-bold">{{ $title }}</h2>
                <p class="text-sm text-ink-soft">Perkiraan AI dari foto dan keluhan pelanggan. Pastikan dengan pemeriksaan langsung.</p>
            </div>
        </div>
        <span class="rounded-full bg-frost px-2.5 py-1 text-[11px] font-bold tracking-wide text-ice-deep uppercase">Perkiraan</span>
    </div>

    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
        @if ($diagnosis->confidence_level)
            <span class="rounded-full bg-paper px-3 py-1 text-ink-soft">Keyakinan {{ $diagnosis->confidence_level }}{{ $diagnosis->confidence_score !== null ? ' · '.$diagnosis->confidence_score.'%' : '' }}</span>
        @endif
        @if ($diagnosis->urgency_level)
            <span class="rounded-full px-3 py-1 {{ $urgency }}">Urgensi {{ $diagnosis->urgency_level }}</span>
        @endif
        @if ($diagnosis->recommended_service_slug)
            <span class="rounded-full bg-paper px-3 py-1 text-ink-soft">Saran layanan: {{ $diagnosis->recommendedServiceName() }}</span>
        @endif
    </div>

    <p class="mt-4 text-sm"><span class="text-mute">Keluhan:</span> {{ $diagnosis->complaint_text }}</p>

    @if ($causes->isNotEmpty())
        <ul class="mt-4 space-y-2">
            @foreach ($causes as $cause)
                <li wire:key="cause-{{ $loop->index }}" class="rounded-xl bg-paper p-3 text-sm">
                    <p class="flex flex-wrap items-center justify-between gap-2 font-semibold">
                        {{ $cause['title'] ?? 'Kemungkinan penyebab' }}
                        @if (! empty($cause['likelihood']))
                            <span class="text-xs font-medium text-mute">Kemungkinan {{ $cause['likelihood'] }}</span>
                        @endif
                    </p>
                    @if (! empty($cause['explanation']))
                        <p class="mt-1 text-ink-soft">{{ $cause['explanation'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($diagnosis->actual_cause_by_technician)
        <div class="mt-4 rounded-xl bg-leaf-soft p-3 text-sm text-leaf">
            <p class="font-bold">Penyebab sebenarnya menurut teknisi{{ $diagnosis->is_accurate === null ? '' : ($diagnosis->is_accurate ? ' · perkiraan AI sesuai' : ' · perkiraan AI tidak sesuai') }}</p>
            <p class="mt-1">{{ $diagnosis->actual_cause_by_technician }}</p>
        </div>
    @endif

    {{ $slot }}
</x-card>
