<?php

namespace App\Support;

use App\Models\Book;
use App\Models\DictionaryEntry;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sözlük maddeleri (Faz G3, "Sözlüğe Dair"): yazar imleci bir satıra getirip "Kavram"ı seçince
 * o satır `<p data-concept="anahtar">Egemenlik</p>` olur. Belgedeki kural —
 *  1. o satır bir sözlük maddesinin başlangıcıdır,
 *  2. kavram İçindekiler'e girer (WorkOutline),
 *  3. sonraki metin, bir sonraki Kavram işaretine kadar o maddeye aittir.
 * Başlıklar (Başlık 1–4; ör. "A", "B" harf bölümleri) da maddeyi bitirir — başlık bir tanımın
 * parçası değil, sözlüğün düzeni. Maddeler her kayıtta metinden yeniden üretilir; kimlik
 * anahtar olduğu için kavramın adı değişse de madde ve ona verilen bağlantılar korunur.
 */
class DictionaryDocument
{
    private const KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,31}$/';

    public static function isValidKey(string $key): bool
    {
        return (bool) preg_match(self::KEY_PATTERN, $key);
    }

    /**
     * Kavram anahtarlarını tamamlar: eksik / geçersiz anahtar kavramın adından üretilir (Word'den
     * gelen ya da elle yazılmış HTML), kopyala-yapıştırla çoğalan anahtar "-2", "-3" alır. Sonuç
     * deterministik — editör eski anahtarı göndermeye devam etse de her kayıtta aynı düzeltme çıkar.
     */
    public static function assignKeys(string $html): string
    {
        if (! str_contains($html, 'data-concept')) {
            return $html;
        }

        [$dom, $root] = self::parse($html);
        $seen = [];

        foreach ((new DOMXPath($dom))->query('//p[@data-concept]') as $concept) {
            /** @var DOMElement $concept */
            $key = strtolower(trim($concept->getAttribute('data-concept')));
            if (! self::isValidKey($key)) {
                $key = Str::limit(Str::slug(self::text($concept)), 24, '') ?: 'kavram';
            }

            $base = substr($key, 0, 28);
            for ($n = 2; isset($seen[$key]); $n++) {
                $key = $base.'-'.$n;
            }
            $seen[$key] = true;
            $concept->setAttribute('data-concept', $key);
        }

        return self::innerHtml($dom, $root);
    }

    /**
     * Sözlüğün bölümlerinden maddeler, metindeki sırayla.
     *
     * @return list<array{key: string, term: string, chapter_order: int, html: string}>
     */
    public static function extract(Book $book): array
    {
        $entries = [];

        foreach ($book->chapters()->get() as $chapter) {
            $html = (string) $chapter->content;
            if (! str_contains($html, 'data-concept')) {
                continue;
            }

            [$dom, $root] = self::parse($html);
            $current = null;

            foreach (iterator_to_array($root->childNodes) as $node) {
                if ($node instanceof DOMElement) {
                    $tag = strtolower($node->nodeName);

                    if ($tag === 'p' && $node->hasAttribute('data-concept')) {
                        $current !== null && $entries[] = $current;
                        $term = self::text($node);
                        $key = $node->getAttribute('data-concept');
                        // Adı boş kavram satırı madde açmaz (yazar henüz yazmadı).
                        $current = $term === '' || ! self::isValidKey($key) ? null : [
                            'key' => $key,
                            'term' => Str::limit($term, 250, ''),
                            'chapter_order' => $chapter->order,
                            'html' => '',
                        ];

                        continue;
                    }

                    if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
                        $current !== null && $entries[] = $current;
                        $current = null;

                        continue;
                    }
                }

                if ($current !== null) {
                    $current['html'] .= $dom->saveHTML($node);
                }
            }

            $current !== null && $entries[] = $current;
        }

        // Aynı anahtar iki bölümde (anahtarlar kayıtta tekilleşiyor, ama yine de): ilki geçerli.
        return collect($entries)->unique('key')->values()->all();
    }

    /** Maddeleri sözlüğün metniyle eşitler (ekler, günceller, metinden çıkanları siler). */
    public static function sync(Book $book): int
    {
        if (! $book->isDictionary()) {
            $book->entries()->delete();

            return 0;
        }

        $items = self::extract($book);

        DB::transaction(function () use ($book, $items) {
            $keys = array_column($items, 'key');
            $book->entries()->whereNotIn('key', $keys)->delete();
            $existing = $book->entries()->get()->keyBy('key');

            $planned = [];
            $slugs = [];
            foreach ($items as $position => $item) {
                $base = Str::limit(Str::slug($item['term']), 200, '') ?: 'madde';
                $slug = $base;
                for ($n = 2; isset($slugs[$slug]); $n++) {
                    $slug = $base.'-'.$n;
                }
                $slugs[$slug] = true;

                $planned[$item['key']] = [
                    'term' => $item['term'],
                    'slug' => $slug,
                    'term_search' => DictionaryEntry::searchable($item['term']),
                    'position' => $position + 1,
                    'chapter_order' => $item['chapter_order'],
                    'content' => RichText::normalize($item['html']),
                    'excerpt' => Str::limit(RichText::plainText($item['html']), 280) ?: null,
                ];
            }

            // (sözlük, slug) benzersiz: adresi başka maddeye geçecek olanlar önce geçici adrese.
            $holders = $existing->keyBy('slug');
            foreach ($planned as $key => $attributes) {
                $holder = $holders->get($attributes['slug']);
                if ($holder && $holder->key !== $key) {
                    $holder->newQuery()->whereKey($holder->id)->toBase()->update(['slug' => '~'.$holder->id]);
                    $holder->forceFill(['slug' => '~'.$holder->id])->syncOriginalAttribute('slug');
                }
            }

            // Değişmeyen madde yazılmaz (otomatik kayıt her birkaç saniyede bir geliyor).
            foreach ($planned as $key => $attributes) {
                ($entry = $existing->get($key))
                    ? $entry->fill($attributes)->save()
                    : $book->entries()->create(['key' => $key, ...$attributes]);
            }
        });

        return count($items);
    }

    private static function text(DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private static function parse(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="dd-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

        return [$dom, $dom->getElementById('dd-root') ?? $dom->getElementsByTagName('div')->item(0)];
    }

    private static function innerHtml(DOMDocument $dom, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
