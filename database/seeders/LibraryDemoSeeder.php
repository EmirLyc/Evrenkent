<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Enums\ReadingStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\Note;
use App\Models\User;
use App\Support\BookDocument;
use App\Support\RichText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dolu içerikli demo kütüphane (2026-09-29): dört kitap (her biri yedi bölüm, 20+ sayfa) ve iki
 * dergi (ikişer sayı, toplam on makale) — metinler database/seeders/content altında. Okur hesabına
 * bu eserlerde satın almalar, okuma ilerlemesi, metinde konumlu alıntı / not / fosforlar, üç
 * defter ve hesaba özel (daha yüksek ama görünen) sınırlar. DemoContentSeeder'dan çağrılır; tek
 * başına da çalışır, tekrar çalıştırılınca çoğaltmaz (içerikleri günceller):
 *   php artisan db:seed --class=LibraryDemoSeeder
 */
class LibraryDemoSeeder extends Seeder
{
    /** Yazar hesapları: e-posta adı → ad (yoksa oluşturulur, yazar rolüyle). */
    private const AUTHORS = [
        'author' => 'Ahmet Yılmaz',
        'elif.nazli' => 'Elif Nazlı',
        'murat.ekin' => 'Murat Ekin',
        'deniz.yalcin' => 'Deniz Yalçın',
        'orhan.demir' => 'Orhan Demir',
        'ece.yilmaz' => 'Ece Yılmaz',
    ];

    private const BOOKS = [
        'kiyida-bir-sabah' => [
            'title' => 'Kıyıda Bir Sabah',
            'author' => 'murat.ekin',
            'categories' => ['Roman'],
            'ratio' => '13x21',
            'numbering' => false,
            'price' => 165,
            'published' => 40,
            'rating' => [4.6, 37],
            'pages' => 21,
            'description' => "İstanbul'da restorasyon ustası olarak çalışan Nevin, anneannesinin ölümünden sonra on iki yıldır gitmediği Ege kasabasına döner. Niyeti evi satıp birkaç gün içinde geri dönmektir. Ama mavi kapılı evde kilitli bir sandık, tarif defterinden koparılmış bir sayfa ve limandaki kırmızı teknenin yaşlı sahibi onu bekliyordur.\n\nAltmış yıl boyunca gönderilmemiş mektuplar, bir fırtına gecesi ve onarılan şeylerin güzelliği üzerine bir roman.",
        ],
        'zihnin-sessiz-bahcesi' => [
            'title' => 'Zihnin Sessiz Bahçesi',
            'author' => 'deniz.yalcin',
            'categories' => ['Psikoloji', 'Deneme'],
            'ratio' => '13x20',
            'numbering' => true,
            'price' => 145,
            'discount' => 119,
            'editors_pick' => true,
            'published' => 25,
            'rating' => [4.8, 64],
            'pages' => 16,
            'description' => "Dikkat, yavaşlık, hafıza, kaygı, alışkanlık ve tek başınalık üzerine altı deneme. Deniz Yalçın, zihnin bu bildik hallerine bir bahçıvanın gözüyle bakıyor: zorla büyütmeden, toprağı hazırlayarak ve bekleyerek.\n\nWilliam James'ten Kierkegaard'a, Ebbinghaus'tan Winnicott'a psikolojinin ve felsefenin klasiklerinden beslenen, her bölümün sonunda küçük bir uygulama öneren sakin bir kitap.",
        ],
        'istanbul-defterleri' => [
            'title' => 'İstanbul Defterleri',
            'author' => 'orhan.demir',
            'categories' => ['Tarih', 'Deneme'],
            'ratio' => '16x24',
            'numbering' => true,
            'price' => 210,
            'editors_pick' => true,
            'published' => 18,
            'rating' => [4.4, 21],
            'pages' => 15,
            'description' => "Galata Kulesi'nden vapur iskelelerine, kahvehanelerden yanmış ahşap mahallelere, Kapalıçarşı'nın sokaklarından Boğaz'ın mevsimlerine… Orhan Demir, yıllar boyunca İstanbul'da yürürken tuttuğu defterlerden bir şehrin gündelik hayatını ve çok katmanlı hafızasını anlatıyor.",
        ],
        'doganin-fisiltisi' => [
            'title' => 'Doğanın Fısıltısı',
            'author' => 'ece.yilmaz',
            'categories' => ['Deneme'],
            'ratio' => '13x20',
            'numbering' => false,
            'price' => 0,
            'published' => 9,
            'rating' => [4.7, 18],
            'pages' => 14,
            'description' => "Şehirden dağ eteğindeki küçük bir eve taşınan yazar, bir yıl boyunca kuşları, ağaçları, yağmuru, karıncaları, göçmen kuşları ve gece gökyüzünü dinliyor. Bilimle şiirin buluştuğu, yavaşlamaya ve dikkat etmeye davet eden yedi doğa denemesi.",
        ],
    ];

    /**
     * Okurun metinde işaretledikleri (Faz H3): [kitap, bölüm adı, tür, seçilen metin, not, sayfa, kaç gün önce].
     * Sayfa numaraları sayfalı okumanın (her cihazda aynı) dizgisinden.
     */
    private const MARKS = [
        ['kiyida-bir-sabah', 'Dönüş', 'alinti', 'İnsan başkalarının hasarını onarmaya alışınca kendi evinin çatısının aktığını fark etmiyordu.', null, '1', 38],
        ['kiyida-bir-sabah', 'Dönüş', 'fosfor', 'Bu evdeki her şey bir sözdü sanki; açmak, o sözü bozmak gibi gelecekti.', null, '2', 38],
        ['kiyida-bir-sabah', 'Balıkçı Hasan', 'alinti', 'Bazen insanın bir şeyi konuşmaması, o şeyi korumasıdır.', null, '8', 30],
        ['kiyida-bir-sabah', 'Balıkçı Hasan', 'not', 'Deniz uzaklaşanı bekler.', 'Anneannemin de sık söylediği bir sözdü bu. Denizi sabırla eşleştirmek… Kitabın bütün temasını bu cümle taşıyor bence.', '7', 30],
        ['kiyida-bir-sabah', 'Kilitli Sandık', 'alinti', 'Bilmediğim bir şeyden korkmak, bildiğim bir şeyi kaybetmekten daha kolay geliyor bana şimdi.', null, '11', 24],
        ['kiyida-bir-sabah', 'Mektuplar', 'not', 'Olmayan şeyler için de mektup yazılır, Hasan.', 'Kitabın kalbi burası. Gönderilmemiş bir mektubun yine de "olmuş" sayılması fikri çok güzel.', '14', 20],
        ['kiyida-bir-sabah', 'Kıyıda Bir Sabah', 'alinti', 'Onarıldığı belli olsun. Onarılmış şeyler daha güzeldir.', null, '20', 11],
        ['kiyida-bir-sabah', 'Kıyıda Bir Sabah', 'fosfor', 'Belki masal ile gerçek arasındaki fark, yalnızca birinin onu anlatmaya cesaret edip etmemesiydi.', null, '21', 11],
        ['zihnin-sessiz-bahcesi', 'Dikkatin Ekonomisi', 'alinti', 'Neye dikkat ettiğimiz, kim olduğumuzu belirler.', null, '1', 14],
        ['zihnin-sessiz-bahcesi', 'Dikkatin Ekonomisi', 'not', 'Bu bir karakter zaafı değil; dikkatimizi kazanmak için tasarlanmış bir çevrenin doğal sonucu.', 'Kendimi suçlamak yerine çevremi değiştirmeyi denemeliyim. Telefonu yatak odasından çıkarmakla başlıyorum.', '1', 14],
        ['zihnin-sessiz-bahcesi', 'Dikkatin Ekonomisi', 'fosfor', 'Aklınıza gelen ilk düşüncenin sizin mi, yoksa bir ekrandan mı geldiğini ayırt etmeye çalışın.', null, '2', 13],
        ['zihnin-sessiz-bahcesi', 'Yavaşlığın Hakkı', 'alinti', 'Sorun hız değildi; sorun, her şeyi aynı hızda yapma zorunluluğuydu.', null, '3', 9],
        ['zihnin-sessiz-bahcesi', 'Yavaşlığın Hakkı', 'not', 'Bir tohumu sabırsızlıkla kazıp bakmak, onu öldürmenin en kısa yoludur.', 'Yeni işe başladığımdan beri her şeyin hemen sonuç vermesini bekliyorum. Bu cümleyi masama yazacağım.', '4', 9],
        ['zihnin-sessiz-bahcesi', 'Hatırlamak ve Unutmak', 'alinti', 'Unutmak, zihnin budama makasıdır.', null, '6', 5],
        ['zihnin-sessiz-bahcesi', 'Hatırlamak ve Unutmak', 'fosfor', 'Önemli bulduğunuz şeyleri tekrar tekrar ziyaret edin.', null, '6', 5],
        ['zihnin-sessiz-bahcesi', 'Kaygının Haritası', 'not', 'Harita, araziyi değiştirmez ama arazide kaybolmanızı önler.', 'Bu alıştırmayı bu akşam yapacağım: kaygılarımı yazıp yanına "olursa ne yaparım" sorusunu koyacağım.', '8', 2],
        ['istanbul-defterleri', 'Galata\'dan Bakmak', 'alinti', 'Bir şehrin efsaneleri, belgelerinden daha çok şey anlatır bazen.', null, '2', 16],
        ['istanbul-defterleri', 'Vapur İskeleleri', 'not', 'vapur saatinde gelir, saatinde gider.', 'Kadıköy iskelesinde her sabah bunu yaşıyorum. Hafta sonu Kanlıca iskelesine gidip yazarın anlattığı binaya bakmak istiyorum.', '3', 15],
        ['istanbul-defterleri', 'Vapur İskeleleri', 'fosfor', 'Boğaz, bir sayfiye yeri olmaktan çıkıp şehrin bir parçası olur.', null, '3', 15],
        ['istanbul-defterleri', 'Kahvehaneler ve Sohbet', 'alinti', 'Bir çay bardağının dibi görünene kadar geçen süre, şehrin bütün telaşından daha önemlidir.', null, '6', 7],
        ['doganin-fisiltisi', 'Sabah Kuşları', 'alinti', 'Belki trafiğin ve kliman gürültüsünün altında kaybolmuştu.', null, '1', 4],
        ['doganin-fisiltisi', 'Yağmurdan Sonra', 'not', 'Kuraklık boyunca bahçe ölü gibi görünmüştü. Ama aslında bekliyordu.', 'Petrikor kelimesini ilk kez öğrendim. Balkondaki saksılara da bu gözle bakacağım artık.', '6', 3],
    ];

    /** Okurun defterleri (Faz H5). */
    private const NOTEBOOKS = [
        [
            'title' => 'Okuma günlüğüm',
            'subtitle' => 'Bu yıl okuduklarım ve kafamda kalanlar',
            'tags' => ['okuma', 'günlük'],
            'info' => 'Her kitabı bitirince birkaç satır yazıyorum.',
            'days' => 1,
            'content' => '<h1>Eylül</h1><p>Bu ay <strong>Kıyıda Bir Sabah</strong>\'ı bitirdim. Bir oturuşta okunacak kadar akıcı ama bitince uzun süre düşündüren bir roman. Nevin\'in "profesyonel mesafe" ile başlayan envanteri, kitabın sonunda bir kabullenişe dönüşüyor.</p><blockquote><p>Onarıldığı belli olsun. Onarılmış şeyler daha güzeldir.</p><p>— Kıyıda Bir Sabah, s. 20</p></blockquote><p>Bu cümleyi okuyunca kendi hayatımdaki "görünür onarımları" düşündüm. Saklamaya çalıştığım kırıkların belki de en güzel yerlerim olduğunu.</p><h2>Şimdi okuduklarım</h2><ul><li><em>Zihnin Sessiz Bahçesi</em> — dikkat bölümü bana çok iyi geldi, yavaş okuyorum.</li><li><em>İstanbul Defterleri</em> — her bölümden sonra o semte gitmek istiyorum.</li></ul><h2>Sırada</h2><ol><li>Doğanın Fısıltısı (kitaplığıma ekledim, ücretsizdi)</li><li>Siyaset Bilimi Sözlüğü\'nden birkaç madde</li></ol>',
        ],
        [
            'title' => 'Zihnin bahçesi üzerine',
            'subtitle' => 'Kitaptaki önerileri deniyorum',
            'tags' => ['psikoloji', 'alışkanlık', 'deney'],
            'info' => 'Deniz Yalçın\'ın kitabındaki her bölüm sonu önerisini bir hafta deneyip not alıyorum.',
            'days' => 3,
            'content' => '<h1>Bir haftalık deney</h1><p>Kitabın ilk bölümündeki öneriyle başladım: uyandıktan sonraki ilk on beş dakika ekran yok.</p><h2>Gün gün</h2><ul><li><strong>Pazartesi:</strong> Zor. Elim üç kez telefona gitti. Pencereden dışarı baktım, karşı apartmanın çatısındaki martıları saydım.</li><li><strong>Salı:</strong> Biraz daha kolay. Çayımı balkonda içtim.</li><li><strong>Çarşamba:</strong> İlk kez aklıma gelen ilk düşüncenin <em>benim</em> olduğunu fark ettim: bir arkadaşımı aramak.</li><li><strong>Perşembe–Pazar:</strong> Alışmaya başladım. Güne daha sakin başlıyorum.</li></ul><blockquote><p>Neye dikkat ettiğimiz, kim olduğumuzu belirler.</p><p>— Zihnin Sessiz Bahçesi, s. 1</p></blockquote><h2>Sonraki hafta</h2><p>Alışkanlık bölümündeki "işareti ve ödülü aynı bırak, rutini değiştir" fikrini deneyeceğim. Öğleden sonra üçteki atıştırma alışkanlığım için: tatlı yerine on dakikalık yürüyüş.</p><p>Notum: Kaygının haritası alıştırmasını da bir akşam mutlaka yapmalıyım.</p>',
        ],
        [
            'title' => 'İstanbul yürüyüşleri',
            'subtitle' => 'İstanbul Defterleri\'nden rota planı',
            'tags' => ['istanbul', 'yürüyüş', 'plan'],
            'info' => null,
            'days' => 6,
            'content' => '<h1>Rota</h1><p>Orhan Demir\'in kitabını okurken bir yürüyüş listesi çıkardım. Her hafta sonu birini yapacağım.</p><ol><li><strong>Galata Kulesi → Karaköy iskelesi:</strong> Kuleden inip vapurla karşıya geçmek. Kitaptaki gibi önce yukarıdan bakmak.</li><li><strong>Kanlıca iskelesi:</strong> Eski iskele binasını görmek, yoğurt yemek.</li><li><strong>Kapalıçarşı:</strong> Sokak adlarını okuyarak yürümek (Kalpakçılar, Yorgancılar, Sahaflar).</li><li><strong>Mısır Çarşısı → Eminönü:</strong> Baharatçılardan tarçın almak.</li><li><strong>Kuzguncuk:</strong> Kalan ahşap evlere bakmak.</li></ol><blockquote><p>Bir şehrin efsaneleri, belgelerinden daha çok şey anlatır bazen.</p><p>— İstanbul Defterleri, s. 2</p></blockquote><p>Yazarın sonundaki öneriyi de yapacağım: kendi şehir defterimi tutmak. Belki bu defter onun başlangıcı olur.</p>',
        ],
    ];

    public function run(): void
    {
        $authors = collect(self::AUTHORS)->mapWithKeys(function (string $name, string $key) {
            $user = User::firstOrCreate(['email' => "{$key}@evrenkent.test"], ['name' => $name, 'password' => bcrypt('password')]);
            if (! $user->canAuthor()) {
                $user->assignRole('yazar');
            }

            return [$key => $user];
        });

        $categories = collect(['Roman', 'Deneme', 'Tarih', 'Şiir', 'Psikoloji', 'Felsefe'])
            ->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])]);

        $books = $this->books($authors, $categories);
        $this->magazines($authors, $categories);
        $this->demoBuyers($books);
        $this->reader($books);

        $this->command?->info('Dolu içerikli demo kütüphane: '.count($books).' kitap, 2 dergi (4 sayı, 10 makale), okur için satın alma, ilerleme, işaretler ve defterler.');
    }

    /** @return array<string, Book> */
    private function books($authors, $categories): array
    {
        $books = [];

        foreach (self::BOOKS as $slug => $data) {
            $book = Book::withTrashed()->firstOrNew(['slug' => $slug]);
            $book->fill([
                'author_id' => $authors[$data['author']]->id,
                'kind' => Book::KIND_KITAP,
                'title' => $data['title'],
                'description' => $data['description'],
                'page_ratio' => $data['ratio'],
                'heading_numbering' => $data['numbering'],
                'price' => $data['price'],
                'discount_price' => $data['discount'] ?? null,
                'is_editors_pick' => $data['editors_pick'] ?? false,
                'status' => ContentStatus::Yayinda,
                'published_at' => $book->published_at ?? now()->subDays($data['published']),
                'average_rating' => $data['rating'][0],
                'review_count' => $data['rating'][1],
                'page_count' => $data['pages'],
            ])->save();
            $book->restore();
            $book->categories()->syncWithoutDetaching($categories->only($data['categories'])->pluck('id'));

            $content = require __DIR__."/content/{$slug}.php";
            $html = self::html($content['preface'] ?? []);
            foreach (array_filter($content, 'is_int', ARRAY_FILTER_USE_KEY) as $chapter) {
                $html .= '<h1>'.e($chapter['title']).'</h1>'.self::html($chapter['blocks']);
            }
            BookDocument::sync($book, $html);

            $sources = $book->chapters()->pluck('content')->flatMap(fn ($c) => RichText::citations($c))->unique()->count();
            $book->forceFill(['source_count' => $sources ?: null])->saveQuietly();

            $books[$slug] = $book->fresh('chapters');
        }

        return $books;
    }

    private function magazines($authors, $categories): void
    {
        $editor = User::where('email', 'editor@evrenkent.test')->first();
        if (! $editor) {
            return;
        }

        foreach (require __DIR__.'/content/dergiler.php' as $data) {
            $magazine = Magazine::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                ['name' => $data['name'], 'description' => $data['description'], 'editor_id' => $editor->id],
            );

            foreach ($data['issues'] as $issueData) {
                $issue = MagazineIssue::updateOrCreate(['title' => $issueData['title']], [
                    'magazine_id' => $magazine->id,
                    'editor_id' => $editor->id,
                    'issue_number' => $issueData['number'],
                    'editor_note' => $issueData['editor_note'],
                    'status' => ContentStatus::Yayinda,
                    'publish_date' => now()->subDays($issueData['days_ago'])->toDateString(),
                ]);

                foreach ($issueData['articles'] as $articleData) {
                    $author = $authors[$articleData['author']];
                    $magazine->authors()->syncWithoutDetaching([$author->id]);

                    $article = Article::withTrashed()->firstOrNew(['slug' => Str::slug($articleData['title'])]);
                    $article->fill([
                        'author_id' => $author->id,
                        'magazine_issue_id' => $issue->id,
                        'title' => $articleData['title'],
                        'description' => Str::limit(strip_tags(preg_replace('/\[\[.*?\]\]/s', '', $articleData['blocks'][0])), 240),
                        'content' => self::html($articleData['blocks']),
                        'page_ratio' => '21x27.5',
                        'heading_numbering' => false,
                        'status' => ContentStatus::Yayinda,
                        'published_at' => $article->published_at ?? now()->subDays($issueData['days_ago']),
                    ])->save();
                    $article->restore();
                    $article->categories()->syncWithoutDetaching([$categories[$articleData['category']]->id]);
                }
            }
        }
    }

    /** "Çok Satanlar" ve yönetim paneli sayılarında yeni kitaplar da görünsün. */
    private function demoBuyers(array $books): void
    {
        $counts = ['zihnin-sessiz-bahcesi' => 6, 'kiyida-bir-sabah' => 4, 'istanbul-defterleri' => 3, 'doganin-fisiltisi' => 5];

        foreach ($counts as $slug => $count) {
            foreach (range(1, $count) as $i) {
                $buyer = User::where('email', "demo.okur{$i}@evrenkent.test")->first();
                $buyer?->purchases()->firstOrCreate(
                    ['book_id' => $books[$slug]->id],
                    ['amount' => $books[$slug]->priceFor($buyer), 'purchased_at' => now()->subDays(random_int(1, 20)), 'payment_status' => 'completed'],
                );
            }
        }
    }

    private function reader(array $books): void
    {
        $reader = User::where('email', 'reader@evrenkent.test')->first();
        if (! $reader) {
            return;
        }

        // Demo hesabı: sınırlar daha yüksek ama yine görünür (Süper Admin → Kullanıcılar'dan da ayarlanır).
        $reader->forceFill(['quota_overrides' => ['defter' => 5, 'defter_words' => 3000, 'not' => 30, 'alinti' => 30]])->save();

        // Satın almalar (Doğanın Fısıltısı ücretsiz — "Kitaplığıma Ekle").
        foreach (['kiyida-bir-sabah' => 35, 'zihnin-sessiz-bahcesi' => 15, 'istanbul-defterleri' => 16, 'doganin-fisiltisi' => 5] as $slug => $days) {
            $reader->purchases()->firstOrCreate(
                ['book_id' => $books[$slug]->id],
                ['amount' => $books[$slug]->priceFor($reader), 'purchased_at' => now()->subDays($days), 'payment_status' => 'completed'],
            );
        }

        // Okuma durumu: biri bitmiş, ikisi yarıda, biri henüz başlanmamış.
        // [durum, kaldığı bölüm, yüzde, kaç gün önce]
        $progress = [
            'kiyida-bir-sabah' => [ReadingStatus::Tamamlandi, 'Kıyıda Bir Sabah', 100, 10],
            'zihnin-sessiz-bahcesi' => [ReadingStatus::Listede, 'Kaygının Haritası', 48, 2],
            'istanbul-defterleri' => [ReadingStatus::Listede, 'Kahvehaneler ve Sohbet', 34, 7],
        ];
        foreach ($progress as $slug => [$status, $chapterTitle, $percent, $days]) {
            $item = $reader->readingListItems()->updateOrCreate(
                ['readable_type' => Book::class, 'readable_id' => $books[$slug]->id],
                [
                    'status' => $status,
                    'last_chapter_number' => $books[$slug]->chapters->firstWhere('title', $chapterTitle)->order,
                    'progress' => $percent,
                    'completed_at' => $status === ReadingStatus::Tamamlandi ? now()->subDays($days) : null,
                ],
            );
            $item->forceFill(['updated_at' => now()->subDays($days)])->saveQuietly();
        }
        $reader->favorites()->firstOrCreate(['favoritable_type' => Book::class, 'favoritable_id' => $books['kiyida-bir-sabah']->id]);

        foreach (self::MARKS as [$slug, $chapterTitle, $type, $quote, $note, $page, $days]) {
            $this->mark($reader, $books[$slug], $chapterTitle, NoteType::from($type), $quote, $note, $page, $days);
        }

        foreach (self::NOTEBOOKS as $data) {
            $notebook = $reader->notes()->updateOrCreate(
                ['type' => NoteType::Defter, 'title' => $data['title']],
                ['subtitle' => $data['subtitle'], 'content' => $data['content'], 'tags' => $data['tags'], 'info' => $data['info']],
            );
            $notebook->forceFill(['created_at' => now()->subDays($data['days'] + 20), 'updated_at' => now()->subDays($data['days'])->setTime(19, 34)])->saveQuietly();
        }
    }

    /**
     * Metinde konumlu işaret: bölümün düz metninde seçilen yer + çevresi (okuma sayfası yeri bu
     * bilgiyle yeniden buluyor — reading-marks.js locate()).
     */
    private function mark(User $reader, Book $book, string $chapterTitle, NoteType $type, string $quote, ?string $note, string $page, int $days): void
    {
        $chapter = $book->chapters->firstWhere('title', $chapterTitle);
        $text = RichText::plainText($chapter->content);
        $start = mb_strpos($text, $quote);
        if ($start === false) {
            throw new \RuntimeException("Alıntı metinde bulunamadı: {$book->title} / {$chapterTitle}: {$quote}");
        }
        $end = $start + mb_strlen($quote);

        $mark = Note::firstOrNew([
            'user_id' => $reader->id,
            'type' => $type,
            'noteable_type' => Book::class,
            'noteable_id' => $book->id,
            'quote' => $quote,
        ]);
        $mark->forceFill([
            'content' => $type === NoteType::Not ? $note : $quote,
            'anchor' => [
                'chapter' => $chapter->order,
                'start' => $start,
                'end' => $end,
                'prefix' => mb_substr($text, max(0, $start - 32), min(32, $start)),
                'suffix' => mb_substr($text, $end, 32),
            ],
            'page' => $page,
            'location' => "Sayfa {$page} · {$chapterTitle}",
            'created_at' => now()->subDays($days)->setTime(random_int(8, 22), random_int(0, 59)),
            'updated_at' => now()->subDays($days),
        ])->save();
    }

    /**
     * İçerik dosyalarındaki blokları editörün HTML'ine çevirir: "## " / "### " ara başlık, "> " alıntı,
     * "---" süs ayırıcı, "@icindekiler", "@kaynakca"; satır içinde [[dn:…]] dipnot, [[kaynak:…]] kaynak.
     *
     * @param  list<string>  $blocks
     */
    public static function html(array $blocks): string
    {
        $inline = fn (string $text) => preg_replace_callback(
            '/\[\[(dn|kaynak):(.*?)\]\]/s',
            // Metin zaten kaçışlı: işaretin içi ikinci kez kaçışlanmasın.
            fn ($m) => '<span data-'.($m[1] === 'dn' ? 'footnote' : 'cite').'="'.e($m[2], false).'"></span>',
            e($text),
        );

        return collect($blocks)->map(fn (string $block) => match (true) {
            $block === '---' => '<hr>',
            $block === '@icindekiler' => '<nav data-toc="true"></nav>',
            $block === '@kaynakca' => '<section data-bibliography="true"></section>',
            str_starts_with($block, '### ') => '<h3>'.$inline(substr($block, 4)).'</h3>',
            str_starts_with($block, '## ') => '<h2>'.$inline(substr($block, 3)).'</h2>',
            str_starts_with($block, '> ') => '<blockquote><p>'.$inline(substr($block, 2)).'</p></blockquote>',
            default => '<p>'.$inline($block).'</p>',
        })->implode('');
    }
}
