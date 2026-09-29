<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Chapter;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kitap editörde tek belge, veritabanında bölümler (Faz G2, "Yazarın Gözünden" 1.1.1: eser tek
 * sayfada yazılır, Başlık 1–4 içindekileri kurar; Word'den gelen dosya da aynı katmanlarla).
 *
 *  - toHtml(): bölümler → tek belge; her bölüm bir "Başlık 1" (<h1>), başlıksız giriş bölümü
 *    başlıksız kalır.
 *  - sync(): tek belge → bölümler; her üst düzey <h1> yeni bölüm, ilk h1'den önceki metin
 *    başlıksız giriş bölümü (numaralamaya katılmaz). Var olan bölüm satırları sırasıyla
 *    güncellenir (kimlikleri korunur), fazlası silinir, eksik olanlar eklenir.
 */
class BookDocument
{
    public static function toHtml(Book $book): string
    {
        return $book->chapters
            ->map(fn ($chapter) => ($chapter->is_preface ? '' : '<h1>'.e($chapter->title).'</h1>').$chapter->content)
            ->implode('');
    }

    /**
     * @return list<array{title: string, html: string, preface: bool}>
     */
    public static function split(string $html): array
    {
        $html = RichText::normalize($html);
        if ($html === '') {
            return [];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="bd-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        $root = $dom->getElementById('bd-root') ?? $dom->getElementsByTagName('div')->item(0);

        $sections = [];
        $current = ['title' => null, 'html' => ''];

        foreach (iterator_to_array($root->childNodes) as $node) {
            /** @var DOMNode $node */
            if ($node instanceof DOMElement && strtolower($node->nodeName) === 'h1') {
                $sections[] = $current;
                $title = trim(preg_replace('/\s+/u', ' ', $node->textContent));
                $current = ['title' => $title !== '' ? $title : 'Başlıksız bölüm', 'html' => ''];

                continue;
            }
            $current['html'] .= $dom->saveHTML($node);
        }
        $sections[] = $current;

        return collect($sections)
            // İlk başlıktan önce metin yoksa giriş bölümü açılmaz.
            ->filter(fn ($section) => $section['title'] !== null || RichText::hasText($section['html']))
            ->map(fn ($section) => [
                'title' => Str::limit($section['title'] ?? 'Giriş', 250, ''),
                'html' => $section['html'],
                'preface' => $section['title'] === null,
            ])
            ->values()
            ->all();
    }

    /** Tek belgeyi kitabın bölümlerine yazar; bölüm sayısını döner. */
    public static function sync(Book $book, string $html): int
    {
        $sections = self::split($html);

        // Otomatik kayıt her birkaç saniyede bütün kitabı gönderiyor: değişmemiş bölümlere hiç
        // dokunulmuyor, belge/video sayımı bölüm başına değil en sonda bir kez (uzun kitapta her
        // kayıt bütün bölümleri yeniden temizleyip yazıyor, süre bölüm sayısıyla katlanıyordu).
        Chapter::withoutCountRefresh(fn () => DB::transaction(function () use ($book, $sections) {
            $existing = $book->chapters()->get()->values();

            // Fazla bölümler (belgeden silinenler).
            $existing->slice(count($sections))->each->delete();
            $existing = $existing->take(count($sections));

            // (kitap, sıra) benzersiz: sırası değişecek bölümler önce geçici sıralara, sonra 1..n.
            foreach ($existing as $index => $chapter) {
                if ($chapter->order === $index + 1) {
                    continue;
                }
                $temporary = $chapter->order + 100000;
                $chapter->newQueryWithoutScopes()->whereKey($chapter->id)->update(['order' => $temporary]);
                // Modelin "orijinali" de geçici sıra olsun — yoksa yeni sıra eskisiyle aynıysa
                // alan değişmemiş sayılır, kayıt geçici sırada kalırdı.
                $chapter->forceFill(['order' => $temporary])->syncOriginal();
            }

            foreach ($sections as $index => $section) {
                $attributes = ['title' => $section['title'], 'order' => $index + 1, 'is_preface' => $section['preface']];
                $chapter = $existing[$index] ?? null;

                if (! $chapter) {
                    $book->chapters()->create($attributes + ['content' => $section['html']]);

                    continue;
                }

                // İçerik kayıtta RichText::normalize'dan geçiyor; aynıysa yeniden temizleme yok.
                if ($chapter->content !== $section['html']) {
                    $attributes['content'] = $section['html'];
                }
                $chapter->fill($attributes);
                if ($chapter->isDirty()) {
                    $chapter->save();
                }
            }
        }));

        $book->unsetRelation('chapters');
        $book->refreshContentCounts();

        return count($sections);
    }
}
