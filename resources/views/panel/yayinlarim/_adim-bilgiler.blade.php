{{-- Adım 1 — Temel Bilgiler. Yeni eserde tür seçimi ve isteğe bağlı Word / EPUB dosyası da burada
     (belge: "eser ya hazır bir docx'ten sisteme dahil edilir ya da Evrenkent içinde yazılır"). --}}
@php
    $input = 'w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500';
    $ratios = \App\Http\Controllers\WorkController::RATIOS;
    $defaultRatio = $isBook ? '13x20' : '21x27.5';
@endphp

<form method="POST"
      action="{{ $work ? ($isBook ? route('panel.yayinlarim.kitap.guncelle', $work) : route('panel.yayinlarim.makale.guncelle', $work)) : route('panel.yayinlarim.taslaklarim.store') }}"
      enctype="multipart/form-data"
      x-data="{ type: @js(old('type', $isDictionary ? 'sozluk' : $type)) }"
      class="card p-4 sm:p-6 space-y-6 max-w-3xl">
    @if ($work)
        @method('PUT')
    @elseif ($isDictionary)
        {{-- Pop-up'ta "Sözlük" seçildi (Faz G3): tür sabit. --}}
        <input type="hidden" name="type" value="sozluk">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-900">
            <span class="font-serif text-lg font-semibold">Sözlük</span>
            <p class="mt-1">Editörde imleci bir satıra getirip <span class="font-medium">Ekle → Kavram</span>'ı seçtiğinizde o satır bir sözlük maddesinin başı olur, İçindekiler'e eklenir; sonraki metin bir sonraki kavrama kadar o maddeye ait sayılır. Diğer yazarlar metinlerindeki kelimeleri bu maddelere bağlayabilir.</p>
        </div>
        @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    @else
        <fieldset>
            <legend class="block text-sm font-medium text-slate-700 mb-2">Ne yayınlıyorsunuz?</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach (['kitap' => ['Kitap', 'Kitap, inceleme, araştırma — bölüm bölüm.'], 'makale' => ['Dergi Yazısı', 'Atandığınız bir derginin sayısı için yazı.']] as $value => [$label, $hint])
                    <label class="flex cursor-pointer gap-3 rounded-lg border p-3.5" :class="type === '{{ $value }}' ? 'border-brand-400 bg-brand-50/60' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="type" value="{{ $value }}" x-model="type" class="mt-1 text-slate-900 focus:ring-slate-500">
                        <span><span class="block font-serif text-lg font-semibold text-slate-900">{{ $label }}</span><span class="block text-sm text-slate-500">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </fieldset>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $work?->title) }}" required maxlength="255" class="{{ $input }} font-reading text-lg" placeholder="{{ $isDictionary ? 'Ör. Siyaset Bilimi Sözlüğü' : 'Ör. Modern Devletin Dönüşümü' }}">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="subtitle" class="block text-sm font-medium text-slate-700 mb-1">Alt başlık <span class="font-normal text-slate-400">(isteğe bağlı)</span></label>
            <input id="subtitle" name="subtitle" type="text" value="{{ old('subtitle', $work?->subtitle) }}" maxlength="255" class="{{ $input }} font-reading" placeholder="Ör. Egemenlik, Sınırlar ve Yeni İmkanlar">
            @error('subtitle') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Dergi yazısı: gönderileceği sayı --}}
    <div x-show="type === 'makale'" x-cloak>
        <label for="magazine_issue_id" class="block text-sm font-medium text-slate-700 mb-1">Dergi sayısı</label>
        @if ($magazineIssues->isEmpty())
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                Yazı gönderebileceğiniz açık bir dergi sayısı yok. Yazılar yalnızca Süper Admin'in sizi yazar ya da editör olarak atadığı dergilere gönderilebilir; atandığınız dergide yeni bir sayı açılınca burada görünür.
            </p>
        @else
            <select id="magazine_issue_id" name="magazine_issue_id" class="{{ $input }}" :disabled="type !== 'makale'">
                <option value="">— Seçiniz —</option>
                @foreach ($magazineIssues->groupBy(fn ($issue) => $issue->magazine?->name ?? 'Diğer') as $magazineName => $issuesOfMagazine)
                    <optgroup label="{{ $magazineName }}">
                        @foreach ($issuesOfMagazine as $issue)
                            <option value="{{ $issue->id }}" @selected(old('magazine_issue_id', $work?->magazine_issue_id) == $issue->id)>{{ $issue->title }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Yalnızca yazar ya da editör olarak atandığınız dergilerin açık sayıları.</p>
        @endif
        @error('magazine_issue_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Sayfa oranı: belge — "yazar kitabı hazırlarken bir oran seçebilmeli; oran tüm sayfalar için". --}}
    <fieldset x-data="{ ratio: @js(old('page_ratio', $work?->page_ratio ?? $defaultRatio)) }" x-effect="if (! {{ $work ? 'true' : 'false' }}) ratio = type === 'makale' ? '21x27.5' : (ratio === '21x27.5' ? '13x20' : ratio)">
        <legend class="block text-sm font-medium text-slate-700 mb-1">Sayfa oranı</legend>
        <p class="text-xs text-slate-500 mb-2">Eser ekrandan okunacağı için ölçü değil oran önemli. Seçtiğiniz oran eserin bütün sayfaları için geçerli; okur punto değiştiremez, sayfayı büyütebilir.</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach ($ratios as $key => [$label, $hint])
                @php [$w, $h] = array_map('floatval', explode('x', $key)); @endphp
                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 text-center" :class="ratio === '{{ $key }}' ? 'border-brand-400 bg-brand-50/60' : 'border-slate-200 hover:bg-slate-50'">
                    <input type="radio" name="page_ratio" value="{{ $key }}" x-model="ratio" class="sr-only">
                    <span class="block rounded-sm border border-slate-400 bg-white" style="width: {{ round($w * 2.4) }}px; height: {{ round($h * 2.4) }}px" aria-hidden="true"></span>
                    <span class="text-sm font-medium text-slate-900">{{ $label }}</span>
                    <span class="text-xs text-slate-500 leading-tight">{{ $hint }}</span>
                </label>
            @endforeach
        </div>
        @error('page_ratio') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </fieldset>

    <label class="flex items-start gap-3 text-sm text-slate-700">
        <input type="hidden" name="heading_numbering" value="0">
        <input type="checkbox" name="heading_numbering" value="1" @checked(old('heading_numbering', $work?->heading_numbering ?? ! $isDictionary)) class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
        <span><span class="font-medium">Başlıkları otomatik numarala</span> <span class="text-slate-500">— Başlık 1 "I.", altları "1.1.", "1.1.1." (roman gibi eserlerde kapatabilirsiniz)</span></span>
    </label>

    <div class="border-t border-slate-100 pt-5">
        <div class="text-sm font-medium text-slate-700 mb-2">Kategoriler</div>
        <div class="flex flex-wrap gap-x-4 gap-y-2">
            @foreach ($categories as $category)
                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(collect(old('categories', $work?->categories->pluck('id') ?? []))->contains($category->id)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    {{ $category->name }}
                </label>
            @endforeach
        </div>
        @error('categories') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Fiyat yazardan alınmıyor (2026-09-27 revizesi). --}}
    <p x-show="type !== 'makale'" class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
        {{ $isDictionary ? 'Sözlüğün' : 'Kitabın' }} satış fiyatı, onay aşamasında Süper Admin tarafından belirlenir.
    </p>

    {{-- Kitap: yazarın önerdiği yayın tarihi (Süper Admin onaylarken görür) --}}
    <div x-show="type !== 'makale'" x-cloak class="border-t border-slate-100 pt-5">
        <label for="scheduled_publish_at" class="block text-sm font-medium text-slate-700 mb-1">Önerilen yayın tarihi <span class="font-normal text-slate-400">(isteğe bağlı)</span></label>
        <input id="scheduled_publish_at" name="scheduled_publish_at" type="datetime-local" :disabled="type === 'makale'" value="{{ old('scheduled_publish_at', $work instanceof \App\Models\Book ? $work->scheduled_publish_at?->format('Y-m-d\TH:i') : null) }}" class="{{ $input }} sm:max-w-xs">
        <p class="text-xs text-slate-400 mt-1">Kesin tarihi Süper Admin onay sırasında belirler.</p>
        @error('scheduled_publish_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    @unless ($work)
        <div class="border-t border-slate-100 pt-5">
            <label for="file" class="block text-sm font-medium text-slate-700 mb-1">Hazır bir dosyanız var mı? <span class="font-normal text-slate-400">(isteğe bağlı)</span></label>
            <p class="text-xs text-slate-500 mb-2">Word (.docx) ya da EPUB — metin başlıklarıyla birlikte aktarılır: Word'deki "Başlık 1–4" editörde de Başlık 1–4 olur{{ $isBook ? ', her Başlık 1 bir bölüm' : '' }}. Görseller, tablolar ve dipnotlar da taşınır. Dosya yoksa metni bir sonraki adımda yazabilirsiniz.</p>
            <input id="file" name="file" type="file" accept=".docx,.epub" class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm file:text-slate-700 hover:file:bg-slate-50">
            @error('file') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    @endunless

    <div class="flex flex-wrap items-center gap-3 pt-1">
        @if ($work)
            <button type="submit" name="devam" value="1" class="btn-dark">Kaydet ve İçeriğe Geç <x-heroicon-o-arrow-right class="w-4 h-4" /></button>
            <button type="submit" class="btn-outline">Kaydet</button>
        @else
            <button type="submit" class="btn-dark">Oluştur ve Yazmaya Başla <x-heroicon-o-arrow-right class="w-4 h-4" /></button>
            <a href="{{ route('panel.yayinlarim.taslaklarim') }}" class="text-sm text-slate-500 hover:text-slate-900">Vazgeç</a>
        @endif
    </div>
    @csrf
</form>
