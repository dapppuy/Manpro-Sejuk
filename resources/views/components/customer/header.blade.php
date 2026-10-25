@php
    $user = auth()->user();
    $ordersUrl = Route::has('orders.index') ? route('orders.index') : route('login');
    $navItems = [
        ['label' => 'Cari teknisi', 'href' => route('search'), 'active' => request()->routeIs('search', 'technicians.*')],
        ['label' => 'Pesanan saya', 'href' => $ordersUrl, 'active' => request()->routeIs('orders.*'), 'dot' => $hasActiveOrder],
        ['label' => 'Cara kerja', 'href' => route('home').'#cara-kerja', 'active' => false],
    ];
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line/70 bg-paper/80 backdrop-blur-lg">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:gap-6">
        <a href="{{ route('home') }}" wire:navigate class="shrink-0">
            <x-logo />
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Menu utama">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" wire:navigate @class([
                    'relative rounded-full px-3.5 py-2 text-sm font-semibold transition',
                    'bg-white text-ink ring-1 ring-line' => $item['active'],
                    'text-ink-soft hover:text-ink' => ! $item['active'],
                ])>
                    {{ $item['label'] }}
                    @if ($item['dot'] ?? false)
                        <span class="absolute top-1.5 right-1.5 size-2 rounded-full bg-coral ring-2 ring-paper" aria-label="Ada pesanan aktif"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('home') }}#lokasi" wire:navigate class="hidden max-w-56 items-center gap-1.5 rounded-full bg-white px-3 py-2 text-sm font-semibold text-ink-soft ring-1 ring-line hover:ring-ink-soft md:inline-flex">
                <flux:icon.map-pin class="size-4 shrink-0 text-ice" />
                <span class="truncate">{{ $locationLabel ?? 'Atur lokasi servis' }}</span>
            </a>

            <div class="hidden sm:block">
                <x-button variant="outline" size="sm" :href="route('mitra.login')" class="whitespace-nowrap">
                    Untuk Teknisi
                </x-button>
            </div>

            @auth
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="rounded-2xl focus-visible:outline-2 focus-visible:outline-ice" aria-label="Menu akun">
                        <x-avatar :name="$user->name" size="sm" />
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

            <button type="button" class="grid size-9 place-items-center rounded-full text-ink ring-1 ring-line lg:hidden" @click="open = ! open" :aria-expanded="open" aria-label="Buka menu">
                <flux:icon.menu class="size-5" x-show="! open" />
                <flux:icon.x class="size-5" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition.opacity @click.outside="open = false" class="border-t border-line bg-paper lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6" aria-label="Menu utama seluler">
            <a href="{{ route('home') }}#lokasi" wire:navigate class="mb-1 flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-ink-soft ring-1 ring-line md:hidden">
                <flux:icon.map-pin class="size-4 text-ice" />
                <span class="truncate">{{ $locationLabel ?? 'Atur lokasi servis' }}</span>
            </a>
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" wire:navigate class="flex items-center justify-between rounded-2xl px-4 py-3 text-base font-semibold text-ink hover:bg-white">
                    {{ $item['label'] }}
                    @if ($item['dot'] ?? false)
                        <span class="size-2 rounded-full bg-coral"></span>
                    @endif
                </a>
            @endforeach
            <a href="{{ route('mitra.login') }}" class="rounded-2xl px-4 py-3 text-base font-semibold text-ice sm:hidden">Untuk Teknisi →</a>
        </nav>
    </div>
</header>
