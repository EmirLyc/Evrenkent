<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Kitap fiyatını Süper Admin'in formlarından (onay ekranı, admin kitap formu) okurken
 * uygulanan ortak kural: fiyat 0 ise kitap açıkça "Ücretsiz" olarak işaretlenmiş olmalı.
 *
 * Neden: fiyatı 0 olan bir kitap BookController::read'de kilitsiz sayılıyor, yani herkes
 * satın almadan okuyabiliyor. Yazar artık fiyat girmediği için yeni kitaplar onaya 0 TL
 * ile geliyor — fiyat alanı unutulup kitabın yanlışlıkla bedava yayına çıkmaması için
 * "ücretsiz" kararı bilinçli bir işaretle verilmeli.
 */
trait ResolvesBookPrice
{
    /**
     * @param  array<string, mixed>  $data  Doğrulanmış form verisi (en az 'price' içerir)
     * @return array<string, mixed>
     */
    protected function resolveBookPrice(Request $request, array $data): array
    {
        if ($request->boolean('is_free')) {
            // Ücretsiz bir kitabın kampanya indirimi olamaz.
            return array_merge($data, ['price' => 0, 'discount_price' => null, 'discount_ends_at' => null]);
        }

        if ((float) ($data['price'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'price' => 'Bir fiyat girin ya da kitabı "Ücretsiz" olarak işaretleyin — fiyatı 0 olan kitabı herkes satın almadan okuyabilir.',
            ]);
        }

        return $data;
    }
}
