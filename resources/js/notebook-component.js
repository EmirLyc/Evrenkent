// Alpine 'notebook' bileşeni — Defterim (Faz H5, "Okurun Gözünden" — Defterim 1). Editör nesnesi
// Alpine'in reaktif verisine konmuyor (Proxy Tiptap'i bozar); `tick` araç çubuğunun etkin
// durumlarını yeniden hesaplatmak için. Değişiklikler 1,2 sn sonra kendiliğinden kaydediliyor.

const THEME_KEY = 'evrenkent.notebook.theme';

export default function notebook(config) {
    let editor = null;
    let module = null;
    let saveTimer = null;

    return {
        ready: false,
        tick: 0,
        title: config.title,
        subtitle: config.subtitle || '',
        tags: config.tags || [],
        tagInput: '',
        tagOpen: false,
        info: config.info || '',
        infoOpen: false,
        words: 0,
        empty: true,
        saveState: 'saved',
        savedAt: config.savedAt,
        message: '',
        menu: null,
        panel: null,
        link: { href: '' },
        source: { type: 'alinti', q: '', items: [], loading: false },
        focusMode: false,
        dark: false,

        async init() {
            try {
                this.dark = localStorage.getItem(THEME_KEY) === 'dark';
            } catch {
                // depolama yok
            }
            this.applyShell();
            module = await import('./notebook-editor.js');
            if (!this.$root.isConnected) return;
            editor = module.createNotebookEditor({
                element: this.$refs.surface,
                content: config.content || '',
                onUpdate: () => this.changed(),
                onSelection: () => this.tick++,
            });
            this.refreshStats();
            this.ready = true;

            this.onLeave = () => this.flush();
            this.onUnload = (event) => {
                if (this.saveState === 'dirty' || this.saveState === 'saving') {
                    this.flush();
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            document.addEventListener('turbo:before-visit', this.onLeave);
            window.addEventListener('beforeunload', this.onUnload);
        },
        destroy() {
            clearTimeout(saveTimer);
            document.removeEventListener('turbo:before-visit', this.onLeave);
            window.removeEventListener('beforeunload', this.onUnload);
            editor?.destroy();
            editor = null;
        },

        // --- Kayıt -----------------------------------------------------------------------------
        refreshStats() {
            this.words = module.countWords(editor);
            this.empty = editor.isEmpty;
            this.tick++;
        },
        changed() {
            this.refreshStats();
            this.scheduleSave();
        },
        scheduleSave() {
            if (!this.ready) return;
            this.saveState = 'dirty';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => this.save(), 1200);
        },
        overLimit() {
            return config.wordLimit !== null && this.words > config.wordLimit;
        },
        payload() {
            return JSON.stringify({
                title: this.title.trim() || 'Adsız Defter',
                subtitle: this.subtitle,
                content: editor ? editor.getHTML() : config.content,
                tags: this.tags,
                info: this.info,
            });
        },
        headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };
        },
        async save() {
            clearTimeout(saveTimer);
            if (!editor) return;
            if (this.overLimit()) {
                this.saveState = 'limit';
                return;
            }
            this.saveState = 'saving';
            try {
                const response = await fetch(config.saveUrl, { method: 'PUT', headers: this.headers(), body: this.payload() });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.saveState = response.status === 422 && data.premium ? 'limit' : 'error';
                    this.message = data.message || 'Kaydedilemedi.';
                    return;
                }
                if (this.saveState === 'saving') this.saveState = 'saved';
                this.savedAt = data.saved_at;
                this.message = '';
                this.$dispatch('notebook-saved', { id: config.id, title: this.title.trim() || 'Adsız Defter', time: data.saved_at });
            } catch {
                this.saveState = 'error';
                this.message = 'Bağlantı kurulamadı.';
            }
        },
        // Sayfadan çıkarken bekleyen değişiklik: istek sayfa kapansa da gitsin.
        flush() {
            if (!editor || (this.saveState !== 'dirty' && this.saveState !== 'saving') || this.overLimit()) return;
            clearTimeout(saveTimer);
            fetch(config.saveUrl, { method: 'PUT', headers: this.headers(), body: this.payload(), keepalive: true }).catch(() => {});
            this.saveState = 'saved';
        },

        // --- Araç çubuğu ---------------------------------------------------------------------------
        isActive(name, attributes = {}) {
            this.tick;
            return editor ? editor.isActive(name, attributes) : false;
        },
        can(command) {
            this.tick;
            return editor ? editor.can()[command]() : false;
        },
        run(command, ...args) {
            if (!editor) return;
            editor.chain().focus()[command](...args).run();
            this.menu = null;
        },
        blockLabel() {
            this.tick;
            for (const level of [1, 2, 3]) {
                if (this.isActive('heading', { level })) return `Başlık ${level}`;
            }
            return this.isActive('blockquote') ? 'Alıntı' : 'Normal';
        },
        setBlock(kind) {
            if (!editor) return;
            const chain = editor.chain().focus();
            kind === 'normal' ? chain.setParagraph().run() : chain.setHeading({ level: kind }).run();
            this.menu = null;
        },
        heading(level) {
            this.run('toggleHeading', { level });
        },
        indent() {
            if (editor?.can().sinkListItem('listItem')) this.run('sinkListItem', 'listItem');
        },
        outdent() {
            if (editor?.can().liftListItem('listItem')) this.run('liftListItem', 'listItem');
        },
        undo() {
            this.run('undo');
        },
        redo() {
            this.run('redo');
        },

        // Bağlantı
        openLink() {
            this.link.href = editor?.getAttributes('link').href || '';
            this.panel = 'link';
            this.menu = null;
            this.$nextTick(() => this.$refs.linkInput?.focus());
        },
        applyLink() {
            let href = this.link.href.trim();
            if (!editor) return;
            if (!href) {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
            } else {
                if (!/^(https?:|mailto:)/i.test(href)) href = `https://${href}`;
                const { empty } = editor.state.selection;
                if (empty && !editor.isActive('link')) {
                    editor.chain().focus().insertContent({ type: 'text', text: href, marks: [{ type: 'link', attrs: { href } }] }).insertContent(' ').run();
                } else {
                    editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
                }
            }
            this.panel = null;
        },

        // Görsel
        pickImage() {
            this.menu = null;
            this.$refs.imageInput.click();
        },
        async uploadImage(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file || !editor) return;
            const form = new FormData();
            form.append('image', file);
            this.message = '';
            try {
                const response = await fetch(config.imageUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, Accept: 'application/json' }, body: form });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.message = data.errors?.image?.[0] || data.message || 'Görsel yüklenemedi.';
                    return;
                }
                editor.chain().focus().insertContent([{ type: 'image', attrs: { src: data.url, alt: file.name.replace(/\.[^.]+$/, '') } }, { type: 'paragraph' }]).run();
            } catch {
                this.message = 'Görsel yüklenemedi.';
            }
        },

        // Alıntı ekle / Not ekle: okurun alıntıları ve notları
        async openSource(type) {
            this.menu = null;
            this.source = { type, q: '', items: [], loading: true };
            this.panel = 'source';
            await this.loadSources();
            this.$nextTick(() => this.$refs.sourceInput?.focus());
        },
        async loadSources() {
            this.source.loading = true;
            try {
                const url = new URL(config.sourcesUrl, window.location.href);
                url.searchParams.set('tur', this.source.type);
                if (this.source.q.trim()) url.searchParams.set('q', this.source.q.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                this.source.items = response.ok ? await response.json() : [];
            } catch {
                this.source.items = [];
            } finally {
                this.source.loading = false;
            }
        },
        insertSource(item) {
            if (!editor) return;
            editor.chain().focus().insertContent(module.sourceHtml(item)).run();
            this.panel = null;
        },

        // Etiketler ve bilgi
        addTag() {
            const tag = this.tagInput.trim().replace(/^#+/, '').slice(0, 30);
            if (tag && !this.tags.includes(tag) && this.tags.length < 10) {
                this.tags = [...this.tags, tag];
                this.scheduleSave();
            }
            this.tagInput = '';
        },
        removeTag(tag) {
            this.tags = this.tags.filter((item) => item !== tag);
            this.scheduleSave();
        },

        // Tam ekran (defter listesi gizlenir, tarayıcı izin verirse tam ekran) ve tema — ikisi de
        // bütün sayfanın (.notebook-app) sınıfı.
        applyShell() {
            const shell = this.$root.closest('.notebook-app');
            shell?.classList.toggle('nb-dark', this.dark);
            shell?.classList.toggle('nb-focus', this.focusMode);
        },
        toggleFocus() {
            this.focusMode = !this.focusMode;
            this.applyShell();
            const shell = this.$root.closest('.notebook-app');
            if (this.focusMode) {
                shell?.requestFullscreen?.().catch(() => {});
            } else if (document.fullscreenElement) {
                document.exitFullscreen?.().catch(() => {});
            }
        },
        toggleTheme() {
            this.dark = !this.dark;
            this.applyShell();
            try {
                localStorage.setItem(THEME_KEY, this.dark ? 'dark' : 'light');
            } catch {
                // yok say
            }
        },
    };
}
