// Sayfalı okuma (Faz G4, x-paged-reader). Metin sabit ölçüde bir sayfanın içinde CSS sütunlarıyla
// akıyor: her sütun bir sayfa (sütun aralığı iki kenar boşluğu, böylece bir sayfa kaydırması tam
// sayfa genişliği). Okur puntoyu değil sayfayı büyütüyor — dizgi her cihazda aynı kalıyor.
//
// Durum Alpine.store('pager')'da da tutuluyor: okuma düzeninin başlığı (yakınlaştırma, ilerleme
// çubuğu), bölümler çekmecesi ve not formu buradan okuyor.

const ZOOMS = [0.75, 1, 1.25, 1.5, 2, 2.5, 3];
const ZOOM_KEY = 'evrenkent.reader.zoom';
const MAX_SCALE = 2.5;

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

export default function pagedReader(config) {
    const PAGE = config.pageWidth;
    let relayoutTimer = null;
    let positionTimer = null;
    let touch = null;

    return {
        page: 0,
        total: 1,
        scale: 1,
        zoomIndex: 1,
        ready: false,
        starts: [],
        chapter: null,
        chapterTitle: '',

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
            // "yerinde oku", bölümler çekmecesi) Turbo'dan önce sayfaya çevriliyor.
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
        layout() {
            const flow = this.$refs.flow;
            this.total = Math.max(1, Math.round((flow.scrollWidth + (PAGE - flow.clientWidth)) / PAGE));
            this.starts = Array.from(flow.querySelectorAll('[data-chapter]')).map((element) => ({
                order: Number(element.dataset.chapter),
                title: element.dataset.title || '',
                page: this.pageOf(element),
            }));
        },
        // Öğenin bulunduğu sayfa: ilk parçasının sütunu (kaydırma ve yakınlaştırmadan bağımsız).
        pageOf(element) {
            const base = this.$refs.flow.getBoundingClientRect().left;
            const rect = element.getClientRects()[0] || element.getBoundingClientRect();
            const page = Math.floor((rect.left - base) / (PAGE * this.scale) + 0.01);
            return Math.min(this.total - 1, Math.max(0, page));
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
        next() {
            if (this.page < this.total - 1) {
                this.go(this.page + 1);
                this.showPageTop();
            }
        },
        prev() {
            if (this.page > 0) {
                this.go(this.page - 1);
                this.showPageTop();
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
                location: `Sayfa ${this.page + 1}${this.chapterTitle ? ` · ${this.chapterTitle}` : ''}`,
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
            if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
            if (document.querySelector('[role=dialog]:not([style*="display: none"])')) return;
            if (['ArrowRight', 'PageDown'].includes(event.key)) {
                event.preventDefault();
                this.next();
            } else if (['ArrowLeft', 'PageUp'].includes(event.key)) {
                event.preventDefault();
                this.prev();
            }
        },
        link(event) {
            if (!this.$root.isConnected || !this.ready || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            const anchor = event.target.closest?.('a[href]');
            if (!anchor || 'documentViewer' in anchor.dataset || anchor.target === '_blank') return;

            const url = new URL(anchor.getAttribute('href'), window.location.href);
            if (url.origin !== window.location.origin) return;
            const base = config.readBase ? new URL(config.readBase, window.location.href).pathname : null;
            const inBook = base && (url.pathname === base || url.pathname.startsWith(`${base}/`));
            const samePage = url.pathname === window.location.pathname;
            if (!inBook && !samePage) return;

            const hash = decodeURIComponent(url.hash.slice(1));
            const target = hash ? document.getElementById(hash) : null;
            let page = null;
            if (target && this.$refs.flow.contains(target)) {
                page = this.pageOf(target);
            } else if (inBook) {
                const order = parseInt(url.pathname.slice(base.length + 1), 10);
                page = this.starts.find((item) => item.order === order)?.page ?? null;
            }
            if (page === null) return;

            event.preventDefault();
            event.stopPropagation();
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
    };
}
