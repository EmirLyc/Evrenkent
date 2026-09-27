<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasDocuments;
use App\Support\PlatformSettings;
use App\Support\VideoEmbed;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Book extends Model
{
    use HasDocuments;

    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'author_id', 'title', 'slug', 'description',
        'cover_image', 'price', 'discount_price', 'discount_ends_at', 'status',
        'is_editors_pick', 'published_at', 'scheduled_publish_at',
        'average_rating', 'review_count',
        'page_count', 'document_count', 'video_count',
        'map_count', 'author_note_count', 'source_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'discount_ends_at' => 'datetime',
            'is_editors_pick' => 'boolean',
            'average_rating' => 'decimal:2',
            'review_count' => 'integer',
            'page_count' => 'integer',
            'document_count' => 'integer',
            'video_count' => 'integer',
            'map_count' => 'integer',
            'author_note_count' => 'integer',
            'source_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Kitabın tanıtım sayfasının URL'i — Kitaplığım/Favorilerim/Okuma Listem/
     * Satın Aldıklarım/Notlarım gibi panellerde tek tip bir "içeriğe git" linki
     * kurabilmek için (Article::url() ile birlikte polymorphic kullanılabiliyor).
     */
    public function url(): string
    {
        return route('kitaplar.show', $this);
    }

    /**
     * Şu an geçerli kampanya fiyatı — indirim yoksa ya da bitiş tarihi geçmişse null.
     * Süresi dolan indirimi temizleyen bir zamanlanmış iş yok, gerek de yok: süresi
     * geçmiş discount_price her yerde (kart, sayfa, sepet, satın alma, Fırsatlar rafı)
     * bu metod/scopeOnSale üzerinden okunduğu için kendiliğinden devre dışı kalıyor.
     */
    public function activeDiscountPrice(): ?string
    {
        if ($this->discount_price === null) {
            return null;
        }

        if ($this->discount_ends_at !== null && $this->discount_ends_at->isPast()) {
            return null;
        }

        return $this->discount_price;
    }

    /**
     * Kullanıcının bu kitap için ödeyeceği fiyat — kart, kitap sayfası, sepet ve
     * satın alma hepsi buradan okur, fiyat mantığı tek yerde kalsın diye.
     *
     * Premium üyede premium indirimi (varsayılan %30, Süper Admin ayarlar) kampanya
     * indirimiyle toplanmaz: ikisinden hangisi daha ucuzsa o uygulanır (toplantı kararı).
     */
    public function priceFor(?User $user = null): string
    {
        $price = $this->activeDiscountPrice() ?? $this->price;

        if ($user?->isPremium()) {
            $premium = $this->premiumPrice();

            if ((float) $premium < (float) $price) {
                return $premium;
            }
        }

        return $price;
    }

    /** Premium indirimi uygulanmış fiyat (kampanyadan bağımsız) — "Premium ile X TL" için. */
    public function premiumPrice(): string
    {
        $percent = PlatformSettings::get('premium_discount_percent');

        return number_format(round((float) $this->price * (100 - $percent) / 100, 2), 2, '.', '');
    }

    /** priceFor() sonucu premium indiriminden mi geliyor (kartta "Premium" etiketi için). */
    public function isPremiumPriceFor(?User $user): bool
    {
        return $user?->isPremium()
            && (float) $this->premiumPrice() < (float) ($this->activeDiscountPrice() ?? $this->price);
    }

    /**
     * Tanıtım sayfasını bu kullanıcı görebilir mi. "Onaylandı" + hedef tarihi olan
     * kitaplar bir teaser (Yakında Çıkacak) olarak herkese açık; tarihsiz "Onaylandı"
     * kitaplar hâlâ sadece yazarına görünür (henüz kamuya duyurulmaya hazır değil).
     * Hem tanıtım sayfası hem not ekleme aynı kuralı kullansın diye tek yerde
     * (bkz. NoteController::store).
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->status === ContentStatus::Yayinda
            || ($this->status === ContentStatus::Onaylandi && $this->scheduled_publish_at !== null)
            || ($user && $user->id === $this->author_id)
            || ($user && $user->hasRole('super_admin'));
    }

    /**
     * Bölümleri bu kullanıcı okuyabilir mi — yazarı her zaman; diğerleri kitap yayındaysa
     * ve ücretsizse ya da satın aldılarsa. Okuma sayfası ve gömülü belgeler (Faz F2) aynı
     * kuralı kullansın diye tek yerde (bkz. BookController::read, Document::isViewableBy).
     */
    public function isReadableBy(?User $user): bool
    {
        if ($user && $user->id === $this->author_id) {
            return true;
        }

        return $this->status === ContentStatus::Yayinda
            && ($this->price <= 0 || ($user && $user->hasPurchased($this)));
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_book');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'reviewable');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Yayinda);
    }

    /** En çok satın alınandan aza doğru sıralar (Çok Satanlar). */
    public function scopeBestsellers(Builder $query): Builder
    {
        return $query->withCount('purchases')->orderByDesc('purchases_count');
    }

    public function scopeEditorsPick(Builder $query): Builder
    {
        return $query->where('is_editors_pick', true);
    }

    /** Sadece şu an geçerli bir indirimi olan (Fırsatlar) kitaplar — süresi dolanlar hariç. */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('discount_price')
            ->where(fn (Builder $q) => $q->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>', now()));
    }

    /**
     * Onaylandı ama henüz yayınlanmamış, planlanan bir yayın tarihi olan kitaplar
     * (Yakında Çıkacaklar). Tarih geldiğinde books:publish-scheduled komutu bunları
     * otomatik "Yayında"ya çevirir.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Onaylandi)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '>', now())
            ->orderBy('scheduled_publish_at');
    }

    /**
     * Belge ve video sayısını metinden yeniden hesaplar (2026-09-27): önceden yazar elle
     * giriyordu ve gerçek içerikle tutarsız kalabiliyordu. Belge = bölümlerde kar tanesiyle
     * kullanılan (bu kitaba ait) farklı belgeler, video = geçerli YouTube/Vimeo satırları.
     * 0 ise null — tanıtım sayfasındaki istatistik şeridinde görünmez. Bölüm kaydedilince /
     * silinince ve belge silinince çağrılıyor (Chapter, Document modelleri).
     */
    public function refreshContentCounts(): void
    {
        $html = $this->chapters()->pluck('content')->implode('');

        preg_match_all('/data-document="(\d+)"/', $html, $documents);
        $documentIds = array_unique($documents[1]);
        $documentCount = $documentIds ? $this->documents()->whereIn('id', $documentIds)->count() : 0;

        preg_match_all('/<figure\b[^>]*\bdata-video="([^"]*)"/', $html, $videos);
        $videoCount = collect($videos[1])
            ->filter(fn (string $url) => VideoEmbed::parse(html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) !== null)
            ->count();

        $counts = ['document_count' => $documentCount ?: null, 'video_count' => $videoCount ?: null];

        // updated_at'e dokunmadan (kitabın "güncellenme" tarihi yazarın düzenlemesini göstersin).
        static::whereKey($this->getKey())->toBase()->update($counts);
        $this->forceFill($counts)->syncOriginalAttributes(array_keys($counts));
    }

    /**
     * Kitap tanıtım sayfasındaki içerik istatistik şeridi — sadece dolu olan
     * alanlar döner (yazar panelinden hangisini girmişse sadece o gösterilir).
     *
     * @return array<int, array{count: int, icon: string, label: string}>
     */
    public function contentStats(): array
    {
        return collect([
            ['count' => $this->page_count, 'icon' => 'heroicon-o-document-text', 'label' => 'sayfa'],
            ['count' => $this->document_count, 'icon' => 'heroicon-o-paper-clip', 'label' => 'belge'],
            ['count' => $this->video_count, 'icon' => 'heroicon-o-play-circle', 'label' => 'video'],
            ['count' => $this->map_count, 'icon' => 'heroicon-o-map', 'label' => 'harita'],
            ['count' => $this->author_note_count, 'icon' => 'heroicon-o-pencil-square', 'label' => 'yazar notu'],
            ['count' => $this->source_count, 'icon' => 'heroicon-o-link', 'label' => 'kaynak'],
        ])
            ->filter(fn (array $stat) => ! empty($stat['count']))
            ->values()
            ->all();
    }
}
