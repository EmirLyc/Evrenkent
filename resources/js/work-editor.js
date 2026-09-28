// Yeni Yayın editörü (Faz G2, "Yazarın Gözünden" 1.1.1–1.1.6). app.js'teki Alpine 'workEditor'
// bileşeni bu dosyayı sadece editör sayfasında dinamik import ediyor (Tiptap ayrı chunk).
//
// Kaydedilen HTML sunucuda RichText::normalize'dan geçiyor; buradaki şema oradaki izinli
// etiketlerle aynı tutulmalı:
//  - Başlık 1–4 (<h1>–<h4>) ve hizalama (data-align) — kitapta her Başlık 1 bir bölüm,
//  - yazı tipi / punto (<span data-font data-size>),
//  - dipnot, kar taneli belge, video (F1–F3), metin içi görsel (<figure data-image>),
//  - kaynak numarası (<span data-cite="kaynak">), Kaynakça (<section data-bibliography>),
//    İçindekiler (<nav data-toc>), Sayfa Sonu (<hr data-page-break>), tablo.
import { Editor, Extension, Mark, Node, mergeAttributes } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';

export const FONTS = {
    georgia: "Georgia, Gelasio, 'Times New Roman', serif",
    times: "'Times New Roman', Tinos, Times, serif",
    palatino: "'Palatino Linotype', Palatino, 'Book Antiqua', serif",
    garamond: "'EB Garamond', Garamond, serif",
    arial: 'Arial, Arimo, Helvetica, sans-serif',
    helvetica: 'Helvetica, Arial, Arimo, sans-serif',
    lato: 'Lato, sans-serif',
    'source-serif': "'Source Serif 4', 'Source Serif Pro', serif",
    merriweather: 'Merriweather, serif',
    'libre-baskerville': "'Libre Baskerville', serif",
    'crimson-pro': "'Crimson Pro', serif",
    'open-sans': "'Open Sans', sans-serif",
    roboto: 'Roboto, sans-serif',
};

// Hizalama: paragraf ve başlıklarda data-align (editörde style ile de gösteriliyor; style
// sunucuda atılıyor, okumada data-align'dan sınıf üretiliyor).
const TextAlign = Extension.create({
    name: 'textAlign',
    addGlobalAttributes() {
        return [{
            types: ['paragraph', 'heading'],
            attributes: {
                textAlign: {
                    default: null,
                    parseHTML: (element) => {
                        const value = element.getAttribute('data-align');
                        return ['center', 'right', 'justify'].includes(value) ? value : null;
                    },
                    renderHTML: (attributes) => (attributes.textAlign
                        ? { 'data-align': attributes.textAlign, style: `text-align: ${attributes.textAlign}` }
                        : {}),
                },
            },
        }];
    },
    addCommands() {
        return {
            setTextAlign: (align) => ({ commands }) => ['paragraph', 'heading']
                .map((type) => commands.updateAttributes(type, { textAlign: align === 'left' ? null : align }))
                .some(Boolean),
        };
    },
});

// Yazı tipi ve punto (mockup 1.1.4): tek işaret, iki öznitelik.
const FontStyle = Mark.create({
    name: 'fontStyle',
    addAttributes() {
        return {
            font: {
                default: null,
                parseHTML: (element) => (FONTS[element.getAttribute('data-font')] ? element.getAttribute('data-font') : null),
                renderHTML: (attributes) => (attributes.font ? { 'data-font': attributes.font } : {}),
            },
            size: {
                default: null,
                parseHTML: (element) => {
                    const size = parseInt(element.getAttribute('data-size'), 10);
                    return size >= 8 && size <= 96 ? size : null;
                },
                renderHTML: (attributes) => (attributes.size ? { 'data-size': String(attributes.size) } : {}),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-font]' }, { tag: 'span[data-size]' }];
    },
    renderHTML({ mark, HTMLAttributes }) {
        const style = [
            mark.attrs.font ? `font-family: ${FONTS[mark.attrs.font]}` : null,
            mark.attrs.size ? `font-size: ${mark.attrs.size}px` : null,
        ].filter(Boolean).join('; ');
        return ['span', mergeAttributes(HTMLAttributes, style ? { style } : {}), 0];
    },
    addCommands() {
        return {
            setFontStyle: (attributes) => ({ chain, editor }) => {
                const next = { ...editor.getAttributes('fontStyle'), ...attributes };
                return next.font || next.size
                    ? chain().setMark('fontStyle', next).run()
                    : chain().unsetMark('fontStyle').run();
            },
        };
    },
});

// Dipnot (F1): <span data-footnote="metin"></span> — numaralar sırayla.
const Footnote = Node.create({
    name: 'footnote',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    addAttributes() {
        return {
            text: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-footnote') || '',
                renderHTML: (attributes) => ({ 'data-footnote': attributes.text }),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-footnote]' }];
    },
    renderHTML({ node, HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes, { class: 'footnote-marker', title: node.attrs.text })];
    },
});

// Kaynak numarası ("1 Kaynak No."): <span data-cite="kaynak metni"> — aynı kaynak aynı numara,
// numaralar editörde refreshNumbers() ile, okumada sunucuda veriliyor.
const Citation = Node.create({
    name: 'citation',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    addAttributes() {
        return {
            text: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-cite') || '',
                renderHTML: (attributes) => ({ 'data-cite': attributes.text }),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-cite]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', HTMLAttributes];
    },
    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('span');
            dom.className = 'cite-marker';
            dom.dataset.cite = node.attrs.text;
            dom.title = node.attrs.text;
            dom.textContent = '[·]';
            return { dom, ignoreMutation: () => true };
        };
    },
});

// Gömülü belge (F2): kar tanesi.
const createDocumentNode = (getDocuments) => Node.create({
    name: 'embeddedDocument',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-document'),
                renderHTML: (attributes) => ({ 'data-document': attributes.id }),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-document]' }];
    },
    renderHTML({ node, HTMLAttributes }) {
        const found = getDocuments().find((document) => String(document.id) === String(node.attrs.id));
        return ['span', mergeAttributes(HTMLAttributes, {
            class: found ? 'document-marker-edit' : 'document-marker-edit is-missing',
            title: found ? found.caption : 'Bu belge silinmiş — okur sayfasında görünmez',
        })];
    },
});

// Video satırı (F3).
const VideoLink = Node.create({
    name: 'videoLink',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,
    addAttributes() {
        const attribute = (name, key) => ({
            default: '',
            parseHTML: (element) => element.getAttribute(name) || '',
            renderHTML: (attributes) => ({ [name]: attributes[key] }),
        });
        return {
            url: attribute('data-video', 'url'),
            title: attribute('data-title', 'title'),
            duration: attribute('data-duration', 'duration'),
        };
    },
    parseHTML() {
        return [{ tag: 'figure[data-video]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['figure', mergeAttributes(HTMLAttributes, { class: 'video-edit' })];
    },
});

// Metin içi görsel ("Ekle → Görsel"): <figure data-image="id" data-caption="..">.
const createImageNode = (getImages) => Node.create({
    name: 'contentImage',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-image'),
                renderHTML: (attributes) => ({ 'data-image': attributes.id }),
            },
            caption: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-caption') || '',
                renderHTML: (attributes) => ({ 'data-caption': attributes.caption }),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'figure[data-image]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['figure', HTMLAttributes];
    },
    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('figure');
            dom.className = 'rt-image image-edit';
            const image = getImages().find((item) => String(item.id) === String(node.attrs.id));
            if (image) {
                const img = document.createElement('img');
                img.src = image.url;
                img.alt = node.attrs.caption || image.title || '';
                dom.appendChild(img);
            } else {
                dom.classList.add('is-missing');
                dom.textContent = 'Görsel bulunamadı — okur sayfasında görünmez';
            }
            if (node.attrs.caption) {
                const caption = document.createElement('figcaption');
                caption.textContent = node.attrs.caption;
                dom.appendChild(caption);
            }
            return { dom, ignoreMutation: () => true };
        };
    },
});

// Sayfa Sonu: <hr data-page-break="true"> (süs ayırıcı <hr>'dan önce eşleşsin diye öncelikli).
const PageBreak = Node.create({
    name: 'pageBreak',
    group: 'block',
    atom: true,
    selectable: true,
    parseHTML() {
        return [{ tag: 'hr[data-page-break]', priority: 100 }];
    },
    renderHTML() {
        return ['hr', { 'data-page-break': 'true' }];
    },
    addNodeView() {
        return () => {
            const dom = document.createElement('div');
            dom.className = 'page-break-edit';
            dom.setAttribute('data-page-break', 'true');
            dom.innerHTML = '<span>SAYFA SONU</span>';
            return { dom, ignoreMutation: () => true };
        };
    },
    addKeyboardShortcuts() {
        return { 'Mod-Enter': () => this.editor.commands.insertContent([{ type: this.name }, { type: 'paragraph' }]) };
    },
});

// İçindekiler ve Kaynakça: yer tutucu bloklar — içerikleri editörde refreshNumbers(),
// okumada sunucu üretiyor.
const createPlaceholderBlock = (name, tag, attribute, className) => Node.create({
    name,
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,
    parseHTML() {
        return [{ tag: `${tag}[${attribute}]` }];
    },
    renderHTML() {
        return [tag, { [attribute]: 'true' }];
    },
    addNodeView() {
        return () => {
            const dom = document.createElement(tag);
            dom.className = className;
            dom.setAttribute(attribute, 'true');
            return { dom, ignoreMutation: () => true };
        };
    },
});

const TableOfContents = createPlaceholderBlock('tableOfContents', 'nav', 'data-toc', 'rt-toc toc-edit');
const Bibliography = createPlaceholderBlock('bibliography', 'section', 'data-bibliography', 'rt-bibliography bibliography-edit');

// Sözlük "Kavram" satırı (Faz G3, "Sözlüğe Dair"): <p data-concept="anahtar">Egemenlik</p> — bir
// sözlük maddesinin başı; İçindekiler'e girer, sonraki metin bir sonraki kavrama kadar o maddeye
// ait (sunucuda DictionaryDocument). Anahtar maddenin kimliği: kavramın adı değişse de bağlantılar
// korunur. Kopyala-yapıştırla çoğalan ya da eksik anahtar burada yenilenir.
const newConceptKey = () => (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 10);

const Concept = Node.create({
    name: 'concept',
    group: 'block',
    content: 'inline*',
    defining: true,
    addAttributes() {
        return {
            key: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-concept') || null,
                renderHTML: (attributes) => ({ 'data-concept': attributes.key || '' }),
            },
        };
    },
    parseHTML() {
        // Paragraftan (öncelik 50) önce eşleşsin.
        return [{ tag: 'p[data-concept]', priority: 60 }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['p', mergeAttributes(HTMLAttributes, { class: 'rt-concept' }), 0];
    },
    addCommands() {
        return {
            setConcept: () => ({ commands }) => commands.setNode(this.name, { key: newConceptKey() }),
        };
    },
    addKeyboardShortcuts() {
        return {
            // Kavram satırında Enter: tanım normal paragrafla sürer (satır ortada bölünse bile).
            Enter: ({ editor }) => {
                const { $from, empty } = editor.state.selection;
                if (!empty || $from.parent.type.name !== this.name) return false;
                return editor.chain().splitBlock().setParagraph().run();
            },
        };
    },
    addProseMirrorPlugins() {
        return [new Plugin({
            appendTransaction: (transactions, oldState, newState) => {
                if (!transactions.some((transaction) => transaction.docChanged)) return null;
                const seen = new Set();
                let tr = null;
                newState.doc.forEach((node, offset) => {
                    if (node.type.name !== 'concept') return;
                    let key = node.attrs.key;
                    if (!key || seen.has(key)) {
                        key = newConceptKey();
                        tr = tr || newState.tr;
                        tr.setNodeMarkup(offset, undefined, { ...node.attrs, key });
                    }
                    seen.add(key);
                });
                return tr;
            },
        })];
    },
});

// "Sözlüğe Bağla" (Faz G3, 1.1.6): seçili kelime bir sözlük maddesine bağlanır —
// <span data-concept-link="madde id" data-concept-term="Egemenlik">egemenlik</span>. Okurken
// tıklanabilir, madde sayfasına gider (RichText::renderConcepts). Normal bağlantıyla üst üste binmez.
const ConceptLink = Mark.create({
    name: 'conceptLink',
    inclusive: false,
    excludes: 'link',
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-concept-link'),
                renderHTML: (attributes) => ({ 'data-concept-link': attributes.id }),
            },
            term: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-concept-term') || '',
                renderHTML: (attributes) => (attributes.term ? { 'data-concept-term': attributes.term } : {}),
            },
        };
    },
    parseHTML() {
        return [{ tag: 'span[data-concept-link]' }];
    },
    renderHTML({ mark, HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes, {
            class: 'concept-link-edit',
            title: mark.attrs.term ? `Sözlüğe bağlı: ${mark.attrs.term}` : 'Sözlüğe bağlı',
        }), 0];
    },
});

// Başlık numarası: Başlık 1 → I., altları 1.1., 1.1.1. (RichText::headingNumber ile aynı).
const ROMAN = [['M', 1000], ['CM', 900], ['D', 500], ['CD', 400], ['C', 100], ['XC', 90], ['L', 50], ['XL', 40], ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1]];
const roman = (number) => ROMAN.reduce((out, [symbol, value]) => {
    while (number >= value) {
        out += symbol;
        number -= value;
    }
    return out;
}, '');

// İçindekiler satırları: başlıklar ve (sözlükte) kavramlar — kavram numarasız, bulunduğu başlığın
// bir altında (WorkOutline ile aynı kural).
export function headingOutline(doc, numbering) {
    const counters = [0, 0, 0, 0, 0];
    const outline = [];
    let lastLevel = 1;
    doc.descendants((node) => {
        if (node.type.name === 'concept') {
            if (node.textContent.trim()) {
                outline.push({ level: Math.min(4, lastLevel + 1), number: '', text: node.textContent, concept: true });
            }
            return false;
        }
        if (node.type.name !== 'heading') return true;
        const level = node.attrs.level;
        lastLevel = level;
        counters[level]++;
        for (let deeper = level + 1; deeper <= 4; deeper++) counters[deeper] = 0;
        const number = !numbering ? '' : (level === 1 ? `${roman(Math.max(1, counters[1]))}.` : `${counters.slice(1, level + 1).join('.')}.`);
        outline.push({ level, number, text: node.textContent });
        return false;
    });
    return outline;
}

export function citations(doc) {
    const sources = [];
    doc.descendants((node) => {
        if (node.type.name === 'citation' && node.attrs.text && !sources.includes(node.attrs.text)) {
            sources.push(node.attrs.text);
        }
    });
    return sources;
}

// Numaralar ve yer tutucular: kaynak numaraları, İçindekiler ve Kaynakça listeleri.
export function refreshNumbers(editor, numbering) {
    const root = editor.view.dom;
    const sources = citations(editor.state.doc);
    root.querySelectorAll('.cite-marker').forEach((marker) => {
        marker.textContent = `[${sources.indexOf(marker.dataset.cite) + 1 || '·'}]`;
    });

    const escape = (text) => text.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
    const outline = headingOutline(editor.state.doc, numbering);
    root.querySelectorAll('.toc-edit').forEach((toc) => {
        toc.innerHTML = `<p class="rt-toc-title">İçindekiler</p>${outline.length
            ? `<ol>${outline.map((entry) => `<li class="rt-toc-l${entry.level}${entry.concept ? ' rt-toc-concept' : ''}"><span class="rt-toc-num">${entry.number}</span><span>${escape(entry.text || '(başlıksız)')}</span></li>`).join('')}</ol>`
            : '<p class="rt-empty">Başlık eklendikçe burada listelenir.</p>'}`;
    });
    root.querySelectorAll('.bibliography-edit').forEach((section) => {
        section.innerHTML = `<p class="rt-bibliography-title">Kaynakça</p>${sources.length
            ? `<ol>${sources.map((source) => `<li>${escape(source)}</li>`).join('')}</ol>`
            : '<p class="rt-empty">"Kaynak No." ile eklenen kaynaklar burada sıralanır.</p>'}`;
    });

    return { sources, outline };
}

export function createWorkEditor({ element, content, editable = true, getDocuments, getImages, onUpdate, onSelection }) {
    return new Editor({
        element,
        content,
        editable,
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3, 4] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    protocols: ['http', 'https', 'mailto'],
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: null },
                },
            }),
            TextAlign,
            FontStyle,
            TableKit.configure({ table: { resizable: false } }),
            Footnote,
            Citation,
            createDocumentNode(getDocuments),
            VideoLink,
            createImageNode(getImages),
            PageBreak,
            TableOfContents,
            Bibliography,
            Concept,
            ConceptLink,
        ],
        editorProps: {
            attributes: { class: 'rich-content rt-editor-surface', 'aria-label': 'Eser metni', spellcheck: 'true', lang: 'tr' },
        },
        onUpdate: ({ editor }) => onUpdate(editor),
        onSelectionUpdate: ({ editor }) => onSelection(editor),
        onTransaction: ({ editor }) => onSelection(editor),
    });
}

// Sayfa hesabı: sabit sayfa oranında, üst düzey blokların yüksekliğiyle (blok düzeyinde; okuma
// sayfası satır düzeyinde böldüğü için sayı yaklaşık). Sayfa Sonu ve — okumadaki gibi — her
// Başlık 1 (kitapta bölüm) yeni sayfa başlatır.
export function paginate(surface, pageContentHeight) {
    const blocks = Array.from(surface.children);
    const breaks = [];
    let used = 0;
    let pages = blocks.length ? 1 : 0;

    blocks.forEach((block, index) => {
        if (block.matches('[data-page-break]')) {
            pages++;
            used = 0;
            breaks.push({ top: block.offsetTop + block.offsetHeight, forced: true });
            return;
        }
        const next = blocks[index + 1];
        const height = (next ? next.offsetTop : block.offsetTop + block.offsetHeight) - block.offsetTop;
        if (used > 0 && (block.tagName === 'H1' || used + height > pageContentHeight)) {
            pages++;
            used = 0;
            breaks.push({ top: block.offsetTop, forced: false });
        }
        used += height;
    });

    return { pages, breaks };
}
