{{--
    Zengin metin editörü (Faz F1) — bölüm ve makale içeriği. Alpine 'richEditor' (app.js)
    Tiptap'i bu sayfada yükler; HTML gizli input'a yazılır, form normal POST ile gider.
    Editör yüklenene kadar mevcut içerik aynı tipografiyle gösterilir (sayfa zıplamasın).

    import-url verilirse "Word'den Aktar" düğmesi çıkar (panel.yayinlarim.word-aktar);
    title-input o formdaki başlık alanının id'si — boşsa Word'deki başlıkla doldurulur.

    documents-url verilirse (Faz F2) kar tanesi "Belge" düğmesi çıkar: documents listesinden
    (kitabın/makalenin belgeleri) seçilen belge imlecin olduğu yere eklenir. Belge yükleme
    ayrı sayfada (Belgeler), documents-url oraya gider.
--}}
@props(['name', 'value' => '', 'label' => 'İçerik', 'importUrl' => null, 'titleInput' => null, 'id' => null, 'documents' => null, 'documentsUrl' => null])

@php
    $id ??= $name;
    $documentOptions = collect($documents)->map(fn ($document) => ['id' => $document->id, 'caption' => $document->caption()])->values();

    // [etiket, ikon, komut, argüman, aktif durum adı, aktif durum özellikleri]
    $groups = [
        [
            ['Kalın (Ctrl+B)', 'bold', 'toggleBold', null, 'bold', []],
            ['Eğik (Ctrl+I)', 'italic', 'toggleItalic', null, 'italic', []],
            ['Altı çizili (Ctrl+U)', 'underline', 'toggleUnderline', null, 'underline', []],
            ['Üstü çizili', 'strikethrough', 'toggleStrike', null, 'strike', []],
        ],
        [
            ['Başlık', 'h2', 'toggleHeading', ['level' => 2], 'heading', ['level' => 2]],
            ['Alt başlık', 'h3', 'toggleHeading', ['level' => 3], 'heading', ['level' => 3]],
            ['Alıntı', 'chat-bubble-bottom-center-text', 'toggleBlockquote', null, 'blockquote', []],
            ['Madde işaretli liste', 'list-bullet', 'toggleBulletList', null, 'bulletList', []],
            ['Numaralı liste', 'numbered-list', 'toggleOrderedList', null, 'orderedList', []],
        ],
    ];
@endphp

<div x-data="richEditor(@js(['importUrl' => $importUrl, 'titleInput' => $titleInput, 'documents' => $documentOptions]))" {{ $attributes }}>
    {{-- Etiket + "Word'den Aktar" aynı satırda — araç çubuğunda yer kaplayıp onu ikinci satıra itmesin. --}}
    <div class="flex items-end justify-between gap-3 mb-1">
        {{-- Editör alanının kendi aria-label'ı var (rich-editor.js); bu görsel etiket. --}}
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($importUrl)
            <label class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-600 cursor-pointer" :class="(importing || !ready) && 'opacity-50 pointer-events-none'" title="Word (.docx) dosyasından aktar">
                <x-heroicon-o-document-arrow-up class="w-4 h-4" />
                <span x-text="importing ? 'Aktarılıyor…' : 'Word\'den Aktar'">Word'den Aktar</span>
                <input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="sr-only" @change="importWord($event)" :disabled="!ready || importing">
            </label>
        @endif
    </div>

    <div class="rounded-md border border-slate-300 bg-white focus-within:border-slate-500 focus-within:ring-1 focus-within:ring-slate-500">
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" x-ref="input" value="{{ $value }}">

    {{-- Araç çubuğu: uzun bölümlerde yazarken görünür kalsın diye header'ın altına yapışık. --}}
    {{-- mousedown.prevent: düğmeye tıklamak odağı/seçimi editörden almasın (imleç yerinde kalsın). --}}
    <div class="sticky top-16 z-10 flex flex-wrap items-center gap-0.5 rounded-t-md border-b border-slate-200 bg-white/95 backdrop-blur px-1.5 py-1" role="toolbar" aria-label="Biçimlendirme" @mousedown="$event.target.closest('button') && $event.preventDefault()">
        @foreach ($groups as $group)
            @foreach ($group as [$label, $icon, $command, $argument, $activeName, $activeAttributes])
                <button
                    type="button"
                    class="rich-editor-btn"
                    title="{{ $label }}"
                    aria-label="{{ $label }}"
                    :class="isActive(@js($activeName), @js((object) $activeAttributes)) && 'is-active'"
                    :aria-pressed="isActive(@js($activeName), @js((object) $activeAttributes))"
                    :disabled="!ready"
                    @click="run(@js($command){{ $argument ? ', '.Illuminate\Support\Js::from($argument) : '' }})"
                >
                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-5 h-5" />
                </button>
            @endforeach
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
        @endforeach

        <button type="button" class="rich-editor-btn" title="Bağlantı" aria-label="Bağlantı" :class="isActive('link') && 'is-active'" :disabled="!ready" @click="openPanel('link')">
            <x-heroicon-o-link class="w-5 h-5" />
        </button>
        <button type="button" class="rich-editor-btn gap-1 text-sm font-medium" title="Dipnot ekle (seçili dipnotu düzenler)" aria-label="Dipnot" :class="isActive('footnote') && 'is-active'" :disabled="!ready" @click="openPanel('footnote')">
            <x-heroicon-o-hashtag class="w-4 h-4" /> Dipnot
        </button>
        <button type="button" class="rich-editor-btn" title="Video ekle (YouTube / Vimeo; seçili videoyu düzenler)" aria-label="Video" :class="(panel === 'video' || isActive('videoLink')) && 'is-active'" :disabled="!ready" @click="panel === 'video' ? closePanel() : openPanel('video')">
            <x-heroicon-o-play-circle class="w-5 h-5" />
        </button>
        @if ($documentsUrl)
            <button type="button" class="rich-editor-btn" title="Belge ekle (kar tanesi)" aria-label="Belge ekle" :class="panel === 'document' && 'is-active'" :disabled="!ready" @click="panel === 'document' ? closePanel() : openPanel('document')">
                <x-snowflake-icon class="w-5 h-5" />
            </button>
        @endif

        <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

        <button type="button" class="rich-editor-btn" title="Geri al (Ctrl+Z)" aria-label="Geri al" :disabled="!ready || !can('undo')" @click="run('undo')">
            <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
        </button>
        <button type="button" class="rich-editor-btn" title="Yinele (Ctrl+Y)" aria-label="Yinele" :disabled="!ready || !can('redo')" @click="run('redo')">
            <x-heroicon-o-arrow-uturn-right class="w-5 h-5" />
        </button>

    </div>

    {{-- Belge seçme paneli (Faz F2) --}}
    @if ($documentsUrl)
        {{-- mousedown.prevent: seçimden sonra odak editörde kalsın — kalmazsa hemen ardından basılan
             Enter gizlenen düğmeyi yeniden tetikleyip belgeyi iki kez ekliyordu. --}}
        <div x-show="panel === 'document'" x-cloak class="border-b border-slate-200 bg-slate-50 px-3 py-3" @keydown.escape.prevent="closePanel()" @mousedown="$event.target.closest('button') && $event.preventDefault()">
            <template x-if="documents.length">
                <div>
                    <div class="text-xs font-medium text-slate-600 mb-2">Eklenecek belgeyi seçin — imlecin olduğu yere kar tanesi olarak eklenir</div>
                    <div class="grid gap-1 max-h-56 overflow-y-auto">
                        <template x-for="doc in documents" :key="doc.id">
                            <button type="button" class="flex items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-slate-700 hover:bg-white hover:shadow-sm" @click="insertDocument(doc.id)">
                                <x-snowflake-icon class="w-4 h-4 shrink-0 text-navy" />
                                <span class="min-w-0 break-words" x-text="doc.caption"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
            <p x-show="!documents.length" class="text-sm text-slate-600">Henüz belge yüklenmedi.</p>
            <div class="flex flex-wrap items-center gap-3 mt-2">
                {{-- Yeni sekmede: editördeki kaydedilmemiş metin kaybolmasın. --}}
                <a href="{{ $documentsUrl }}" target="_blank" class="text-sm font-medium text-brand-700 hover:text-brand-600">Belge yükle / yönet ↗</a>
                <span class="text-xs text-slate-400">Yeni yüklenen belge için bu sayfayı kaydedip yeniden açın.</span>
                <button type="button" class="btn-ghost btn-sm ml-auto" @click="closePanel()">Kapat</button>
            </div>
        </div>
    @endif

    {{-- Video paneli (Faz F3) --}}
    <div x-show="panel === 'video'" x-cloak class="border-b border-slate-200 bg-slate-50 px-3 py-3 space-y-2" @keydown.escape.prevent="closePanel()">
        <div class="text-xs font-medium text-slate-600" x-text="editingVideo ? 'Videoyu düzenle' : 'Video ekle — imlecin olduğu yere ayrı satır olarak eklenir'"></div>
        <input type="url" inputmode="url" x-model="video.url" @keydown.enter.prevent="savePanel()" placeholder="YouTube ya da Vimeo bağlantısı" aria-label="Video bağlantısı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        <div class="grid grid-cols-[1fr_7rem] gap-2">
            <input type="text" x-model="video.title" @keydown.enter.prevent="savePanel()" placeholder="Başlık (ör. Osmanlı Diplomasisinde Yazışma Usulü)" aria-label="Video başlığı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            <input type="text" inputmode="numeric" x-model="video.duration" @keydown.enter.prevent="savePanel()" placeholder="Süre 12:45" aria-label="Video süresi" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <p x-show="panelError" x-text="panelError" class="text-sm text-red-600" role="alert"></p>
        <p class="text-xs text-slate-400">Liste dışı (unlisted) videolar da olur. Okur videoyu sayfadan ayrılmadan izler.</p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-dark btn-sm" @click="savePanel()" x-text="editingVideo ? 'Güncelle' : 'Ekle'"></button>
            <button type="button" class="btn-ghost btn-sm" @click="closePanel()">Vazgeç</button>
            <button type="button" x-show="editingVideo" class="btn-sm btn text-red-600 hover:bg-red-50 ml-auto" @click="removeSelected()">Videoyu Sil</button>
        </div>
    </div>

    {{-- Bağlantı / dipnot paneli --}}
    <div x-show="panel === 'link' || panel === 'footnote'" x-cloak class="border-b border-slate-200 bg-slate-50 px-3 py-3" @keydown.escape.prevent="closePanel()">
        <label :for="'{{ $id }}-panel'" class="block text-xs font-medium text-slate-600 mb-1" x-text="panel === 'link' ? 'Bağlantı adresi (boş bırakırsanız bağlantı kaldırılır)' : (editingFootnote ? 'Dipnotu düzenle' : 'Dipnot metni — imlecin olduğu yere eklenir')"></label>
        <template x-if="panel === 'link'">
            <input :id="'{{ $id }}-panel'" x-ref="panelInput" type="url" inputmode="url" x-model="panelText" @keydown.enter.prevent="savePanel()" placeholder="https://" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        </template>
        <template x-if="panel === 'footnote'">
            <textarea :id="'{{ $id }}-panel'" x-ref="panelInput" rows="2" x-model="panelText" @keydown.enter.ctrl.prevent="savePanel()" placeholder="Ör. Kaynak: Evliya Çelebi, Seyahatname, c. 1, s. 42." class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
        </template>
        <div class="flex flex-wrap items-center gap-2 mt-2">
            <button type="button" class="btn-dark btn-sm" @click="savePanel()">Kaydet</button>
            <button type="button" class="btn-ghost btn-sm" @click="closePanel()">Vazgeç</button>
            <button type="button" x-show="panel === 'footnote' && editingFootnote" class="btn-sm btn text-red-600 hover:bg-red-50 ml-auto" @click="removeSelected()">Dipnotu Sil</button>
        </div>
    </div>

    <p x-show="importError" x-cloak x-text="importError" class="border-b border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <p x-show="importNotice" x-cloak x-text="importNotice" class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status"></p>

    {{-- Tiptap yüklenince içini temizleyip kendi (aynı sınıflı) alanını takıyor. --}}
    <div x-ref="surface"><div class="rich-content rich-editor-surface">{!! \App\Support\RichText::normalize($value) !!}</div></div>
    </div>
</div>
