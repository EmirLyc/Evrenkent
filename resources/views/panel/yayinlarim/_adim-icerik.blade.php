{{-- Adım 2 — İçerik: tek belge editörü (kitapta her "Başlık 1" bir bölüm; bkz. BookDocument). --}}
@php $ownerRoute = $isBook ? 'panel.yayinlarim.kitap.' : 'panel.yayinlarim.makale.'; @endphp

@if ($isDictionary)
    <p class="mb-3 text-sm text-slate-500">
        İmleci bir satıra getirip <span class="font-medium text-slate-700">Ekle → Kavram</span>'ı seçin: o satır bir sözlük maddesinin başı olur ve İçindekiler'e eklenir; altına yazdığınız metin bir sonraki kavrama (ya da başlığa) kadar o maddeye aittir.
        <span class="font-medium text-slate-700">Başlık 1</span> sözlüğü bölümlere ayırır (ör. harfler).
    </p>
@elseif ($isBook)
    <p class="mb-3 text-sm text-slate-500">
        Her <span class="font-medium text-slate-700">Başlık 1</span> kitabın bir bölümü olur; Başlık 2–4 bölüm içindeki alt başlıklardır ve içindekileri oluşturur.
        Hazır metniniz varsa "…" menüsünden Word ya da EPUB dosyasından aktarabilirsiniz.
    </p>
@endif

<x-work-editor
    mode="autosave"
    :value="$editorHtml"
    :save-url="route($ownerRoute.'icerik', $work)"
    :import-url="route($ownerRoute.'aktar', $work)"
    :image-url="route($ownerRoute.'gorsel', $work)"
    :document-url="route($ownerRoute.'belgeler.store', $work)"
    :documents-page-url="route($ownerRoute.'belgeler', $work)"
    :documents="$work->documents->where('kind', \App\Models\Document::KIND_BELGE)"
    :images="$work->documents->where('kind', \App\Models\Document::KIND_GORSEL)"
    :ratio="$work->page_ratio"
    :numbering="$work->heading_numbering"
    :is-book="$isBook"
    :dictionary="$isDictionary"
    :concept-search-url="route('panel.sozluk-maddeleri')"
    :saved-label="$work->updated_at->translatedFormat('j F Y, H:i')"
    :heading="$work->title"
    :subheading="$work->subtitle"
/>

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ $stepUrl('bilgiler') }}" class="text-sm text-slate-600 hover:text-slate-900">&larr; Temel Bilgiler</a>
    <a href="{{ $stepUrl('kapak') }}" class="btn-dark">Kapak ve Tanıtım <x-heroicon-o-arrow-right class="w-4 h-4" /></a>
</div>

<x-document-viewer />
