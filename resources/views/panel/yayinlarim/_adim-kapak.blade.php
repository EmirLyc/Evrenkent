{{-- Adım 3 — Kapak ve Tanıtım: kapak görseli, tanıtım metni, (kitapta) içerik istatistikleri. --}}
@php $input = 'w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500'; @endphp

<form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.kapak', $work) : route('panel.yayinlarim.makale.kapak', $work) }}" enctype="multipart/form-data" class="card p-4 sm:p-6 max-w-4xl"
      x-data="{ preview: null }">
    <div class="grid grid-cols-1 md:grid-cols-[13rem_1fr] gap-6">
        <div>
            <div class="text-sm font-medium text-slate-700 mb-2">Kapak</div>
            <label class="group relative block aspect-[3/4] w-full max-w-[13rem] cursor-pointer overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                <div x-show="!preview" class="h-full w-full">
                    @if ($work->cover_image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.covers_disk'))->url($work->cover_image) }}" alt="{{ $work->title }} kapağı" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center gap-2 px-4 text-center text-sm text-slate-500">
                            <x-heroicon-o-photo class="w-8 h-8 text-slate-400" /> Kapak görseli seçin
                        </div>
                    @endif
                </div>
                <span class="absolute inset-x-0 bottom-0 bg-slate-900/70 py-1.5 text-center text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity">Değiştir</span>
                <input type="file" name="cover_image" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            </label>
            <p class="mt-2 text-xs text-slate-500">JPG, PNG ya da WEBP, en fazla 5 MB. Dikey (3:4) görsel önerilir.</p>
            @error('cover_image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-5 min-w-0">
            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Tanıtım metni</label>
                <textarea id="description" name="description" rows="9" maxlength="5000" class="{{ $input }}" placeholder="Okurun tanıtım sayfasında göreceği kısa anlatım.">{{ old('description', $work->description) }}</textarea>
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($isBook)
                <div>
                    <div class="text-sm font-medium text-slate-700 mb-1">İçerik istatistikleri</div>
                    <p class="text-xs text-slate-500 mb-3">Sayfa, belge, video ve kaynak sayısı metinden otomatik hesaplanır; tanıtım sayfasında yalnızca dolu olanlar gösterilir.</p>
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3 text-sm">
                        @foreach (['page_count' => 'Sayfa', 'document_count' => 'Belge', 'video_count' => 'Video', 'source_count' => 'Kaynak'] as $field => $label)
                            <div class="rounded-md bg-slate-50 px-3 py-2"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-semibold text-slate-900 tabular-nums">{{ $work->$field ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="grid grid-cols-2 gap-4 sm:max-w-sm">
                        @foreach (['map_count' => 'Harita', 'author_note_count' => 'Yazar notu'] as $field => $label)
                            <div>
                                <label for="{{ $field }}" class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" value="{{ old($field, $work->$field) }}" class="{{ $input }}">
                                @error($field) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
        <button type="submit" name="devam" value="1" class="btn-dark">Kaydet ve Önizlemeye Geç <x-heroicon-o-arrow-right class="w-4 h-4" /></button>
        <button type="submit" class="btn-outline">Kaydet</button>
        <a href="{{ $stepUrl('icerik') }}" class="text-sm text-slate-600 hover:text-slate-900 sm:ml-auto">&larr; İçerik</a>
    </div>
    @csrf
</form>
