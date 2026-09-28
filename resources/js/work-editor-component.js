// Alpine 'workEditor' bileşeni (x-work-editor, Faz G2) — "Yazarın Gözünden" 1.1.1–1.1.6'daki
// Yeni Yayın sayfasının editörü. Tiptap work-editor.js'te, sadece editör sayfasında yükleniyor.
// Editör nesnesi Alpine'in reaktif verisine konmuyor (Proxy Tiptap'i bozuyor); `tick` araç
// çubuğundaki etkin durumları yeniden hesaplatmak için.
//
// İki kullanım: 'autosave' (yazarın editör sayfası — değişiklikler kendiliğinden JSON ile
// kaydedilir, "Taslak kaydedildi · tarih") ve 'form' (Süper Admin'in makale formu — HTML gizli
// alana yazılır, form normal POST ile gider).

const RATIOS = { '13x20': [13, 20], '13x21': [13, 21], '16x24': [16, 24], '21x27.5': [21, 27.5] };
// Sayfa ölçüsü her cihazda aynı (belge: dizgi okurun ekranına göre bozulmasın): 736 px genişlik,
// 56 px kenar boşluğu → 624 px metin genişliği. Okuma sayfası (Faz G4) da bu ölçüyle sayfalar.
export const PAGE_WIDTH = 736;
export const PAGE_PADDING = 56;

export default function workEditor(config) {
    let editor = null;
    let module = null;
    let saveTimer = null;
    let measureTimer = null;
    const documents = [...(config.documents || [])];
    const images = [...(config.images || [])];

    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;

    return {
        ready: false,
        tick: 0,
        menu: null,
        panel: null,
        panelError: '',
        panelText: '',
        editingFootnote: false,
        editingVideo: false,
        video: { url: '', title: '', duration: '' },
        cite: { text: '' },
        image: { caption: '', busy: false },
        docForm: { title: '', date: '', busy: false },
        customSize: '',
        moreFonts: false,
        documents: [...documents],
        sources: [],
        ratio: config.ratio || '13x20',
        numbering: config.numbering !== false,
        saveState: 'saved',
        savedLabel: config.savedLabel || '',
        stats: { pages: 0, words: 0, chars: 0, entries: 0 },
        concept: { query: '', results: [], busy: false, searched: false, current: '' },
        pageBreaks: [],
        bubble: { show: false, top: 0, left: 0 },
        importing: false,
        importError: '',
        importNotice: '',

        async init() {
            module = await import('./work-editor.js');
            this.$refs.surface.innerHTML = '';
            editor = module.createWorkEditor({
                element: this.$refs.surface,
                content: this.$refs.input.value,
                editable: config.editable !== false,
                getDocuments: () => documents,
                getImages: () => images,
                onUpdate: () => this.changed(),
                onSelection: () => this.selectionChanged(),
            });
            this.ready = true;
            this.refresh();

            this.beforeVisit = (event) => {
                if (this.saveState !== 'dirty' || config.mode !== 'autosave' || !this.$root.isConnected) return;
                event.preventDefault();
                const url = event.detail.url;
                this.save().finally(() => window.Turbo.visit(url));
            };
            this.beforeUnload = (event) => {
                if (['dirty', 'saving', 'error'].includes(this.saveState) && config.mode === 'autosave') {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            document.addEventListener('turbo:before-visit', this.beforeVisit);
            window.addEventListener('beforeunload', this.beforeUnload);
            this.resizeObserver = new ResizeObserver(() => this.scheduleMeasure());
            this.resizeObserver.observe(this.$refs.paper);
        },
        destroy() {
            document.removeEventListener('turbo:before-visit', this.beforeVisit);
            window.removeEventListener('beforeunload', this.beforeUnload);
            this.resizeObserver?.disconnect();
            clearTimeout(saveTimer);
            clearTimeout(measureTimer);
            editor?.destroy();
            editor = null;
        },

        // --- Durum -----------------------------------------------------------------------
        changed() {
            this.$refs.input.value = editor.isEmpty ? '' : editor.getHTML();
            this.refresh();
            if (config.mode === 'autosave') {
                this.saveState = 'dirty';
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => this.save(), 1500);
            }
        },
        refresh() {
            this.tick++;
            const { sources } = module.refreshNumbers(editor, this.numbering);
            this.sources = sources;
            const text = editor.state.doc.textContent;
            this.stats.words = (text.trim().match(/\S+/g) || []).length;
            this.stats.chars = text.length;
            if (config.dictionary) {
                let entries = 0;
                editor.state.doc.forEach((node) => {
                    if (node.type.name === 'concept' && node.textContent.trim()) entries++;
                });
                this.stats.entries = entries;
            }
            this.scheduleMeasure();
        },
        selectionChanged() {
            this.tick++;
            this.placeBubble();
        },
        scheduleMeasure() {
            clearTimeout(measureTimer);
            measureTimer = setTimeout(() => this.measure(), 250);
        },
        // Sayfa sayısı ve sayfa çizgileri: sabit sayfa ölçüsünde (PAGE_WIDTH) ve seçilen oranda.
        // Metin gizli bir ölçüm kutusunda o genişlikte dizilip sayılıyor — böylece mobilde de
        // masaüstündeki (ve okurdaki) sayfa sayısı çıkıyor. Çizgiler sadece kâğıt tam genişlikteyken.
        measure() {
            if (!editor || !this.$refs.paper?.isConnected || !this.$refs.measurer) return;
            const [w, h] = RATIOS[this.ratio] || RATIOS['13x20'];
            const pageHeight = PAGE_WIDTH * (h / w);
            const target = this.$refs.measurer.firstElementChild;
            target.innerHTML = editor.view.dom.innerHTML;
            const { pages, breaks } = module.paginate(target, pageHeight - PAGE_PADDING * 2);
            this.stats.pages = pages;
            const exact = Math.abs(editor.view.dom.clientWidth - (PAGE_WIDTH - PAGE_PADDING * 2)) < 2;
            this.pageBreaks = exact ? breaks.map((item, index) => ({ top: item.top, label: `Sayfa ${index + 2}`, forced: item.forced })) : [];
        },
        setRatio(ratio) {
            this.ratio = ratio;
            this.menu = null;
            this.scheduleMeasure();
            if (config.mode === 'autosave') {
                this.saveState = 'dirty';
                this.save();
            } else if (this.$refs.ratio) {
                this.$refs.ratio.value = ratio;
            }
        },

        // --- Araç çubuğu ------------------------------------------------------------------
        isActive(name, attributes = {}) {
            this.tick;
            return editor ? editor.isActive(name, attributes) : false;
        },
        can(command) {
            this.tick;
            return editor ? editor.can()[command]() : false;
        },
        run(command, ...args) {
            this.menu = null;
            editor?.chain().focus()[command](...args).run();
        },
        toggleMenu(name) {
            this.menu = this.menu === name ? null : name;
            if (name !== 'font') this.moreFonts = false;
        },
        currentStyle() {
            this.tick;
            if (!editor) return 'Normal Metin';
            for (let level = 1; level <= 4; level++) {
                if (editor.isActive('heading', { level })) return `Başlık ${level}`;
            }
            if (editor.isActive('concept')) return 'Kavram';
            return editor.isActive('blockquote') ? 'Alıntı' : 'Normal Metin';
        },
        setStyle(style) {
            const chain = editor.chain().focus();
            if (editor.isActive('blockquote') && style !== 'quote') chain.lift('blockquote');
            if (style === 'paragraph') chain.setParagraph();
            else if (style === 'concept') chain.setConcept();
            else if (style === 'quote') chain.setParagraph().setBlockquote();
            else chain.setHeading({ level: style });
            chain.run();
            this.menu = null;
        },
        currentFont() {
            this.tick;
            return editor?.getAttributes('fontStyle').font || config.defaultFont || 'georgia';
        },
        currentSize() {
            this.tick;
            return editor?.getAttributes('fontStyle').size || 16;
        },
        setFont(font) {
            editor.chain().focus().setFontStyle({ font: font === 'georgia' ? null : font }).run();
            this.menu = null;
            this.moreFonts = false;
        },
        setSize(size) {
            const value = parseInt(size, 10);
            if (!(value >= 8 && value <= 96)) {
                this.panelError = 'Punto 8 ile 96 arasında olmalı.';
                return;
            }
            editor.chain().focus().setFontStyle({ size: value === 16 ? null : value }).run();
            this.menu = null;
            this.closePanel();
        },
        currentAlign() {
            this.tick;
            if (!editor) return 'left';
            return ['center', 'right', 'justify'].find((align) => editor.isActive({ textAlign: align })) || 'left';
        },
        setAlign(align) {
            editor.chain().focus().setTextAlign(align).run();
            this.menu = null;
        },
        insert(type) {
            this.menu = null;
            if (type === 'table') {
                editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
                return;
            }
            // "Ekle → Kavram" (sözlük): imlecin olduğu satır madde başı olur (belge: "imleci o
            // satıra getirir ve Kavramı seçer").
            if (type === 'concept') {
                this.setStyle('concept');
                return;
            }
            this.insertBlock({ type });
        },
        // Blok öğe (sayfa sonu, içindekiler, kaynakça, video, görsel) eklenince seçim öğenin üstünde
        // kalıyordu: ardından yazılan harf ya da eklenen başka öğe onu siliyordu. Arkasına boş bir
        // paragraf eklenip imleç oraya alınıyor.
        insertBlock(node) {
            editor.chain().focus().insertContent([node, { type: 'paragraph' }]).run();
        },

        // --- Seçim balonu (1.1.5) ---------------------------------------------------------
        placeBubble() {
            if (!editor || !this.$refs.paper) return;
            const { from, to, empty } = editor.state.selection;
            if (empty || editor.state.selection.node || !editor.isFocused) {
                this.bubble.show = false;
                return;
            }
            const start = editor.view.coordsAtPos(from);
            const end = editor.view.coordsAtPos(to);
            const box = this.$refs.paper.getBoundingClientRect();
            // "Sözlüğe Bağla" düğmesiyle balon genişliyor.
            const width = config.conceptSearchUrl ? 330 : 220;
            this.bubble = {
                show: true,
                top: Math.max(0, Math.min(start.top, end.top) - box.top - 48),
                left: Math.max(8, Math.min((start.left + end.left) / 2 - box.left - width / 2, box.width - width - 8)),
            };
        },

        // --- Paneller ---------------------------------------------------------------------
        openPanel(type) {
            if (!editor) return;
            this.menu = null;
            this.panelError = '';
            // Balon, odak panele geçince kendiliğinden kapanmıyordu (mobilde panelin üstüne biniyordu).
            this.bubble.show = false;
            const selected = editor.state.selection.node;
            if (type === 'link') {
                this.panelText = editor.getAttributes('link').href || '';
            } else if (type === 'footnote') {
                this.editingFootnote = selected?.type.name === 'footnote';
                this.panelText = this.editingFootnote ? selected.attrs.text : '';
            } else if (type === 'video') {
                this.editingVideo = selected?.type.name === 'videoLink';
                this.video = this.editingVideo ? { ...selected.attrs } : { url: '', title: '', duration: '' };
            } else if (type === 'cite') {
                this.cite.text = '';
            } else if (type === 'size') {
                this.customSize = String(this.currentSize());
            } else if (type === 'concept') {
                const { from, to } = editor.state.selection;
                const current = editor.getAttributes('conceptLink');
                this.concept = {
                    query: current.term || editor.state.doc.textBetween(from, to, ' ').trim().slice(0, 100),
                    results: [],
                    busy: false,
                    searched: false,
                    current: current.id ? (current.term || 'bir sözlük maddesi') : '',
                };
                this.searchConcepts();
            }
            this.panel = type;
            this.$nextTick(() => this.$refs.panelFocus?.focus?.());
        },
        closePanel() {
            this.panel = null;
            this.panelText = '';
            this.panelError = '';
            this.editingFootnote = false;
            this.editingVideo = false;
            // Paneldeki alan DOM'dan kalkınca odak sayfaya düşüyordu (Tiptap'in focus'u bir kare
            // gecikmeli): ardından yazılan ilk harfler kayboluyordu. Odak açıkça editöre dönüyor.
            this.$nextTick(() => editor?.view.focus());
        },
        savePanel() {
            const text = this.panelText.trim();
            const chain = editor.chain().focus();

            if (this.panel === 'link') {
                if (text) {
                    const href = /^(https?:\/\/|mailto:)/i.test(text) ? text : `https://${text}`;
                    chain.extendMarkRange('link').setLink({ href }).run();
                } else {
                    chain.extendMarkRange('link').unsetLink().run();
                }
            } else if (this.panel === 'footnote') {
                if (this.editingFootnote) {
                    text ? chain.updateAttributes('footnote', { text }).run() : chain.deleteSelection().run();
                } else if (text) {
                    chain.insertContent({ type: 'footnote', attrs: { text } }).run();
                }
            } else if (this.panel === 'video') {
                const attrs = { url: this.video.url.trim(), title: this.video.title.trim(), duration: this.video.duration.trim() };
                // Sunucu (VideoEmbed) de aynı kontrolü yapıyor; burada yazar hemen uyarılsın diye.
                if (!/^https?:\/\/((www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)|(player\.)?vimeo\.com)\//i.test(attrs.url)) {
                    this.panelError = 'Sadece YouTube ya da Vimeo bağlantısı eklenebilir.';
                    return;
                }
                if (attrs.duration && !/^\d{1,3}(:\d{2}){1,2}$/.test(attrs.duration)) {
                    this.panelError = 'Süreyi dakika:saniye biçiminde yazın (ör. 12:45).';
                    return;
                }
                this.editingVideo
                    ? chain.updateAttributes('videoLink', attrs).run()
                    : (chain.run(), this.insertBlock({ type: 'videoLink', attrs }));
            }
            this.closePanel();
        },
        insertCitation(text) {
            const value = (text ?? this.cite.text).trim();
            if (!value) {
                this.panelError = 'Kaynağı yazın ya da listeden seçin.';
                return;
            }
            editor.chain().focus().insertContent({ type: 'citation', attrs: { text: value } }).run();
            this.closePanel();
        },
        insertDocument(id) {
            editor.chain().focus().insertContent({ type: 'embeddedDocument', attrs: { id: String(id) } }).run();
            this.closePanel();
        },
        // "Sözlüğe Bağla": yayındaki sözlüklerde (ve yazarın kendi sözlüklerinde) madde arama.
        async searchConcepts() {
            const query = this.concept.query.trim();
            if (!config.conceptSearchUrl || query.length < 2) {
                this.concept.results = [];
                this.concept.searched = false;
                return;
            }
            this.concept.busy = true;
            try {
                const response = await fetch(`${config.conceptSearchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                // Yazar yazmaya devam ettiyse eski sonuç yenisinin üstüne yazılmasın.
                if (query === this.concept.query.trim()) {
                    this.concept.results = data.entries || [];
                    this.concept.searched = true;
                }
            } catch {
                this.panelError = 'Sözlükler aranamadı. Bağlantınızı kontrol edin.';
            } finally {
                this.concept.busy = false;
            }
        },
        linkConcept(entry) {
            editor.chain().focus().extendMarkRange('conceptLink').unsetLink()
                .setMark('conceptLink', { id: String(entry.id), term: entry.term }).run();
            this.closePanel();
        },
        unlinkConcept() {
            editor.chain().focus().extendMarkRange('conceptLink').unsetMark('conceptLink').run();
            this.closePanel();
        },
        removeSelected() {
            editor.chain().focus().deleteSelection().run();
            this.closePanel();
        },

        async upload(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Yüklenemedi.'));
            }
            return data;
        },
        async uploadImage() {
            const file = this.$refs.imageFile?.files[0];
            if (!file) {
                this.panelError = 'Bir görsel (JPG/PNG) seçin.';
                return;
            }
            this.image.busy = true;
            this.panelError = '';
            const body = new FormData();
            body.append('file', file);
            body.append('caption', this.image.caption);
            try {
                const data = await this.upload(config.imageUrl, body);
                images.push(data);
                this.insertBlock({ type: 'contentImage', attrs: { id: String(data.id), caption: this.image.caption.trim() } });
                this.image.caption = '';
                this.closePanel();
            } catch (error) {
                this.panelError = error.message;
            } finally {
                this.image.busy = false;
            }
        },
        async uploadDocument() {
            const file = this.$refs.documentFile?.files[0];
            if (!file || !this.docForm.title.trim()) {
                this.panelError = 'Belgenin adını yazın ve bir PDF / JPG / PNG seçin.';
                return;
            }
            this.docForm.busy = true;
            this.panelError = '';
            const body = new FormData();
            body.append('file', file);
            body.append('title', this.docForm.title.trim());
            body.append('date_label', this.docForm.date.trim());
            try {
                const data = await this.upload(config.documentUrl, body);
                documents.push(data);
                this.documents = [...documents];
                this.insertDocument(data.id);
                this.docForm = { title: '', date: '', busy: false };
            } catch (error) {
                this.panelError = error.message;
            } finally {
                this.docForm.busy = false;
            }
        },

        // --- Word / EPUB'dan içe aktarma ----------------------------------------------------
        async importFile(event) {
            const file = event.target.files[0];
            event.target.value = '';
            this.menu = null;
            if (!file || !config.importUrl || !editor) return;
            if (!editor.isEmpty && !confirm('Editördeki metin, dosyadaki metinle değiştirilecek. Devam edilsin mi?')) return;

            this.importing = true;
            this.importError = '';
            this.importNotice = '';
            const body = new FormData();
            body.append('file', file);
            try {
                const data = await this.upload(config.importUrl, body);
                images.push(...(data.images_list || []));
                editor.commands.setContent(data.html);
                this.changed();
                const imported = data.images?.imported ?? 0;
                const skipped = data.images?.skipped ?? 0;
                this.importNotice = `${file.name.toLowerCase().endsWith('.epub') ? 'EPUB' : 'Word'} dosyası aktarıldı${config.isBook ? '; her "Başlık 1" bir bölüm oldu' : ''}. Kontrol edin.`
                    + (imported ? ` ${imported} görsel metne eklendi.` : '')
                    + (skipped ? ` ${skipped} görsel desteklenmeyen biçimde olduğu için atlandı (sadece PNG/JPG).` : '');
            } catch (error) {
                this.importError = error.message;
            } finally {
                this.importing = false;
            }
        },

        // --- Kayıt --------------------------------------------------------------------------
        async save() {
            if (config.mode !== 'autosave' || !editor) return;
            clearTimeout(saveTimer);
            this.saveState = 'saving';
            try {
                const response = await fetch(config.saveUrl, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ html: this.$refs.input.value, page_ratio: this.ratio, page_count: this.stats.pages }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.saveState = 'error';
                    this.importError = data.errors ? Object.values(data.errors)[0][0] : 'Kaydedilemedi. Bağlantınızı kontrol edin.';
                    return;
                }
                this.importError = '';
                this.savedLabel = data.saved_label;
                // Sayfa başlığındaki "Taslak kaydedildi · tarih" (mockup) bu olayla güncelleniyor.
                this.$dispatch('work-saved', { label: data.saved_label });
                this.saveState = this.saveState === 'saving' ? 'saved' : this.saveState;
            } catch {
                this.saveState = 'error';
            }
        },
    };
}
