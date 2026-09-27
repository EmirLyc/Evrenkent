{{--
    Zengin metin editörü (Faz F1) — bölüm ve makale içeriği. Alpine 'richEditor' (app.js)
    Tiptap'i bu sayfada yükler; HTML gizli input'a yazılır, form normal POST ile gider.
    Editör yüklenene kadar mevcut içerik aynı tipografiyle gösterilir (sayfa zıplamasın).

    import-url verilirse "Word'den Aktar" düğmesi çıkar (panel.yayinlarim.word-aktar);
    title-input o formdaki başlık alanının id'si — boşsa Word'deki başlıkla doldurulur.
--}}
@props(['name', 'value' => '', 'label' => 'İçerik', 'importUrl' => null, 'titleInput' => null, 'id' => null])

@php
    $id ??= $name;

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

<div x-data="richEditor(@js(['importUrl' => $importUrl, 'titleInput' => $titleInput]))" {{ $attributes }}>
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

        <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

        <button type="button" class="rich-editor-btn" title="Geri al (Ctrl+Z)" aria-label="Geri al" :disabled="!ready || !can('undo')" @click="run('undo')">
            <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
        </button>
        <button type="button" class="rich-editor-btn" title="Yinele (Ctrl+Y)" aria-label="Yinele" :disabled="!ready || !can('redo')" @click="run('redo')">
            <x-heroicon-o-arrow-uturn-right class="w-5 h-5" />
        </button>

    </div>

    {{-- Bağlantı / dipnot paneli --}}
    <div x-show="panel" x-cloak class="border-b border-slate-200 bg-slate-50 px-3 py-3" @keydown.escape.prevent="closePanel()">
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
            <button type="button" x-show="panel === 'footnote' && editingFootnote" class="btn-sm btn text-red-600 hover:bg-red-50 ml-auto" @click="removeFootnote()">Dipnotu Sil</button>
        </div>
    </div>

    <p x-show="importError" x-cloak x-text="importError" class="border-b border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
    <p x-show="importNotice" x-cloak x-text="importNotice" class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status"></p>

    {{-- Tiptap yüklenince içini temizleyip kendi (aynı sınıflı) alanını takıyor. --}}
    <div x-ref="surface"><div class="rich-content rich-editor-surface">{!! \App\Support\RichText::normalize($value) !!}</div></div>
    </div>
</div>
