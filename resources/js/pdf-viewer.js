// Gömülü PDF belgeleri için site içi görüntüleyici (Faz F2). app.js'teki Alpine
// 'documentViewer' bileşeni bu dosyayı sadece bir PDF açılınca dinamik import ediyor —
// pdf.js (~1 MB worker dahil) okuma sayfasının ilk yüklemesine binmesin.
//
// Sayfalar <canvas>'a çiziliyor: tarayıcının kendi PDF görüntüleyicisi (ve "İndir"
// düğmesi) açılmıyor. Metin katmanı eklenmedi — seçip kopyalama da istenmiyor.
// "legacy" derleme: modern derleme çok yeni JS özelliklerine (ör. Uint8Array.toHex)
// dayanıyor, biraz eski Chrome/Safari'de "toHex is not a function" ile açılmıyordu.
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

/**
 * PDF'i container'a sayfa sayfa çizer. Dönen nesnenin cancel()'ı görüntüleyici
 * kapanınca yüklemeyi/çizimi durdurur.
 */
export function renderPdf(url, container, { onPage } = {}) {
    let cancelled = false;
    const task = getDocument({ url, withCredentials: true, isEvalSupported: false });

    const done = (async () => {
        const pdf = await task.promise;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);

        for (let number = 1; number <= pdf.numPages && !cancelled; number++) {
            const page = await pdf.getPage(number);
            const width = container.clientWidth || 800;
            const scale = width / page.getViewport({ scale: 1 }).width;
            const viewport = page.getViewport({ scale: scale * ratio });

            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.className = 'block w-full h-auto bg-white shadow-sm rounded-sm';
            canvas.setAttribute('aria-label', `Sayfa ${number} / ${pdf.numPages}`);
            container.appendChild(canvas);

            await page.render({ canvas, viewport }).promise;
            onPage?.(number, pdf.numPages);
        }

        return pdf.numPages;
    })();

    return {
        done,
        cancel() {
            cancelled = true;
            task.destroy();
        },
    };
}
