{{--
    Yeni Yayın editörü (Faz G2, "Yazarın Gözünden" 1.1.1–1.1.6). Alpine 'workEditor'
    (resources/js/work-editor-component.js) + Tiptap (work-editor.js, bu sayfada yüklenir).

    mode="autosave": yazarın editör sayfası — değişiklikler kendiliğinden save-url'e (PUT, JSON).
    mode="form": Süper Admin'in makale formu — HTML `name` adlı gizli alana yazılır.

    Araç çubuğu mockup'taki sırayla: Ekle ▾ · Stil ▾ · Yazı tipi ▾ · Punto ▾ · B I U S bağlantı ·
    listeler · hizalama ▾ · "1 Kaynak No." · "a Dipnot" · Sayfa Sonu · sayfa oranı ▾ · geri al / yinele.
    Renkler bilerek mockup'taki mavi değil, ilk tasarımın tonları (belgedeki not).
--}}
@props([
    'mode' => 'autosave',
    'name' => 'body',
    'value' => '',
    'saveUrl' => null,
    'importUrl' => null,
    'imageUrl' => null,
    'documentUrl' => null,
    'documentsPageUrl' => null,
    'documents' => [],
    'images' => [],
    'ratio' => '13x20',
    'numbering' => true,
    'editable' => true,
    'isBook' => false,
    'savedLabel' => '',
    'heading' => null,
    'subheading' => null,
])

@php
    $fonts = collect(\App\Support\RichText::FONTS)->map(fn ($font) => ['label' => $font[0], 'css' => $font[1]]);
    $primaryFonts = ['georgia', 'times', 'palatino', 'garamond', 'arial', 'helvetica', 'lato', 'source-serif'];
    $ratios = ['13x20' => '13 × 20 cm', '13x21' => '13 × 21 cm', '16x24' => '16 × 24 cm', '21x27.5' => '21 × 27,5 cm (dergi)'];
    $config = [
        'mode' => $mode,
        'saveUrl' => $saveUrl,
        'importUrl' => $importUrl,
        'imageUrl' => $imageUrl,
        'documentUrl' => $documentUrl,
        'documents' => collect($documents)->map(fn ($d) => ['id' => $d->id, 'caption' => $d->caption()])->values(),
        'images' => collect($images)->map(fn ($d) => ['id' => $d->id, 'url' => $d->viewUrl(), 'title' => $d->title])->values(),
        'ratio' => $ratio,
        'numbering' => (bool) $numbering,
        'editable' => (bool) $editable,
        'isBook' => (bool) $isBook,
        'savedLabel' => $savedLabel,
    ];
    $btn = 'rich-editor-btn';
    $menuBtn = 'inline-flex items-center gap-1.5 h-9 px-2.5 rounded-md text-sm text-slate-700 hover:bg-slate-100';
    $menu = 'absolute left-0 top-full mt-1 z-30 card py-1 min-w-[11rem] text-sm';
    $menuItem = 'w-full flex items-center gap-2.5 px-3 py-1.5 text-left text-slate-700 hover:bg-brand-50';
@endphp

<div x-data="workEditor(@js($config))" {{ $attributes->merge(['class' => 'work-editor']) }} @keydown.escape="menu = null; panel && closePanel()" @click.outside="menu = null">
    <textarea name="{{ $name }}" x-ref="input" class="hidden" @if ($mode !== 'form') disabled @endif>{{ $value }}</textarea>
    @if ($mode === 'form')
        <input type="hidden" name="page_ratio" x-ref="ratio" value="{{ $ratio }}">
    @endif

    {{-- Araç çubuğu: uzun metinde görünür kalsın diye header'ın altına yapışık. mousedown.prevent:
         düğmeye tıklamak seçimi editörden almasın. --}}
    <div class="sticky top-16 z-20 rounded-t-lg border border-slate-200 bg-white/95 backdrop-blur" @mousedown="$event.target.closest('button') && !$event.target.closest('input, textarea') && $event.preventDefault()">
        <div class="flex flex-wrap items-center gap-0.5 px-1.5 py-1" role="toolbar" aria-label="Biçimlendirme">
            {{-- Ekle ▾ (1.1.2) --}}
            <div class="relative">
                <button type="button" class="{{ $menuBtn }} font-medium" :class="menu === 'insert' && 'bg-brand-50'" :disabled="!ready" @click="toggleMenu('insert')" aria-haspopup="true" :aria-expanded="menu === 'insert'">
                    Ekle <x-heroicon-o-chevron-down class="w-4 h-4" />
                </button>
                <div x-show="menu === 'insert'" x-cloak class="{{ $menu }}">
                    <button type="button" class="{{ $menuItem }}" @click="insert('tableOfContents')"><x-heroicon-o-list-bullet class="w-4 h-4" /> İçindekiler</button>
                    @if ($imageUrl)
                        <button type="button" class="{{ $menuItem }}" @click="openPanel('image')"><x-heroicon-o-photo class="w-4 h-4" /> Görsel</button>
                    @endif
                    <button type="button" class="{{ $menuItem }}" @click="insert('table')"><x-heroicon-o-table-cells class="w-4 h-4" /> Tablo</button>
                    <button type="button" class="{{ $menuItem }}" @click="openPanel('video')"><x-heroicon-o-play-circle class="w-4 h-4" /> Video</button>
                    <button type="button" class="{{ $menuItem }}" @click="openPanel('document')"><x-snowflake-icon class="w-4 h-4" /> Belge</button>
                    <button type="button" class="{{ $menuItem }}" @click="openPanel('link')"><x-heroicon-o-link class="w-4 h-4" /> Bağlantı</button>
                    {{ $insertItems ?? '' }}
                    <div class="my-1 border-t border-slate-100"></div>
                    <button type="button" class="{{ $menuItem }}" @click="insert('bibliography')"><x-heroicon-o-book-open class="w-4 h-4" /> Kaynakça</button>
                </div>
            </div>
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

            {{-- Yazı stili ▾ (1.1.3) --}}
            <div class="relative">
                <button type="button" class="{{ $menuBtn }} min-w-[7.5rem] justify-between" :disabled="!ready" @click="toggleMenu('style')" aria-haspopup="true">
                    <span x-text="currentStyle()">Normal Metin</span> <x-heroicon-o-chevron-down class="w-4 h-4" />
                </button>
                <div x-show="menu === 'style'" x-cloak class="{{ $menu }} min-w-[12rem]">
                    <button type="button" class="{{ $menuItem }}" :class="currentStyle() === 'Normal Metin' && 'bg-brand-50'" @click="setStyle('paragraph')"><span class="inline-flex" x-bind:class="currentStyle() !== 'Normal Metin' && 'invisible'"><x-heroicon-o-check class="w-4 h-4" /></span> Normal Metin</button>
                    @foreach ([1 => 'text-lg font-semibold', 2 => 'text-base font-semibold', 3 => 'text-[0.95rem] font-semibold', 4 => 'text-sm font-semibold'] as $level => $class)
                        <button type="button" class="{{ $menuItem }}" :class="currentStyle() === 'Başlık {{ $level }}' && 'bg-brand-50'" @click="setStyle({{ $level }})">
                            <span class="w-4 text-xs font-bold text-slate-500">H{{ $level }}</span> <span class="{{ $class }}">Başlık {{ $level }}</span>
                        </button>
                    @endforeach
                    <div class="my-1 border-t border-slate-100"></div>
                    <button type="button" class="{{ $menuItem }}" @click="setStyle('quote')"><x-heroicon-s-chat-bubble-bottom-center-text class="w-4 h-4" /> Alıntı</button>
                </div>
            </div>

            {{-- Yazı tipi ▾ ve punto ▾ (1.1.4) --}}
            <div class="relative">
                <button type="button" class="{{ $menuBtn }} min-w-[7rem] justify-between" :disabled="!ready" @click="toggleMenu('font')" aria-haspopup="true" aria-label="Yazı tipi">
                    <span x-text="@js($fonts->map->label)[currentFont()] || 'Georgia'">Georgia</span> <x-heroicon-o-chevron-down class="w-4 h-4" />
                </button>
                <div x-show="menu === 'font'" x-cloak class="{{ $menu }} min-w-[13rem] max-h-80 overflow-y-auto">
                    @foreach ($fonts as $key => $font)
                        <button type="button" class="{{ $menuItem }}" @if (! in_array($key, $primaryFonts, true)) x-show="moreFonts" @endif :class="currentFont() === @js($key) && 'bg-brand-50'" @click="setFont(@js($key))" style="font-family: {{ $font['css'] }}">
                            <span class="inline-flex" x-bind:class="currentFont() !== @js($key) && 'invisible'"><x-heroicon-o-check class="w-4 h-4 shrink-0" /></span> {{ $font['label'] }}
                        </button>
                    @endforeach
                    <button type="button" class="{{ $menuItem }} border-t border-slate-100 text-slate-500" x-show="!moreFonts" @click="moreFonts = true">Daha fazla yazı tipi…</button>
                </div>
            </div>
            <div class="relative">
                <button type="button" class="{{ $menuBtn }} min-w-[3.75rem] justify-between" :disabled="!ready" @click="toggleMenu('size')" aria-haspopup="true" aria-label="Punto">
                    <span x-text="currentSize()">16</span> <x-heroicon-o-chevron-down class="w-4 h-4" />
                </button>
                <div x-show="menu === 'size'" x-cloak class="{{ $menu }} min-w-[5.5rem] max-h-80 overflow-y-auto">
                    @foreach ([10, 11, 12, 14, 16, 18, 20, 24, 28, 36] as $size)
                        <button type="button" class="{{ $menuItem }} tabular-nums" :class="currentSize() === {{ $size }} && 'bg-brand-50'" @click="setSize({{ $size }})">
                            <span class="inline-flex" x-bind:class="currentSize() !== {{ $size }} && 'invisible'"><x-heroicon-o-check class="w-4 h-4 shrink-0" /></span> {{ $size }}
                        </button>
                    @endforeach
                    <button type="button" class="{{ $menuItem }} border-t border-slate-100" @click="openPanel('size')">Özel…</button>
                </div>
            </div>
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

            @foreach ([['Kalın (Ctrl+B)', 'bold', 'toggleBold', 'bold'], ['Eğik (Ctrl+I)', 'italic', 'toggleItalic', 'italic'], ['Altı çizili (Ctrl+U)', 'underline', 'toggleUnderline', 'underline'], ['Üstü çizili', 'strikethrough', 'toggleStrike', 'strike']] as [$label, $icon, $command, $active])
                <button type="button" class="{{ $btn }}" title="{{ $label }}" aria-label="{{ $label }}" :class="isActive(@js($active)) && 'is-active'" :disabled="!ready" @click="run(@js($command))">
                    @svg('heroicon-o-'.$icon, 'w-5 h-5')
                </button>
            @endforeach
            <button type="button" class="{{ $btn }}" title="Bağlantı" aria-label="Bağlantı" :class="isActive('link') && 'is-active'" :disabled="!ready" @click="openPanel('link')"><x-heroicon-o-link class="w-5 h-5" /></button>
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

            <button type="button" class="{{ $btn }}" title="Madde işaretli liste" aria-label="Madde işaretli liste" :class="isActive('bulletList') && 'is-active'" :disabled="!ready" @click="run('toggleBulletList')"><x-heroicon-o-list-bullet class="w-5 h-5" /></button>
            <button type="button" class="{{ $btn }}" title="Numaralı liste" aria-label="Numaralı liste" :class="isActive('orderedList') && 'is-active'" :disabled="!ready" @click="run('toggleOrderedList')"><x-heroicon-o-numbered-list class="w-5 h-5" /></button>
            <div class="relative">
                <button type="button" class="{{ $btn }} gap-0.5 !w-auto px-1.5" title="Hizalama" aria-label="Hizalama" :disabled="!ready" @click="toggleMenu('align')">
                    <x-heroicon-o-bars-3-bottom-left class="w-5 h-5" x-show="currentAlign() === 'left'" />
                    <x-heroicon-o-bars-3 class="w-5 h-5" x-show="currentAlign() === 'center'" x-cloak />
                    <x-heroicon-o-bars-3-bottom-right class="w-5 h-5" x-show="currentAlign() === 'right'" x-cloak />
                    <x-heroicon-o-bars-4 class="w-5 h-5" x-show="currentAlign() === 'justify'" x-cloak />
                    <x-heroicon-o-chevron-down class="w-3.5 h-3.5" />
                </button>
                <div x-show="menu === 'align'" x-cloak class="{{ $menu }}">
                    @foreach (['left' => ['Sola yasla', 'bars-3-bottom-left'], 'center' => ['Ortala', 'bars-3'], 'right' => ['Sağa yasla', 'bars-3-bottom-right'], 'justify' => ['İki yana yasla', 'bars-4']] as $align => [$label, $icon])
                        <button type="button" class="{{ $menuItem }}" :class="currentAlign() === @js($align) && 'bg-brand-50'" @click="setAlign(@js($align))">@svg('heroicon-o-'.$icon, 'w-4 h-4') {{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

            <button type="button" class="{{ $menuBtn }}" title="Kaynak numarası ekle" :class="isActive('citation') && 'bg-brand-50'" :disabled="!ready" @click="openPanel('cite')"><span class="font-semibold">1</span> <span class="hidden sm:inline">Kaynak No.</span></button>
            <button type="button" class="{{ $menuBtn }}" title="Dipnot ekle (seçili dipnotu düzenler)" :class="isActive('footnote') && 'bg-brand-50'" :disabled="!ready" @click="openPanel('footnote')"><span class="font-serif italic font-semibold">a</span> <span class="hidden sm:inline">Dipnot</span></button>
            <button type="button" class="{{ $menuBtn }} bg-sky-50/0" title="Sayfa sonu (Ctrl+Enter)" :disabled="!ready" @click="insert('pageBreak')"><x-heroicon-o-document-minus class="w-4 h-4" /> <span class="hidden sm:inline">Sayfa Sonu</span></button>

            {{-- Sayfa oranı (belge: "yazar kitabı hazırlarken bir oran seçebilmeli", tüm sayfalar için tek) --}}
            <div class="relative">
                <button type="button" class="{{ $menuBtn }}" title="Sayfa oranı — eserin bütün sayfaları için" :disabled="!ready" @click="toggleMenu('ratio')">
                    <x-heroicon-o-rectangle-stack class="w-4 h-4" /> <span x-text="ratio.replace('x', '×').replace('.', ',')">{{ str_replace(['x', '.'], ['×', ','], $ratio) }}</span>
                </button>
                <div x-show="menu === 'ratio'" x-cloak class="{{ $menu }} min-w-[13rem]">
                    <p class="px-3 pt-1 pb-1.5 text-xs text-slate-500">Sayfa oranı — eserin bütün sayfaları için geçerli.</p>
                    @foreach ($ratios as $key => $label)
                        <button type="button" class="{{ $menuItem }}" :class="ratio === @js($key) && 'bg-brand-50'" @click="setRatio(@js($key))">
                            <span class="inline-flex" x-bind:class="ratio !== @js($key) && 'invisible'"><x-heroicon-o-check class="w-4 h-4 shrink-0" /></span> {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <span class="hidden sm:block mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>

            <button type="button" class="{{ $btn }}" title="Geri al (Ctrl+Z)" aria-label="Geri al" :disabled="!ready || !can('undo')" @click="run('undo')"><x-heroicon-o-arrow-uturn-left class="w-5 h-5" /></button>
            <button type="button" class="{{ $btn }}" title="Yinele (Ctrl+Y)" aria-label="Yinele" :disabled="!ready || !can('redo')" @click="run('redo')"><x-heroicon-o-arrow-uturn-right class="w-5 h-5" /></button>
        </div>

        {{-- Tablo içindeyken satır / sütun işlemleri --}}
        <div x-show="ready && isActive('table')" x-cloak class="flex flex-wrap items-center gap-1 border-t border-slate-100 px-2 py-1 text-xs">
            <span class="text-slate-500 mr-1">Tablo:</span>
            @foreach ([['Üste satır', 'addRowBefore'], ['Alta satır', 'addRowAfter'], ['Satırı sil', 'deleteRow'], ['Sola sütun', 'addColumnBefore'], ['Sağa sütun', 'addColumnAfter'], ['Sütunu sil', 'deleteColumn'], ['Başlık satırı', 'toggleHeaderRow']] as [$label, $command])
                <button type="button" class="rounded px-2 py-1 text-slate-700 hover:bg-slate-100" @click="run(@js($command))">{{ $label }}</button>
            @endforeach
            <button type="button" class="rounded px-2 py-1 text-red-600 hover:bg-red-50" @click="run('deleteTable')">Tabloyu sil</button>
        </div>

        {{-- Paneller --}}
        <div x-show="panel" x-cloak class="border-t border-slate-200 bg-slate-50 px-3 py-3 space-y-2 text-sm">
            <template x-if="panel === 'link'">
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-slate-600" for="we-link">Bağlantı adresi (boş bırakırsanız bağlantı kaldırılır)</label>
                    <input id="we-link" x-ref="panelFocus" type="url" inputmode="url" x-model="panelText" @keydown.enter.prevent="savePanel()" placeholder="https://" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </template>
            <template x-if="panel === 'footnote'">
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-slate-600" for="we-footnote" x-text="editingFootnote ? 'Dipnotu düzenle' : 'Dipnot metni — imlecin olduğu yere eklenir'"></label>
                    <textarea id="we-footnote" x-ref="panelFocus" rows="2" x-model="panelText" @keydown.enter.ctrl.prevent="savePanel()" placeholder="Ör. Kaynak: Evliya Çelebi, Seyahatname, c. 1, s. 42." class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                </div>
            </template>
            <template x-if="panel === 'cite'">
                <div class="space-y-2">
                    <div class="text-xs font-medium text-slate-600">Kaynak numarası — aynı kaynak her yerde aynı numarayı alır, "Ekle → Kaynakça" hepsini listeler.</div>
                    <template x-if="sources.length">
                        <div class="grid gap-1 max-h-40 overflow-y-auto">
                            <template x-for="(source, index) in sources" :key="source">
                                <button type="button" class="flex gap-2 rounded-md px-2.5 py-1.5 text-left text-slate-700 hover:bg-white hover:shadow-sm" @click="insertCitation(source)">
                                    <span class="shrink-0 font-semibold text-brand-700" x-text="'[' + (index + 1) + ']'"></span><span class="min-w-0 break-words" x-text="source"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <textarea x-ref="panelFocus" rows="2" x-model="cite.text" @keydown.enter.ctrl.prevent="insertCitation()" placeholder="Yeni kaynak — ör. Arendt, H. (1951). Totalitarizmin Kaynakları. İstanbul: İletişim." class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                </div>
            </template>
            <template x-if="panel === 'video'">
                <div class="space-y-2">
                    <div class="text-xs font-medium text-slate-600" x-text="editingVideo ? 'Videoyu düzenle' : 'Video — imlecin olduğu yere ayrı satır olarak eklenir'"></div>
                    <input x-ref="panelFocus" type="url" inputmode="url" x-model="video.url" placeholder="YouTube ya da Vimeo bağlantısı" aria-label="Video bağlantısı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <div class="grid grid-cols-[1fr_7rem] gap-2">
                        <input type="text" x-model="video.title" placeholder="Başlık" aria-label="Video başlığı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <input type="text" inputmode="numeric" x-model="video.duration" placeholder="Süre 12:45" aria-label="Video süresi" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                </div>
            </template>
            <template x-if="panel === 'size'">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-slate-600" for="we-size">Özel punto (8–96)</label>
                    <input id="we-size" x-ref="panelFocus" type="number" min="8" max="96" x-model="customSize" @keydown.enter.prevent="setSize(customSize)" class="w-24 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </template>
            <template x-if="panel === 'image'">
                <div class="space-y-2">
                    <div class="text-xs font-medium text-slate-600">Görsel — metnin içinde görünür (JPG / PNG, en fazla 10 MB).</div>
                    <input x-ref="imageFile" type="file" accept="image/png,image/jpeg" class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm">
                    <input x-ref="panelFocus" type="text" x-model="image.caption" maxlength="200" placeholder="Alt yazı (isteğe bağlı)" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </template>
            <template x-if="panel === 'document'">
                <div class="space-y-3">
                    <div>
                        <div class="text-xs font-medium text-slate-600 mb-1.5">Belge — imlecin olduğu yere kar tanesi olarak eklenir; okur üstüne gelince adını görür, tıklayınca açar.</div>
                        <div class="grid gap-1 max-h-40 overflow-y-auto" x-show="documents.length">
                            <template x-for="doc in documents" :key="doc.id">
                                <button type="button" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-slate-700 hover:bg-white hover:shadow-sm" @click="insertDocument(doc.id)">
                                    <x-snowflake-icon class="w-4 h-4 shrink-0 text-navy" /><span class="min-w-0 break-words" x-text="doc.caption"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="!documents.length" class="text-slate-500">Henüz belge yok.</p>
                    </div>
                    @if ($documentUrl)
                        <div class="rounded-md border border-slate-200 bg-white p-2.5 space-y-2">
                            <div class="text-xs font-medium text-slate-600">Yeni belge yükle (PDF / JPG / PNG)</div>
                            <div class="grid sm:grid-cols-[1fr_9rem] gap-2">
                                <input type="text" x-model="docForm.title" placeholder="Belgenin adı" aria-label="Belgenin adı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                <input type="text" x-model="docForm.date" placeholder="Tarih (ör. 1873)" aria-label="Belgenin tarihi" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <input x-ref="documentFile" type="file" accept="application/pdf,image/png,image/jpeg" class="min-w-0 flex-1 text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm">
                                <button type="button" class="btn-outline btn-sm" :disabled="docForm.busy" @click="uploadDocument()" x-text="docForm.busy ? 'Yükleniyor…' : 'Yükle ve Ekle'"></button>
                            </div>
                        </div>
                    @endif
                    @if ($documentsPageUrl)
                        <a href="{{ $documentsPageUrl }}" target="_blank" class="text-xs font-medium text-brand-700 hover:text-brand-600">Belgeleri yönet (ad, tarih, silme) ↗</a>
                    @endif
                </div>
            </template>

            <p x-show="panelError" x-text="panelError" class="text-sm text-red-600" role="alert"></p>
            <div class="flex flex-wrap items-center gap-2" x-show="panel !== 'document'">
                <button type="button" class="btn-dark btn-sm"
                        :disabled="image.busy"
                        @click="panel === 'cite' ? insertCitation() : (panel === 'size' ? setSize(customSize) : (panel === 'image' ? uploadImage() : savePanel()))"
                        x-text="panel === 'image' ? (image.busy ? 'Yükleniyor…' : 'Yükle ve Ekle') : ((editingVideo || editingFootnote) ? 'Güncelle' : (panel === 'link' ? 'Kaydet' : 'Ekle'))"></button>
                <button type="button" class="btn-ghost btn-sm" @click="closePanel()">Vazgeç</button>
                <button type="button" x-show="editingVideo || editingFootnote" class="btn-sm btn text-red-600 hover:bg-red-50 ml-auto" @click="removeSelected()">Sil</button>
            </div>
            <div x-show="panel === 'document'" class="flex justify-end"><button type="button" class="btn-ghost btn-sm" @click="closePanel()">Kapat</button></div>
        </div>

        <p x-show="importError" x-cloak x-text="importError" class="border-t border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <p x-show="importNotice" x-cloak class="border-t border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status"><span x-text="importNotice"></span> <button type="button" class="ml-2 underline" @click="importNotice = ''">Kapat</button></p>
    </div>

    {{-- Kâğıt: seçilen sayfa oranında otomatik sayfa çizgileri; Sayfa Sonu yeni sayfa. --}}
    <div class="border-x border-slate-200 bg-slate-100/70 px-2 py-6 sm:px-6 sm:py-8">
        <div x-ref="paper" class="relative mx-auto w-full max-w-[46rem] rounded-sm bg-white shadow-[0_1px_3px_rgba(15,23,42,0.12)] px-5 py-10 sm:px-14 sm:py-14" :class="numbering && 'rt-numbered'">
            @if ($heading)
                <header class="text-center mb-8 pb-6 border-b border-slate-200">
                    <h2 class="font-reading text-3xl sm:text-4xl font-semibold text-navy leading-tight">{{ $heading }}</h2>
                    @if ($subheading)
                        <p class="font-reading text-xl text-slate-600 mt-2">{{ $subheading }}</p>
                    @endif
                </header>
            @endif

            {{-- Seçim balonu (1.1.5): kalın, eğik, altı çizili, bağlantı (+ Sözlüğe Bağla, Faz G3). --}}
            <div x-show="bubble.show" x-cloak class="absolute z-20 flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-1 py-1 shadow-lg" :style="`top:${bubble.top}px;left:${bubble.left}px`" @mousedown.prevent>
                <button type="button" class="{{ $btn }}" :class="isActive('bold') && 'is-active'" @click="run('toggleBold')" aria-label="Kalın"><x-heroicon-o-bold class="w-5 h-5" /></button>
                <button type="button" class="{{ $btn }}" :class="isActive('italic') && 'is-active'" @click="run('toggleItalic')" aria-label="Eğik"><x-heroicon-o-italic class="w-5 h-5" /></button>
                <button type="button" class="{{ $btn }}" :class="isActive('underline') && 'is-active'" @click="run('toggleUnderline')" aria-label="Altı çizili"><x-heroicon-o-underline class="w-5 h-5" /></button>
                <button type="button" class="{{ $btn }}" :class="isActive('link') && 'is-active'" @click="openPanel('link')" aria-label="Bağlantı"><x-heroicon-o-link class="w-5 h-5" /></button>
                {{ $bubbleItems ?? '' }}
            </div>

            <div class="relative">
                {{-- Otomatik sayfa çizgileri (sayfa oranına göre) --}}
                <template x-for="line in pageBreaks" :key="line.top + line.label">
                    <div class="pointer-events-none absolute -left-5 -right-5 sm:-left-14 sm:-right-14 border-t border-dashed" :class="line.forced ? 'border-transparent' : 'border-slate-300'" :style="`top:${line.top - 14}px`" aria-hidden="true">
                        <span x-show="!line.forced" class="absolute right-2 -top-2.5 bg-white px-1.5 text-[10px] uppercase tracking-wider text-slate-400" x-text="line.label"></span>
                    </div>
                </template>
                <div x-ref="surface"><div class="rich-content rt-editor-surface">{!! \App\Support\RichText::normalize($value) !!}</div></div>
            </div>
        </div>
    </div>

    {{-- Sayfa hesabı için gizli ölçüm kutusu: sabit metin genişliği (736 − 2×56 px), bkz. measure(). --}}
    <div x-ref="measurer" aria-hidden="true" class="pointer-events-none invisible fixed -left-[9999px] top-0" :class="numbering && 'rt-numbered'"><div class="rich-content rt-editor-surface" style="width: 624px"></div></div>

    {{-- Durum çubuğu (mockup: Sayfa · Kelime · Karakter · Tüm değişiklikler kaydedildi) --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 rounded-b-lg border border-t-0 border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600">
        <span class="tabular-nums">Sayfa: <span x-text="stats.pages.toLocaleString('tr-TR')">0</span></span>
        <span class="tabular-nums">Kelime: <span x-text="stats.words.toLocaleString('tr-TR')">0</span></span>
        <span class="tabular-nums">Karakter: <span x-text="stats.chars.toLocaleString('tr-TR')">0</span></span>
        @if ($mode === 'autosave')
            <span class="ml-auto inline-flex items-center gap-1.5" aria-live="polite">
                <template x-if="saveState === 'saved'"><span class="inline-flex items-center gap-1.5"><x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600" /> Tüm değişiklikler kaydedildi</span></template>
                <template x-if="saveState === 'saving'"><span>Kaydediliyor…</span></template>
                <template x-if="saveState === 'dirty'"><span class="text-slate-500">Kaydedilmemiş değişiklikler…</span></template>
                <template x-if="saveState === 'error'"><button type="button" class="inline-flex items-center gap-1.5 text-red-600" @click="save()"><x-heroicon-o-exclamation-triangle class="w-5 h-5" /> Kaydedilemedi — tekrar dene</button></template>
            </span>
        @endif
    </div>

    {{-- Word / EPUB içe aktarma: sayfa bu input'u tetikliyor (x-ref="importInput"). --}}
    @if ($importUrl)
        <input x-ref="importInput" type="file" accept=".docx,.epub,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/epub+zip" class="hidden" @change="importFile($event)" data-work-import>
    @endif
</div>
