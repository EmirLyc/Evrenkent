<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anahtar/değer platform ayarı — doğrudan değil App\Support\PlatformSettings üzerinden
 * kullanılır (varsayılanlar ve tip dönüşümü orada).
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
