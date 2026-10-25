@php
    $items = collect([
        ['label' => 'Ringkasan', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
        ['label' => 'Verifikasi teknisi', 'route' => 'admin.verifications.index', 'active' => 'admin.verifications.*', 'icon' => 'shield-check'],
        ['label' => 'Teknisi', 'route' => 'admin.technicians.index', 'active' => 'admin.technicians.*', 'icon' => 'users'],
        ['label' => 'Pesanan', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'clipboard-list'],
        ['label' => 'Dana & pencairan', 'route' => 'admin.funds', 'icon' => 'banknote'],
        ['label' => 'Ulasan', 'route' => 'admin.reviews', 'icon' => 'star'],
    ])->filter(fn (array $item) => Route::has($item['route']))->values()->all();
@endphp

<x-layouts::portal :title="$title ?? null" portal="admin" :items="$items" subtitle="Tim Sejuk">
    {{ $slot }}
</x-layouts::portal>
