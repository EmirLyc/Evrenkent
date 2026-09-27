{{--
    Gömülü belge ve video görüntüleyici (Faz F2/F3, mockup 3 / 3.1: "tıklayınca belge açılır"). Sayfada bir
    kere yer alır; data-document-viewer taşıyan bağlantılara tıklanınca açılır (app.js
    'documentViewer'). Görsel <img> ile, PDF pdf.js ile tuvale çizilerek, video YouTube/Vimeo
    oynatıcısıyla gösterilir —
    indirme düğmesi yok, sağ tık menüsü kapalı.
--}}
<div
    x-data="documentViewer"
    @click.window="handle($event)"
    @keydown.escape.window="open && $root.isConnected && close()"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-stretch sm:items-center justify-center bg-slate-900/80 sm:p-6"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.self="close()"
        @contextmenu.prevent
    >
        <div class="flex w-full max-w-4xl flex-col bg-paper sm:rounded-lg shadow-xl overflow-hidden sm:max-h-full">
            <div class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <x-snowflake-icon x-show="type !== 'video'" class="w-5 h-5 shrink-0 text-navy" />
                <x-heroicon-o-play-circle x-show="type === 'video'" class="w-5 h-5 shrink-0 text-brand-600" />
                <div class="min-w-0 flex-1 text-sm font-medium text-slate-900 truncate" x-text="title"></div>
                <button type="button" x-ref="close" class="rich-editor-btn" aria-label="Kapat" @click="close()">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain p-3 sm:p-5 select-none">
                {{-- Video (Faz F3): x-if — kapatınca iframe DOM'dan kalkıp oynatma duruyor. --}}
                <template x-if="type === 'video' && url">
                    <div class="mx-auto max-w-3xl aspect-video overflow-hidden rounded-md bg-black shadow-sm">
                        <iframe :src="url" :title="title" class="h-full w-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                </template>

                <template x-if="type === 'image' && url">
                    <img :src="url" :alt="title" draggable="false" class="mx-auto max-w-full h-auto rounded-sm bg-white shadow-sm">
                </template>

                <div x-show="type === 'pdf'" x-ref="pages" class="mx-auto max-w-3xl space-y-3"></div>

                <p x-show="loading" class="py-16 text-center text-sm text-slate-500">Belge yükleniyor…</p>
                <p x-show="progress" x-text="progress" class="pt-3 text-center text-xs text-slate-400"></p>
                <p x-show="error" x-text="error" class="py-16 text-center text-sm text-red-600" role="alert"></p>
            </div>
        </div>
    </div>
</div>
