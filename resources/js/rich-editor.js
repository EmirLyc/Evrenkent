// Bölüm / makale zengin metin editörü (Faz F1). app.js'teki Alpine 'richEditor' bileşeni
// bu dosyayı sadece editör olan sayfada dinamik import ediyor — Tiptap her sayfada yüklenmesin.
//
// Kaydedilen HTML sunucuda RichText::normalize'dan geçiyor; buradaki şema (h2/h3, kalın,
// eğik, altı çizili, üstü çizili, alıntı, listeler, bağlantı, dipnot) oradaki izinli
// etiketlerle aynı tutulmalı.
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// Dipnot: <span data-footnote="metin"></span> — metin işaretin olduğu yerde saklanıyor,
// numaralar hem editörde (CSS sayacı) hem okuma sayfasında sırayla veriliyor.
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

// Gömülü belge (Faz F2): <span data-document="id"></span> — editörde kar tanesi, üstüne
// gelince belgenin açıklaması. Okuma sayfasında RichText::render bunu belgeye çözüyor;
// listede olmayan (silinmiş) belge editörde kırmızı görünür, okur sayfasında görünmez.
const createDocumentNode = (documents) => Node.create({
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
        const found = documents.find((document) => String(document.id) === String(node.attrs.id));

        return ['span', mergeAttributes(HTMLAttributes, {
            class: found ? 'document-marker-edit' : 'document-marker-edit is-missing',
            title: found ? found.caption : 'Bu belge silinmiş — okur sayfasında görünmez',
        })];
    },
});

export function createEditor({ element, content, documents = [], onUpdate, onSelection }) {
    return new Editor({
        element,
        content,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    protocols: ['http', 'https', 'mailto'],
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: null },
                },
            }),
            Footnote,
            createDocumentNode(documents),
        ],
        editorProps: {
            attributes: { class: 'rich-content rich-editor-surface', 'aria-label': 'İçerik' },
        },
        onUpdate: ({ editor }) => onUpdate(editor),
        onSelectionUpdate: ({ editor }) => onSelection(editor),
        onTransaction: ({ editor }) => onSelection(editor),
    });
}
