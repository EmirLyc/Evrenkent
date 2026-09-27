<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Metne gömülü belgeler (Faz F2, mockup 3 / 3.1).
 *
 * Yazar paneli: kitabın ya da makalenin "Belgeler" sayfası — PDF/JPG/PNG yükleme, ad /
 * tarih / sayfa sayısı düzenleme, silme. Yetki içeriğin kendi `update` policy'si (yazar
 * sadece Taslak/Revizyon İstendi'deyken, Süper Admin her zaman).
 *
 * Okur: show() — dosyayı sadece içeriği okuyabilen kişiye, satır içi (inline) ve
 * önbelleğe alınmadan veriyor. Site içi görüntüleyici (x-document-viewer) PDF'i pdf.js
 * ile tuvale çiziyor; tarayıcının PDF görüntüleyicisi ve indirme düğmesi açılmıyor.
 * (Tam koruma değil — tarayıcıya gelen her dosya kararlı biri tarafından alınabilir —
 * amaç "İndir" düğmesiyle paylaşımı engellemek.)
 */
class DocumentController extends Controller
{
    public function bookIndex(Book $book): View
    {
        return $this->index($book, route('panel.yayinlarim.kitap.bolumler', $book), 'Bölümlere dön');
    }

    public function articleIndex(Article $article): View
    {
        return $this->index($article, route('panel.yayinlarim.makale.duzenle', $article), 'Makaleye dön');
    }

    public function bookStore(Request $request, Book $book): RedirectResponse
    {
        return $this->store($request, $book);
    }

    public function articleStore(Request $request, Article $article): RedirectResponse
    {
        return $this->store($request, $article);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document->documentable);

        $document->update($request->validate($this->metaRules()));

        return back()->with('status', 'Belge güncellendi.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('update', $document->documentable);

        $document->delete();

        return back()->with('status', 'Belge silindi. Metindeki kar tanesi işaretleri okur sayfasında artık görünmez.');
    }

    public function show(Request $request, Document $document): StreamedResponse
    {
        abort_unless($document->isViewableBy($request->user()), 404);

        $disk = Storage::disk(config('filesystems.documents_disk'));
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->response($document->file_path, null, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            // Yüklemede içerik türü (uzantı değil) PDF/JPG/PNG diye doğrulanıyor; nosniff
            // tarayıcının onu başka bir türmüş gibi (ör. HTML) yorumlamasını engelliyor.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function index(Model $owner, string $backUrl, string $backLabel): View
    {
        $this->authorize('update', $owner);

        $documents = $owner->documents()->get()->each(fn (Document $document) => $document->setAttribute('usage_count', $document->usageCount()));

        return view('panel.yayinlarim.belgeler', [
            'owner' => $owner,
            'documents' => $documents,
            'storeUrl' => $owner instanceof Book
                ? route('panel.yayinlarim.kitap.belgeler.store', $owner)
                : route('panel.yayinlarim.makale.belgeler.store', $owner),
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
        ]);
    }

    private function store(Request $request, Model $owner): RedirectResponse
    {
        $this->authorize('update', $owner);

        $data = $request->validate([
            ...$this->metaRules(),
            // mimetypes: dosyanın içeriğine bakıyor (uzantı değil) — uzantısı .pdf olan bir HTML reddedilir.
            'file' => ['required', 'file', 'mimetypes:'.implode(',', Document::MIME_TYPES), 'max:20480'],
        ], [
            'file.required' => 'Bir PDF ya da görsel (JPG/PNG) seçin.',
            'file.mimetypes' => 'Sadece PDF, JPG ya da PNG yüklenebilir.',
            'file.max' => 'Dosya en fazla 20 MB olabilir.',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $path = $file->store('documents', config('filesystems.documents_disk'));

        $owner->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'date_label' => $data['date_label'] ?? null,
            // Boş bırakılırsa PDF'ten okunmaya çalışılıyor (bulunamazsa açıklamada sayfa yazmaz).
            'page_count' => $data['page_count'] ?? ($mime === 'application/pdf' ? $this->pdfPageCount($file->getRealPath()) : null),
            'file_path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
        ]);

        return back()->with('status', 'Belge yüklendi. Metne eklemek için editördeki kar tanesi düğmesini kullanın.');
    }

    /** @return array<string, list<string>> */
    private function metaRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'date_label' => ['nullable', 'string', 'max:100'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * PDF'teki sayfa sayısı — kütüphanesiz, en iyi tahmin: sayfa ağacının kökündeki /Count
     * (en büyük değer). Nesne akışları sıkıştırılmış PDF'lerde bulunamayabilir, o zaman null.
     */
    private function pdfPageCount(string $path): ?int
    {
        $contents = @file_get_contents($path, false, null, 0, 30 * 1024 * 1024);
        if ($contents === false || ! preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)|/Count\s+(\d+)[^>]*?/Type\s*/Pages\b#s', $contents, $matches)) {
            return null;
        }

        $count = max(array_map('intval', array_merge(array_filter($matches[1]), array_filter($matches[2]))) ?: [0]);

        return $count > 0 ? $count : null;
    }
}
