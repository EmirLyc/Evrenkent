<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Document;
use App\Models\MagazineIssue;
use App\Rules\RichTextContent;
use App\Support\BookDocument;
use App\Support\DictionaryDocument;
use App\Support\DocxImporter;
use App\Support\EpubImporter;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Yeni Yayın sayfası (Faz G2, "Yazarın Gözünden" 1.1.1–1.1.6). Sol sütunda 4 adım:
 *  1. Temel Bilgiler — tür, başlık, alt başlık, (dergi yazısında) sayı, kategoriler, sayfa oranı,
 *     başlık numaralandırma; yeni eserde isteğe bağlı Word / EPUB dosyası (belge: eser ya hazır
 *     bir docx'ten gelir ya da burada yazılır),
 *  2. İçerik — tek belge editörü (kitapta her Başlık 1 bir bölüm, bkz. BookDocument), otomatik kayıt,
 *  3. Kapak ve Tanıtım — kapak görseli, tanıtım metni, (kitapta) içerik istatistikleri,
 *  4. Önizleme ve Gönder — eksik kontrolü, önizleme, onaya gönderme.
 */
class WorkController extends Controller
{
    public const STEPS = [
        'bilgiler' => 'Temel Bilgiler',
        'icerik' => 'İçerik',
        'kapak' => 'Kapak ve Tanıtım',
        'gonder' => 'Önizleme ve Gönder',
    ];

    public const RATIOS = [
        '13x20' => ['13 × 20 cm', 'En yaygın kitap ölçüsü'],
        '13x21' => ['13 × 21 cm', 'Roman ve deneme'],
        '16x24' => ['16 × 24 cm', 'Akademik, görselli kitap'],
        '21x27.5' => ['21 × 27,5 cm', 'Dergi'],
    ];

    // --- Yeni eser (Adım 1) ----------------------------------------------------------------

    /**
     * "Yeni Yayın Oluştur" pop-up'ı (1.1.0) Kitap / Dergi ile Sözlük'ü ayırıyor: ?tur=sozluk ile
     * gelen form sözlük oluşturur (Faz G3) — sözlük bir kitap türü, editörde "Kavram" açılır.
     */
    public function create(Request $request): View
    {
        $tur = $request->query('tur');

        return view('panel.yayinlarim.eser', [
            'work' => null,
            'type' => $tur === 'makale' ? 'makale' : 'kitap',
            'kind' => $tur === 'sozluk' ? Book::KIND_SOZLUK : Book::KIND_KITAP,
            'step' => 'bilgiler',
            ...$this->infoFormData(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type') === 'makale' ? 'makale' : 'kitap';
        $this->authorize('create', $type === 'kitap' ? Book::class : Article::class);

        $data = $request->validate([
            'type' => ['required', 'in:kitap,makale,sozluk'],
            ...$this->infoRules($type, null),
            'file' => ['nullable', 'file', 'extensions:docx,epub', 'max:20480'],
        ], $this->infoMessages());

        $attributes = $this->infoAttributes($data, $type);
        $attributes['author_id'] = $request->user()->id;
        if ($type === 'kitap') {
            $attributes['kind'] = $data['type'] === 'sozluk' ? Book::KIND_SOZLUK : Book::KIND_KITAP;
        }
        $attributes['slug'] = Str::slug($data['title']).'-'.Str::random(6);
        $attributes['status'] = ContentStatus::Taslak;

        $work = $type === 'kitap' ? Book::create($attributes) : Article::create($attributes);
        $work->categories()->sync($data['categories'] ?? []);

        $status = 'Eser oluşturuldu. Metni buraya yazabilir ya da "…" menüsünden Word / EPUB dosyasından aktarabilirsiniz.';
        if ($request->hasFile('file')) {
            $result = $this->importInto($work, $request->file('file'), $request);
            $this->storeContent($work, $result['html']);
            $status = 'Eser oluşturuldu ve dosyadaki metin aktarıldı'.($work instanceof Book ? ' (her "Başlık 1" bir bölüm oldu)' : '').'. Kontrol edin.';
        }

        return redirect()->to($this->stepUrl($work, 'icerik'))->with('status', $status);
    }

    // --- Adımlar ---------------------------------------------------------------------------

    public function editBook(Book $book, string $step = 'icerik'): View
    {
        return $this->edit($book, $step);
    }

    public function editArticle(Article $article, string $step = 'icerik'): View
    {
        return $this->edit($article, $step);
    }

    private function edit(Book|Article $work, string $step): View
    {
        $this->authorize('update', $work);
        abort_unless(array_key_exists($step, self::STEPS), 404);

        $isBook = $work instanceof Book;
        $work->load(['categories', 'documents', ...($isBook ? ['chapters'] : ['magazineIssue.magazine'])]);

        return view('panel.yayinlarim.eser', [
            'work' => $work,
            'type' => $isBook ? 'kitap' : 'makale',
            'kind' => $isBook ? $work->kind : null,
            'step' => $step,
            'done' => $this->completion($work),
            'editorHtml' => $isBook ? BookDocument::toHtml($work) : (string) $work->content,
            ...($step === 'bilgiler' ? $this->infoFormData($work) : []),
        ]);
    }

    /** Adım 1: Temel Bilgiler. */
    public function updateBook(Request $request, Book $book): RedirectResponse
    {
        return $this->updateInfo($request, $book);
    }

    public function updateArticle(Request $request, Article $article): RedirectResponse
    {
        return $this->updateInfo($request, $article);
    }

    private function updateInfo(Request $request, Book|Article $work): RedirectResponse
    {
        $this->authorize('update', $work);
        $type = $work instanceof Book ? 'kitap' : 'makale';

        $data = $request->validate($this->infoRules($type, $work), $this->infoMessages());
        $work->update($this->infoAttributes($data, $type));
        $work->categories()->sync($data['categories'] ?? []);

        return $request->boolean('devam')
            ? redirect()->to($this->stepUrl($work, 'icerik'))
            : redirect()->to($this->stepUrl($work, 'bilgiler'))->with('status', 'Temel bilgiler kaydedildi.');
    }

    /** Adım 2: İçerik — editörün otomatik kaydı (JSON). */
    public function saveBookContent(Request $request, Book $book): JsonResponse
    {
        return $this->saveContent($request, $book);
    }

    public function saveArticleContent(Request $request, Article $article): JsonResponse
    {
        return $this->saveContent($request, $article);
    }

    private function saveContent(Request $request, Book|Article $work): JsonResponse
    {
        $this->authorize('update', $work);

        $data = Validator::make($request->all(), [
            'html' => ['present', 'nullable', 'string', 'max:'.($work instanceof Book ? 20_000_000 : RichTextContent::MAX_LENGTH)],
            'page_ratio' => ['nullable', Rule::in(array_keys(self::RATIOS))],
            'page_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['html.max' => 'Metin çok uzun.'])->validate();

        $this->storeContent($work, (string) ($data['html'] ?? ''), $data['page_count'] ?? null);
        if (! empty($data['page_ratio'])) {
            $work->forceFill(['page_ratio' => $data['page_ratio']])->save();
        }
        $work->touch();

        return response()->json([
            'saved_label' => now()->translatedFormat('j F Y, H:i'),
            'chapters' => $work instanceof Book ? $work->chapters()->count() : null,
        ]);
    }

    /** Adım 3: Kapak ve Tanıtım. */
    public function updateBookCover(Request $request, Book $book): RedirectResponse
    {
        return $this->updateCover($request, $book);
    }

    public function updateArticleCover(Request $request, Article $article): RedirectResponse
    {
        return $this->updateCover($request, $article);
    }

    private function updateCover(Request $request, Book|Article $work): RedirectResponse
    {
        $this->authorize('update', $work);
        $isBook = $work instanceof Book;

        $data = $request->validate([
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'description' => ['nullable', 'string', 'max:5000'],
            ...($isBook ? [
                'map_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'author_note_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            ] : []),
        ], ['cover_image.image' => 'Kapak bir görsel (JPG / PNG / WEBP) olmalı.', 'cover_image.max' => 'Kapak en fazla 5 MB olabilir.']);

        if ($request->hasFile('cover_image')) {
            $disk = Storage::disk(config('filesystems.covers_disk'));
            $old = $work->cover_image;
            $data['cover_image'] = $request->file('cover_image')->store($isBook ? 'covers/books' : 'covers/articles', config('filesystems.covers_disk'));
            $old ? $disk->delete($old) : null;
        } else {
            unset($data['cover_image']);
        }

        $work->update($data);

        return $request->boolean('devam')
            ? redirect()->to($this->stepUrl($work, 'gonder'))
            : redirect()->to($this->stepUrl($work, 'kapak'))->with('status', 'Kapak ve tanıtım kaydedildi.');
    }

    /** "Ekle → Görsel": metin içi görsel yükleme (JSON). */
    public function uploadBookImage(Request $request, Book $book): JsonResponse
    {
        return $this->uploadImage($request, $book);
    }

    public function uploadArticleImage(Request $request, Article $article): JsonResponse
    {
        return $this->uploadImage($request, $article);
    }

    private function uploadImage(Request $request, Book|Article $work): JsonResponse
    {
        $this->authorize('update', $work);

        $data = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:200'],
        ], [
            'file.required' => 'Bir görsel (JPG / PNG) seçin.',
            'file.mimetypes' => 'Sadece JPG ya da PNG görsel eklenebilir.',
            'file.max' => 'Görsel en fazla 10 MB olabilir.',
        ])->validate();

        $file = $request->file('file');
        $document = Document::storeContents(
            $work, (string) file_get_contents($file->getRealPath()), $file->getMimeType(), $file->getClientOriginalName(),
            trim($data['caption'] ?? '') !== '' ? trim($data['caption']) : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            $request->user(), Document::KIND_GORSEL,
        );

        return response()->json(['id' => $document->id, 'url' => $document->viewUrl(), 'title' => $document->title], 201);
    }

    /**
     * Word / EPUB → editör (JSON; kaydetmez, editör kendi kaydeder). Görseller eserin metin içi
     * görselleri olarak kaydedilir.
     */
    public function importBook(Request $request, Book $book): JsonResponse
    {
        return $this->importJson($request, $book);
    }

    public function importArticle(Request $request, Article $article): JsonResponse
    {
        return $this->importJson($request, $article);
    }

    private function importJson(Request $request, Book|Article $work): JsonResponse
    {
        $this->authorize('update', $work);

        $validator = Validator::make($request->all(), ['file' => ['required', 'file', 'extensions:docx,epub', 'max:20480']], [
            'file.required' => 'Bir Word (.docx) ya da EPUB dosyası seçin.',
            'file.extensions' => 'Sadece Word (.docx) ya da EPUB dosyası aktarılabilir. Eski .doc dosyasını Word\'de "Farklı Kaydet → .docx" ile dönüştürün.',
            'file.max' => 'Dosya en fazla 20 MB olabilir.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first('file'), 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->importInto($work, $request->file('file'), $request);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json($result);
    }

    // --- Yardımcılar -----------------------------------------------------------------------

    /**
     * @return array{title: ?string, html: string, images: array{imported: int, skipped: int}, images_list: list<array{id: int, url: string, title: string}>}
     */
    private function importInto(Book|Article $work, UploadedFile $file, Request $request): array
    {
        $importer = strtolower($file->getClientOriginalExtension()) === 'epub' ? new EpubImporter : new DocxImporter;
        $created = [];
        $importer->withImages(function (string $contents, string $mime, string $name, string $title) use ($work, $request, &$created) {
            $document = Document::storeContents($work, $contents, $mime, $name, $title, $request->user(), Document::KIND_GORSEL);
            $created[] = ['id' => $document->id, 'url' => $document->viewUrl(), 'title' => $document->title];

            return $document->id;
        });

        try {
            $result = $importer->importDocument($file->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if (! RichText::hasText($result['html'])) {
            throw ValidationException::withMessages(['file' => 'Dosyada aktarılacak metin bulunamadı.']);
        }

        return [...$result, 'images' => $importer->imageStats(), 'images_list' => $created];
    }

    private function storeContent(Book|Article $work, string $html, ?int $pageCount = null): void
    {
        if ($work instanceof Book) {
            // Sözlük (Faz G3): kavram anahtarları tamamlanır, maddeler metinden yeniden üretilir.
            BookDocument::sync($work, $work->isDictionary() ? DictionaryDocument::assignKeys($html) : $html);
            DictionaryDocument::sync($work);
            // Sayfa ve kaynak sayısı artık otomatik: editördeki sayfa hesabı ve tekil kaynaklar.
            $sources = collect($work->chapters()->pluck('content'))->flatMap(fn ($content) => RichText::citations($content))->unique()->count();
            $work->forceFill([
                'page_count' => $pageCount ?: $work->page_count,
                'source_count' => $sources ?: null,
            ])->saveQuietly();
        } else {
            $work->update(['content' => $html]);
        }
    }

    /** @return array<string, bool> */
    private function completion(Book|Article $work): array
    {
        $hasText = $work instanceof Book
            ? $work->chapters->contains(fn ($chapter) => RichText::hasText($chapter->content))
            : RichText::hasText($work->content);

        return [
            'bilgiler' => filled($work->title) && ($work instanceof Book || $work->magazine_issue_id !== null),
            'icerik' => $hasText,
            'kapak' => filled($work->cover_image) && filled($work->description),
            'gonder' => ! in_array($work->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true),
        ];
    }

    public function stepUrl(Book|Article $work, string $step): string
    {
        return $work instanceof Book
            ? route('panel.yayinlarim.kitap.duzenle', [$work, $step])
            : route('panel.yayinlarim.makale.duzenle', [$work, $step]);
    }

    /** @return array<string, mixed> */
    private function infoFormData(Book|Article|null $work): array
    {
        $issues = $this->assignableMagazineIssues();
        if ($work instanceof Article && $work->magazineIssue && ! $issues->contains('id', $work->magazine_issue_id)) {
            $issues = $issues->push($work->magazineIssue)->sortBy('title')->values();
        }

        return [
            'categories' => Category::orderBy('name')->get(),
            'magazineIssues' => $issues,
        ];
    }

    /**
     * Makale gönderilebilecek sayılar — yazar ya da editör olarak atandığı dergilerin yayında
     * olmayan / reddedilmemiş sayıları (Faz E).
     *
     * @return Collection<int, MagazineIssue>
     */
    private function assignableMagazineIssues(): Collection
    {
        return MagazineIssue::whereNotIn('status', [ContentStatus::Yayinda, ContentStatus::Reddedildi])
            ->whereIn('magazine_id', auth()->user()->writableMagazineIds())
            ->with('magazine')
            ->orderBy('title')
            ->get();
    }

    /** @return array<string, array<mixed>> */
    private function infoRules(string $type, Book|Article|null $work): array
    {
        $allowedIssues = $this->assignableMagazineIssues()->pluck('id')
            ->when($work instanceof Article && $work->magazine_issue_id, fn ($ids) => $ids->push($work->magazine_issue_id));

        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'page_ratio' => ['required', Rule::in(array_keys(self::RATIOS))],
            'heading_numbering' => ['nullable', 'boolean'],
            ...($type === 'makale'
                ? ['magazine_issue_id' => ['required', Rule::in($allowedIssues)]]
                // Yazarın önerdiği hedef yayın tarihi — Süper Admin onaylarken görür, değiştirebilir.
                : ['scheduled_publish_at' => ['nullable', 'date', 'after:now']]),
        ];
    }

    /** @return array<string, string> */
    private function infoMessages(): array
    {
        return [
            'magazine_issue_id.required' => 'Yazının gönderileceği dergi sayısını seçin.',
            'magazine_issue_id.in' => 'Bu sayıya yazı gönderemezsiniz — sadece yazar ya da editör olarak atandığınız dergilerin açık sayılarına gönderebilirsiniz.',
            'file.extensions' => 'Sadece Word (.docx) ya da EPUB dosyası aktarılabilir.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function infoAttributes(array $data, string $type): array
    {
        return [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'page_ratio' => $data['page_ratio'],
            'heading_numbering' => (bool) ($data['heading_numbering'] ?? false),
            ...($type === 'makale'
                ? ['magazine_issue_id' => $data['magazine_issue_id']]
                : ['scheduled_publish_at' => $data['scheduled_publish_at'] ?? null]),
        ];
    }
}
