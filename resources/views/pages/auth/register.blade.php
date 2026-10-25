<x-layouts::auth title="Daftar">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Buat akun pelanggan" description="Cari teknisi AC terdekat dengan harga jelas sebelum pintu diketuk." />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input name="name" label="Nama lengkap" :value="old('name')" type="text" required autofocus autocomplete="name" placeholder="Nama sesuai panggilan" />

            <flux:input name="phone" label="Nomor HP (WhatsApp)" :value="old('phone')" type="tel" required autocomplete="tel" placeholder="0812…" />

            <flux:input name="email" label="Email" :value="old('email')" type="email" required autocomplete="email" placeholder="nama@email.com" />

            <flux:input
                name="password"
                label="Kata sandi"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                label="Ulangi kata sandi"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Ulangi kata sandi"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-button type="submit" variant="dark" size="lg" class="w-full" data-test="register-user-button">
                Buat akun
            </x-button>
        </form>

        <p class="text-center text-sm text-mute">
            Sudah punya akun?
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-ice hover:underline">Masuk</a>
        </p>
    </div>

    <x-slot:footer>
        Kamu teknisi AC? <a href="{{ Route::has('mitra.register') ? route('mitra.register') : route('mitra.login') }}" class="font-semibold text-ink hover:underline">Daftar sebagai mitra</a>
    </x-slot:footer>
</x-layouts::auth>
