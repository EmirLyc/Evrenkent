<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\NoteType;
use App\Enums\SubscriptionPlan;
use App\Support\PlatformSettings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_premium', 'premium_until'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Yazarlık (Yayın Yönetimi) yetkisi olan roller. Rol PDF'i: Dergi Editörü "Yazar'ın paneline
     * ek olarak" Dergi Yönetimi'ne sahip — editöre ayrıca yazar rolü atamak gerekmiyor.
     */
    public const AUTHOR_ROLES = ['yazar', 'dergi_editoru'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function editedMagazineIssues(): HasMany
    {
        return $this->hasMany(MagazineIssue::class, 'editor_id');
    }

    /** Editörü olduğu dergiler (Süper Admin atar). */
    public function editedMagazines(): HasMany
    {
        return $this->hasMany(Magazine::class, 'editor_id');
    }

    /** Makale gönderebildiği dergiler (Süper Admin atar). */
    public function authoredMagazines(): BelongsToMany
    {
        return $this->belongsToMany(Magazine::class, 'magazine_author');
    }

    /** Kitap/makale yazabilir mi (Yayın Yönetimi) — yazar ya da dergi editörü. */
    public function canAuthor(): bool
    {
        return $this->hasAnyRole(self::AUTHOR_ROLES);
    }

    /** Yazarlık yetkisi olan kullanıcılar (canAuthor ile aynı koşul, sorgu olarak). */
    public function scopeAuthors(Builder $query): Builder
    {
        return $query->role(self::AUTHOR_ROLES);
    }

    /**
     * Makale gönderebildiği dergiler: yazar olarak atandıkları + editörü oldukları (editör kendi
     * dergisine de yazar — mockup'taki "Editörün Makalesi").
     *
     * @return Collection<int, int>
     */
    public function writableMagazineIds(): Collection
    {
        return $this->authoredMagazines()->pluck('magazines.id')
            ->merge($this->editedMagazines()->pluck('id'))
            ->unique()
            ->values();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function readingListItems(): HasMany
    {
        return $this->hasMany(ReadingListItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Premium şu an geçerli mi — is_premium tek başına yetmez, bitiş tarihi de geçmemiş
     * olmalı (önceden premium_until hiçbir yerde kontrol edilmiyordu, süresi dolan üye
     * premium sayılmaya devam ediyordu). premium_until boşsa süresiz premium (Süper Admin
     * elle verebiliyor).
     */
    public function isPremium(): bool
    {
        return $this->is_premium
            && ($this->premium_until === null || $this->premium_until->isFuture());
    }

    /** Şu an geçerli premium üyeler (isPremium ile aynı koşul, sorgu olarak). */
    public function scopePremium(Builder $query): Builder
    {
        return $query->where('is_premium', true)
            ->where(fn (Builder $q) => $q->whereNull('premium_until')->orWhere('premium_until', '>', now()));
    }

    /**
     * Premium abonelik satın alır (mock ödeme — gerçek gateway gelene kadar purchase() ile
     * aynı yaklaşım). Zaten premium olan üyenin süresi bitiş tarihinin üzerine eklenir,
     * yani erken yenileme kalan günleri yakmaz.
     */
    public function subscribe(SubscriptionPlan $plan): Subscription
    {
        $startsAt = $this->isPremium() && $this->premium_until !== null ? $this->premium_until : now();
        $endsAt = $plan->endsAt($startsAt);

        $subscription = $this->subscriptions()->create([
            'plan' => $plan,
            'amount' => $plan->price(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'payment_status' => 'completed',
        ]);

        $this->update(['is_premium' => true, 'premium_until' => $endsAt]);

        return $subscription;
    }

    /**
     * Çalışma alanındaki (Defter/Not/Alıntı) kayıt sınırı — premium üyede sınırsız (null),
     * ücretsiz hesapta Süper Admin'in ayarladığı kota (her alan ayrı, varsayılan 10).
     */
    public function noteQuota(NoteType $type): ?int
    {
        return $this->isPremium() ? null : PlatformSettings::noteQuota($type);
    }

    public function canCreateNote(NoteType $type): bool
    {
        $quota = $this->noteQuota($type);

        return $quota === null || $this->notes()->where('type', $type)->count() < $quota;
    }

    public function hasFavorited(Model $favoritable): bool
    {
        return $this->favorites()
            ->where('favoritable_type', $favoritable::class)
            ->where('favoritable_id', $favoritable->getKey())
            ->exists();
    }

    public function hasPurchased(Book $book): bool
    {
        return $this->purchases()->where('book_id', $book->id)->exists();
    }

    public function hasInCart(Book $book): bool
    {
        return $this->cartItems()->where('book_id', $book->id)->exists();
    }

    /**
     * Gerçek ödeme entegrasyonu (Stripe/iyzico) gelecek bir faz — bu metod ödeme
     * sorulmadan anında/mock tamamlanmış bir satın alma kaydı oluşturur. Hem tekil
     * "Satın Al" (PurchaseController) hem sepet ödemesi (CartController) bunu kullanır.
     * Tutar Book::priceFor()'dan gelir (geçerli kampanya indirimi varsa o).
     */
    public function purchase(Book $book): Purchase
    {
        return $this->purchases()->firstOrCreate([
            'book_id' => $book->id,
        ], [
            'amount' => $book->priceFor($this),
            'purchased_at' => now(),
            'payment_status' => 'completed',
        ]);
    }

    public function readingListItemFor(Model $readable): ?ReadingListItem
    {
        return $this->readingListItems()
            ->where('readable_type', $readable::class)
            ->where('readable_id', $readable->getKey())
            ->first();
    }

    /**
     * Girişten sonra role göre yönlendirilecek yol.
     * Süper Admin -> kendi dashboard'u, Dergi Editörü -> kendi dashboard'u,
     * Yazar -> Yayınlarım, Okur -> Anasayfa (panele değil — sidebar zaten açık
     * geliyor, "Kitaplığım" bir tık uzakta, ayrıca kullanıcı doğrudan panele
     * düşürülmek istemedi).
     */
    public function redirectPath(): string
    {
        if ($this->hasRole('super_admin')) {
            return '/panel/admin-panel';
        }

        if ($this->hasRole('dergi_editoru')) {
            return '/panel/dergi';
        }

        if ($this->hasRole('yazar')) {
            return '/panel/yayinlarim';
        }

        return '/';
    }
}
