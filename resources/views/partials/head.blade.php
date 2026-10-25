<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#0c1c2a">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
