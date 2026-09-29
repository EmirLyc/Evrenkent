// Defterim editörü (Faz H5, "Okurun Gözünden" — Defterim 1): Normal / Başlık 1–3, kalın, eğik,
// altı ve üstü çizili, listeler, girinti, alıntı, bağlantı, görsel. Tiptap ayrı parça olarak
// yalnızca Defterim sayfasında yükleniyor (notebook-component.js dinamik import ediyor).

import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// Okurun kendi yüklediği görsel (sunucu sadece defter görsellerinin adresini kabul ediyor).
const Image = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return { src: { default: null }, alt: { default: '' } };
    },
    parseHTML() {
        return [{ tag: 'img[src]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['img', mergeAttributes(HTMLAttributes)];
    },
});

export function createNotebookEditor({ element, content, onUpdate, onSelection }) {
    return new Editor({
        element,
        content,
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    protocols: ['http', 'https', 'mailto'],
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
                },
            }),
            Image,
        ],
        editorProps: {
            attributes: { class: 'notebook-surface', 'aria-label': 'Defter metni', spellcheck: 'true', lang: 'tr' },
        },
        onUpdate: ({ editor }) => onUpdate(editor),
        onSelectionUpdate: ({ editor }) => onSelection(editor),
        onTransaction: ({ editor }) => onSelection(editor),
    });
}

// Sunucudaki NotebookHtml::wordCount ile aynı kural: boşlukla ayrılan parçalar.
export function countWords(editor) {
    const text = editor.getText({ blockSeparator: ' ' }).trim();
    return text ? text.split(/\s+/u).length : 0;
}

function escape(text) {
    return String(text ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

// "Alıntı ekle" / "Not ekle": kaynağıyla bir alıntı bloğu (notta altında okurun notu).
export function sourceHtml(item) {
    const cite = `— ${escape(item.work)}${item.page ? `, s. ${escape(item.page)}` : ''}`;
    const quote = item.quote ? `<blockquote><p>${escape(item.quote).replace(/\n+/g, '</p><p>')}</p><p><em>${cite}</em></p></blockquote>` : '';
    const note = item.note ? `<p><strong>Notum:</strong> ${escape(item.note).replace(/\n/g, '<br>')}</p>` : '';
    return `${quote}${note}<p></p>`;
}
