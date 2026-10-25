@props([
    'title' => null,
    'mitra' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10 sm:px-6">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="self-center" wire:navigate>
                    <x-logo :mitra="$mitra" />
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>
                <div class="rise rounded-3xl bg-white p-6 ring-1 ring-line sm:p-8">
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="text-center text-sm text-mute">{{ $footer }}</div>
                @endisset
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
