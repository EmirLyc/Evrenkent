// Sayfalı okuma (Faz G4, x-paged-reader). Metin sabit ölçüde bir sayfanın içinde CSS sütunlarıyla
// akıyor: her sütun bir sayfa (sütun aralığı iki yan boşluk, böylece bir sayfa kaydırması tam
// sayfa genişliği). Okur puntoyu değil sayfayı büyütüyor — dizgi her cihazda aynı kalıyor.
//
// Faz H1 ("Okurun Gözünden"): İçindekiler çekmecesi (sayfa numaraları, okunan yer, "Sayfaya
// git"), kitap içi arama ve giriş bölümünün roma rakamlı sayfaları.
//
// Durum Alpine.store('pager')'da da tutuluyor: okuma düzeninin başlığı (Aa menüsündeki
// yakınlaştırma, ilerleme çubuğu, arayüzün gizlenmesi) ve not formu buradan okuyor.
//
// Faz H6 — telefon: sayfa her cihazda aynı (aynı kelimeyle başlar, aynı kelimeyle biter; "s. 24"
// her yerde aynı yer), ama 544 px'lik satırlar telefona ancak okunmayacak kadar küçülerek sığıyor.
// Dar ekranda sayfa sınırları yine sayfalar dizilmişken ölçülüyor (measureSheets), sonra metin
// ekran genişliğinde akıyor ve sınırlara .rt-sheet ayracı giriyor: sayfalar alt alta kâğıtlar,
// her birinin boyu içeriği kadar, altında kendi numarası. Okur dikey kaydırıyor; o an okunan sayfa
// kaydırmadan izleniyor.

import readingMarks from './reading-marks.js';

const ZOOMS = [0.75, 1, 1.25, 1.5, 2, 2.5, 3];
const DEFAULT_ZOOM = 1;
const ZOOM_KEY = 'evrenkent.reader.zoom';
const MAX_SCALE = 2.5;
const MAX_RESULTS = 200;
// Aramada tek parça sayılan bloklar (içinde başka blok olmayanlar aranıyor).
const BLOCKS = 'p, li, h1, h2, h3, h4, blockquote, td, th, figcaption, .rt-opener-title';

// Telefon görünümü bu genişliğin altında (Tailwind sm). Ayraç ölçüleri app.css .rt-sheet ile aynı:
// yeni kâğıt ayracın üstünden 60 px aşağıda başlıyor, sınır şeridin ortası (52 px).
const FLOW_BELOW = 640;
const SHEET_START = 60;
const SHEET_EDGE = 52;
const HEADER_OFFSET = 64;
// Metni olmayan ama sayfada yer tutan öğeler (sayfa bunlardan biriyle başlayabilir).
const ATOMIC = 'img, svg, video, iframe, canvas, hr:not(.rt-page-break)';
const SHEET_SKIP = '.rt-note-pin, .rt-sheet, .document-tooltip, [hidden]';

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

// Öğeden önce okunur bir şey yok mu (boşluk, boş öğe ve sayfa ayraçları sayılmıyor).
function atStart(node) {
    for (let sibling = node.previousSibling; sibling; sibling = sibling.previousSibling) {
        if (sibling.nodeType === Node.TEXT_NODE) {
            if (sibling.nodeValue.trim()) return false;
        } else if (sibling.nodeType === Node.ELEMENT_NODE && !sibling.matches(SHEET_SKIP)) {
            if (sibling.textContent.trim() || sibling.matches(ATOMIC) || sibling.querySelector(ATOMIC)) return false;
        }
    }
    return true;
}

// Sayfa ayracını (telefon) sayfanın ilk karakterinin / öğesinin önüne koyar. Sayfa bir blokun ya
// da satır içi öğenin başında başlıyorsa ayraç onun önünde (yeni kâğıt paragrafla, madde imiyle
// başlasın); paragrafın ortasındaysa metin orada bölünüyor. Listede <li>, tabloda <tr> olarak.
function placeSheet(flow, point, ending, starting) {
    let parent = flow;
    let ref = null;
    if (point.node) {
        ref = point.node.nodeType === Node.TEXT_NODE && point.offset > 0 ? point.node.splitText(point.offset) : point.node;
        parent = ref.parentNode;
        while (parent !== flow && atStart(ref)) {
            ref = parent;
            parent = parent.parentNode;
        }
        if (parent.tagName === 'TR') {
            ref = parent;
            parent = parent.parentNode;
        }
        // Aynı yerde başlayan boş sayfa: sonraki sayfanın ayracı zaten burada — onun önüne.
        while (ref.previousSibling?.classList?.contains('rt-sheet')) ref = ref.previousSibling;
    }
    let sheet;
    if (parent.tagName === 'OL' || parent.tagName === 'UL') {
        sheet = document.createElement('li');
    } else if (['TABLE', 'TBODY', 'THEAD', 'TFOOT'].includes(parent.tagName)) {
        sheet = document.createElement('tr');
        const cell = sheet.insertCell();
        cell.colSpan = 99;
        cell.dataset.label = ending;
    } else {
        sheet = document.createElement('span');
    }
    sheet.className = 'rt-sheet';
    sheet.dataset.label = ending;
    sheet.setAttribute('role', 'separator');
    sheet.setAttribute('aria-label', `Sayfa ${starting}`);
    parent.insertBefore(sheet, ref);
    return sheet;
}

export default function pagedReader(config) {
    const PAGE = config.pageWidth;
    let relayoutTimer = null;
    let positionTimer = null;
    let sentPage = null;
    let pendingPosition = null;
    let touch = null;
    // Arama sonuçlarının Range'leri reaktif olmayan yerde (Alpine vekili yerel nesneleri bozar).
    let searchRanges = [];
    // Telefonda sayfa ayraçları (sheets[n]: n. sayfanın başı; 0. sayfanın ayracı yok), son hizalanan
    // ekran genişliği ve kaydırma izleme.
    let sheets = [];
    let flowWidth = 0;
    let scrollFrame = null;
    let lastScrollY = 0;

    return {
        // Alıntıla / Not Al / Fosforla (Faz H3) — bkz. reading-marks.js.
        ...readingMarks(config),

        page: 0,
        total: 1,
        front: 0,
        scale: 1,
        zoomIndex: DEFAULT_ZOOM,
        ready: false,
        flowing: false,
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
            this.onScroll = () => this.scrolled();
            this.onKey = (event) => this.key(event);
            this.onClick = (event) => this.link(event);
            this.onFonts = () => this.scheduleRelayout();
            // Sayfadan çıkarken son sayfa hemen kaydedilsin: Turbo geçişinde yeni sayfa istenmeden
            // önce (yoksa Kitaplığım bir önceki yüzdeyi gösterebiliyordu), sekme kapanırken de.
            this.onPageHide = () => this.flushPosition();
            window.addEventListener('pagehide', this.onPageHide);
            document.addEventListener('turbo:before-visit', this.onPageHide);
            window.addEventListener('resize', this.onResize);
            window.addEventListener('scroll', this.onScroll, { passive: true });
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
                    // İşaretler önce: adres bir işareti gösteriyorsa (#isaret-12) o sayfa açılsın.
                    this.renderMarks();
                    this.openInitial();
                });
            });
            this.initMarks();
        },
        destroy() {
            window.removeEventListener('pagehide', this.onPageHide);
            document.removeEventListener('turbo:before-visit', this.onPageHide);
            window.removeEventListener('resize', this.onResize);
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('keydown', this.onKey);
            window.removeEventListener('click', this.onClick, true);
            document.fonts?.removeEventListener?.('loadingdone', this.onFonts);
            clearTimeout(relayoutTimer);
            cancelAnimationFrame(scrollFrame);
            this.flushPosition();
            CSS.highlights?.delete('reader-search');
            this.destroyMarks();
            this.$store.pager.active = false;
        },

        // --- Ölçü ---------------------------------------------------------------------------
        // Ekran genişliği değişince: sayfa ölçüsü; telefon ↔ geniş ekran geçişinde sayfalar yeniden.
        fit() {
            if (this.ready && this.wantsFlow() !== this.flowing) {
                this.relayout();
                return;
            }
            if (this.flowing) {
                // Telefonda yükseklik değişimi (adres çubuğu, klavye) satırları değiştirmez.
                if (window.innerWidth !== flowWidth) {
                    flowWidth = window.innerWidth;
                    this.alignSheets();
                    this.placePins();
                }
            } else {
                this.scale = this.pageScale();
            }
            this.sync();
        },
        pageScale() {
            const width = this.$refs.viewport.clientWidth;
            return Math.min(MAX_SCALE, Math.min(1, width / PAGE) * ZOOMS[this.zoomIndex]);
        },
        wantsFlow() {
            return window.innerWidth < FLOW_BELOW;
        },
        zoom(step) {
            if (this.flowing) return;
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
        // Sayfalar her zaman dizilmiş hâlde (544 px sütunlar) ölçülüyor; telefonda ardından akışa geçiliyor.
        layout() {
            this.leaveFlow();
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
            if (this.wantsFlow()) this.enterFlow();
        },
        // Öğenin bulunduğu sayfa: ilk parçasının sütunu (kaydırma ve yakınlaştırmadan bağımsız);
        // telefonda üstündeki son sayfa ayracı.
        pageOf(element) {
            return this.pageOfRect(element.getClientRects()[0] || element.getBoundingClientRect());
        },
        pageOfRect(rect) {
            if (!rect) return 0;
            if (this.flowing) return this.pageAtY(rect.top + Math.min(rect.height, 24) / 2);
            const base = this.$refs.flow.getBoundingClientRect().left;
            // Sayfa genişliği ekranda ölçülerek (yakınlaştırma henüz çizilmemiş olabilir).
            const unit = this.$refs.page.getBoundingClientRect().width || PAGE * this.scale;
            const page = Math.floor((rect.left - base) / unit + 0.01);
            return Math.min(this.total - 1, Math.max(0, page));
        },

        // --- Telefon: alt alta sayfalar (Faz H6) ----------------------------------------------
        // Her sayfanın (1'den) ilk karakterinin ya da metinsiz öğesinin yeri: { node, offset }
        // (sayfa metinden sonra boş kaldıysa node null). Sayfalar dizilmişken ölçülüyor.
        measureSheets() {
            const flow = this.$refs.flow;
            const total = this.total;
            const unit = this.$refs.page.getBoundingClientRect().width;
            const base = flow.getBoundingClientRect().left;
            const pageAt = (rect) => Math.min(total - 1, Math.max(0, Math.floor((rect.left - base) / unit + 0.01)));
            const visible = (list) => Array.from(list).filter((rect) => rect.width > 0 || rect.height > 0);
            const range = document.createRange();
            // Karakterin sayfası (görünmeyen boşlukta bir sonraki görünen karakterinki).
            const charPage = (node, index) => {
                for (let at = index; at < node.length; at++) {
                    range.setStart(node, at);
                    range.setEnd(node, at + 1);
                    const rect = visible(range.getClientRects())[0];
                    if (rect) return pageAt(rect);
                }
                return null;
            };
            const walker = document.createTreeWalker(flow, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    if (node.nodeType === Node.TEXT_NODE) return node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
                    if (node.matches(SHEET_SKIP)) return NodeFilter.FILTER_REJECT;
                    return node.matches(ATOMIC) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
                },
            });
            const points = [];
            let next = 1;
            for (let node = walker.nextNode(); node && next < total; node = walker.nextNode()) {
                const text = node.nodeType === Node.TEXT_NODE;
                if (text) range.selectNodeContents(node);
                const rects = visible(text ? range.getClientRects() : node.getClientRects());
                if (!rects.length) continue;
                const first = pageAt(rects[0]);
                const last = pageAt(rects[rects.length - 1]);
                // Öğe yeni bir sayfada başlıyor (aradaki boş sayfalar da burada başlıyor).
                while (next <= first && next < total) points[next++] = { node, offset: 0 };
                if (!text) continue;
                // Paragraf sayfadan taşıyor: sonraki sayfanın ilk karakteri (ikili arama).
                while (next <= last && next < total) {
                    let low = 0;
                    let high = node.length - 1;
                    while (low < high) {
                        const middle = (low + high) >> 1;
                        const page = charPage(node, middle);
                        if (page !== null && page >= next) high = middle;
                        else low = middle + 1;
                    }
                    points[next++] = { node, offset: low };
                }
            }
            while (next < total) points[next++] = { node: null, offset: 0 };
            return points;
        },
        enterFlow() {
            const flow = this.$refs.flow;
            const points = this.measureSheets();
            sheets = [];
            // Metnin sonrasında kalan boş sayfalar sırayla sona; ötekiler sondan başa (aynı metin
            // düğümünde bölünen yerler kaymasın).
            points.forEach((point, page) => {
                if (page > 0 && !point.node) sheets[page] = placeSheet(flow, point, this.label(page - 1), this.label(page));
            });
            for (let page = points.length - 1; page > 0; page--) {
                if (points[page].node) sheets[page] = placeSheet(flow, points[page], this.label(page - 1), this.label(page));
            }
            this.$root.classList.add('is-flowing');
            this.flowing = true;
            this.$store.pager.flowing = true;
            this.scale = 1;
            flowWidth = window.innerWidth;
            this.alignSheets();
        },
        leaveFlow() {
            sheets.forEach((sheet) => {
                const parent = sheet?.parentNode;
                if (!parent) return;
                sheet.remove();
                parent.normalize();
            });
            sheets = [];
            this.$root.classList.remove('is-flowing');
            this.flowing = false;
            this.$store.pager.flowing = false;
        },
        // Ayraç (kâğıtlar arası şerit) paragrafın, alıntının, listenin içinde de olsa kâğıt boyunca.
        alignSheets() {
            const paper = this.$refs.page.getBoundingClientRect();
            const blocks = sheets.filter((sheet) => sheet && sheet.tagName !== 'TR');
            blocks.forEach((sheet) => {
                sheet.style.marginLeft = '';
                sheet.style.width = '';
            });
            const lefts = blocks.map((sheet) => sheet.getBoundingClientRect().left);
            blocks.forEach((sheet, index) => {
                sheet.style.marginLeft = `${paper.left - lefts[index]}px`;
                sheet.style.width = `${paper.width}px`;
            });
        },
        // Ekranda y yüksekliğindeki sayfa: üstünde kalan son ayraç.
        pageAtY(y) {
            let low = 1;
            let high = sheets.length - 1;
            let found = 0;
            while (low <= high) {
                const middle = (low + high) >> 1;
                const top = sheets[middle] ? sheets[middle].getBoundingClientRect().top + SHEET_EDGE : Infinity;
                if (top <= y) {
                    found = middle;
                    low = middle + 1;
                } else {
                    high = middle - 1;
                }
            }
            return found;
        },
        // Sayfanın kâğıdının belgedeki üst kenarı.
        sheetTop(page) {
            const sheet = page > 0 ? sheets[page] : null;
            const top = sheet ? sheet.getBoundingClientRect().top + SHEET_START : this.$refs.page.getBoundingClientRect().top;
            return top + window.scrollY;
        },
        // Okur kaydırdıkça: okunan sayfa (başlığın hemen altındaki satırın sayfası — sayfaya gidince
        // kısa bir sayfa da okunan sayfa olarak kalsın), aşağı inerken arayüz çekiliyor.
        scrolled() {
            if (!this.flowing || !this.ready || scrollFrame) return;
            scrollFrame = requestAnimationFrame(() => {
                scrollFrame = null;
                if (!this.$root.isConnected || !this.flowing) return;
                const y = window.scrollY;
                const delta = y - lastScrollY;
                lastScrollY = y;
                this.follow();
                if (delta > 6 && y > 120) this.turned();
                else if (delta < -6) window.dispatchEvent(new CustomEvent('pager-reveal'));
            });
        },
        follow() {
            const bottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
            const page = bottom ? this.total - 1 : this.pageAtY(HEADER_OFFSET + 40);
            if (page === this.page) return;
            this.page = page;
            this.sync();
            storage.set(config.storageKey, { page: this.page, chapter: this.chapter });
        },
        // Telefonda bir yere gitmek (dipnot, arama sonucu, işaret): o satır başlığın hemen altında
        // (okunan sayfa da onun sayfası olsun — follow aynı çizgiye bakıyor).
        reveal(target) {
            const rect = target instanceof Element ? (target.getClientRects()[0] || target.getBoundingClientRect()) : target;
            if (!rect) return;
            this.dismissMarks();
            window.scrollTo({ top: Math.max(0, window.scrollY + rect.top - HEADER_OFFSET - 56), behavior: 'instant' });
            this.follow();
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
            relayoutTimer = setTimeout(() => this.relayout(), 150);
        },
        // Telefonda okur sayfanın içinde de aynı yerde kalıyor (sayfanın başından uzaklık).
        relayout() {
            if (!this.ready || !this.$root.isConnected) return;
            clearTimeout(relayoutTimer);
            const current = this.currentStart();
            const offset = this.page - (current?.page ?? 0);
            const within = this.flowing ? window.scrollY - this.sheetTop(this.page) : null;
            this.layout();
            if (!this.flowing) this.scale = this.pageScale();
            const start = this.starts.find((item) => item.order === current?.order);
            this.go((start?.page ?? 0) + offset, { remember: false, scroll: false });
            if (this.flowing) {
                window.scrollTo({ top: Math.max(0, this.sheetTop(this.page) + (within ?? -HEADER_OFFSET)), behavior: 'instant' });
            } else if (within !== null) {
                this.showPageTop();
            }
            // Yakınlaştırma çizildikten sonra (not simgeleri sayfa ölçüsüyle yerleşiyor).
            this.$nextTick(() => this.placePins());
        },

        // --- Gezinme ------------------------------------------------------------------------
        openInitial() {
            const hash = decodeURIComponent(window.location.hash.slice(1));
            const target = hash ? document.getElementById(hash) : null;
            this.$refs.clip.scrollLeft = 0;
            if (target && this.$refs.flow.contains(target)) {
                if (this.flowing) {
                    this.reveal(target);
                } else {
                    this.go(this.pageOf(target));
                    this.showPageTop();
                }
                // Alıntıdan / nottan gelindiyse yer kısa bir süre belirginleşir.
                if (target.matches('mark[data-mark]')) {
                    const marks = this.$refs.flow.querySelectorAll(`mark[data-mark="${target.dataset.mark}"]`);
                    marks.forEach((mark) => mark.classList.add('is-flash'));
                    setTimeout(() => marks.forEach((mark) => mark.classList.remove('is-flash')), 2400);
                }
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
        // Telefonda sayfaya gitmek = o sayfanın kâğıdının başına kaydırmak.
        go(page, { remember = true, scroll = true } = {}) {
            const target = Math.min(this.total - 1, Math.max(0, page));
            if (target !== this.page) this.dismissMarks();
            this.page = target;
            if (this.flowing && scroll) {
                window.scrollTo({ top: target > 0 ? Math.max(0, this.sheetTop(target) - HEADER_OFFSET) : 0, behavior: 'instant' });
            }
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
            if (this.flowing) return;
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
            if (this.ready && this.page !== sentPage) this.savePosition();
        },
        // Adres çubuğu okunan bölümü göstersin (yenileyince aynı yere dönülür).
        chapterChanged() {
            if (config.readBase && this.chapter) {
                window.history.replaceState(window.history.state, '', `${config.readBase}/${this.chapter}`);
            }
        },
        // Okuma listesindeki konum: bölüm ("kaldığın yerden devam et") ve sayfa / toplam sayfa
        // (Kitaplığım'daki "%45 okundu"). Hızlı çevirmede tek istek.
        savePosition() {
            if (!config.positionUrl || !this.chapter) return;
            clearTimeout(positionTimer);
            pendingPosition = JSON.stringify({ bolum: this.chapter, sayfa: this.page, toplam: this.total });
            sentPage = this.page;
            positionTimer = setTimeout(() => this.flushPosition(), 1200);
        },
        // Bekleyen konumu hemen gönderir (sayfadan çıkarken de — keepalive istek sayfa gitse de tamamlanır).
        flushPosition() {
            clearTimeout(positionTimer);
            if (!pendingPosition) return;
            const body = pendingPosition;
            pendingPosition = null;
            fetch(config.positionUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body,
            }).catch(() => {});
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

            const href = anchor.getAttribute('href');
            const page = this.pageForUrl(href);
            if (page === null) return;

            event.preventDefault();
            event.stopPropagation();
            this.tocOpen = false;
            // Telefonda sayfanın başı değil, çapanın kendisi (sayfa iki üç ekran boyu olabilir).
            const target = this.flowing ? this.anchorTarget(href) : null;
            if (target) {
                this.reveal(target);
            } else {
                this.go(page);
                this.showPageTop();
            }
            window.dispatchEvent(new CustomEvent('pager-navigated'));
        },
        anchorTarget(href) {
            const hash = decodeURIComponent(new URL(href, window.location.href).hash.slice(1));
            const target = hash ? document.getElementById(hash) : null;
            return target && this.$refs.flow.contains(target) ? target : null;
        },

        // Kaydırma ile sayfa çevirme (dokunmatik). Sayfa ekrandan büyükken yatay kaydırma
        // sayfayı gezmek için — çevirmez. Telefonda sayfalar dikey kaydırılıyor, yatay çevirme yok.
        touchStart(event) {
            touch = event.touches.length === 1 && !this.flowing ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
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
            if (!this.ready || event.target.closest('a, button, input, textarea, mark[data-mark], [data-document-viewer]')) return;
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
            if (this.flowing) {
                this.reveal(range.getBoundingClientRect());
            } else {
                this.go(this.pageOfRect(range.getClientRects()[0]));
                this.showPageTop();
            }
            // Dar ekranda panel sayfayı örtüyor: sonuca gidince kapanır (vurgu kalır).
            if (window.innerWidth < 640) this.closeSearch();
        },
    };
}
