@php
    $profile = auth()->user()?->technicianProfile;
    $items = collect([
        ['label' => 'Dashboard', 'route' => 'mitra.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Pesanan', 'route' => 'mitra.orders.index', 'active' => 'mitra.orders.*', 'icon' => 'clipboard-list'],
        ['label' => 'Harga acuan', 'route' => 'mitra.prices', 'icon' => 'tag'],
        ['label' => 'Jadwal', 'route' => 'mitra.schedule', 'icon' => 'calendar-days'],
        ['label' => 'Galeri kerja', 'route' => 'mitra.gallery', 'icon' => 'images'],
        ['label' => 'Pendapatan', 'route' => 'mitra.earnings', 'icon' => 'wallet'],
        ['label' => 'Profil', 'route' => 'mitra.profile', 'icon' => 'user'],
    ])->filter(fn (array $item) => Route::has($item['route']))->values()->all();
@endphp

<x-layouts::portal :title="$title ?? null" portal="mitra" :items="$items" :subtitle="$profile?->business_name">
    {{ $slot }}
</x-layouts::portal>
