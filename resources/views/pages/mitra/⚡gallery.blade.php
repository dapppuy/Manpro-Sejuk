<?php

use App\Models\TechnicianPhoto;
use App\Models\TechnicianProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::mitra'), Title('Galeri kerja')] class extends Component {
    use WithFileUploads;

    public const MAX_PHOTOS = 12;

    /** @var list<TemporaryUploadedFile> */
    public array $uploads = [];

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    /**
     * @return Collection<int, TechnicianPhoto>
     */
    #[Computed]
    public function photos(): Collection
    {
        return $this->profile->photos()->latest()->get();
    }

    public function updatedUploads(): void
    {
        $remaining = self::MAX_PHOTOS - $this->photos->count();

        $this->validate([
            'uploads' => ['array', 'max:'.max($remaining, 0)],
            'uploads.*' => ['image', 'max:5120'],
        ], ['uploads.max' => "Galeri maksimal ".self::MAX_PHOTOS." foto. Sisa {$remaining} slot."], ['uploads.*' => 'foto']);

        foreach ($this->uploads as $file) {
            $this->profile->photos()->create(['path' => $file->store("gallery/{$this->profile->id}", 'public')]);
        }

        $this->uploads = [];
        unset($this->photos);

        Flux::toast(variant: 'success', text: 'Foto ditambahkan ke galeri.');
    }

    public function updateCaption(int $id, string $caption): void
    {
        $this->profile->photos()->findOrFail($id)->update(['caption' => Str::limit(trim($caption), 120, '') ?: null]);
    }

    public function deletePhoto(int $id): void
    {
        $photo = $this->profile->photos()->findOrFail($id);

        if (! Str::startsWith($photo->path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($photo->path);
        }

        $photo->delete();
        unset($this->photos);
    }
}; ?>

<div>
    <p class="text-sm text-mute">Sejuk Mitra</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Galeri kerja</h1>
    <p class="mt-2 max-w-2xl text-ink-soft">Foto hasil kerja tampil di profilmu. Pilih foto yang rapi dan tidak menampilkan wajah atau barang pribadi pelanggan.</p>

    <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @if ($this->photos->count() < self::MAX_PHOTOS)
            <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-line bg-white text-center hover:border-ice">
                <span wire:loading.remove wire:target="uploads" class="flex flex-col items-center">
                    <flux:icon.image-plus class="size-7 text-ice" />
                    <span class="mt-2 text-sm font-bold">Tambah foto</span>
                    <span class="text-xs text-mute">{{ $this->photos->count() }}/{{ self::MAX_PHOTOS }} · maks. 5 MB</span>
                </span>
                <flux:icon.loading wire:loading wire:target="uploads" class="size-7 text-ice" />
                <input type="file" accept="image/*" multiple wire:model="uploads" class="sr-only" />
            </label>
        @endif

        @foreach ($this->photos as $photo)
            <figure wire:key="photo-{{ $photo->id }}" class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-line">
                <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?? 'Foto hasil kerja' }}" loading="lazy" class="aspect-[4/3] w-full object-cover" />
                <button type="button" wire:click="deletePhoto({{ $photo->id }})" wire:confirm="Hapus foto ini dari galeri?" class="absolute top-2 right-2 grid size-8 place-items-center rounded-full bg-ink/70 text-white" aria-label="Hapus foto">
                    <flux:icon.trash class="size-4" />
                </button>
                <figcaption class="p-2">
                    <input type="text" value="{{ $photo->caption }}" placeholder="Tambah keterangan…" maxlength="120"
                        x-on:change="$wire.updateCaption({{ $photo->id }}, $event.target.value)"
                        class="w-full rounded-lg bg-transparent px-2 py-1 text-xs outline-none placeholder:text-mute focus:bg-paper" aria-label="Keterangan foto" />
                </figcaption>
            </figure>
        @endforeach
    </div>
    @error('uploads') <p class="mt-3 text-sm text-coral">{{ $message }}</p> @enderror
    @error('uploads.*') <p class="mt-3 text-sm text-coral">{{ $message }}</p> @enderror
</div>
