// Okurken metinde Alıntıla / Not Al / Fosforla (Faz H3, "Okurun Gözünden" — Okuma moduna dair).
// pagedReader'a karışıyor (paged-reader.js): akış, sayfa ölçüsü ve sayfa numarası oradan.
//
// Metindeki yer: bölümün (section[data-chapter]) okunur metni içinde başlangıç/bitiş + seçilen
// metin + çevresindeki birkaç kelime. Bölüm açılışı, dipnot/kaynak işaretleri ve dipnot listesi
// sayılmıyor; bloklar arasında "\n" var sayılıyor. Yazar metni sonradan düzeltirse seçilen metin
// çevresiyle yeniden bulunuyor; bulunamazsa işaret metinde görünmüyor (listelerde duruyor).
//
// Görünüm: seçilen metin <mark>'larla sarılıyor (dolgusuz — satırlar ve sayfalar kaymıyor);
// alıntının başında ve sonunda altın çizgi, notun satırının solunda simge (akışın içinde, sayfa
// kenarında), fosforda sarı kalem izi.

const SKIP = '.rt-opener, sup.footnote-ref, sup.cite-ref, .footnotes';
const BLOCK = 'p, li, h1, h2, h3, h4, blockquote, td, th, figcaption, pre, div, section';
const BLOCK_PARENTS = new Set(['SECTION', 'DIV', 'OL', 'UL', 'BLOCKQUOTE', 'TABLE', 'TBODY', 'THEAD', 'TFOOT', 'TR', 'NAV', 'HEADER', 'FIGURE']);
const CONTEXT = 32;
const PIN_SIZE = 26;
const PIN_GAP = 34;

const PIN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5z"/><path d="M8 8.5h8M8 12h5"/></svg>';

// Bölümün okunur metni ve metin düğümlerinin o metindeki başlangıçları.
function textModel(section) {
    const segments = [];
    let text = '';
    let lastBlock = null;
    const walker = document.createTreeWalker(section, NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => (node.parentElement?.closest(SKIP) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
    });
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const block = node.parentElement.closest(BLOCK);
        if (lastBlock && block !== lastBlock && text && !text.endsWith('\n')) text += '\n';
        lastBlock = block;
        segments.push({ node, start: text.length });
        text += node.nodeValue;
    }
    return { text, segments };
}

// Seçim sınırının (düğüm, konum) okunur metindeki yeri.
function offsetOf(model, container, offset) {
    const own = model.segments.find((segment) => segment.node === container);
    if (own) return own.start + Math.min(offset, container.nodeValue.length);
    const point = document.createRange();
    point.setStart(container, offset);
    for (const segment of model.segments) {
        if (point.comparePoint(segment.node, 0) > 0) return segment.start;
    }
    return model.text.length;
}

// Kaydedilmiş yeri bugünkü metinde bul: aynı yerde aynı metin varsa o; yoksa metnin geçtiği
// yerlerden çevresi en çok tutan (eşitse eski yere en yakın).
function locate(text, mark) {
    const { start, end } = mark.anchor;
    if (text.slice(start, end) === mark.quote) return [start, end];
    const prefix = mark.anchor.prefix || '';
    const suffix = mark.anchor.suffix || '';
    let best = null;
    for (let at = text.indexOf(mark.quote); at !== -1; at = text.indexOf(mark.quote, at + 1)) {
        let score = 0;
        while (score < prefix.length && text[at - 1 - score] === prefix[prefix.length - 1 - score]) score++;
        const after = at + mark.quote.length;
        let tail = 0;
        while (tail < suffix.length && text[after + tail] === suffix[tail]) tail++;
        const candidate = { at, score: score + tail, distance: Math.abs(at - start) };
        if (!best || candidate.score > best.score || (candidate.score === best.score && candidate.distance < best.distance)) best = candidate;
    }
    return best ? [best.at, best.at + mark.quote.length] : null;
}

function wrap(model, start, end, type, id) {
    const marks = [];
    for (const { node, start: at } of model.segments) {
        const length = node.nodeValue.length;
        const from = Math.max(start, at) - at;
        const to = Math.min(end, at + length) - at;
        if (to <= from) continue;
        // Bloklar arasındaki boşluk düğümü sarılırsa satır açılır: atla.
        if (!node.nodeValue.slice(from, to).trim() && BLOCK_PARENTS.has(node.parentElement?.tagName)) continue;
        if (to < length) node.splitText(to);
        const target = from > 0 ? node.splitText(from) : node;
        const mark = document.createElement('mark');
        mark.className = `rt-mark rt-mark-${type}`;
        mark.dataset.mark = String(id);
        target.parentNode.insertBefore(mark, target);
        mark.appendChild(target);
        marks.push(mark);
    }
    if (marks.length) {
        marks[0].classList.add('is-first');
        marks[marks.length - 1].classList.add('is-last');
    }
    return marks;
}

function unwrap(root, id) {
    root.querySelectorAll(`mark[data-mark="${id}"]`).forEach((mark) => {
        const parent = mark.parentNode;
        while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
        parent.removeChild(mark);
        parent.normalize();
    });
}

export default function readingMarks(config) {
    const enabled = Boolean(config.marks);
    let selectionTimer = null;
    let toastTimer = null;

    return {
        marks: config.marks || [],
        selBar: null,
        pending: null,
        notePanel: null,
        markMenu: null,
        toast: null,
        markBusy: false,

        initMarks() {
            if (!enabled) return;
            this.onSelection = () => {
                clearTimeout(selectionTimer);
                selectionTimer = setTimeout(() => this.selectionChanged(), 180);
            };
            this.onMarkClick = (event) => this.markClicked(event);
            this.onMarkScroll = () => this.hideSelectionBar();
            document.addEventListener('selectionchange', this.onSelection);
            window.addEventListener('scroll', this.onMarkScroll, { passive: true });
            this.$refs.flow.addEventListener('click', this.onMarkClick);
        },
        destroyMarks() {
            if (!enabled) return;
            clearTimeout(selectionTimer);
            clearTimeout(toastTimer);
            document.removeEventListener('selectionchange', this.onSelection);
            window.removeEventListener('scroll', this.onMarkScroll);
        },

        // --- Metindeki işaretler --------------------------------------------------------------
        section(order) {
            return this.$refs.flow.querySelector(`[data-chapter="${order}"]`);
        },
        // Sayfalar dizildikten sonra: bütün işaretler metne, not simgeleri sayfa kenarına.
        renderMarks() {
            if (!enabled) return;
            this.marks.forEach((mark) => this.applyMark(mark));
            this.placePins();
        },
        applyMark(mark) {
            const order = mark.anchor?.chapter;
            const sections = [this.section(order), ...this.$refs.flow.querySelectorAll('[data-chapter]')].filter(Boolean);
            for (const section of new Set(sections)) {
                const model = textModel(section);
                const range = locate(model.text, mark);
                if (!range) continue;
                mark.located = { chapter: Number(section.dataset.chapter), start: range[0], end: range[1] };
                wrap(model, range[0], range[1], mark.type, mark.id);
                return true;
            }
            mark.located = null;
            return false;
        },
        removeMark(id) {
            unwrap(this.$refs.flow, id);
            this.marks = this.marks.filter((mark) => mark.id !== id);
            this.placePins();
        },
        // Not simgesi notun ilk satırının solunda, sayfanın kenar boşluğunda. Akışın içinde mutlak
        // konumlu: sayfalar kaydıkça onlarla gidiyor, sütunlara karışmıyor.
        placePins() {
            if (!enabled) return;
            const flow = this.$refs.flow;
            flow.querySelectorAll(':scope > .rt-note-pin').forEach((pin) => pin.remove());
            const base = flow.getBoundingClientRect();
            this.marks.filter((mark) => mark.type === 'not').forEach((mark) => {
                const first = flow.querySelector(`mark[data-mark="${mark.id}"]`);
                const rect = first?.getClientRects()[0];
                if (!rect) return;
                const x = (rect.left - base.left) / this.scale;
                const y = (rect.top - base.top) / this.scale;
                const page = Math.floor(x / config.pageWidth + 0.01);
                const pin = document.createElement('button');
                pin.type = 'button';
                pin.className = 'rt-note-pin';
                pin.dataset.mark = String(mark.id);
                pin.setAttribute('aria-label', 'Notu gör');
                pin.innerHTML = PIN_ICON;
                pin.style.left = `${page * config.pageWidth - PIN_GAP}px`;
                pin.style.top = `${y + (rect.height / this.scale - PIN_SIZE) / 2}px`;
                flow.appendChild(pin);
            });
        },

        // --- Seçim ----------------------------------------------------------------------------
        // Seçim tek bir bölümün içindeyse üstünde Alıntıla / Not Al / Fosforla.
        selectionChanged() {
            if (!this.$root.isConnected || !this.ready || this.notePanel) return;
            const selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) {
                this.selBar = null;
                this.pending = null;
                return;
            }
            const range = selection.getRangeAt(0);
            const element = (node) => (node.nodeType === Node.TEXT_NODE ? node.parentElement : node);
            const section = element(range.startContainer)?.closest('[data-chapter]');
            if (!section || !this.$refs.flow.contains(section) || element(range.endContainer)?.closest('[data-chapter]') !== section) {
                this.selBar = null;
                this.pending = null;
                return;
            }
            const model = textModel(section);
            let start = offsetOf(model, range.startContainer, range.startOffset);
            let end = offsetOf(model, range.endContainer, range.endOffset);
            while (start < end && /\s/.test(model.text[start])) start++;
            while (end > start && /\s/.test(model.text[end - 1])) end--;
            if (end <= start) {
                this.selBar = null;
                this.pending = null;
                return;
            }
            const rects = Array.from(range.getClientRects()).filter((rect) => rect.width > 0);
            const first = rects[0] || range.getBoundingClientRect();
            const last = rects[rects.length - 1] || first;
            const page = this.pageOfRect(first);
            this.pending = {
                chapter: Number(section.dataset.chapter),
                start,
                end,
                quote: model.text.slice(start, end),
                prefix: model.text.slice(Math.max(0, start - CONTEXT), start),
                suffix: model.text.slice(end, end + CONTEXT),
                page: this.label(page),
                location: `Sayfa ${this.label(page)}${section.dataset.title ? ` · ${section.dataset.title}` : ''}`,
                top: first.top,
                bottom: last.bottom,
                left: Math.min(first.left, last.left),
                right: Math.max(first.right, last.right),
            };
            // Dokunmatikte telefonun kendi seçim menüsü üstte: bizimki altta.
            const coarse = window.matchMedia('(pointer: coarse)').matches;
            const width = Math.min(312, window.innerWidth - 16);
            const center = (first.left + first.right) / 2;
            const above = !coarse && first.top > 120;
            this.selBar = {
                x: Math.max(8, Math.min(center - width / 2, window.innerWidth - width - 8)),
                y: above ? first.top - 10 : last.bottom + 10,
                above,
            };
        },
        hideSelectionBar() {
            this.selBar = null;
        },
        clearSelection() {
            window.getSelection()?.removeAllRanges();
            this.selBar = null;
            this.pending = null;
        },
        // Sayfa değişince seçim, menü ve pencereler kapanır.
        dismissMarks() {
            if (!enabled) return;
            if (this.pending || this.selBar) this.clearSelection();
            if (this.notePanel?.mode === 'new') this.cancelNote();
            this.notePanel = null;
            this.markMenu = null;
        },

        // --- Eylemler ---------------------------------------------------------------------------
        overlapsQuote(pending) {
            return this.marks.some((mark) => mark.type === 'alinti' && mark.located
                && mark.located.chapter === pending.chapter && mark.located.start < pending.end && pending.start < mark.located.end);
        },
        async createMark(type, content = null) {
            const pending = this.pending;
            if (!pending || this.markBusy) return null;
            this.markBusy = true;
            try {
                const response = await fetch(config.marksUrl, {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        type,
                        noteable_type: config.noteableType,
                        noteable_id: config.noteableId,
                        quote: pending.quote,
                        content,
                        anchor: { chapter: pending.chapter, start: pending.start, end: pending.end, prefix: pending.prefix, suffix: pending.suffix },
                        page: pending.page,
                        location: pending.location,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.showToast(data.message || 'Kaydedilemedi. Sayfayı yenileyip tekrar deneyin.', data.premium ? { href: data.premium, text: "Premium'a göz at" } : null);
                    return null;
                }
                this.marks.push(data);
                this.applyMark(this.marks[this.marks.length - 1]);
                this.placePins();
                return data;
            } catch {
                this.showToast('Bağlantı kurulamadı, tekrar deneyin.');
                return null;
            } finally {
                this.markBusy = false;
            }
        },
        async quoteSelection() {
            if (!this.pending) return;
            if (this.overlapsQuote(this.pending)) {
                this.showToast('Bu kısmı zaten alıntıladınız.');
                this.clearSelection();
                return;
            }
            const created = await this.createMark('alinti');
            this.clearSelection();
            if (created) this.showToast("Alıntılarım'a eklendi.", { href: config.quotesUrl, text: 'Alıntılarım' });
        },
        async highlightSelection() {
            await this.createMark('fosfor');
            this.clearSelection();
        },
        // Not Al: seçilen yer açık mavi kalır, altında "Not Al" penceresi.
        startNote() {
            const pending = this.pending;
            if (!pending) return;
            this.selBar = null;
            const model = textModel(this.section(pending.chapter));
            wrap(model, pending.start, pending.end, 'pending', 'pending');
            window.getSelection()?.removeAllRanges();
            this.notePanel = { mode: 'new', quote: pending.quote, text: '', ...this.panelPlace(pending) };
            this.$nextTick(() => this.$refs.noteText?.focus());
        },
        cancelNote() {
            unwrap(this.$refs.flow, 'pending');
            this.notePanel = null;
            this.pending = null;
        },
        async saveNote() {
            const text = this.notePanel?.text.trim();
            if (!text) return;
            unwrap(this.$refs.flow, 'pending');
            const created = await this.createMark('not', text);
            if (created) {
                this.notePanel = null;
                this.pending = null;
                this.showToast("Not kaydedildi — Notlarım'da.", { href: config.notesUrl, text: 'Notlarım' });
            } else {
                // Kaydedilemedi: yazılan kaybolmasın, seçim yine görünsün.
                wrap(textModel(this.section(this.pending.chapter)), this.pending.start, this.pending.end, 'pending', 'pending');
            }
        },
        // İşarete / not simgesine tıklama: notta "Notum", alıntı ve fosforda kaldırma menüsü.
        markClicked(event) {
            if (!(window.getSelection()?.isCollapsed ?? true)) return;
            const target = event.target.closest('.rt-note-pin, mark[data-mark]');
            if (!target || target.dataset.mark === 'pending') return;
            const mark = this.marks.find((item) => String(item.id) === target.dataset.mark);
            if (!mark) return;
            event.preventDefault();
            const rect = target.getBoundingClientRect();
            if (mark.type === 'not') {
                this.markMenu = null;
                this.notePanel = { mode: 'view', id: mark.id, quote: mark.quote, text: mark.content || '', confirm: false, ...this.panelPlace({ top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right }) };
                return;
            }
            const width = Math.min(240, window.innerWidth - 16);
            this.notePanel = null;
            this.markMenu = {
                id: mark.id,
                type: mark.type,
                x: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
                y: rect.bottom + 8,
            };
        },
        editNote() {
            this.notePanel = { ...this.notePanel, mode: 'edit', draft: this.notePanel.text };
            this.$nextTick(() => this.$refs.noteText?.focus());
        },
        async updateNote() {
            const panel = this.notePanel;
            const text = panel?.draft?.trim();
            if (!text || this.markBusy) return;
            this.markBusy = true;
            try {
                const response = await fetch(config.markUrl.replace('__ID__', panel.id), { method: 'PATCH', headers: this.jsonHeaders(), body: JSON.stringify({ content: text }) });
                if (!response.ok) throw new Error();
                const mark = this.marks.find((item) => item.id === panel.id);
                if (mark) mark.content = text;
                this.notePanel = { ...panel, mode: 'view', text };
            } catch {
                this.showToast('Not güncellenemedi, tekrar deneyin.');
            } finally {
                this.markBusy = false;
            }
        },
        async deleteMark(id) {
            if (this.markBusy) return;
            this.markBusy = true;
            try {
                const response = await fetch(config.markUrl.replace('__ID__', id), { method: 'DELETE', headers: this.jsonHeaders() });
                if (!response.ok && response.status !== 404) throw new Error();
                this.removeMark(id);
                this.notePanel = null;
                this.markMenu = null;
            } catch {
                this.showToast('Silinemedi, tekrar deneyin.');
            } finally {
                this.markBusy = false;
            }
        },

        // --- Yardımcılar --------------------------------------------------------------------------
        // Pencere seçimin altında; yer yoksa üstünde. Dar ekranda ortada.
        panelPlace(rect) {
            const width = Math.min(416, window.innerWidth - 16);
            const center = (rect.left + rect.right) / 2;
            const below = rect.bottom + 14 + 320 < window.innerHeight;
            return {
                x: Math.max(8, Math.min(center - width / 2, window.innerWidth - width - 8)),
                y: below ? rect.bottom + 14 : Math.max(8, rect.top - 14),
                above: !below,
                arrow: Math.max(20, Math.min(center - Math.max(8, Math.min(center - width / 2, window.innerWidth - width - 8)), width - 20)),
            };
        },
        jsonHeaders() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };
        },
        showToast(text, link = null) {
            clearTimeout(toastTimer);
            this.toast = { text, link };
            toastTimer = setTimeout(() => (this.toast = null), 4500);
        },
    };
}
