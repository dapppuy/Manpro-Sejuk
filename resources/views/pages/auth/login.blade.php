@php
    $mitra ??= false;
@endphp

<x-layouts::auth :title="$mitra ? 'Masuk Sejuk Mitra' : 'Masuk'" :mitra="$mitra">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="$mitra ? 'Masuk ke Sejuk Mitra' : 'Masuk ke Sejuk'"
            :description="$mitra ? 'Kelola pesanan, jadwal, dan pendapatanmu.' : 'Pantau pesanan dan simpan alamat servismu.'"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                label="Email atau nomor HP"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="nama@email.com atau 0812…"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Kata sandi"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Kata sandi"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 end-0 text-sm" :href="route('password.request')" wire:navigate>
                        Lupa kata sandi?
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <x-button type="submit" variant="dark" size="lg" class="w-full" data-test="login-button">
                Masuk
            </x-button>
        </form>

        <p class="text-center text-sm text-mute">
            @if ($mitra)
                Belum jadi mitra?
                @if (Route::has('mitra.register'))
                    <a href="{{ route('mitra.register') }}" class="font-semibold text-ice hover:underline">Buat akun teknisi</a>
                @endif
            @else
                Belum punya akun?
                <a href="{{ route('register') }}" wire:navigate class="font-semibold text-ice hover:underline">Daftar</a>
            @endif
        </p>
    </div>

    <x-slot:footer>
        @if ($mitra)
            Mencari teknisi? <a href="{{ route('login') }}" class="font-semibold text-ink hover:underline">Masuk sebagai pelanggan</a>
        @else
            Kamu teknisi AC? <a href="{{ route('mitra.login') }}" class="font-semibold text-ink hover:underline">Masuk Sejuk Mitra</a>
        @endif
    </x-slot:footer>
</x-layouts::auth>
