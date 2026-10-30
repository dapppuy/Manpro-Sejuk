@php
    $user = auth()->user();
    $ordersUrl = Route::has('orders.index') ? route('orders.index') : route('login');
    $navItems = [
        ['label' => 'Cari teknisi', 'href' => route('search'), 'active' => request()->routeIs('search', 'technicians.*')],
        ['label' => 'Pesanan saya', 'href' => $ordersUrl, 'active' => request()->routeIs('orders.*'), 'dot' => $hasActiveOrder],
        ['label' => 'Cara kerja', 'href' => route('home').'#cara-kerja', 'active' => false],
    ];
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-30 border-b border-line bg-paper/85 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 md:gap-8">
        <a href="{{ route('home') }}" wire:navigate aria-label="Sejuk, ke beranda">
            <x-logo />
        </a>

        <nav class="hidden items-center gap-1 text-sm font-semibold text-ink-soft md:flex" aria-label="Menu utama">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" wire:navigate @class([
                    'inline-flex items-center rounded-full px-3.5 py-2 transition',
                    'bg-white text-ink ring-1 ring-line' => $item['active'],
                    'hover:text-ink' => ! $item['active'],
                ])>
                    {{ $item['label'] }}
                    @if ($item['dot'] ?? false)
                        <span class="ml-1.5 inline-block size-2 rounded-full bg-coral" aria-label="Ada pesanan aktif"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            @if ($locationLabel)
                <a href="{{ route('home') }}#lokasi" wire:navigate class="hidden max-w-60 items-center gap-1.5 truncate rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink-soft ring-1 ring-line lg:inline-flex">
                    <flux:icon.map-pin class="size-3.5 shrink-0" />
                    <span class="truncate">{{ $locationLabel }}</span>
                </a>
            @endif

            @if (config('sejuk.show_technician_entry'))
                <a href="{{ $user?->isTechnician() ? route('mitra.dashboard') : route('mitra.login') }}" class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold ring-1 ring-line hover:ring-ink/30 sm:px-3.5" aria-label="Buka portal teknisi">
                    <flux:icon.wrench class="size-4" />
                    <span class="hidden sm:inline">Untuk Teknisi</span>
                </a>
            @endif

            @auth
                <livewire:notification-bell />
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="flex items-center gap-2 rounded-full text-sm font-semibold" aria-label="Buka menu akun">
                        <x-avatar :name="$user->name" :src="$user->avatarUrl()" size="sm" class="rounded-full" />
                        <span class="hidden sm:inline">{{ str($user->name)->before(' ') }}</span>
                    </button>
                    <flux:menu>
                        <div class="px-2 py-1.5">
                            <p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p>
                            <p class="truncate text-xs text-mute">{{ $user->email }}</p>
                        </div>
                        <flux:menu.separator />
                        @if ($user->isCustomer())
                            <flux:menu.item :href="Route::has('account') ? route('account') : route('profile.edit')" icon="user" wire:navigate>Profil saya</flux:menu.item>
                            <flux:menu.item :href="$ordersUrl" icon="clipboard-list" wire:navigate>Pesanan saya</flux:menu.item>
                        @else
                            <flux:menu.item :href="$user->homeRoute()" icon="layout-dashboard">Buka portal</flux:menu.item>
                        @endif
                        <flux:menu.item :href="route('security.edit')" icon="settings" wire:navigate>Keamanan akun</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="log-out" class="w-full cursor-pointer">Keluar</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <x-button variant="dark" size="sm" :href="route('login')">Masuk</x-button>
            @endauth

            <button type="button" class="grid size-9 place-items-center rounded-full ring-1 ring-line md:hidden" @click="open = ! open" :aria-expanded="open" aria-label="Buka menu">
                <flux:icon.menu class="size-5" x-show="! open" />
                <flux:icon.x class="size-5" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition.opacity @click.outside="open = false" class="border-t border-line bg-paper md:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3" aria-label="Menu utama seluler">
            @if ($locationLabel)
                <a href="{{ route('home') }}#lokasi" wire:navigate class="mb-1 flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-medium text-ink-soft ring-1 ring-line">
                    <flux:icon.map-pin class="size-4 shrink-0" />
                    <span class="truncate">{{ $locationLabel }}</span>
                </a>
            @endif
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" wire:navigate class="flex items-center justify-between rounded-xl px-4 py-3 text-base font-semibold text-ink hover:bg-white">
                    {{ $item['label'] }}
                    @if ($item['dot'] ?? false)
                        <span class="size-2 rounded-full bg-coral"></span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</header>
