import '@hotwired/turbo';
import Alpine from 'alpinejs';
import workEditor from './work-editor-component.js';
import pagedReader from './paged-reader.js';

window.Alpine = Alpine;

// Turbo yalnızca <body>'yi değiştirir, JS ortamı sayfalar arası canlı kalır —
// bu yüzden panel sidebar'ının açık/kapalı durumu Alpine.store'da tutulur.
// Böylece "Kitaplığım -> Favorilerim" gibi bir Turbo geçişinde sidebar
// pozisyonunu kaybetmez (aksi halde her body swap'inde x-data sıfırlanırdı).
// Başlangıç değeri ekran genişliğine göre: masaüstünde (lg ve üstü) açık,
// mobilde kapalı — mobilde sidebar artık push değil overlay (bkz.
// layouts/public.blade.php), açık gelmesi ekranın çoğunu kaplardı.
Alpine.store('ui', {
    sidebarOpen: window.matchMedia('(min-width: 1024px)').matches,
});

// Sepet sayacı (header rozeti) ve "sepete eklendi" toast'ı — sayfa yenilenmeden
// (fetch ile) güncellenebilsin diye Alpine store'da tutuluyor. Her Turbo geçişinde
// header'daki x-init sunucudan gelen gerçek sayıyla senkronlar (bkz. layouts/public.blade.php).
Alpine.store('cart', {
    count: 0,
    toast: { visible: false, title: '', url: '' },
    toastTimeout: null,
    showToast(title, url) {
        this.toast.title = title;
        this.toast.url = url;
        this.toast.visible = true;
        clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            this.toast.visible = false;
        }, 4000);
    },
});

// Yayın geri sayımı (x-countdown bileşeni) — Yakında Çıkacaklar kartları ve tanıtım
// sayfaları. Metin sunucuda da üretiliyor; burada sadece canlı güncelleniyor. Turbo bir
// sayfadan çıkarken elementi DOM'dan kaldırınca Alpine destroy()'u çağırıyor, interval sızmıyor.
Alpine.data('countdown', (iso) => ({
    label: '',
    timer: null,
    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    tick() {
        const seconds = Math.floor((new Date(iso) - new Date()) / 1000);

        if (seconds <= 0) {
            this.label = 'Yayına giriyor';
            clearInterval(this.timer);
            return;
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) {
            this.label = `${days} gün ${hours} saat kaldı`;
        } else if (hours > 0) {
            this.label = `${hours} saat ${minutes} dk kaldı`;
        } else {
            this.label = `${minutes} dk ${seconds % 60} sn kaldı`;
        }
    },
}));

// Yeni Yayın editörü (x-work-editor, Faz G2) — bkz. work-editor-component.js.
Alpine.data('workEditor', workEditor);

// Gömülü belge ve video görüntüleyici (x-document-viewer, Faz F2/F3 — mockup 3.1 "tıklayınca
// belge açılır"). Okuma sayfasındaki kar tanesi işaretleri ve panelin Belgeler listesindeki
// "Görüntüle" bağlantıları data-document-viewer taşıyor; tıklama window'da yakalanıp
// bağlantı yerine modal açılıyor (JS yoksa bağlantı dosyayı doğrudan açar). PDF'ler
// pdf.js ile tuvale çiziliyor (pdf-viewer.js, sadece gerektiğinde yüklenir).
Alpine.data('documentViewer', () => {
    let pdf = null;

    return {
        open: false,
        type: null,
        url: '',
        title: '',
        loading: false,
        progress: '',
        error: '',

        handle(event) {
            // Turbo body'yi değiştirince eski örneğin window dinleyicisi her zaman
            // temizlenmiyor; DOM'dan kopmuş örnek tıklamayı alıp preventDefault ederse yeni
            // örnek olayı "işlenmiş" sayıp modalı açmıyordu (form gönderiminden sonra görüldü).
            if (!this.$root.isConnected) return;
            const link = event.target.closest?.('[data-document-viewer]');
            if (!link || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            const type = link.dataset.documentViewer;
            // Video (Faz F3): oynatıcı adresi sunucuda video kimliğinden kuruluyor (VideoEmbed);
            // burada da sadece bu iki oynatıcıya izin veriliyor.
            const url = type === 'video' ? link.dataset.embedUrl : link.getAttribute('href');
            if (type === 'video' && !/^https:\/\/(www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/)/.test(url || '')) return;
            event.preventDefault();
            this.show(url, type, link.dataset.documentTitle || '');
        },
        async show(url, type, title) {
            this.close();
            Object.assign(this, { url, type, title, open: true, error: '', progress: '' });
            document.documentElement.classList.add('overflow-hidden');
            await this.$nextTick();
            this.$refs.close?.focus();

            if (type !== 'pdf') return;

            this.loading = true;
            try {
                const { renderPdf } = await import('./pdf-viewer.js');
                if (!this.open || this.url !== url) return;
                pdf = renderPdf(url, this.$refs.pages, {
                    onPage: (number, total) => {
                        this.loading = false;
                        this.progress = number < total ? `${number} / ${total} sayfa yüklendi` : '';
                    },
                });
                await pdf.done;
            } catch (e) {
                console.error('Belge görüntülenemedi:', e?.name, e?.message ?? e);
                if (this.open) this.error = 'Belge açılamadı. Sayfayı yenileyip tekrar deneyin.';
            } finally {
                this.loading = false;
            }
        },
        close() {
            pdf?.cancel();
            pdf = null;
            if (this.$refs.pages) this.$refs.pages.innerHTML = '';
            this.open = false;
            this.url = '';
            document.documentElement.classList.remove('overflow-hidden');
        },
        destroy() {
            this.close();
        },
    };
});

// Sayfalı okuma (x-paged-reader, Faz G4) — bkz. paged-reader.js. Okuma düzeninin başlığı
// (yakınlaştırma, ilerleme) ve bölümler çekmecesi bu store'dan okuyor.
Alpine.store('pager', {
    active: false,
    page: 0,
    total: 0,
    percent: 100,
    canZoomIn: true,
    canZoomOut: true,
    chapter: null,
    last: false,
    progress: 0,
    location: '',
    // Başlık ve sayfa düğmeleri görünür mü (Faz H1: okurken arayüz çekiliyor).
    chrome: true,
});
Alpine.data('pagedReader', pagedReader);

// Okuma modu (layouts/reader, Faz F4 / H1): okuma ilerleme çubuğu, Aa menüsü (Okuma Görünümü) ve
// ←/→ ile önceki/sonraki bölüm. Okurun yazı boyutu ayarı Faz G4'te kalktı (belge: "punto üzerinde
// değişiklik hakkı olursa tüm kitap dizgisini bozar") — sayfalı okumada sayfa büyütülüyor.
//
// Görünüm tercihleri (tema, gece modu, sözlük kavramları) bu cihazda hatırlanıyor; ilk boyamadan
// önce layouts/reader'daki satır içi betik uyguluyor, burası değişiklikleri.
const READER_PREFS_KEY = 'evrenkent.reader.prefs';
const READER_DEFAULTS = { theme: 'acik', auto: false, concepts: false };
const CHROME_IDLE = 2500;

Alpine.data('reader', () => ({
    progress: 0,
    aa: false,
    prefs: { ...READER_DEFAULTS },
    chromeTimer: null,

    init() {
        try {
            const saved = JSON.parse(localStorage.getItem(READER_PREFS_KEY)) || {};
            this.prefs = {
                theme: ['acik', 'sepya', 'koyu'].includes(saved.theme) ? saved.theme : READER_DEFAULTS.theme,
                auto: saved.auto === true,
                concepts: saved.concepts === true,
            };
        } catch {
            // depolama yok: varsayılanlar
        }
        this.darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.onScheme = () => this.applyPrefs();
        this.darkQuery.addEventListener?.('change', this.onScheme);
        this.applyPrefs();
        this.$store.pager.chrome = true;
        this.$nextTick(() => this.trackProgress());
    },
    destroy() {
        this.darkQuery?.removeEventListener?.('change', this.onScheme);
        clearTimeout(this.chromeTimer);
    },
    applyPrefs() {
        const dark = this.prefs.auto && this.darkQuery?.matches;
        document.body.dataset.readerTheme = dark ? 'koyu' : this.prefs.theme;
        document.body.dataset.concepts = this.prefs.concepts ? 'on' : 'off';
    },
    savePrefs() {
        try {
            localStorage.setItem(READER_PREFS_KEY, JSON.stringify(this.prefs));
        } catch {
            // yok say
        }
        this.applyPrefs();
    },
    setTheme(theme) {
        this.prefs.theme = theme;
        this.savePrefs();
    },
    setPref(key, value) {
        this.prefs[key] = value;
        this.savePrefs();
    },
    resetPrefs() {
        this.prefs = { ...READER_DEFAULTS };
        this.savePrefs();
        window.dispatchEvent(new CustomEvent('pager-zoom-reset'));
    },

    // --- Arayüzün çekilmesi (sadece sayfalı okumada) --------------------------------------------
    showChrome() {
        this.$store.pager.chrome = true;
        clearTimeout(this.chromeTimer);
    },
    hideChrome() {
        if (!this.$root.isConnected || !this.$store.pager.active || this.aa) return;
        clearTimeout(this.chromeTimer);
        this.$store.pager.chrome = false;
    },
    toggleChrome() {
        if (!this.$root.isConnected) return;
        this.$store.pager.chrome ? this.hideChrome() : this.showChrome();
    },
    // Fare kıpırdayınca arayüz geri gelir, bir süre durunca çekilir; menü açıkken ya da imleç
    // başlığın / sayfa düğmelerinin üzerindeyken kalır.
    pointerMoved(event) {
        if (!this.$root.isConnected || event.pointerType !== 'mouse' || !this.$store.pager.active) return;
        this.showChrome();
        this.scheduleHide();
    },
    scheduleHide() {
        clearTimeout(this.chromeTimer);
        this.chromeTimer = setTimeout(() => {
            const busy = this.aa || Array.from(document.querySelectorAll('[data-reader-chrome]'))
                .some((element) => element.matches(':hover') || element.contains(document.activeElement));
            busy ? this.scheduleHide() : this.hideChrome();
        }, CHROME_IDLE);
    },
    trackProgress() {
        if (!this.$root.isConnected) return;
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        this.progress = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 1;
    },
    keyNav(event) {
        // Turbo sonrası temizlenmemiş eski örnek (bkz. documentViewer) olayı almasın.
        // Sayfalı okumada oklar sayfa çeviriyor (paged-reader.js).
        if (!this.$root.isConnected || this.$store.pager.active || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || this.aa) return;
        if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
        if (Array.from(document.querySelectorAll('[role=dialog]')).some((dialog) => dialog.getClientRects().length > 0)) return;

        const link = event.key === 'ArrowLeft'
            ? document.querySelector('[data-reader-prev]')
            : event.key === 'ArrowRight' ? document.querySelector('[data-reader-next]') : null;

        if (link) {
            event.preventDefault();
            window.Turbo ? window.Turbo.visit(link.href) : (window.location.href = link.href);
        }
    },
}));

// Sidebar scroll pozisyonu — Turbo her geçişte <body>'yi (dolayısıyla <aside>'ı)
// baştan render ediyor, bu yüzden aşağı kaydırıp bir linke tıklayınca sidebar
// görsel olarak "sıfırlanıp" en başa dönüyordu. scroll event'i bubble etmediği
// için document üzerinde capture:true ile dinleniyor; pozisyon düz bir JS
// değişkeninde tutuluyor (bu da Turbo geçişleri arasında canlı kalıyor, aynı
// yukarıdaki store'lar gibi). Sidebar içeriği (aktif link vurgusu, rozet
// sayıları) yine sunucudan taze geliyor — data-turbo-permanent gibi tüm
// elementi "donduran" bir yöntem kullanılmadı, o zaman hem aktif link vurgusu
// hem "Onay Bekleyenler" rozeti bir önceki sayfadan kalma/bayat kalırdı.
//
// Sadece scrollTop'u turbo:render'da (turbo:load'dan önce) düzeltmek yeterli
// olmadı: Turbo'nun kendi render() döngüsü yeni <body>'yi takmadan önce ve
// sonra en az bir kere nextRepaint() ile tarayıcıya boyama fırsatı veriyor,
// yani hangi event'i dinlersek dinleyelim JS'imiz çalışana kadar tarayıcı
// scrollTop=0 olan taze hâli zaten bir kere boyamış oluyordu (kullanıcının
// bildirdiği "saniyelik en üste gelip düzelme"). Çözüm: yarışı kazanmaya
// çalışmak yerine yanlış durumun hiç boyanmasını engellemek — sidebar,
// yeni body takılırken .js-restoring-scroll ile gizleniyor (bkz. app.css),
// scrollTop doğru değere ayarlanır ayarlanmaz aynı JS görünür kılıyor.
let sidebarScrollTop = 0;
document.addEventListener('scroll', (event) => {
    if (event.target?.classList?.contains('sidebar-scroll')) {
        sidebarScrollTop = event.target.scrollTop;
    }
}, true);
document.addEventListener('turbo:before-render', (event) => {
    const incomingSidebar = event.detail.newBody?.querySelector?.('.sidebar-scroll');
    if (incomingSidebar) {
        incomingSidebar.classList.add('js-restoring-scroll');
    }
});
document.addEventListener('turbo:render', () => {
    const sidebar = document.querySelector('.sidebar-scroll');
    if (sidebar) {
        sidebar.scrollTop = sidebarScrollTop;
        sidebar.classList.remove('js-restoring-scroll');
    }
});

// Turbo <body>'yi değiştirirken eski sayfanın Alpine bileşenleri her zaman temizlenmiyordu:
// window dinleyicileri (@click.window, @keydown.window) ve destroy() kancaları (Tiptap
// editörü, geri sayım interval'i, PDF yüklemesi) kopmuş DOM'da yaşamaya devam ediyordu
// (belge görüntüleyicide "Görüntüle" tepkisiz kalınca görüldü). Yeni body takılmadan önce eski
// ağaç açıkça yok ediliyor; Alpine'in kendi temizliğiyle çakışmaz (işlem tekrarlanabilir).
document.addEventListener('turbo:before-render', () => {
    Alpine.destroyTree(document.body);
});

Alpine.start();
