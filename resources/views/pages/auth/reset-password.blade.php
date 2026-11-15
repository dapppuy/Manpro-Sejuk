<x-layouts::auth title="Atur ulang kata sandi">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Buat kata sandi baru" description="Kata sandi baru langsung berlaku setelah disimpan." />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <flux:input
                name="email"
                :value="old('email', request('email'))"
                label="Email"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                name="password"
                label="Kata sandi baru"
                type="password"
                required
                autofocus
                autocomplete="new-password"
                placeholder="Kata sandi baru"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                label="Ulangi kata sandi baru"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Ulangi kata sandi baru"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-button type="submit" variant="dark" size="lg" class="w-full" data-test="reset-password-button">
                Simpan kata sandi baru
            </x-button>
        </form>
    </div>
</x-layouts::auth>
