@props([
    'actions' => [],
])

{{-- Local development only: plays the technician and payment gateway. Never rendered outside APP_ENV=local. --}}
<div {{ $attributes->class('rounded-2xl border-2 border-dashed border-ink/15 bg-paper/70 p-4') }}>
    <div class="mb-3 flex items-center gap-2 text-[11px] font-bold tracking-wider text-ink-soft uppercase">
        <flux:icon.wrench class="size-3.5" />
        Panel simulasi · hanya di lokal
    </div>
    @if ($actions === [])
        <p class="text-xs text-mute">Tidak ada langkah simulasi untuk status ini. Lanjutkan dari tombol di atas.</p>
    @else
        <p class="mb-3 text-xs text-mute">Memainkan peran teknisi dan gateway pembayaran supaya alur bisa dicoba dari akun pelanggan.</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($actions as $action => $label)
                <x-button wire:key="sim-{{ $action }}" size="sm" :variant="$loop->first ? 'dark' : 'secondary'" wire:click="simulate('{{ $action }}')" wire:loading.attr="disabled" wire:target="simulate">
                    {{ $label }}
                </x-button>
            @endforeach
        </div>
    @endif
</div>
