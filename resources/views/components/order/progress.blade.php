@props([
    'status',
])

@php
    /** @var \App\Enums\OrderStatus $status */
    $steps = ['Respons', 'Estimasi', 'Terjadwal', 'Biaya final', 'Pembayaran', 'Pengerjaan', 'Konfirmasi', 'Selesai'];
    $current = $status->progressStep();
@endphp

<ol {{ $attributes->class('grid grid-cols-8 gap-1.5') }} aria-label="Progres pesanan">
    @foreach ($steps as $index => $step)
        <li @if ($index === $current) aria-current="step" @endif>
            <div @class(['h-1.5 rounded-full transition-colors duration-500', 'bg-ice' => $index < $current, 'bg-ice/50' => $index === $current, 'bg-line' => $index > $current])></div>
            <p @class(['mt-2 hidden text-xs font-semibold md:block', 'text-ink' => $index <= $current, 'text-mute' => $index > $current])>{{ $step }}</p>
        </li>
    @endforeach
</ol>
<p class="mt-2 text-xs font-semibold text-ink md:hidden">Langkah {{ $current + 1 }} dari 8 · {{ $steps[$current] }}</p>
