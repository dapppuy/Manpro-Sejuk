<x-layouts::customer title="Masuk Sejuk Mitra">
    <div class="mx-auto grid min-h-[calc(100vh-8rem)] max-w-5xl items-center gap-10 px-4 py-10 sm:px-6 sm:py-12 lg:grid-cols-2 lg:gap-12">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-frost px-3 py-1 text-xs font-bold text-ice-deep">
                <flux:icon.snowflake class="size-3.5" /> Sejuk Mitra
            </span>
            <h1 class="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">Kelola pekerjaan AC dalam satu tempat.</h1>
            <p class="mt-4 text-lg leading-relaxed text-ink-soft">Terima pesanan, kirim estimasi, catat biaya final, dan unggah bukti kerja langsung dari dashboard teknisi.</p>
            <a href="{{ route('home') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-ice">
                <flux:icon.arrow-left class="size-4" /> Kembali ke situs pelanggan
            </a>
        </div>

        <x-card class="p-6 sm:p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-ink text-white">
                <flux:icon.log-in class="size-5" />
            </span>
            <h2 class="mt-5 text-2xl font-extrabold">Masuk sebagai teknisi</h2>
            <p class="mt-1 text-sm text-ink-soft">Gunakan akun mitra Sejuk milikmu.</p>

            <x-auth-session-status class="mt-5" :status="session('status')" />

            <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                @csrf
                <x-form.input label="Email atau nomor HP" name="email" :value="old('email')" autocomplete="username" autofocus />
                <x-form.input label="Kata sandi" name="password" type="password" autocomplete="current-password" />
                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="remember" class="size-4 accent-ice" @checked(old('remember')) /> Ingat saya
                </label>
                <x-button type="submit" size="lg" class="w-full" icon-trailing="arrow-right">Masuk</x-button>
                <div class="border-t border-line pt-5 text-center">
                    <p class="text-sm text-mute">Belum punya akun teknisi?</p>
                    <a href="{{ route('mitra.register') }}" class="mt-2 inline-block text-sm font-bold text-ice hover:text-ice-deep">Buat akun teknisi</a>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts::customer>
