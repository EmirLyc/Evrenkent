<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Support\DocxImporter;
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
 * - chapters: kitabın bölümler sayfası — tüm kitap dosyasını en üst düzey başlıklardan
 *   bölümlere ayırıp mevcut bölümlerin sonuna ekler.
 */
class DocxImportController extends Controller
{
    /** @return array<string, mixed> */
    private function fileRules(): array
    {
        // mimes:docx yerine uzantı: içerikten tahmin, Google Docs/LibreOffice çıktısını "zip"
        // sanabiliyor. Asıl doğrulama DocxImporter'ın dosyayı açabilmesi; dosya saklanmıyor.
        return ['file' => ['required', 'file', 'extensions:docx', 'max:20480']];
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

        try {
            $result = $importer->importSingle($request->file('file')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage());
        }

        if ($result['html'] === '') {
            return $this->jsonError('Dosyada aktarılacak metin bulunamadı.');
        }

        return response()->json($result);
    }

    private function jsonError(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422);
    }

    public function chapters(Request $request, Book $book, DocxImporter $importer): RedirectResponse
    {
        $this->authorize('update', $book);

        $request->validate($this->fileRules(), $this->fileMessages());
        $file = $request->file('file');

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

        return redirect()
            ->route('panel.yayinlarim.kitap.bolumler', $book)
            ->with('status', count($chapters).' bölüm Word dosyasından eklendi. Başlıkları ve sırayı kontrol edin.');
    }
}
