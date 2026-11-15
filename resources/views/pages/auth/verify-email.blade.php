@php
    $user = auth()->user();
@endphp

<x-layouts::auth title="Verifikasi email" :mitra="$user?->isTechnician() ?? false">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Cek email kamu"
            description="Kami sudah mengirim tautan verifikasi. Klik tautan itu untuk mengaktifkan akunmu."
        />

        @if (session('status') === 'verification-link-sent')
            <x-auth-session-status status="Tautan verifikasi baru sudah dikirim ke emailmu." />
        @endif

        <div class="flex items-center gap-3 rounded-2xl bg-frost p-4 text-sm">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-ice ring-1 ring-ice/20">
                <flux:icon.envelope class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs text-mute">Dikirim ke</p>
                <p class="truncate font-semibold" data-test="verification-email">{{ $user?->email }}</p>
            </div>
        </div>

        <p class="text-sm text-ink-soft">Tidak menemukan emailnya? Cek folder spam atau promosi, atau kirim ulang tautannya.</p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" variant="dark" size="lg" class="w-full" data-test="resend-verification-button">
                Kirim ulang email verifikasi
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm font-semibold text-mute hover:text-ink" data-test="logout-button">
                Salah email? Keluar dan daftar ulang
            </button>
        </form>
    </div>
</x-layouts::auth>
