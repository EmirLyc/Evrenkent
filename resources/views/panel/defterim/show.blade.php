@extends('layouts.focus')

{{--
    Defterim (Faz H5, "Okurun Gözünden" — Defterim 1; "Bazı Prensipler": "kitaplardan bağımsız
    olarak ben ne düşünüyorum?"). Sitenin kabuğu yok: solda logo, DEFTERLERİM, + Yeni Defter, arama
    ve tarihe göre gruplu defterler; sağda ad (✎), alt başlık, kayıt durumu, geri al / yinele, "…",
    tema; araç çubuğu, editör ("Başlamaya hazır mısın?" — Alıntı ekle / Not ekle / Bağlantı ekle),
    altta kelime sayısı, etiket, "Deftere bilgi ekle", tam ekran. Dar ekranda liste çekmecede.
--}}

@section('title', $notebook ? $notebook->title.' — Defterim' : 'Defterim')

@php
    $btn = 'nb-btn';
    $config = $notebook ? [
        'id' => $notebook->id,
        'title' => $notebook->title,
        'subtitle' => $notebook->subtitle,
        'content' => $notebook->content,
        'tags' => $notebook->tags ?? [],
        'info' => $notebook->info,
        'savedAt' => $notebook->updated_at->format('H:i'),
        'fresh' => $notebook->title === 'Yeni Defter' && trim(strip_tags((string) $notebook->content)) === '',
        'wordLimit' => $wordLimit,
        'saveUrl' => route('panel.defterim.kaydet', $notebook),
        'imageUrl' => route('panel.defterim.gorsel', $notebook),
        'sourcesUrl' => route('panel.defterim.kaynaklar'),
    ] : null;
    $atQuota = $quota !== null && $count >= $quota;
@endphp

@section('content')
    <div class="notebook-app flex min-h-screen" x-data="{ list: false, q: '', title: @js($notebook?->title), time: null }"
         @notebook-saved.window="title = $event.detail.title; time = $event.detail.time">
        {{-- Defterlerim --}}
        <div x-show="list" x-cloak class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="list = false"></div>
        <aside class="nb-side fixed inset-y-0 left-0 z-40 flex w-[19rem] max-w-[88vw] flex-col border-r transition-transform lg:static lg:z-auto lg:translate-x-0"
               :class="list ? 'translate-x-0' : '{{ $notebook ? '-translate-x-full' : 'translate-x-0 max-lg:static max-lg:w-full max-lg:max-w-none max-lg:border-r-0' }}'"
               data-nb-side>
            <a href="{{ route('panel.index') }}" class="flex items-center gap-3 px-6 pb-5 pt-6" title="Kitaplığıma dön">
                <x-heroicon-o-book-open class="h-9 w-9 shrink-0 text-navy" />
                <span>
                    <span class="block font-serif text-xl font-semibold tracking-wide text-navy">EVRENKENT</span>
                    <span class="nb-muted block text-xs">Okumanın yeni bir evreni var</span>
                </span>
            </a>

            <div class="px-6">
                <p class="text-sm font-semibold tracking-wide text-navy">DEFTERLERİM</p>
                <form method="POST" action="{{ route('panel.defterim.yeni') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-sm font-medium text-white hover:bg-navy/90 disabled:opacity-50" @disabled($atQuota)>
                        <x-heroicon-o-plus class="h-4 w-4" /> Yeni Defter
                    </button>
                </form>
                @if (session('quota') || $atQuota)
                    <p class="mt-2 text-xs leading-snug text-amber-700">
                        {{ session('quota') ?: "Ücretsiz hesapta {$quota} defter tutabilirsiniz." }}
                        <a href="{{ route('abonelik') }}" class="font-medium underline">Premium ile sınırsız</a>
                    </p>
                @endif

                <div class="mt-4 flex items-center gap-2" x-data="{ sortMenu: false }">
                    <label class="relative min-w-0 flex-1">
                        <span class="sr-only">Defterlerde ara</span>
                        <x-heroicon-o-magnifying-glass class="nb-muted pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <input type="search" x-model="q" placeholder="Defterlerde ara…" class="nb-input w-full rounded-lg py-2 pl-9 text-sm">
                    </label>
                    <div class="relative" @click.outside="sortMenu = false">
                        <button type="button" class="nb-btn h-9 w-9 border" @click="sortMenu = !sortMenu" :aria-expanded="sortMenu.toString()" aria-label="Sırala"><x-heroicon-o-bars-3-bottom-left class="h-4 w-4" /></button>
                        <div x-show="sortMenu" x-cloak class="nb-panel absolute right-0 top-full z-10 mt-1 w-52 rounded-lg border p-1 text-sm shadow-lg">
                            @foreach (['son' => 'Son düzenlenen', 'olusturma' => 'Oluşturulma tarihi', 'ad' => 'Ada göre (A–Z)'] as $value => $label)
                                <a href="{{ $notebook ? route('panel.defterim.goster', [$notebook, 'sirala' => $value === 'son' ? null : $value]) : route('panel.defterim', ['sirala' => $value === 'son' ? null : $value]) }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 hover:bg-black/5 {{ $sort === $value ? 'font-semibold text-navy' : '' }}">{{ $label }} @if ($sort === $value)<x-heroicon-o-check class="h-4 w-4" />@endif</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <nav class="mt-4 flex-1 overflow-y-auto px-3 pb-6" aria-label="Defterler">
                @forelse ($groups as $label => $items)
                    @php
                        // Arama (başlık + metnin başı), grup başlığı da eşleşen yoksa gizlenir.
                        $searches = $items->map(fn ($item) => mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $item->title.' '.\Illuminate\Support\Str::limit(\App\Support\NotebookHtml::plainText($item->content), 400, ''))))->values();
                    @endphp
                    <div class="mb-4" x-show="!q || @js($searches).some((text) => text.includes(q.toLocaleLowerCase('tr')))">
                        <p class="px-3 pb-1.5 text-sm font-semibold text-navy">{{ $label }}</p>
                        <ul class="space-y-0.5">
                            @foreach ($items as $item)
                                @php
                                    $active = $notebook && $item->id === $notebook->id;
                                    $stamp = $sort === 'olusturma' ? $item->created_at : $item->updated_at;
                                @endphp
                                <li x-show="!q || @js($searches[$loop->index]).includes(q.toLocaleLowerCase('tr'))">
                                    <a href="{{ route('panel.defterim.goster', array_filter([$item, 'sirala' => $sort !== 'son' ? $sort : null])) }}"
                                       class="flex items-start gap-3 rounded-lg px-3 py-2.5 text-sm {{ $active ? 'nb-active font-medium' : 'hover:bg-black/5' }}"
                                       @if ($active) aria-current="page" @endif>
                                        <x-heroicon-o-document class="mt-0.5 h-5 w-5 shrink-0" />
                                        <span class="min-w-0 flex-1 break-words" @if ($active) x-text="title" @endif>{{ $item->title }}</span>
                                        <span class="nb-muted shrink-0 pt-0.5 text-xs tabular-nums" @if ($active) x-text="time ?? @js($stamp->isToday() ? $stamp->format('H:i') : $stamp->translatedFormat('j M'))" @endif>{{ $stamp->isToday() ? $stamp->format('H:i') : $stamp->translatedFormat('j M') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="nb-muted px-3 py-6 text-center text-sm">Henüz defterin yok.</p>
                @endforelse
            </nav>

            <a href="{{ route('panel.index') }}" class="nb-muted flex items-center gap-2 border-t border-inherit px-6 py-3.5 text-sm hover:text-navy">
                <x-heroicon-o-arrow-left class="h-4 w-4" /> Kitaplığıma dön
            </a>
        </aside>

        {{-- Editör --}}
        @if ($notebook)
            <main class="min-w-0 flex-1 px-3 py-4 sm:px-8 sm:py-6" x-data="notebook(@js($config))" @keydown.escape.window="panel = null; menu = null">
                <div class="mx-auto max-w-5xl">
                    <header class="flex flex-wrap items-start gap-3">
                        <button type="button" class="nb-btn mt-1 h-10 w-10 border lg:hidden" @click="list = true" aria-label="Defterlerim"><x-heroicon-o-bars-3 class="h-5 w-5" /></button>
                        <x-heroicon-o-document class="nb-ink mt-1.5 hidden h-9 w-9 shrink-0 sm:block" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <label for="nb-title" class="sr-only">Defterin adı</label>
                                {{-- Genişlik yazılana göre: kalem simgesi adın hemen yanında (mockup). --}}
                                <input id="nb-title" x-ref="title" x-model="title" @input="scheduleSave()" maxlength="120" :size="Math.max(8, title.length + 1)" class="nb-title min-w-0 max-w-full border-0 bg-transparent p-0 font-serif text-2xl font-semibold focus:ring-0 sm:text-3xl" placeholder="Defterin adı">
                                <button type="button" class="nb-muted rounded p-1 hover:text-navy" @click="$refs.title.focus(); $refs.title.select()" aria-label="Adı düzenle"><x-heroicon-o-pencil class="h-5 w-5" /></button>
                            </div>
                            <label for="nb-subtitle" class="sr-only">Alt başlık</label>
                            <input id="nb-subtitle" x-model="subtitle" @input="scheduleSave()" maxlength="200" class="nb-muted mt-1 w-full border-0 bg-transparent p-0 text-[0.95rem] focus:ring-0" placeholder="Alt başlık eklemek için tıkla…">
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="nb-muted mr-1 inline-flex items-center gap-1.5 whitespace-nowrap text-sm" aria-live="polite">
                                <x-heroicon-o-cloud-arrow-up class="h-5 w-5" x-show="saveState === 'saving' || saveState === 'dirty'" />
                                <x-heroicon-o-cloud class="h-5 w-5" x-show="saveState === 'saved'" />
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600" x-show="saveState === 'error' || saveState === 'limit'" x-cloak />
                                <span x-text="{ saving: 'Kaydediliyor…', dirty: 'Kaydediliyor…', saved: 'Kaydedildi · ' + savedAt, error: 'Kaydedilemedi', limit: 'Kelime sınırı' }[saveState]"></span>
                            </span>
                            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="undo()" :disabled="!can('undo')" aria-label="Geri al"><x-heroicon-o-arrow-uturn-left class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" @mousedown.prevent @click="redo()" :disabled="!can('redo')" aria-label="Yinele"><x-heroicon-o-arrow-uturn-right class="h-5 w-5" /></button>
                            <div class="relative" @click.outside="menu === 'more' && (menu = null)">
                                <button type="button" class="{{ $btn }} border" @click="menu = menu === 'more' ? null : 'more'" :aria-expanded="(menu === 'more').toString()" aria-label="Diğer"><x-heroicon-o-ellipsis-horizontal class="h-5 w-5" /></button>
                                <div x-show="menu === 'more'" x-cloak class="nb-panel absolute right-0 top-full z-20 mt-1 w-56 rounded-lg border p-1 text-sm shadow-lg" x-data="{ confirmDelete: false }">
                                    <button type="button" class="nb-menu-item" @click="openSource('alinti')"><span class="font-serif text-lg leading-none">“</span> Alıntı ekle</button>
                                    <button type="button" class="nb-menu-item" @click="openSource('not')"><x-heroicon-o-document-text class="h-4 w-4" /> Not ekle</button>
                                    <button type="button" class="nb-menu-item" @click="openLink()"><x-heroicon-o-link class="h-4 w-4" /> Bağlantı ekle</button>
                                    <button type="button" class="nb-menu-item" @click="pickImage()"><x-heroicon-o-photo class="h-4 w-4" /> Görsel ekle</button>
                                    <div class="nb-rule my-1 border-t"></div>
                                    <button type="button" x-show="!confirmDelete" class="nb-menu-item text-red-600" @click="confirmDelete = true"><x-heroicon-o-trash class="h-4 w-4" /> Defteri sil</button>
                                    <form x-show="confirmDelete" x-cloak method="POST" action="{{ route('panel.defterim.sil', $notebook) }}" class="flex items-center justify-between gap-2 px-3 py-2">
                                        @csrf
                                        @method('DELETE')
                                        <span class="nb-muted">Silinsin mi?</span>
                                        <span class="flex gap-2"><button type="submit" class="font-medium text-red-600">Sil</button><button type="button" class="nb-muted" @click="confirmDelete = false">Vazgeç</button></span>
                                    </form>
                                </div>
                            </div>
                            <button type="button" class="{{ $btn }} nb-theme" @click="toggleTheme()" :aria-label="dark ? 'Açık tema' : 'Koyu tema'">
                                <x-heroicon-o-sun class="h-5 w-5" x-show="!dark" />
                                <x-heroicon-o-moon class="h-5 w-5" x-show="dark" x-cloak />
                            </button>
                        </div>
                    </header>

                    <div class="nb-panel mt-5 overflow-hidden rounded-xl border">
                        {{-- Araç çubuğu --}}
                        {{-- Düğmeye basmak odağı editörden almasın (yoksa ilk harfler düğmeye gider). --}}
                        <div class="nb-rule flex flex-wrap items-center gap-0.5 border-b px-2 py-1.5 sm:px-3" role="toolbar" aria-label="Biçim" @mousedown="$event.target.closest('button') && $event.preventDefault()">
                            <div class="relative" @click.outside="menu === 'style' && (menu = null)">
                                <button type="button" class="{{ $btn }} w-28 justify-between px-3 text-sm" @click="menu = menu === 'style' ? null : 'style'" :aria-expanded="(menu === 'style').toString()">
                                    <span x-text="blockLabel()">Normal</span> <x-heroicon-o-chevron-down class="h-4 w-4" />
                                </button>
                                <div x-show="menu === 'style'" x-cloak class="nb-panel absolute left-0 top-full z-20 mt-1 w-44 rounded-lg border p-1 shadow-lg">
                                    <button type="button" class="nb-menu-item" @click="setBlock('normal')">Normal</button>
                                    <button type="button" class="nb-menu-item font-serif text-xl font-semibold" @click="setBlock(1)">Başlık 1</button>
                                    <button type="button" class="nb-menu-item font-serif text-lg font-semibold" @click="setBlock(2)">Başlık 2</button>
                                    <button type="button" class="nb-menu-item font-serif font-semibold" @click="setBlock(3)">Başlık 3</button>
                                </div>
                            </div>
                            <span class="nb-rule mx-1 h-6 border-l" aria-hidden="true"></span>
                            <button type="button" class="{{ $btn }}" :class="isActive('bold') && 'is-active'" @click="run('toggleBold')" aria-label="Kalın"><x-heroicon-o-bold class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" :class="isActive('italic') && 'is-active'" @click="run('toggleItalic')" aria-label="Eğik"><x-heroicon-o-italic class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" :class="isActive('underline') && 'is-active'" @click="run('toggleUnderline')" aria-label="Altı çizili"><x-heroicon-o-underline class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" :class="isActive('strike') && 'is-active'" @click="run('toggleStrike')" aria-label="Üstü çizili"><x-heroicon-o-strikethrough class="h-5 w-5" /></button>
                            <span class="nb-rule mx-1 h-6 border-l" aria-hidden="true"></span>
                            @foreach ([1, 2, 3] as $level)
                                <button type="button" class="{{ $btn }} font-serif text-sm font-semibold" :class="isActive('heading', { level: {{ $level }} }) && 'is-active'" @click="heading({{ $level }})" aria-label="Başlık {{ $level }}">H<sub class="text-[0.65em]">{{ $level }}</sub></button>
                            @endforeach
                            <span class="nb-rule mx-1 h-6 border-l" aria-hidden="true"></span>
                            <button type="button" class="{{ $btn }}" :class="isActive('bulletList') && 'is-active'" @click="run('toggleBulletList')" aria-label="Madde işaretli liste"><x-heroicon-o-list-bullet class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" :class="isActive('orderedList') && 'is-active'" @click="run('toggleOrderedList')" aria-label="Numaralı liste"><x-heroicon-o-numbered-list class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" @click="indent()" :disabled="!isActive('listItem')" aria-label="Girintiyi artır"><x-heroicon-o-bars-3-bottom-right class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" @click="outdent()" :disabled="!isActive('listItem')" aria-label="Girintiyi azalt"><x-heroicon-o-bars-3-bottom-left class="h-5 w-5" /></button>
                            <span class="nb-rule mx-1 h-6 border-l" aria-hidden="true"></span>
                            <button type="button" class="{{ $btn }} font-serif text-xl font-bold" :class="isActive('blockquote') && 'is-active'" @click="run('toggleBlockquote')" aria-label="Alıntı">“</button>
                            <button type="button" class="{{ $btn }}" :class="isActive('link') && 'is-active'" @click="openLink()" aria-label="Bağlantı"><x-heroicon-o-link class="h-5 w-5" /></button>
                            <button type="button" class="{{ $btn }}" @click="pickImage()" aria-label="Görsel"><x-heroicon-o-photo class="h-5 w-5" /></button>
                            <input x-ref="imageInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="uploadImage($event)">
                        </div>

                        {{-- Metin --}}
                        <div class="relative">
                            <div x-ref="surface" class="nb-editor min-h-[26rem] px-5 py-6 sm:min-h-[32rem] sm:px-12 sm:py-10"></div>
                            <div x-show="ready && empty" x-cloak class="pointer-events-none absolute inset-0 flex flex-col items-center justify-between px-4 pb-8 pt-24 text-center">
                                <div>
                                    <p class="nb-ink font-reading text-2xl">Başlamaya hazır mısın?</p>
                                    <p class="nb-muted mt-2 font-reading text-lg">Düşüncelerini yaz, fikirlerini geliştir.</p>
                                    <svg class="nb-muted mx-auto mt-5 h-14 w-8" viewBox="0 0 32 56" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M10 4c-6 8 12 10 8 20-3 8-10 6-8 0 3-8 14 2 12 24"/><path d="m17 44 5 5 4-6"/></svg>
                                </div>
                                <div class="pointer-events-auto flex flex-wrap justify-center gap-3">
                                    <button type="button" class="nb-start text-brand-700" @click="openSource('alinti')"><span class="font-serif text-2xl leading-none">“</span> Alıntı ekle</button>
                                    <button type="button" class="nb-start text-navy" @click="openSource('not')"><x-heroicon-o-document-text class="h-5 w-5" /> Not ekle</button>
                                    <button type="button" class="nb-start text-emerald-700" @click="openLink()"><x-heroicon-o-link class="h-5 w-5" /> Bağlantı ekle</button>
                                </div>
                            </div>
                        </div>

                        <p x-show="message" x-cloak class="border-t border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800" role="alert">
                            <span x-text="message"></span>
                            <a x-show="saveState === 'limit'" href="{{ route('abonelik') }}" class="ml-1 font-medium underline">Premium'a göz at</a>
                        </p>
                        @if ($wordLimit)
                            <p x-show="saveState === 'limit' && !message" x-cloak class="border-t border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800" role="alert">
                                Ücretsiz hesapta bir defter en fazla {{ number_format($wordLimit, 0, ',', '.') }} kelime olabilir; fazlası kaydedilmez. <a href="{{ route('abonelik') }}" class="font-medium underline">Premium'a göz at</a>
                            </p>
                        @endif

                        {{-- Alt çubuk --}}
                        <div class="nb-rule flex flex-wrap items-center gap-x-6 gap-y-2 border-t px-4 py-3 text-sm sm:px-6">
                            <span class="nb-muted tabular-nums" :class="saveState === 'limit' && 'text-amber-700'">
                                <span x-text="words.toLocaleString('tr-TR')">0</span> kelime
                                @if ($wordLimit)
                                    <span class="nb-muted">/ {{ number_format($wordLimit, 0, ',', '.') }}</span>
                                @endif
                            </span>
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-center gap-2">
                                <template x-for="tag in tags" :key="tag">
                                    <span class="nb-chip inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs">#<span x-text="tag"></span><button type="button" class="nb-muted hover:text-red-600" @click="removeTag(tag)" :aria-label="'Etiketi kaldır: ' + tag">×</button></span>
                                </template>
                                <input x-show="tagOpen" x-cloak x-ref="tagInput" x-model="tagInput" @keydown.enter.prevent="addTag()" @keydown.comma.prevent="addTag()" @blur="addTag(); tagOpen = false" maxlength="30" class="nb-input w-32 rounded-md px-2 py-1 text-xs" placeholder="etiket">
                                <button type="button" x-show="!tagOpen && tags.length < 10" class="nb-muted inline-flex items-center gap-1 hover:text-navy" @click="tagOpen = true; setTimeout(() => $refs.tagInput.focus(), 30)"># etiket ekle</button>
                            </div>
                            <button type="button" class="nb-muted inline-flex items-center gap-1.5 hover:text-navy" @click="infoOpen = !infoOpen" :aria-expanded="infoOpen.toString()"><x-heroicon-o-information-circle class="h-4 w-4" /> <span x-text="info ? 'Defter bilgisi' : 'Deftere bilgi ekle'"></span></button>
                            <button type="button" class="nb-muted inline-flex items-center gap-1.5 hover:text-navy" @click="toggleFocus()"><x-heroicon-o-arrows-pointing-out class="h-4 w-4" /> <span x-text="focusMode ? 'Tam ekrandan çık' : 'Tam ekran'"></span></button>
                        </div>
                        <div x-show="infoOpen" x-cloak class="nb-rule border-t px-4 py-3 sm:px-6">
                            <label for="nb-info" class="nb-muted text-xs font-medium">Deftere bilgi (neden tuttuğun, kaynaklar, hatırlatmalar)</label>
                            <textarea id="nb-info" x-model="info" @input="scheduleSave()" rows="2" maxlength="1000" class="nb-input mt-1 w-full rounded-lg text-sm"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Bağlantı --}}
                <div x-show="panel === 'link'" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/30 p-4 pt-[18vh]" @click.self="panel = null">
                    <form class="nb-panel w-full max-w-sm rounded-xl border p-5 shadow-xl" @submit.prevent="applyLink()" role="dialog" aria-label="Bağlantı">
                        <label for="nb-link" class="nb-ink text-sm font-medium">Bağlantı adresi</label>
                        <input id="nb-link" x-ref="linkInput" x-model="link.href" type="text" inputmode="url" placeholder="https://" class="nb-input mt-1.5 w-full rounded-lg text-sm">
                        <p class="nb-muted mt-1.5 text-xs">Metin seçiliyse ona bağlanır; seçili değilse adres yazılır. Boş bırakırsan bağlantı kalkar.</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="btn-outline btn-sm" @click="panel = null">İptal</button>
                            <button type="submit" class="btn-dark btn-sm">Uygula</button>
                        </div>
                    </form>
                </div>

                {{-- Alıntı ekle / Not ekle --}}
                <div x-show="panel === 'source'" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/30 p-4 pt-[10vh]" @click.self="panel = null">
                    <div class="nb-panel flex max-h-[76vh] w-full max-w-xl flex-col rounded-xl border shadow-xl" role="dialog" :aria-label="source.type === 'alinti' ? 'Alıntı ekle' : 'Not ekle'">
                        <div class="nb-rule flex items-center gap-3 border-b px-5 py-3.5">
                            <p class="nb-ink font-serif text-lg font-semibold" x-text="source.type === 'alinti' ? 'Alıntılarından ekle' : 'Notlarından ekle'"></p>
                            <button type="button" class="nb-btn ml-auto" @click="panel = null" aria-label="Kapat"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <div class="px-5 pt-3">
                            <input x-ref="sourceInput" x-model="source.q" @input.debounce.300ms="loadSources()" type="search" class="nb-input w-full rounded-lg text-sm" :placeholder="source.type === 'alinti' ? 'Alıntılarında ya da kitap adında ara…' : 'Notlarında ya da kitap adında ara…'">
                        </div>
                        <ul class="mt-2 flex-1 overflow-y-auto px-2 pb-3">
                            <template x-for="item in source.items" :key="item.id">
                                <li>
                                    <button type="button" class="block w-full rounded-lg px-3 py-2.5 text-left hover:bg-black/5" @click="insertSource(item)">
                                        <span class="nb-muted flex justify-between gap-3 text-xs"><span class="truncate" x-text="item.work"></span><span x-show="item.page" x-text="'s. ' + item.page"></span></span>
                                        <span x-show="item.quote" class="nb-ink mt-0.5 line-clamp-2 block font-reading italic" x-text="'“' + (item.quote || '') + '”'"></span>
                                        <span x-show="item.note" class="nb-ink mt-0.5 line-clamp-2 block text-sm" x-text="item.note"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="!source.loading && !source.items.length" class="nb-muted px-3 py-8 text-center text-sm" x-text="source.type === 'alinti' ? 'Alıntı yok — okurken bir cümleyi seçip Alıntıla deyin.' : 'Not yok — okurken bir cümleyi seçip Not Al deyin.'"></li>
                            <li x-show="source.loading" class="nb-muted px-3 py-8 text-center text-sm">Yükleniyor…</li>
                        </ul>
                    </div>
                </div>
            </main>
        @else
            <main class="hidden flex-1 items-center justify-center p-8 lg:flex">
                <div class="max-w-sm text-center">
                    <x-heroicon-o-document class="mx-auto h-12 w-12 text-slate-300" />
                    <p class="mt-4 font-reading text-2xl text-navy">Başlamaya hazır mısın?</p>
                    <p class="mt-2 text-slate-500">Kitaplardan bağımsız olarak senin ne düşündüğün burada. Soldan <span class="font-medium text-slate-700">Yeni Defter</span> ile başla.</p>
                </div>
            </main>
        @endif
    </div>
@endsection
