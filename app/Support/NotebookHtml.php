<?php

namespace App\Support;

use App\Enums\NoteType;
use App\Models\Note;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Defterim içeriği (Faz H5, "Defterim 1" araç çubuğu): paragraf, Başlık 1–3, kalın / eğik /
 * altı / üstü çizili, listeler, alıntı, bağlantı ve okurun kendi yüklediği görsel. Kayıt öncesi
 * bu beyaz listeye indiriliyor; görsel sadece defter görsellerinin adresinden.
 */
class NotebookHtml
{
    public static function clean(?string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('hr')
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(500_000);

        $clean = (new HtmlSanitizer($config))->sanitize((string) $html);

        // Görsel: sadece defter görselleri (başka siteden resim çekip okurun IP'sini sızdırmasın).
        $base = preg_quote(rtrim(self::imageBaseUrl(), '/'), '#');

        return preg_replace_callback('#<img\b[^>]*>#i', fn ($match) => preg_match('#\bsrc="'.$base.'/#', $match[0]) ? $match[0] : '', $clean);
    }

    /** Görünen metnin kelime sayısı (editörün alt çubuğundaki sayaçla aynı kural). */
    public static function wordCount(?string $html): int
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<(/p|br\s*/?|/h[1-3]|/li|/blockquote)>#i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return count($words);
    }

    /** Listede arama ve özet için düz metin. */
    public static function plainText(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<(/p|br\s*/?|/h[1-3]|/li|/blockquote)>#i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function imageBaseUrl(): string
    {
        return Storage::disk(config('filesystems.covers_disk'))->url('defterler');
    }

    /**
     * Metindeki defter görsellerinin diskteki yolları (defterler/{kullanıcı}/{dosya}).
     *
     * @return list<string>
     */
    public static function imagePaths(?string $html): array
    {
        $base = preg_quote(rtrim(self::imageBaseUrl(), '/'), '#');
        preg_match_all('#<img\b[^>]*\bsrc="'.$base.'/([^"?\#]+)"#i', (string) $html, $matches);

        return array_values(array_unique(array_map(fn ($path) => 'defterler/'.rawurldecode($path), $matches[1])));
    }

    /**
     * Artık hiçbir defterinde geçmeyen görselleri siler. Defter silinince hemen çağrılır;
     * metinden çıkarılan görseller için zamanlanmış temizlik (notebooks:prune-images) var.
     * $olderThan verilirse sadece o andan önce yüklenmiş dosyalar silinir — yeni yüklenmiş
     * ama otomatik kaydı henüz gelmemiş bir görsel yanlışlıkla gitmesin.
     *
     * @param  list<string>|null  $candidates  bakılacak yollar (null: kullanıcının bütün görselleri)
     * @return int silinen dosya sayısı
     */
    public static function pruneImages(int $userId, ?array $candidates = null, ?\DateTimeInterface $olderThan = null): int
    {
        $disk = Storage::disk(config('filesystems.covers_disk'));
        $candidates ??= $disk->files('defterler/'.$userId);

        $used = Note::where('user_id', $userId)
            ->where('type', NoteType::Defter)
            ->pluck('content')
            ->flatMap(fn ($content) => self::imagePaths($content))
            ->flip();

        $deleted = 0;
        foreach ($candidates as $path) {
            // Sadece bu kullanıcının klasörü — başkasının görseli asla silinmez.
            if (! str_starts_with($path, 'defterler/'.$userId.'/') || $used->has($path) || ! $disk->exists($path)) {
                continue;
            }
            if ($olderThan && $disk->lastModified($path) > $olderThan->getTimestamp()) {
                continue;
            }
            $disk->delete($path);
            $deleted++;
        }

        return $deleted;
    }
}
