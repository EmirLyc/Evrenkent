import '@hotwired/turbo';
import Alpine from 'alpinejs';

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

// Zengin metin editörü (x-rich-editor bileşeni, Faz F1) — bölüm ve makale içeriği.
// Tiptap sadece editör olan sayfada yükleniyor (dinamik import, ayrı chunk). Editör nesnesi
// Alpine'in reaktif verisine konmuyor (Proxy sarmalayıcısı Tiptap'i bozuyor), kapanışta
// duruyor; `tick` sadece araç çubuğundaki aktif durumları yeniden hesaplatmak için.
// Kaydedilecek HTML gizli input'a yazılıyor, form normal POST ile gidiyor.
Alpine.data('richEditor', ({ importUrl = null, titleInput = null, documents = [] } = {}) => {
    let editor = null;

    return {
        documents,
        ready: false,
        tick: 0,
        panel: null,
        panelText: '',
        editingFootnote: false,
        editingVideo: false,
        video: { url: '', title: '', duration: '' },
        panelError: '',
        importing: false,
        importError: '',
        importNotice: '',

        async init() {
            const { createEditor } = await import('./rich-editor.js');
            this.$refs.surface.innerHTML = '';
            editor = createEditor({
                element: this.$refs.surface,
                content: this.$refs.input.value,
                documents,
                onUpdate: (instance) => this.sync(instance),
                onSelection: () => this.tick++,
            });
            this.ready = true;
        },
        destroy() {
            editor?.destroy();
            editor = null;
        },
        sync(instance) {
            this.$refs.input.value = instance.isEmpty ? '' : instance.getHTML();
            this.tick++;
        },
        isActive(name, attributes = {}) {
            this.tick;
            return editor ? editor.isActive(name, attributes) : false;
        },
        can(command) {
            this.tick;
            return editor ? editor.can()[command]() : false;
        },
        run(command, ...args) {
            editor?.chain().focus()[command](...args).run();
        },

        // Bağlantı ve dipnot için araç çubuğunun altında açılan küçük panel (prompt() yerine —
        // mobilde de düzgün çalışsın, dipnot metni uzun olabilsin).
        openPanel(type) {
            if (!editor) return;
            if (type === 'link') {
                this.panelText = editor.getAttributes('link').href || '';
            } else if (type === 'document') {
                this.panel = type;
                return;
            } else if (type === 'video') {
                const selected = editor.state.selection.node;
                this.editingVideo = selected?.type.name === 'videoLink';
                this.video = this.editingVideo
                    ? { url: selected.attrs.url, title: selected.attrs.title, duration: selected.attrs.duration }
                    : { url: '', title: '', duration: '' };
                this.panelError = '';
            } else {
                const selected = editor.state.selection.node;
                this.editingFootnote = selected?.type.name === 'footnote';
                this.panelText = this.editingFootnote ? selected.attrs.text : '';
            }
            this.panel = type;
            this.$nextTick(() => this.$refs.panelInput?.focus());
        },
        savePanel() {
            const text = this.panelText.trim();

            if (this.panel === 'video') {
                const attrs = {
                    url: this.video.url.trim(),
                    title: this.video.title.trim(),
                    duration: this.video.duration.trim(),
                };
                // Sunucu (VideoEmbed) de aynı kontrolü yapıyor; burada yazar hemen uyarılsın diye.
                if (!/^https?:\/\/((www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)|(player\.)?vimeo\.com)\//i.test(attrs.url)) {
                    this.panelError = 'Sadece YouTube ya da Vimeo bağlantısı eklenebilir.';
                    return;
                }
                if (attrs.duration && !/^\d{1,3}(:\d{2}){1,2}$/.test(attrs.duration)) {
                    this.panelError = 'Süreyi dakika:saniye biçiminde yazın (ör. 12:45).';
                    return;
                }
                const chain = editor.chain().focus();
                this.editingVideo
                    ? chain.updateAttributes('videoLink', attrs).run()
                    : chain.insertContent({ type: 'videoLink', attrs }).run();
                this.closePanel();
                return;
            }

            const chain = editor.chain().focus();

            if (this.panel === 'link') {
                if (text) {
                    const href = /^(https?:\/\/|mailto:)/i.test(text) ? text : `https://${text}`;
                    chain.extendMarkRange('link').setLink({ href }).run();
                } else {
                    chain.extendMarkRange('link').unsetLink().run();
                }
            } else if (this.editingFootnote) {
                text ? chain.updateAttributes('footnote', { text }).run() : chain.deleteSelection().run();
            } else if (text) {
                chain.insertContent({ type: 'footnote', attrs: { text } }).run();
            }

            this.closePanel();
        },
        insertDocument(id) {
            editor?.chain().focus().insertContent({ type: 'embeddedDocument', attrs: { id: String(id) } }).run();
            this.closePanel();
        },
        removeSelected() {
            editor?.chain().focus().deleteSelection().run();
            this.closePanel();
        },
        closePanel() {
            this.panel = null;
            this.panelText = '';
            this.panelError = '';
            this.editingFootnote = false;
            this.editingVideo = false;
        },

        async importWord(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file || !importUrl || !editor) return;
            if (!editor.isEmpty && !confirm('Editördeki metin, Word dosyasındaki metinle değiştirilecek. Devam edilsin mi?')) return;

            this.importing = true;
            this.importError = '';
            this.importNotice = '';
            const body = new FormData();
            body.append('file', file);

            try {
                const response = await fetch(importUrl, {
                    method: 'POST',
                    body,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        Accept: 'application/json',
                    },
                });
                const data = await response.json();

                if (!response.ok) {
                    this.importError = data.errors?.file?.[0] ?? 'Dosya aktarılamadı.';
                    return;
                }

                editor.commands.setContent(data.html);
                this.sync(editor);

                const title = titleInput && document.getElementById(titleInput);
                if (title && data.title && !title.value.trim()) {
                    title.value = data.title;
                }
                this.importNotice = 'Word dosyası aktarıldı. Kontrol edip kaydetmeyi unutmayın.';
            } catch {
                this.importError = 'Dosya aktarılamadı. Bağlantınızı kontrol edip tekrar deneyin.';
            } finally {
                this.importing = false;
            }
        },
    };
});

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

// Okuma modu (layouts/reader, Faz F4): yazı boyutu (bu cihazda hatırlanır), okuma ilerleme
// çubuğu, bölümler çekmecesi ve ←/→ ile önceki/sonraki bölüm.
const READER_SIZES = ['1.0625rem', '1.1875rem', '1.3125rem', '1.4375rem'];
Alpine.data('reader', () => ({
    sizes: READER_SIZES,
    size: 1,
    progress: 0,
    drawer: false,

    init() {
        try {
            const saved = parseInt(localStorage.getItem('evrenkent.reader.size'), 10);
            if (saved >= 0 && saved < READER_SIZES.length) this.size = saved;
        } catch {
            // Gizli pencere / engellenmiş depolama: varsayılan boyutla devam.
        }
        this.$nextTick(() => this.trackProgress());
    },
    resize(step) {
        this.size = Math.min(READER_SIZES.length - 1, Math.max(0, this.size + step));
        try {
            localStorage.setItem('evrenkent.reader.size', String(this.size));
        } catch {
            // yok say
        }
        this.$nextTick(() => this.trackProgress());
    },
    trackProgress() {
        if (!this.$root.isConnected) return;
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        this.progress = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 1;
    },
    keyNav(event) {
        // Turbo sonrası temizlenmemiş eski örnek (bkz. documentViewer) olayı almasın.
        if (!this.$root.isConnected || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || this.drawer) return;
        if (event.target.closest?.('input, textarea, select, [contenteditable]') || document.querySelector('[role=dialog]:not([style*="display: none"])')) return;

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
