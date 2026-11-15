<x-layouts::auth title="Lupa kata sandi">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Lupa kata sandi?"
            description="Masukkan email akunmu. Kami kirim tautan untuk membuat kata sandi baru."
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                label="Email"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="nama@email.com"
            />

            <x-button type="submit" variant="dark" size="lg" class="w-full" data-test="email-password-reset-link-button">
                Kirim tautan atur ulang
            </x-button>
        </form>

        <p class="text-center text-sm text-mute">
            Sudah ingat?
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-ice hover:underline">Masuk</a>
            ·
            <a href="{{ route('mitra.login') }}" class="font-semibold text-ice hover:underline">Masuk Sejuk Mitra</a>
        </p>
    </div>
</x-layouts::auth>
