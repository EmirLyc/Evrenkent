// Sayfalı okuma (Faz G4, x-paged-reader). Metin sabit ölçüde bir sayfanın içinde CSS sütunlarıyla
// akıyor: her sütun bir sayfa (sütun aralığı iki yan boşluk, böylece bir sayfa kaydırması tam
// sayfa genişliği). Okur puntoyu değil sayfayı büyütüyor — dizgi her cihazda aynı kalıyor.
//
// Faz H1 ("Okurun Gözünden"): İçindekiler çekmecesi (sayfa numaraları, okunan yer, "Sayfaya
// git"), kitap içi arama ve giriş bölümünün roma rakamlı sayfaları.
//
// Durum Alpine.store('pager')'da da tutuluyor: okuma düzeninin başlığı (Aa menüsündeki
// yakınlaştırma, ilerleme çubuğu, arayüzün gizlenmesi) ve not formu buradan okuyor.

const ZOOMS = [0.75, 1, 1.25, 1.5, 2, 2.5, 3];
const DEFAULT_ZOOM = 1;
const ZOOM_KEY = 'evrenkent.reader.zoom';
const MAX_SCALE = 2.5;
const MAX_RESULTS = 200;
// Aramada tek parça sayılan bloklar (içinde başka blok olmayanlar aranıyor).
const BLOCKS = 'p, li, h1, h2, h3, h4, blockquote, td, th, figcaption, .rt-opener-title';

// Gizli pencere / engellenmiş depolama: sessizce geç (sayfa depolamasız da çalışır).
const storage = {
    get(key) {
        try {
            return JSON.parse(localStorage.getItem(key));
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // yok say
        }
    },
};

const ROMAN = [[1000, 'm'], [900, 'cm'], [500, 'd'], [400, 'cd'], [100, 'c'], [90, 'xc'], [50, 'l'], [40, 'xl'], [10, 'x'], [9, 'ix'], [5, 'v'], [4, 'iv'], [1, 'i']];

function toRoman(number) {
    let result = '';
    for (const [value, symbol] of ROMAN) {
        while (number >= value) {
            result += symbol;
            number -= value;
        }
    }
    return result;
}

function fromRoman(text) {
    for (let number = 1; number <= 400; number++) {
        if (toRoman(number) === text) return number;
    }
    return null;
}

// Blok içinde [start, end) karakter aralığının Range'i (metin birden çok düğüme bölünmüş olabilir).
function rangeFor(element, start, end) {
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const range = document.createRange();
    let offset = 0;
    let started = false;
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const length = node.nodeValue.length;
        if (!started && start < offset + length) {
            range.setStart(node, start - offset);
            started = true;
        }
        if (started && end <= offset + length) {
            range.setEnd(node, end - offset);
            return range;
        }
        offset += length;
    }
    return null;
}

export default function pagedReader(config) {
    const PAGE = config.pageWidth;
    let relayoutTimer = null;
    let positionTimer = null;
    let touch = null;
    // Arama sonuçlarının Range'leri reaktif olmayan yerde (Alpine vekili yerel nesneleri bozar).
    let searchRanges = [];

    return {
        page: 0,
        total: 1,
        front: 0,
        scale: 1,
        zoomIndex: DEFAULT_ZOOM,
        ready: false,
        starts: [],
        chapter: null,
        chapterTitle: '',

        tocOpen: false,
        tocPages: [],
        tocCurrent: -1,
        tocExpanded: {},
        gotoValue: '',
        gotoError: '',

        searchOpen: false,
        query: '',
        results: [],

        init() {
            const saved = storage.get(ZOOM_KEY);
            if (Number.isInteger(saved) && ZOOMS[saved]) this.zoomIndex = saved;

            this.onResize = () => this.fit();
            this.onKey = (event) => this.key(event);
            this.onClick = (event) => this.link(event);
            this.onFonts = () => this.scheduleRelayout();
            window.addEventListener('resize', this.onResize);
            window.addEventListener('keydown', this.onKey);
            // Yakalama aşamasında: kitap içi bağlantılar (içindekiler, dipnot, kaynak, sözlük
            // "yerinde oku") Turbo'dan önce sayfaya çevriliyor.
            window.addEventListener('click', this.onClick, true);
            document.fonts?.addEventListener?.('loadingdone', this.onFonts);

            this.$nextTick(() => {
                this.fit();
                this.$refs.flow.querySelectorAll('img').forEach((image) => {
                    if (!image.complete) image.addEventListener('load', () => this.scheduleRelayout(), { once: true });
                });
                // Sayfa sayısı yazı tiplerine bağlı: yüklenmeden ölçülürse sayfalar kayar.
                (document.fonts?.ready ?? Promise.resolve()).then(() => {
                    if (!this.$root.isConnected) return;
                    this.layout();
                    this.ready = true;
                    this.openInitial();
                });
            });
        },
        destroy() {
            window.removeEventListener('resize', this.onResize);
            window.removeEventListener('keydown', this.onKey);
            window.removeEventListener('click', this.onClick, true);
            document.fonts?.removeEventListener?.('loadingdone', this.onFonts);
            clearTimeout(relayoutTimer);
            clearTimeout(positionTimer);
            CSS.highlights?.delete('reader-search');
            this.$store.pager.active = false;
        },

        // --- Ölçü ---------------------------------------------------------------------------
        fit() {
            const width = this.$refs.viewport.clientWidth;
            this.scale = Math.min(MAX_SCALE, Math.min(1, width / PAGE) * ZOOMS[this.zoomIndex]);
            this.sync();
        },
        zoom(step) {
            this.zoomIndex = Math.min(ZOOMS.length - 1, Math.max(0, this.zoomIndex + step));
            storage.set(ZOOM_KEY, this.zoomIndex);
            this.fit();
            this.$nextTick(() => {
                const viewport = this.$refs.viewport;
                viewport.scrollLeft = (viewport.scrollWidth - viewport.clientWidth) / 2;
            });
        },
        zoomReset() {
            this.zoom(DEFAULT_ZOOM - this.zoomIndex);
        },
        layout() {
            const flow = this.$refs.flow;
            this.total = Math.max(1, Math.round((flow.scrollWidth + (PAGE - flow.clientWidth)) / PAGE));
            const sections = Array.from(flow.querySelectorAll('[data-chapter]'));
            this.starts = sections.map((element) => ({
                order: Number(element.dataset.chapter),
                title: element.dataset.title || '',
                page: this.pageOf(element),
            }));
            // Giriş bölümünün sayfaları roma rakamıyla (i, ii…); asıl numaralama ilk bölümde 1'den.
            this.front = sections.length > 1 && 'preface' in sections[0].dataset ? this.starts[1].page : 0;
            this.tocPages = config.toc.map((entry) => this.pageForUrl(entry.url));
        },
        // Öğenin bulunduğu sayfa: ilk parçasının sütunu (kaydırma ve yakınlaştırmadan bağımsız).
        pageOf(element) {
            return this.pageOfRect(element.getClientRects()[0] || element.getBoundingClientRect());
        },
        pageOfRect(rect) {
            if (!rect) return 0;
            const base = this.$refs.flow.getBoundingClientRect().left;
            const page = Math.floor((rect.left - base) / (PAGE * this.scale) + 0.01);
            return Math.min(this.total - 1, Math.max(0, page));
        },
        // Görünen sayfa numarası: girişte roma rakamı, sonra 1'den.
        label(page) {
            return page < this.front ? toRoman(page + 1) : String(page - this.front + 1);
        },
        // Tarayıcı bir çapaya atlarken ya da sayfada arama (Ctrl+F) bir eşleşmeyi gösterirken
        // kırpma kutusunu yatayda kaydırıyor: kaydırma sıfırlanıp o sayfaya geçiliyor.
        clipScrolled() {
            const clip = this.$refs.clip;
            if (!clip.scrollLeft) return;
            const target = this.page + Math.round(clip.scrollLeft / PAGE);
            clip.scrollLeft = 0;
            if (this.ready) this.go(target);
        },
        // Görsel ya da yazı tipi sonradan yüklenince sayfalar yeniden hesaplanıyor; okur bölümün
        // içindeki aynı sayfada kalıyor.
        scheduleRelayout() {
            clearTimeout(relayoutTimer);
            relayoutTimer = setTimeout(() => {
                if (!this.ready || !this.$root.isConnected) return;
                const current = this.currentStart();
                const offset = this.page - (current?.page ?? 0);
                this.layout();
                const start = this.starts.find((item) => item.order === current?.order);
                this.go((start?.page ?? 0) + offset, { remember: false });
            }, 150);
        },

        // --- Gezinme ------------------------------------------------------------------------
        openInitial() {
            const hash = decodeURIComponent(window.location.hash.slice(1));
            const target = hash ? document.getElementById(hash) : null;
            this.$refs.clip.scrollLeft = 0;
            if (target && this.$refs.flow.contains(target)) {
                this.go(this.pageOf(target));
                this.showPageTop();
                return;
            }
            const saved = storage.get(config.storageKey);
            if (saved && Number.isInteger(saved.page) && (config.initialChapter === null || saved.chapter === config.initialChapter)) {
                this.go(saved.page);
                return;
            }
            const start = this.starts.find((item) => item.order === config.initialChapter);
            this.go(start ? start.page : 0);
        },
        go(page, { remember = true } = {}) {
            this.page = Math.min(this.total - 1, Math.max(0, page));
            this.sync();
            if (remember) storage.set(config.storageKey, { page: this.page, chapter: this.chapter });
        },
        // Okur sayfa çevirince arayüz çekiliyor ("Okurken arayüz mümkün olduğunca ortadan kaybolmalı").
        turned() {
            window.dispatchEvent(new CustomEvent('pager-turned'));
        },
        next() {
            if (this.page < this.total - 1) {
                this.go(this.page + 1);
                this.showPageTop();
                this.turned();
            }
        },
        prev() {
            if (this.page > 0) {
                this.go(this.page - 1);
                this.showPageTop();
                this.turned();
            }
        },
        // Sayfanın altını okurken çevrildiyse yeni sayfanın başı görünsün.
        showPageTop() {
            const top = this.$root.getBoundingClientRect().top + window.scrollY - 72;
            if (window.scrollY > top + 4) window.scrollTo({ top: Math.max(0, top) });
        },
        currentStart() {
            let current = null;
            this.starts.forEach((item) => {
                if (item.page <= this.page) current = item;
            });
            return current;
        },
        sync() {
            const current = this.currentStart();
            const changed = this.ready && current && current.order !== this.chapter;
            this.chapter = current?.order ?? null;
            this.chapterTitle = current?.title ?? '';
            let tocCurrent = -1;
            this.tocPages.forEach((page, index) => {
                if (page !== null && page <= this.page) tocCurrent = index;
            });
            this.tocCurrent = tocCurrent;
            Object.assign(this.$store.pager, {
                active: true,
                page: this.page,
                total: this.total,
                percent: Math.round(this.scale * 100),
                canZoomIn: this.zoomIndex < ZOOMS.length - 1 && this.scale < MAX_SCALE,
                canZoomOut: this.zoomIndex > 0,
                chapter: this.chapter,
                last: this.page >= this.total - 1,
                progress: this.total > 1 ? this.page / (this.total - 1) : 1,
                location: `Sayfa ${this.label(this.page)}${this.chapterTitle ? ` · ${this.chapterTitle}` : ''}`,
            });
            if (changed) this.chapterChanged();
        },
        // Adres çubuğu okunan bölümü göstersin (yenileyince aynı yere dönülür) ve okuma
        // listesindeki konum güncellensin.
        chapterChanged() {
            if (config.readBase && this.chapter) {
                window.history.replaceState(window.history.state, '', `${config.readBase}/${this.chapter}`);
            }
            if (!config.positionUrl || !this.chapter) return;
            clearTimeout(positionTimer);
            const chapter = this.chapter;
            positionTimer = setTimeout(() => {
                fetch(config.positionUrl, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ bolum: chapter }),
                }).catch(() => {});
            }, 1200);
        },

        key(event) {
            if (!this.$root.isConnected || !this.ready || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
            if (this.tocOpen || this.searchOpen) return;
            if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
            // Açık bir pencere (belge görüntüleyici, Aa menüsü) varken oklar ona ait. Görünürlük
            // kutudan: gizleyen x-show pencerenin kendisinde değil bir üst öğede olabilir.
            if (Array.from(document.querySelectorAll('[role=dialog]')).some((dialog) => dialog.getClientRects().length > 0)) return;
            if (['ArrowRight', 'PageDown'].includes(event.key)) {
                event.preventDefault();
                this.next();
            } else if (['ArrowLeft', 'PageUp'].includes(event.key)) {
                event.preventDefault();
                this.prev();
            }
        },
        // Kitap içi adresin sayfası: çapa (#…) bu akıştaysa onun sayfası, değilse bölüm adresi
        // (/oku/3) o bölümün açılışı; akışın dışındaysa null.
        pageForUrl(href) {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return null;
            const base = config.readBase ? new URL(config.readBase, window.location.href).pathname : null;
            const inBook = base && (url.pathname === base || url.pathname.startsWith(`${base}/`));
            if (!inBook && url.pathname !== window.location.pathname) return null;

            const hash = decodeURIComponent(url.hash.slice(1));
            const target = hash ? document.getElementById(hash) : null;
            if (target && this.$refs.flow.contains(target)) return this.pageOf(target);
            if (!inBook) return null;
            const order = parseInt(url.pathname.slice(base.length + 1), 10);
            return this.starts.find((item) => item.order === order)?.page ?? null;
        },
        link(event) {
            if (!this.$root.isConnected || !this.ready || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            const anchor = event.target.closest?.('a[href]');
            if (!anchor || 'documentViewer' in anchor.dataset || anchor.target === '_blank') return;

            const page = this.pageForUrl(anchor.getAttribute('href'));
            if (page === null) return;

            event.preventDefault();
            event.stopPropagation();
            this.tocOpen = false;
            this.go(page);
            this.showPageTop();
            window.dispatchEvent(new CustomEvent('pager-navigated'));
        },

        // Kaydırma ile sayfa çevirme (dokunmatik). Sayfa ekrandan büyükken yatay kaydırma
        // sayfayı gezmek için — çevirmez.
        touchStart(event) {
            touch = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
        },
        touchEnd(event) {
            if (!touch || event.touches.length) {
                touch = null;
                return;
            }
            const dx = event.changedTouches[0].clientX - touch.x;
            const dy = event.changedTouches[0].clientY - touch.y;
            touch = null;
            const viewport = this.$refs.viewport;
            if (viewport.scrollWidth > viewport.clientWidth + 2) return;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                dx < 0 ? this.next() : this.prev();
            }
        },
        // Sayfaya dokunmak (bağlantı ya da seçim değilse) gizlenen arayüzü geri getirir / gizler.
        tap(event) {
            if (!this.ready || event.target.closest('a, button, input, textarea, [data-document-viewer]')) return;
            if (!(window.getSelection()?.isCollapsed ?? true)) return;
            window.dispatchEvent(new CustomEvent('reader-tap'));
        },

        // --- İçindekiler ----------------------------------------------------------------------
        openToc() {
            if (!this.$root.isConnected) return;
            this.closeSearch();
            this.tocOpen = true;
            this.gotoValue = '';
            this.gotoError = '';
            this.$nextTick(() => this.$refs.toc.querySelector('.is-current')?.scrollIntoView({ block: 'center' }));
        },
        tocLabel(index) {
            const page = this.tocPages[index];
            return page === null || page === undefined ? '' : this.label(page);
        },
        // Kısa içindekilerde bütün bölümler açık; uzunda sadece okunan bölüm (okur açıp kapatabilir).
        tocGroupOpen(group) {
            if (group in this.tocExpanded) return this.tocExpanded[group];
            return config.toc.length <= 40 || config.toc[this.tocCurrent]?.group === group;
        },
        tocToggle(group) {
            this.tocExpanded = { ...this.tocExpanded, [group]: !this.tocGroupOpen(group) };
        },
        goToPage() {
            const value = this.gotoValue.trim().toLocaleLowerCase('tr');
            let page = null;
            if (/^\d+$/.test(value)) {
                const number = parseInt(value, 10);
                if (number >= 1 && number <= this.total - this.front) page = this.front + number - 1;
            } else if (/^[ivxlcdm]+$/.test(value)) {
                // Giriş sayfaları (i, ii…).
                const number = fromRoman(value);
                if (number && number <= this.front) page = number - 1;
            }
            if (page === null) {
                this.gotoError = `Sayfa 1 ile ${this.total - this.front} arasında olmalı.`;
                return;
            }
            this.gotoError = '';
            this.tocOpen = false;
            this.go(page);
            this.showPageTop();
            this.turned();
        },

        // --- Kitap içi arama ------------------------------------------------------------------
        openSearch() {
            if (!this.$root.isConnected) return;
            this.tocOpen = false;
            this.searchOpen = !this.searchOpen;
            if (this.searchOpen) this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        closeSearch() {
            this.searchOpen = false;
        },
        search() {
            const query = this.query.trim();
            searchRanges = [];
            CSS.highlights?.delete('reader-search');
            if (query.length < 2 || !this.ready) {
                this.results = [];
                return;
            }
            const needle = query.toLocaleLowerCase('tr');
            const results = [];
            for (const block of this.$refs.flow.querySelectorAll(BLOCKS)) {
                if (results.length >= MAX_RESULTS) break;
                if (block.querySelector(BLOCKS)) continue;
                const text = block.textContent;
                const haystack = text.toLocaleLowerCase('tr');
                // Küçük harfe çevirmek uzunluğu değiştirdiyse (nadir) konumlar kayar: blok atlanır.
                if (haystack.length !== text.length) continue;
                for (let at = haystack.indexOf(needle); at !== -1 && results.length < MAX_RESULTS; at = haystack.indexOf(needle, at + needle.length)) {
                    const range = rangeFor(block, at, at + needle.length);
                    if (!range) continue;
                    searchRanges.push(range);
                    results.push({
                        page: this.pageOfRect(range.getClientRects()[0]),
                        chapter: block.closest('[data-chapter]')?.dataset.title ?? '',
                        before: (at > 45 ? '…' : '') + text.slice(Math.max(0, at - 45), at),
                        match: text.slice(at, at + needle.length),
                        after: text.slice(at + needle.length, at + needle.length + 70) + (at + needle.length + 70 < text.length ? '…' : ''),
                    });
                }
            }
            this.results = results;
            if (searchRanges.length && window.Highlight && CSS.highlights) {
                CSS.highlights.set('reader-search', new window.Highlight(...searchRanges));
            }
        },
        openResult(index) {
            const range = searchRanges[index];
            if (!range) return;
            this.go(this.pageOfRect(range.getClientRects()[0]));
            this.showPageTop();
            // Dar ekranda panel sayfayı örtüyor: sonuca gidince kapanır (vurgu kalır).
            if (window.innerWidth < 640) this.closeSearch();
        },
    };
}
