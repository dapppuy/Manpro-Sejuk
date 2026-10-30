@props([
    'title' => null,
    'portal' => 'mitra',
    'items' => [],
    'subtitle' => null,
])

@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased" x-data="{ menu: false }">
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink text-white transition-transform lg:translate-x-0"
            :class="menu && 'translate-x-0'"
            aria-label="Navigasi portal"
        >
            <div class="flex h-16 items-center justify-between px-5">
                <a href="{{ $user?->homeRoute() ?? route('home') }}">
                    @if ($portal === 'admin')
                        <span class="inline-flex items-center gap-2">
                            <x-logo light />
                            <span class="rounded-md bg-sun px-1.5 py-0.5 text-[10px] font-extrabold tracking-widest text-ink">ADMIN</span>
                        </span>
                    @else
                        <x-logo mitra light />
                    @endif
                </a>
                <button type="button" class="grid size-9 place-items-center rounded-full text-white/70 hover:bg-white/10 lg:hidden" @click="menu = false" aria-label="Tutup menu">
                    <flux:icon.x class="size-5" />
                </button>
            </div>

            @if ($user)
                <div class="mx-4 mb-4 flex items-center gap-3 rounded-2xl bg-white/5 p-3">
                    <x-avatar :name="$user->name" :src="$user->avatarUrl()" size="sm" class="rounded-full !bg-white/10 !text-frost" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-white/55">{{ $subtitle ?? $user->email }}</p>
                    </div>
                    <div class="hidden lg:block">
                        <livewire:notification-bell :dark="true" />
                    </div>
                </div>
            @endif

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                @foreach ($items as $item)
                    @php $active = request()->routeIs($item['active'] ?? $item['route']); @endphp
                    <a href="{{ route($item['route']) }}" wire:navigate @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-white text-ink' => $active,
                        'text-white/70 hover:bg-white/10 hover:text-white' => ! $active,
                    ])>
                        <flux:icon :icon="$item['icon']" class="size-[18px]" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (! empty($item['count']))
                            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-bold tnum', 'bg-sun text-ink' => ! $active, 'bg-ink text-white' => $active])>{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="space-y-1 border-t border-white/10 p-3">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white">
                    <flux:icon.external-link class="size-[18px]" />
                    Situs pelanggan
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white">
                        <flux:icon.log-out class="size-[18px]" />
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="menu = false"></div>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line/70 bg-paper/80 px-4 backdrop-blur-lg sm:px-6 lg:hidden">
                <button type="button" class="grid size-9 place-items-center rounded-full ring-1 ring-line" @click="menu = true" aria-label="Buka menu">
                    <flux:icon.menu class="size-5" />
                </button>
                @if ($portal === 'admin')
                    <x-logo />
                @else
                    <x-logo mitra />
                @endif
                @if ($user)
                    <div class="ml-auto">
                        <livewire:notification-bell />
                    </div>
                @endif
            </header>

            <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
