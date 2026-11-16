<?php

use App\Models\AuditLog;
use App\Models\ContentPage;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Konten & kebijakan')] class extends Component {
    #[Url(as: 'halaman')]
    public string $slug = 'syarat-ketentuan';

    public string $title = '';

    public string $body = '';

    public bool $preview = false;

    public function mount(): void
    {
        $this->slug = array_key_exists($this->slug, ContentPage::PAGES) ? $this->slug : 'syarat-ketentuan';
        $this->load();
    }

    public function choose(string $slug): void
    {
        abort_unless(array_key_exists($slug, ContentPage::PAGES), 404);

        $this->slug = $slug;
        $this->preview = false;
        $this->load();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'slug' => ['required', Rule::in(array_keys(ContentPage::PAGES))],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:60000'],
        ], attributes: ['title' => 'judul', 'body' => 'isi']);

        $page = ContentPage::updateOrCreate(['slug' => $validated['slug']], [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'updated_by' => auth()->id(),
        ]);

        AuditLog::record('content.saved', "Halaman {$page->title} diperbarui", $page);
        Flux::toast(variant: 'success', text: 'Halaman disimpan dan langsung tampil di website.');
    }

    public function restoreDefault(): void
    {
        $this->body = ContentPage::defaultBody($this->slug);
        $this->title = ContentPage::PAGES[$this->slug]['title'];
    }

    private function load(): void
    {
        $page = ContentPage::findOrDefault($this->slug);

        $this->title = $page->title;
        $this->body = $page->body;
    }
}; ?>

@php
    $page = \App\Models\ContentPage::findOrDefault($slug);
    $draft = new \App\Models\ContentPage(['body' => $body]);
@endphp

<div>
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Konten & kebijakan</h1>
    <p class="mt-1 text-ink-soft">Syarat & ketentuan, kebijakan privasi, perjanjian kemitraan, dan materi bantuan. Format Markdown: <code>## Judul</code>, <code>- poin</code>, <code>**tebal**</code>.</p>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (\App\Models\ContentPage::PAGES as $key => $definition)
            <button type="button" wire:key="p-{{ $key }}" wire:click="choose('{{ $key }}')" @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1', 'bg-ink text-white ring-ink' => $slug === $key, 'bg-white text-ink-soft ring-line hover:text-ink' => $slug !== $key])>{{ $definition['title'] }}</button>
        @endforeach
    </div>

    <x-card class="mt-5 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-mute">
                {{ $page->exists ? 'Terakhir diubah '.$page->updated_at?->translatedFormat('j M Y, H.i').($page->editor ? ' oleh '.$page->editor->name : '') : 'Masih memakai teks bawaan.' }}
            </p>
            <div class="flex items-center gap-3">
                <a href="{{ route(\App\Models\ContentPage::PAGES[$slug]['route']) }}" target="_blank" class="font-semibold text-ice hover:text-ice-deep">Lihat halaman</a>
                <button type="button" wire:click="$toggle('preview')" class="font-semibold text-ink-soft hover:text-ink">{{ $preview ? 'Ubah teks' : 'Pratinjau' }}</button>
            </div>
        </div>

        <form wire:submit="save" class="mt-4 space-y-4">
            <x-form.input label="Judul" name="title" wire:model="title" required />
            @if ($preview)
                <div class="prose-sejuk rounded-2xl bg-paper p-5">{!! $draft->html() !!}</div>
            @else
                <x-form.textarea label="Isi" name="body" rows="22" wire:model="body" class="font-mono" required />
            @endif
            <div class="flex flex-wrap gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button type="button" variant="ghost" wire:click="restoreDefault" wire:confirm="Ganti isi dengan teks bawaan? Perubahan belum tersimpan sampai kamu klik Simpan.">Pakai teks bawaan</x-button>
            </div>
        </form>
    </x-card>
</div>
