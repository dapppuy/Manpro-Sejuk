<?php

use App\Enums\TimeWindow;
use App\Models\TechnicianProfile;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Jadwal')] class extends Component {
    /**
     * Weekly availability: day of week (0 = Minggu) => window value => available.
     *
     * @var array<int, array<string, bool>>
     */
    public array $weekly = [];

    public function mount(): void
    {
        $rules = $this->profile->availabilities()->whereNull('date')->where('is_available', true)->get();

        foreach (range(0, 6) as $day) {
            foreach (TimeWindow::cases() as $window) {
                $this->weekly[$day][$window->value] = $rules->contains(fn ($rule) => $rule->day_of_week === $day && $rule->time_window === $window);
            }
        }
    }

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    /**
     * The next 14 days with whether each is marked as a day off.
     *
     * @return Collection<int, array{date: CarbonImmutable, off: bool, windows: list<TimeWindow>}>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        $profile = $this->profile->load('availabilities');

        return collect(range(0, 13))->map(function (int $offset) use ($profile) {
            $date = CarbonImmutable::today()->addDays($offset);
            $off = $profile->availabilities->contains(fn ($rule) => $rule->date?->isSameDay($date) && ! $rule->is_available);

            return ['date' => $date, 'off' => $off, 'windows' => $profile->windowsOn($date)];
        });
    }

    public function toggle(int $day, string $window): void
    {
        abort_unless(isset($this->weekly[$day]) && TimeWindow::tryFrom($window), 422);

        $this->weekly[$day][$window] = ! $this->weekly[$day][$window];
    }

    public function saveWeekly(): void
    {
        DB::transaction(function () {
            $this->profile->availabilities()->whereNull('date')->delete();

            foreach ($this->weekly as $day => $windows) {
                foreach ($windows as $window => $available) {
                    if ($available) {
                        $this->profile->availabilities()->create(['day_of_week' => $day, 'time_window' => $window, 'is_available' => true]);
                    }
                }
            }
        });

        unset($this->upcoming);
        Flux::toast(variant: 'success', text: 'Jadwal mingguan disimpan.');
    }

    /**
     * Mark a date as a day off, or undo it so the weekly schedule applies again.
     */
    public function toggleDayOff(string $date): void
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        abort_if($day->isPast() && ! $day->isToday(), 422);

        $overrides = $this->profile->availabilities()->whereDate('date', $day);

        if ($overrides->exists()) {
            $overrides->delete();
        } else {
            foreach (TimeWindow::cases() as $window) {
                $this->profile->availabilities()->create(['date' => $day, 'time_window' => $window, 'is_available' => false]);
            }
        }

        unset($this->upcoming, $this->profile);
    }
}; ?>

@php
    $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
@endphp

<div>
    <p class="text-sm text-mute">Sejuk Mitra</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Jadwal & ketersediaan</h1>
    <p class="mt-2 max-w-2xl text-ink-soft">Slot yang aktif menentukan "Tersedia hari ini" dan "Jadwal terdekat" di profilmu. Slot hari ini tertutup 2 jam setelah jam mulainya.</p>

    <x-card class="mt-7 p-5 sm:p-6">
        <x-section-title icon="calendar-days" title="Jadwal mingguan" description="Ketuk slot untuk menyalakan atau mematikannya." />
        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="text-xs text-mute">
                        <th class="pb-2 text-left font-semibold">Hari</th>
                        @foreach (TimeWindow::cases() as $window)
                            <th wire:key="h-{{ $window->value }}" class="pb-2 text-center font-semibold">{{ $window->label() }}<span class="block font-normal tnum">{{ $window->range() }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day => $dayName)
                        <tr wire:key="d-{{ $day }}" class="border-t border-line">
                            <td class="py-2 pr-3 font-semibold">{{ $dayName }}</td>
                            @foreach (TimeWindow::cases() as $window)
                                @php $on = $weekly[$day][$window->value] ?? false; @endphp
                                <td wire:key="c-{{ $day }}-{{ $window->value }}" class="p-1 text-center">
                                    <button type="button" wire:click="toggle({{ $day }}, '{{ $window->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $dayName }} {{ $window->label() }}"
                                        @class(['h-10 w-full rounded-xl text-xs font-semibold transition', 'bg-ink text-white' => $on, 'bg-paper text-mute ring-1 ring-inset ring-line hover:ring-ink/30' => ! $on])>
                                        {{ $on ? 'Buka' : 'Tutup' }}
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-button class="mt-5" icon="check" wire:click="saveWeekly" wire:loading.attr="disabled">Simpan jadwal mingguan</x-button>
    </x-card>

    <x-card class="mt-6 p-5 sm:p-6">
        <x-section-title icon="calendar-clock" title="14 hari ke depan" description="Tandai hari libur tanpa mengubah jadwal mingguan." />
        <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->upcoming as $item)
                <div wire:key="u-{{ $item['date']->toDateString() }}" @class(['flex items-center justify-between gap-3 rounded-2xl px-4 py-3 ring-1', 'bg-coral-soft/50 ring-coral/20' => $item['off'], 'bg-paper ring-line' => ! $item['off']])>
                    <div class="min-w-0">
                        <p class="text-sm font-bold">{{ $item['date']->isToday() ? 'Hari ini' : $item['date']->translatedFormat('D, j M') }}</p>
                        <p class="truncate text-xs text-mute">
                            @if ($item['off'])
                                Libur
                            @elseif ($item['windows'] === [])
                                Tidak ada slot
                            @else
                                {{ collect($item['windows'])->map(fn ($window) => $window->label())->implode(', ') }}
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="toggleDayOff('{{ $item['date']->toDateString() }}')" class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 {{ $item['off'] ? 'bg-white text-ink ring-line' : 'text-coral ring-coral/30 hover:bg-coral-soft' }}">
                        {{ $item['off'] ? 'Batalkan libur' : 'Libur' }}
                    </button>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
