<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Metne gömülü belge (Faz F2) — kitaba ya da makaleye ait PDF veya görsel. Dosya herkese
 * açık olmayan diskte (config filesystems.documents_disk); sadece DocumentController
 * üzerinden, içeriği okuyabilen kişiye site içinde gösteriliyor.
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'uploaded_by', 'title', 'date_label', 'page_count',
        'file_path', 'mime_type', 'size', 'original_name',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Document $document) {
            Storage::disk(config('filesystems.documents_disk'))->delete($document->file_path);

            // Silinen belge metinde kullanılıyorsa kitabın belge sayısı düşer.
            if ($document->documentable instanceof Book) {
                $document->documentable->refreshContentCounts();
            }
        });
    }

    /**
     * Dosya içeriğinden belge oluşturur — Word'den aktarılan görseller (DocxImporter::withImages).
     * İçerik türü çağıran tarafta doğrulanmış olmalı (MIME_TYPES).
     */
    public static function storeContents(Model $owner, string $contents, string $mime, string $originalName, string $title, ?User $by): self
    {
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'][$mime];
        $path = 'documents/'.Str::uuid().'.'.$extension;

        Storage::disk(config('filesystems.documents_disk'))->put($path, $contents);

        return $owner->documents()->create([
            'uploaded_by' => $by?->id,
            'title' => $title,
            'file_path' => $path,
            'mime_type' => $mime,
            'size' => strlen($contents),
            'original_name' => mb_substr($originalName, 0, 255),
        ]);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Site içi görüntüleme adresi (erişim kontrolü DocumentController::show'da). */
    public function viewUrl(): string
    {
        return route('belgeler.goster', $this);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** Kar tanesi ikonunun açıklaması — mockup: "Paris Sefareti'nden Gönderilen Yazı (3 Haziran 1873 / 4 sayfa)". */
    public function caption(): string
    {
        $meta = array_filter([
            $this->date_label,
            $this->page_count ? $this->page_count.' sayfa' : null,
        ]);

        return $meta ? $this->title.' ('.implode(' / ', $meta).')' : $this->title;
    }

    /**
     * Belgeyi bu kullanıcı görebilir mi — ait olduğu içeriği okuyabilen herkes; ayrıca
     * içeriği panelde inceleyenler (Süper Admin, makalede Dergi Editörü) ve yazarı.
     */
    public function isViewableBy(?User $user): bool
    {
        $owner = $this->documentable;

        return match (true) {
            $owner instanceof Book => $owner->isReadableBy($user) || ($user?->can('view', $owner) ?? false),
            $owner instanceof Article => $owner->isVisibleTo($user) || ($user?->can('view', $owner) ?? false),
            default => false,
        };
    }

    /**
     * Bu belgenin geçtiği içerik parçası sayısı (bölüm ya da makale gövdesi) — silmeden
     * önce yazara "3 bölümde kullanılıyor" diye uyarı göstermek için.
     */
    public function usageCount(): int
    {
        $marker = '%data-document="'.$this->id.'"%';
        $owner = $this->documentable;

        return match (true) {
            $owner instanceof Book => $owner->chapters()->where('content', 'like', $marker)->count(),
            $owner instanceof Article => (int) str_contains((string) $owner->content, 'data-document="'.$this->id.'"'),
            default => 0,
        };
    }
}
