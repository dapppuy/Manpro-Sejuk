
{{-- <div class="max-w-3xl mx-auto py-8 px-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Cek AC Pintar (AI)</h1>
        <p class="text-sm text-gray-600 mt-1">
            Unggah foto stiker spesifikasi atau kondisi AC beserta keluhanmu untuk mendapatkan perkiraan awal sebelum teknisi datang.
        </p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-5">
        @if($units->isNotEmpty())
            <div>
                <label class="block text-sm font-semibold text-gray-800 mb-1.5">Pilih Unit AC Tersimpan (Opsional)</label>
                <select wire:model="customerUnitId" class="w-full rounded-xl border-gray-300 text-sm">
                    <option value="">-- Pilih unit AC atau isi manual --</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->name }} {{ $unit->room_label ? "({$unit->room_label})" : '' }} - {{ $unit->brand }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-sm font-semibold text-gray-800">
                    Keluhan AC <span class="text-red-500">*</span>
                </label>
                <span class="text-xs text-gray-400">Min. 10 karakter</span>
            </div>
            <textarea wire:model="complaintText" rows="3"
                placeholder="Contoh: AC menyala tapi anginnya tidak dingin, kadang menetes air dari unit indoor..."
                class="w-full rounded-xl border-gray-300 text-sm focus:border-gray-900 focus:ring-gray-900"></textarea>
            @error('complaintText') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl p-4 bg-gray-50/70 flex flex-col justify-between" style="border: 1.5px dashed #cbd5e1;">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label class="text-sm font-semibold text-gray-800">Foto Stiker Spesifikasi</label>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded bg-gray-200 text-gray-700">Maks. 5 MB</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        Format: JPG, PNG, WEBP. AI akan membaca merek, PK, dan tipe freon.
                    </p>

                    @if($stickerPhoto && !$errors->has('stickerPhoto'))
                        <div class="relative mb-2 rounded-lg overflow-hidden border border-gray-200 bg-white">
                            <img src="{{ $stickerPhoto->temporaryUrl() }}" alt="Preview Stiker" class="w-full h-32 object-cover">
                            <button type="button" wire:click="removeStickerPhoto"
                                class="absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-md shadow"
                                style="background-color: #dc2626; color: #ffffff;">
                                Hapus
                            </button>
                        </div>
                    @else
                        <input type="file" wire:model="stickerPhoto" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-900 file:text-white hover:file:bg-gray-800">
                    @endif

                    <div wire:loading wire:target="stickerPhoto" class="text-xs text-gray-600 mt-2">
                        Mengunggah foto stiker...
                    </div>
                    @error('stickerPhoto') <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl p-4 bg-gray-50/70 flex flex-col justify-between" style="border: 1.5px dashed #cbd5e1;">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label class="text-sm font-semibold text-gray-800">Foto Kondisi AC</label>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded bg-gray-200 text-gray-700">Maks. 5 MB</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        Format: JPG, PNG, WEBP. Misal: bunga es, lampu berkedip, atau bocor air.
                    </p>

                    @if($conditionPhoto && !$errors->has('conditionPhoto'))
                        <div class="relative mb-2 rounded-lg overflow-hidden border border-gray-200 bg-white">
                            <img src="{{ $conditionPhoto->temporaryUrl() }}" alt="Preview Kondisi" class="w-full h-32 object-cover">
                            <button type="button" wire:click="removeConditionPhoto"
                                class="absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-md shadow"
                                style="background-color: #dc2626; color: #ffffff;">
                                Hapus
                            </button>
                        </div>
                    @else
                        <input type="file" wire:model="conditionPhoto" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-900 file:text-white hover:file:bg-gray-800">
                    @endif

                    <div wire:loading wire:target="conditionPhoto" class="text-xs text-gray-600 mt-2">
                        Mengunggah foto kondisi...
                    </div>
                    @error('conditionPhoto') <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <button type="button" wire:click="analyze" wire:loading.attr="disabled"
                style="background-color: #18181b; color: #ffffff;"
                class="px-6 py-2.5 rounded-xl text-sm font-semibold shadow-sm hover:opacity-90 disabled:opacity-50 transition">
                <span wire:loading.remove wire:target="analyze">Analisis dengan AI</span>
                <span wire:loading wire:target="analyze">Sedang menganalisis AC...</span>
            </button>

            <button type="button" wire:click="proceedToSearchTechnician"
                class="text-sm font-medium text-gray-600 hover:text-gray-900 underline">
                Lewati & Pesan Teknisi Biasa
            </button>
        </div>

        @if($errorMessage)
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">
                {{ $errorMessage }}
            </div>
        @endif
    </div>

    @if($diagnosis)
        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-4">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        Perkiraan Awal AI • Bukan Pengganti Pemeriksaan Teknisi
                    </span>
                    <h2 class="text-lg font-bold text-gray-900 mt-2">Hasil Analisis Awal</h2>
                </div>
                <div class="text-right text-xs text-gray-600">
                    <div>Keyakinan AI: <strong class="uppercase">{{ $diagnosis->confidence_level }} ({{ $diagnosis->confidence_score }}%)</strong></div>
                    <div>Urgensi: <strong class="uppercase text-red-600">{{ $diagnosis->urgency_level }}</strong></div>
                </div>
            </div>

            @if(!empty($diagnosis->detected_unit_info))
                <div class="bg-gray-50 rounded-xl p-3.5 text-xs text-gray-700 flex flex-wrap gap-4">
                    <span><strong>Merek:</strong> {{ $diagnosis->detected_unit_info['brand'] ?? '-' }}</span>
                    <span><strong>Kapasitas:</strong> {{ $diagnosis->detected_unit_info['capacity_pk'] ?? '-' }}</span>
                    <span><strong>Freon:</strong> {{ $diagnosis->detected_unit_info['refrigerant'] ?? '-' }}</span>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-semibold text-gray-800 mb-2.5">Kemungkinan Penyebab</h3>
                <div class="space-y-2.5">
                    @foreach($diagnosis->possible_causes as $cause)
                        <div class="p-3.5 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-sm text-gray-900">{{ $cause['title'] }}</span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700">
                                    Kemungkinan {{ $cause['likelihood'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">{{ $cause['explanation'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($diagnosis->preliminary_advice)
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200 text-xs text-gray-800">
                    <strong>Saran Sebelum Teknisi Datang:</strong> {{ $diagnosis->preliminary_advice }}
                </div>
            @endif

            <div class="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                <div class="text-xs text-gray-600">
                    Rekomendasi Layanan: <strong class="uppercase text-gray-900">{{ $diagnosis->recommendedServiceName() }}</strong>
                </div>
                <button type="button" wire:click="proceedToSearchTechnician"
                    style="background-color: #18181b; color: #ffffff;"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90">
                    Cari Teknisi dengan Hasil Ini &rarr;
                </button>
            </div>
        </div>
    @endif
</div>  --}}


<div class="max-w-3xl mx-auto py-8 px-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">Cek AC Pintar (AI)</h1>
        <p class="text-sm text-ink-soft mt-1">
            Unggah foto stiker spesifikasi atau kondisi AC beserta keluhanmu untuk mendapatkan perkiraan awal sebelum teknisi datang.
        </p>
    </div>

    <div class="bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
        @if($units->isNotEmpty())
            <div>
                <label for="customerUnitId" class="block text-sm font-semibold text-ink mb-1.5">
                    Pilih Unit AC Tersimpan (Opsional)
                </label>
                <select id="customerUnitId" wire:model="customerUnitId"
                    class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-ink focus:border-ink focus:outline-none">
                    <option value="">-- Pilih unit AC atau isi manual --</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->name }} {{ $unit->room_label ? "({$unit->room_label})" : '' }} - {{ $unit->brand }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="complaintText" class="block text-sm font-semibold text-ink">
                    Keluhan AC <span class="text-red-500">*</span>
                </label>
                <span class="text-xs text-ink-soft">Min. 10 karakter</span>
            </div>
            <textarea id="complaintText" wire:model="complaintText" rows="4"
                placeholder="Contoh: AC menyala tapi anginnya tidak dingin, kadang menetes air dari unit indoor..."
                class="w-full rounded-xl border border-line bg-paper/40 p-3.5 text-sm text-ink placeholder:text-ink-soft/60 focus:border-ink focus:bg-white focus:outline-none transition"></textarea>
            @error('complaintText') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl p-4 bg-paper/40 flex flex-col justify-between" style="border: 1.5px dashed #cbd5e1;">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-semibold text-ink">Foto Stiker Spesifikasi</span>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-white border border-line text-ink-soft">Maks. 5 MB</span>
                    </div>
                    <p class="text-xs text-ink-soft mb-4">
                        Format: JPG, PNG, WEBP. AI akan membaca merek, PK, dan tipe freon.
                    </p>
                </div>

                <div>
                    @if($stickerPhoto && !$errors->has('stickerPhoto'))
                        <div class="relative rounded-xl overflow-hidden border border-line bg-white">
                            <img src="{{ $stickerPhoto->temporaryUrl() }}" alt="Preview Stiker" class="w-full h-36 object-cover">
                            <button type="button" wire:click="removeStickerPhoto"
                                class="absolute top-2 right-2 inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg shadow bg-red-600 text-white hover:bg-red-700 transition">
                                <flux:icon.trash class="size-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
                    @else
                        <input id="stickerPhotoInput" type="file" wire:model="stickerPhoto"
                            accept="image/jpeg,image/png,image/webp" class="sr-only">
                        <label for="stickerPhotoInput"
                            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-ink shadow-2xs hover:bg-paper transition">
                            <flux:icon.arrow-up-tray class="size-4 text-ink-soft" />
                            <span>Pilih Foto Stiker</span>
                        </label>
                    @endif

                    <div wire:loading wire:target="stickerPhoto" class="text-xs text-ink-soft mt-2">
                        Mengunggah foto stiker...
                    </div>
                    @error('stickerPhoto') <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl p-4 bg-paper/40 flex flex-col justify-between" style="border: 1.5px dashed #cbd5e1;">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-semibold text-ink">Foto Kondisi AC</span>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-white border border-line text-ink-soft">Maks. 5 MB</span>
                    </div>
                    <p class="text-xs text-ink-soft mb-4">
                        Format: JPG, PNG, WEBP. Misal: bunga es, lampu berkedip, atau bocor air.
                    </p>
                </div>

                <div>
                    @if($conditionPhoto && !$errors->has('conditionPhoto'))
                        <div class="relative rounded-xl overflow-hidden border border-line bg-white">
                            <img src="{{ $conditionPhoto->temporaryUrl() }}" alt="Preview Kondisi" class="w-full h-36 object-cover">
                            <button type="button" wire:click="removeConditionPhoto"
                                class="absolute top-2 right-2 inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg shadow bg-red-600 text-white hover:bg-red-700 transition">
                                <flux:icon.trash class="size-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
                    @else
                        <input id="conditionPhotoInput" type="file" wire:model="conditionPhoto"
                            accept="image/jpeg,image/png,image/webp" class="sr-only">
                        <label for="conditionPhotoInput"
                            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-ink shadow-2xs hover:bg-paper transition">
                            <flux:icon.camera class="size-4 text-ink-soft" />
                            <span>Pilih Foto Kondisi</span>
                        </label>
                    @endif

                    <div wire:loading wire:target="conditionPhoto" class="text-xs text-ink-soft mt-2">
                        Mengunggah foto kondisi...
                    </div>
                    @error('conditionPhoto') <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="pt-3 border-t border-line flex flex-wrap items-center justify-between gap-3">
            <x-button type="button" variant="dark" wire:click="analyze" wire:loading.attr="disabled">
                <flux:icon.sparkles class="size-4 mr-1.5" />
                <span wire:loading.remove wire:target="analyze">Analisis dengan AI</span>
                <span wire:loading wire:target="analyze">Sedang menganalisis AC...</span>
            </x-button>

            <button type="button" wire:click="proceedToSearchTechnician"
                class="text-sm font-medium text-ink-soft hover:text-ink underline">
                Lewati & Pesan Teknisi Biasa
            </button>
        </div>

        @if($errorMessage)
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">
                {{ $errorMessage }}
            </div>
        @endif
    </div>

    @if($diagnosis)
        <div class="mt-6 bg-white rounded-2xl border border-line p-6 shadow-sm space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-4">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        Perkiraan Awal AI • Bukan Pengganti Pemeriksaan Teknisi
                    </span>
                    <h2 class="text-lg font-bold text-ink mt-2">Hasil Analisis Awal</h2>
                </div>
                <div class="text-right text-xs text-ink-soft">
                    <div>Keyakinan AI: <strong class="uppercase text-ink">{{ $diagnosis->confidence_level }} ({{ $diagnosis->confidence_score }}%)</strong></div>
                    <div>Urgensi: <strong class="uppercase text-red-600">{{ $diagnosis->urgency_level }}</strong></div>
                </div>
            </div>

            @if(!empty($diagnosis->detected_unit_info))
                <div class="bg-paper/60 rounded-xl border border-line p-3.5 text-xs text-ink-soft flex flex-wrap gap-4">
                    <span><strong class="text-ink">Merek:</strong> {{ $diagnosis->detected_unit_info['brand'] ?? '-' }}</span>
                    <span><strong class="text-ink">Kapasitas:</strong> {{ $diagnosis->detected_unit_info['capacity_pk'] ?? '-' }}</span>
                    <span><strong class="text-ink">Freon:</strong> {{ $diagnosis->detected_unit_info['refrigerant'] ?? '-' }}</span>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-semibold text-ink mb-2.5">Kemungkinan Penyebab</h3>
                <div class="space-y-2.5">
                    @foreach($diagnosis->possible_causes as $cause)
                        <div class="p-3.5 rounded-xl border border-line">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-sm text-ink">{{ $cause['title'] }}</span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full bg-paper text-ink-soft border border-line">
                                    Kemungkinan {{ $cause['likelihood'] }}
                                </span>
                            </div>
                            <p class="text-xs text-ink-soft mt-1">{{ $cause['explanation'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($diagnosis->preliminary_advice)
                <div class="p-3.5 rounded-xl bg-paper/60 border border-line text-xs text-ink">
                    <strong>Saran Sebelum Teknisi Datang:</strong> {{ $diagnosis->preliminary_advice }}
                </div>
            @endif

            <div class="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-line">
                <div class="text-xs text-ink-soft">
                    Rekomendasi Layanan: <strong class="uppercase text-ink">{{ $diagnosis->recommendedServiceName() }}</strong>
                </div>
                <x-button type="button" variant="dark" wire:click="proceedToSearchTechnician">
                    Cari Teknisi dengan Hasil Ini &rarr;
                </x-button>
            </div>
        </div>
    @endif
</div>