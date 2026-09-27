<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\Document;
use App\Support\DocxImporter;
use App\Support\EpubImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Word'den içe aktarma (Faz F1, 2026-09-27 toplantı kararı: metin içerikte ana format DOCX).
 *
 * - preview: editördeki "Word'den Aktar" düğmesi — dosyayı HTML'e çevirip editöre
 *   döndürür, hiçbir şey kaydetmez (yazar kontrol edip kendisi kaydeder).
 * - chapters: kitabın bölümler sayfası — tüm kitap dosyasını bölümlere ayırıp mevcut bölümlerin
 *   sonuna ekler. Word'de en üst düzey başlıklardan, EPUB'da her okuma dosyası bir bölüm
 *   (2026-09-27: EPUB, DOCX'in ikinci seçeneği).
 */
class DocxImportController extends Controller
{
    /** @return array<string, mixed> */
    private function fileRules(string $extensions = 'docx'): array
    {
        // mimes yerine uzantı: içerikten tahmin, Google Docs/LibreOffice çıktısını "zip"
        // sanabiliyor. Asıl doğrulama importer'ın dosyayı açabilmesi; dosya saklanmıyor.
        return ['file' => ['required', 'file', 'extensions:'.$extensions, 'max:20480']];
    }

    /** @return array<string, string> */
    private function fileMessages(): array
    {
        return [
            'file.required' => 'Bir Word (.docx) dosyası seçin.',
            'file.extensions' => 'Sadece Word (.docx) dosyası yüklenebilir. Eski .doc dosyasını Word\'de "Farklı Kaydet → .docx" ile dönüştürün.',
            'file.max' => 'Dosya en fazla 20 MB olabilir.',
        ];
    }

    /**
     * Editörün fetch isteği — hatalar da JSON dönmeli. bootstrap/app.php JSON hata yanıtını
     * sadece api/* için veriyor; $request->validate() burada 302 yönlendirmesi döndürürdü.
     */
    public function preview(Request $request, DocxImporter $importer): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->fileRules(), $this->fileMessages());
        if ($validator->fails()) {
            return $this->jsonError($validator->errors()->first('file'));
        }

        // Görseller belge olarak hangi içeriğe eklenecek (editörün açık olduğu bölümün kitabı
        // ya da makale). Yeni makale taslağında henüz içerik yok — görseller atlanır.
        $owner = match (true) {
            $request->filled('kitap') => Book::find($request->integer('kitap')),
            $request->filled('makale') => Article::find($request->integer('makale')),
            default => null,
        };
        if ($owner && $request->user()->cannot('update', $owner)) {
            return $this->jsonError('Bu içeriğe belge ekleyemezsiniz.', 403);
        }

        $created = [];
        if ($owner) {
            $importer->withImages(function (string $contents, string $mime, string $name, string $title) use ($owner, $request, &$created) {
                $document = Document::storeContents($owner, $contents, $mime, $name, $title, $request->user());
                $created[] = ['id' => $document->id, 'caption' => $document->caption()];

                return $document->id;
            });
        }

        try {
            $result = $importer->importSingle($request->file('file')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage());
        }

        if ($result['html'] === '') {
            return $this->jsonError('Dosyada aktarılacak metin bulunamadı.');
        }

        // documents null: görsellerin eklenecek bir içerik yok (yeni makale taslağı).
        return response()->json([...$result, 'documents' => $owner ? $created : null, 'images' => $importer->imageStats()]);
    }

    private function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], $status);
    }

    public function chapters(Request $request, Book $book, DocxImporter $docx, EpubImporter $epub): RedirectResponse
    {
        $this->authorize('update', $book);

        $request->validate($this->fileRules('docx,epub'), [
            ...$this->fileMessages(),
            'file.required' => 'Bir Word (.docx) ya da EPUB dosyası seçin.',
            'file.extensions' => 'Sadece Word (.docx) ya da EPUB dosyası yüklenebilir. Eski .doc dosyasını Word\'de "Farklı Kaydet → .docx" ile dönüştürün.',
        ]);
        $file = $request->file('file');
        $importer = strtolower($file->getClientOriginalExtension()) === 'epub' ? $epub : $docx;

        // Dosyadaki görseller kitabın belgeleri olur, yerlerinde kar tanesi (Faz F2).
        $importer->withImages(fn (string $contents, string $mime, string $name, string $title) => Document::storeContents($book, $contents, $mime, $name, $title, $request->user())->id);

        try {
            $chapters = $importer->importChapters($file->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $chapters = array_values(array_filter($chapters, fn ($chapter) => $chapter['html'] !== ''));
        if ($chapters === []) {
            throw ValidationException::withMessages(['file' => 'Dosyada aktarılacak metin bulunamadı.']);
        }

        $order = (int) $book->chapters()->max('order');
        $fallbackTitle = Str::of($file->getClientOriginalName())->beforeLast('.')->squish()->limit(200, '')->toString() ?: 'Bölüm';

        foreach ($chapters as $index => $chapter) {
            $book->chapters()->create([
                'order' => ++$order,
                // Başlıktan önceki metin (önsöz) → "Giriş"; hiç başlık yoksa dosya adı.
                'title' => Str::limit($chapter['title'] ?? (count($chapters) > 1 && $index === 0 ? 'Giriş' : $fallbackTitle), 250, ''),
                'content' => $chapter['html'],
            ]);
        }

        $images = $importer->imageStats();
        $message = count($chapters).' bölüm '.($importer === $epub ? 'EPUB' : 'Word').' dosyasından eklendi. Başlıkları ve sırayı kontrol edin.'
            .($images['imported'] ? " {$images['imported']} görsel belge olarak eklendi; adlarını Belgeler sayfasından düzenleyebilirsiniz." : '')
            .($images['skipped'] ? " {$images['skipped']} görsel desteklenmeyen biçimde olduğu için atlandı (sadece PNG/JPG)." : '');

        return redirect()->route('panel.yayinlarim.kitap.bolumler', $book)->with('status', $message);
    }
}
