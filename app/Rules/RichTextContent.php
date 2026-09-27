<?php

namespace App\Rules;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Zengin metin editöründen gelen içerik (Faz F1): boş editör çıktısı ("<p></p>") "dolu"
 * sayılmasın, aşırı büyük gövde reddedilsin. `required|string` ile birlikte kullanılır.
 */
class RichTextContent implements ValidationRule
{
    /** ~2 MB HTML — uzun bir roman bölümü için bile rahat. */
    public const MAX_LENGTH = 2_000_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $fail('İçerik çok uzun. Metni birkaç bölüme ayırın.');

            return;
        }

        if (! RichText::hasText($value)) {
            $fail('İçerik boş olamaz.');
        }
    }
}
