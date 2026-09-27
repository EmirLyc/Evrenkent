<?php

namespace App\Models;

use Database\Factories\MagazineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dergi (Faz E) — sayılar bir dergiye bağlı. Editörünü ve yazarlarını Süper Admin atar
 * (2026-09-27 revizesi). Sayının kendi editor_id'si derginin editörüyle senkron tutulur
 * (bkz. AdminMagazineController::update) — editör yetkileri (policy'ler, Dergi Yönetimi
 * paneli, bildirimler) sayı düzeyinde editor_id'ye bağlı olduğu için.
 */
class Magazine extends Model
{
    /** @use HasFactory<MagazineFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'cover_image', 'editor_id'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    /** Bu dergiye makale gönderebilen yazarlar. */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'magazine_author');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MagazineIssue::class);
    }
}
