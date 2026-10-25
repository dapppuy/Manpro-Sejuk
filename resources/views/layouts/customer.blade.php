<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <x-customer.header />

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-line">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-start md:justify-between">
                <div class="max-w-sm">
                    <x-logo />
                    <p class="mt-3 text-sm text-mute">Teknisi AC terdekat di {{ config('sejuk.city') }}. Harga jelas sebelum pintu diketuk.</p>
                </div>
                <div class="grid grid-cols-2 gap-8 text-sm sm:grid-cols-3">
                    <div class="flex flex-col gap-2">
                        <p class="font-semibold text-ink">Pelanggan</p>
                        <a href="{{ route('search') }}" wire:navigate class="text-mute hover:text-ink">Cari teknisi</a>
                        <a href="{{ route('home') }}#cara-kerja" wire:navigate class="text-mute hover:text-ink">Cara kerja</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        <p class="font-semibold text-ink">Teknisi</p>
                        @if (Route::has('mitra.register'))
                            <a href="{{ route('mitra.register') }}" class="text-mute hover:text-ink">Daftar jadi mitra</a>
                        @endif
                        <a href="{{ route('mitra.login') }}" class="text-mute hover:text-ink">Masuk Sejuk Mitra</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        <p class="font-semibold text-ink">Pembayaran</p>
                        <p class="text-mute">QRIS, virtual account, e-wallet. Dana ditahan sampai pekerjaan selesai.</p>
                    </div>
                </div>
            </div>
            <p class="border-t border-line py-5 text-center text-xs text-mute">© {{ now()->year }} Sejuk · {{ config('sejuk.city') }}</p>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
