<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <x-customer.header />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-8 text-sm text-mute sm:px-6">
                <span class="flex items-center gap-2 font-semibold text-ink">
                    <flux:icon.snowflake class="size-4 text-ice" /> Sejuk
                </span>
                <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Tautan bawah">
                    <a href="{{ route('home') }}#cara-kerja" wire:navigate class="hover:text-ink">Cara kerja</a>
                    @if (Route::has('mitra.register'))
                        <a href="{{ route('mitra.register') }}" class="hover:text-ink">Daftar jadi mitra</a>
                    @endif
                    <span>Teknisi AC terdekat di {{ config('sejuk.city') }}</span>
                </nav>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
