<?php

use App\Models\ContentPage;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::customer')] class extends Component {
    public ContentPage $page;

    public function mount(string $slug): void
    {
        $this->page = ContentPage::findOrDefault($slug);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->page->title);
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
    <p class="text-sm text-mute">{{ $page->exists ? 'Diperbarui '.$page->updated_at?->translatedFormat('j F Y') : 'Sejuk' }}</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $page->title }}</h1>

    <article class="prose-sejuk mt-8">
        {!! $page->html() !!}
    </article>

    <nav class="mt-12 flex flex-wrap gap-x-5 gap-y-2 border-t border-line pt-6 text-sm font-semibold text-ink-soft" aria-label="Halaman lainnya">
        @foreach (ContentPage::PAGES as $key => $definition)
            @if ($key !== $page->slug)
                <a href="{{ route($definition['route']) }}" wire:navigate class="hover:text-ink">{{ $definition['title'] }}</a>
            @endif
        @endforeach
    </nav>
</div>
