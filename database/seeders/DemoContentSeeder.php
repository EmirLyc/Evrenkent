<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\NoteType;
use App\Enums\ReadingStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deneme/demo amaçlı gerçekçi içerik üretir (kitap, makale, dergi sayısı, kategori,
 * ve Okur'a ait favoriler/okuma listesi/notlar/satın almalar). Her rolün panelinde
 * her durum/bölüm en az bir kayıtla temsil edilir. Ana seed zincirine dahil değildir
 * — ayrıca çalıştırılır:
 *   php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Roman', 'Şiir', 'Tarih', 'Deneme', 'Bilim Kurgu'])
            ->mapWithKeys(fn (string $name) => [
                $name => Category::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                ),
            ]);

        $author1 = User::firstOrCreate(
            ['email' => 'author@evrenkent.test'],
            ['name' => 'Ahmet Yılmaz', 'password' => bcrypt('password')]
        );
        if (! $author1->hasRole('yazar')) {
            $author1->assignRole('yazar');
        }

        $author2 = User::firstOrCreate(
            ['email' => 'elif.nazli@evrenkent.test'],
            ['name' => 'Elif Nazlı', 'password' => bcrypt('password')]
        );
        if (! $author2->hasRole('yazar')) {
            $author2->assignRole('yazar');
        }

        $editor = User::firstOrCreate(
            ['email' => 'editor@evrenkent.test'],
            ['name' => 'Ayşe Demir', 'password' => bcrypt('password')]
        );
        if (! $editor->hasRole('dergi_editoru')) {
            $editor->assignRole('dergi_editoru');
        }

        $admin = User::where('email', 'admin@evrenkent.test')->first();
        $reader = User::where('email', 'reader@evrenkent.test')->first();

        // --- Kitaplar ---
        $books = [
            ['title' => 'Sislerin Ardındaki Fener', 'author' => $author2, 'category' => 'Roman', 'price' => 189, 'status' => ContentStatus::Yayinda],
            ['title' => 'Kökler ve Kanatlar', 'author' => $author2, 'category' => 'Şiir', 'price' => 120, 'status' => ContentStatus::Yayinda],
            ['title' => 'Zamanın İzinde', 'author' => $author1, 'category' => 'Tarih', 'price' => 245, 'status' => ContentStatus::Gonderildi],
            ['title' => 'Dinginliğin Kıyısında', 'author' => $author1, 'category' => 'Deneme', 'price' => 150, 'status' => ContentStatus::Taslak],
            ['title' => 'Medeniyetin Ayak İzleri', 'author' => $author2, 'category' => 'Tarih', 'price' => 210, 'status' => ContentStatus::RevizyonIstendi],
            ['title' => 'Uzak Sahiller', 'author' => $author2, 'category' => 'Bilim Kurgu', 'price' => 175, 'status' => ContentStatus::Onaylandi],
            ['title' => 'Kayıp Zamanın Şiirleri', 'author' => $author1, 'category' => 'Şiir', 'price' => 95, 'status' => ContentStatus::RevizyonIstendi],
            // Kitaplığım/anasayfa gibi listeleme sayfalarının gerçekçi görünmesi için ek örnekler.
            ['title' => 'Yıldızların Altında', 'author' => $author1, 'category' => 'Roman', 'price' => 165, 'status' => ContentStatus::Yayinda],
            ['title' => 'Sessiz Sokaklar', 'author' => $author2, 'category' => 'Roman', 'price' => 140, 'status' => ContentStatus::Yayinda],
            ['title' => 'Unutulan Mektuplar', 'author' => $author1, 'category' => 'Deneme', 'price' => 130, 'status' => ContentStatus::Yayinda],
            ['title' => 'Rüzgârın Şarkısı', 'author' => $author2, 'category' => 'Şiir', 'price' => 110, 'status' => ContentStatus::Yayinda],
            ['title' => 'Toprağın Hafızası', 'author' => $author1, 'category' => 'Tarih', 'price' => 220, 'status' => ContentStatus::Yayinda],
            ['title' => 'Ayna Kırıkları', 'author' => $author2, 'category' => 'Roman', 'price' => 175, 'status' => ContentStatus::Yayinda],
            ['title' => 'Zamansız Yolculuk', 'author' => $author1, 'category' => 'Bilim Kurgu', 'price' => 195, 'status' => ContentStatus::Yayinda],
            ['title' => 'Denizin Çağrısı', 'author' => $author2, 'category' => 'Deneme', 'price' => 145, 'status' => ContentStatus::Yayinda],
            ['title' => 'Karanlığın Ötesinde', 'author' => $author1, 'category' => 'Bilim Kurgu', 'price' => 210, 'status' => ContentStatus::Yayinda],
            ['title' => 'Son Mevsim', 'author' => $author2, 'category' => 'Şiir', 'price' => 100, 'status' => ContentStatus::Yayinda],
            ['title' => 'Kırık Aynalar Şehri', 'author' => $author1, 'category' => 'Roman', 'price' => 185, 'status' => ContentStatus::Taslak],
            ['title' => 'Geçmişin Gölgesinde', 'author' => $author2, 'category' => 'Tarih', 'price' => 200, 'status' => ContentStatus::Onaylandi],
            ['title' => 'Uzak Diyarlar', 'author' => $author1, 'category' => 'Bilim Kurgu', 'price' => 160, 'status' => ContentStatus::Gonderildi],
        ];

        $bookModels = [];
        foreach ($books as $data) {
            $book = Book::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'author_id' => $data['author']->id,
                    'title' => $data['title'],
                    'description' => fake()->paragraphs(3, true),
                    'price' => $data['price'],
                    'status' => $data['status'],
                    'published_at' => $data['status'] === ContentStatus::Yayinda ? now()->subDays(random_int(1, 60)) : null,
                ]
            );

            $bookModels[$data['title']] = $book;

            $book->categories()->syncWithoutDetaching([$categories[$data['category']]->id]);

            if ($data['status'] === ContentStatus::RevizyonIstendi && $admin) {
                $book->reviews()->firstOrCreate([
                    'reviewer_id' => $admin->id,
                    'action' => 'revizyon_istendi',
                ], [
                    'note' => $data['title'] === 'Kayıp Zamanın Şiirleri'
                        ? 'Şiir başlıkları eksik, lütfen tamamlayın.'
                        : 'Kapak görseli eksik, lütfen ekleyip tekrar gönderin.',
                ]);
            }

            if ($data['status'] === ContentStatus::Gonderildi && $admin) {
                $book->reviews()->firstOrCreate([
                    'reviewer_id' => $author1->id,
                    'action' => 'gonderildi',
                ], [
                    'note' => 'Yazar tarafından Süper Admin onayına gönderildi.',
                ]);
            }
        }

        // --- Çoklu kategori/etiket örneği (kitap tanıtım sayfasındaki tıklanabilir
        // etiketlerin altyapısı) — yazar birden fazla kategori seçebiliyor artık,
        // demo veride de bunu gösterelim diye birkaç kitaba ikinci/üçüncü etiket eklendi.
        $bookModels['Sislerin Ardındaki Fener']->categories()->syncWithoutDetaching([
            $categories['Deneme']->id, $categories['Tarih']->id,
        ]);
        $bookModels['Zamansız Yolculuk']->categories()->syncWithoutDetaching([
            $categories['Deneme']->id,
        ]);

        // --- Editörün Seçkisi / Fırsatlar (anasayfa pillerinin altyapısı) ---
        // Süper Admin'in ileride Filament'ten işaretleyeceği alanlar — şimdilik demo
        // amaçlı birkaç yayında kitap üzerinde elle set ediliyor, pil boş görünmesin diye.
        foreach (['Sislerin Ardındaki Fener', 'Toprağın Hafızası', 'Zamansız Yolculuk', 'Rüzgârın Şarkısı'] as $title) {
            $bookModels[$title]->update(['is_editors_pick' => true]);
        }

        $discounted = [
            'Sessiz Sokaklar' => 112,
            'Unutulan Mektuplar' => 99,
            'Ayna Kırıkları' => 139,
            'Karanlığın Ötesinde' => 165,
        ];
        foreach ($discounted as $title => $discountPrice) {
            $bookModels[$title]->update(['discount_price' => $discountPrice]);
        }

        // --- Değerlendirme / İçerik İstatistikleri (kitap tanıtım sayfasının altyapısı) ---
        // Gerçek bir yorum sistemi kurulana kadar demo amaçlı elle girildi. Bilerek üç
        // farklı durumu temsil ediyor: tam dolu, kısmi (bazı istatistik alanları boş —
        // "sadece doldurulan alan gösterilir" davranışını kanıtlasın diye) ve hiç
        // girilmemiş (o kitapta bu bölüm hiç görünmemeli, sahte veri değil).
        $bookModels['Sislerin Ardındaki Fener']->update([
            'average_rating' => 4.8, 'review_count' => 128,
            // Belge / video sayısı elle girilmiyor, bölüm metninden hesaplanıyor.
            'page_count' => 248,
            'map_count' => 4, 'author_note_count' => 12, 'source_count' => 38,
        ]);
        $bookModels['Zamansız Yolculuk']->update([
            'average_rating' => 4.5, 'review_count' => 42,
            'page_count' => 310,
        ]);
        $bookModels['Toprağın Hafızası']->update([
            'average_rating' => 4.2, 'review_count' => 15,
        ]);

        // --- Çok Satanlar (anasayfa pilinin altyapısı) ---
        // Gerçek bir "en çok satan" sıralaması satın alma sayısından türetiliyor (sahte
        // sayaç yok) — bunun için birkaç demo okur ve dağılımlı satın alma kaydı gerekiyor.
        $demoBuyers = collect(range(1, 6))->map(
            fn (int $i) => User::firstOrCreate(
                ['email' => "demo.okur{$i}@evrenkent.test"],
                ['name' => "Demo Okur {$i}", 'password' => bcrypt('password')]
            )
        )->each(function (User $buyer) {
            if (! $buyer->hasRole('okur')) {
                $buyer->assignRole('okur');
            }
        });

        $purchaseCounts = [
            'Zamansız Yolculuk' => 5,
            'Sessiz Sokaklar' => 4,
            'Toprağın Hafızası' => 3,
            'Sislerin Ardındaki Fener' => 3,
            'Unutulan Mektuplar' => 2,
            'Ayna Kırıkları' => 1,
        ];
        foreach ($purchaseCounts as $title => $count) {
            $book = $bookModels[$title];
            $price = $book->discount_price ?? $book->price;

            foreach ($demoBuyers->take($count) as $buyer) {
                $buyer->purchases()->firstOrCreate(
                    ['book_id' => $book->id],
                    ['amount' => $price, 'purchased_at' => now()->subDays(random_int(1, 45)), 'payment_status' => 'completed']
                );
            }
        }

        // --- Dergi Sayıları ---
        $issues = [
            ['title' => 'Bilim Tarihi Dergisi - Sayı 23', 'number' => 23, 'status' => ContentStatus::Yayinda],
            ['title' => 'Astronomi Dergisi - Sayı 15', 'number' => 15, 'status' => ContentStatus::Yayinda],
            ['title' => 'Felsefe Dergisi - Sayı 10', 'number' => 10, 'status' => ContentStatus::Gonderildi],
            ['title' => 'Edebiyat Dergisi - Sayı 8', 'number' => 8, 'status' => ContentStatus::Taslak],
            ['title' => 'Sanat Tarihi Dergisi - Sayı 5', 'number' => 5, 'status' => ContentStatus::Onaylandi],
            ['title' => 'Matematik Dergisi - Sayı 12', 'number' => 12, 'status' => ContentStatus::RevizyonIstendi],
        ];

        $issueModels = [];
        foreach ($issues as $data) {
            // Faz E: her sayı bir dergiye bağlı — dergi adı başlıktan ("X Dergisi - Sayı N").
            // Demo editörü tüm dergilerin editörü, iki demo yazar tüm dergilere atanmış
            // (yazar sadece atandığı dergilere makale gönderebiliyor).
            $magazineName = trim(explode(' - Sayı', $data['title'])[0]);
            $magazine = Magazine::firstOrCreate(
                ['slug' => Str::slug($magazineName)],
                ['name' => $magazineName, 'editor_id' => $editor->id, 'description' => "{$magazineName}, Evrenkent'in dergi yayınlarından biri."]
            );
            $magazine->authors()->syncWithoutDetaching([$author1->id, $author2->id]);

            $issueModels[$data['title']] = MagazineIssue::firstOrCreate(
                ['title' => $data['title']],
                [
                    'magazine_id' => $magazine->id,
                    'editor_id' => $editor->id,
                    'issue_number' => $data['number'],
                    'status' => $data['status'],
                    'publish_date' => $data['status'] === ContentStatus::Yayinda ? now()->subDays(random_int(1, 90)) : null,
                ]
            );

            if ($data['status'] === ContentStatus::RevizyonIstendi && $admin) {
                $issueModels[$data['title']]->reviews()->firstOrCreate([
                    'reviewer_id' => $admin->id,
                    'action' => 'revizyon_istendi',
                ], [
                    'note' => 'Kapak görseli ve içindekiler eksik, lütfen tamamlayın.',
                ]);
            }
        }

        // --- Makaleler ---
        $articles = [
            ['title' => 'Evrenin Yaşı ve Genişlemesi', 'author' => $author1, 'issue' => 'Astronomi Dergisi - Sayı 15', 'category' => 'Bilim Kurgu', 'status' => ContentStatus::Yayinda],
            ['title' => 'Kara Deliklerin Sırları', 'author' => $author2, 'issue' => 'Astronomi Dergisi - Sayı 15', 'category' => 'Bilim Kurgu', 'status' => ContentStatus::Onaylandi],
            ['title' => 'Galileo\'nun Gözlemleri', 'author' => $author1, 'issue' => 'Bilim Tarihi Dergisi - Sayı 23', 'category' => 'Tarih', 'status' => ContentStatus::Yayinda],
            ['title' => 'Yıldızların Yaşam Döngüsü', 'author' => $author2, 'issue' => 'Felsefe Dergisi - Sayı 10', 'category' => 'Bilim Kurgu', 'status' => ContentStatus::Incelemede],
            ['title' => 'Kozmik Işınlar ve Dünya', 'author' => $author1, 'issue' => null, 'category' => 'Deneme', 'status' => ContentStatus::Taslak],
            ['title' => 'Kuantum Hesaplama Temelleri', 'author' => $author1, 'issue' => 'Felsefe Dergisi - Sayı 10', 'category' => 'Bilim Kurgu', 'status' => ContentStatus::Gonderildi],
            ['title' => 'Kayıp Uygarlıklar', 'author' => $author2, 'issue' => 'Bilim Tarihi Dergisi - Sayı 23', 'category' => 'Tarih', 'status' => ContentStatus::RevizyonIstendi],
        ];

        $articleModels = [];
        foreach ($articles as $data) {
            $article = Article::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'author_id' => $data['author']->id,
                    'magazine_issue_id' => $data['issue'] ? $issueModels[$data['issue']]->id : null,
                    'title' => $data['title'],
                    'content' => fake()->paragraphs(4, true),
                    'status' => $data['status'],
                    'published_at' => $data['status'] === ContentStatus::Yayinda ? now()->subDays(random_int(1, 60)) : null,
                ]
            );

            $articleModels[$data['title']] = $article;

            $article->categories()->syncWithoutDetaching([$categories[$data['category']]->id]);

            if ($data['status'] === ContentStatus::Gonderildi) {
                $article->reviews()->firstOrCreate([
                    'reviewer_id' => $data['author']->id,
                    'action' => 'gonderildi',
                ], [
                    'note' => 'Yazar tarafından Dergi Editörüne gönderildi.',
                ]);
            }

            if ($data['status'] === ContentStatus::RevizyonIstendi && $admin) {
                $article->reviews()->firstOrCreate([
                    'reviewer_id' => $admin->id,
                    'action' => 'revizyon_istendi',
                ], [
                    'note' => 'Kaynakça eksik, lütfen ekleyin.',
                ]);
            }
        }

        // --- Bildirimler (header'daki zil ikonunun altyapısı) ---
        // Yukarıdaki revizyon/onay kayıtlarının bir kısmı için gerçek bildirim de
        // gönderiliyor — böylece Yazar/Dergi Editörü hesaplarıyla girişte zil dolu
        // görünüyor. notifyOnce() aynı içerik+tip için seeder tekrar çalıştırıldığında
        // bildirimin çoğalmasını engelliyor (idempotent).
        $this->notifyOnce($author1, new \App\Notifications\ContentRevisionRequested(
            $bookModels['Kayıp Zamanın Şiirleri'],
            'Şiir başlıkları eksik, lütfen tamamlayın.'
        ));
        $this->notifyOnce($author2, new \App\Notifications\ContentRevisionRequested(
            $bookModels['Medeniyetin Ayak İzleri'],
            'Kapak görseli eksik, lütfen ekleyip tekrar gönderin.'
        ));
        $this->notifyOnce($author2, new \App\Notifications\ContentRevisionRequested(
            $articleModels['Kayıp Uygarlıklar'],
            'Kaynakça eksik, lütfen ekleyin.'
        ));
        $this->notifyOnce($editor, new \App\Notifications\ContentRevisionRequested(
            $issueModels['Matematik Dergisi - Sayı 12'],
            'Kapak görseli ve içindekiler eksik, lütfen tamamlayın.'
        ));
        $this->notifyOnce($author2, new \App\Notifications\ContentPublished($bookModels['Sislerin Ardındaki Fener']));
        $this->notifyOnce($author1, new \App\Notifications\ContentPublished($articleModels['Evrenin Yaşı ve Genişlemesi']));

        // --- Bölümler (Okuma Modu demo içeriği) ---
        // 'Sislerin Ardındaki Fener' reader tarafından zaten satın alınmış (aşağıda) — kilidi açık okunabilir.
        // 'Kökler ve Kanatlar' satın alınmamış, ücretli — okuma sayfasında kilitli görünmeli.
        $chapterSets = [
            'Sislerin Ardındaki Fener' => [
                ['title' => 'Uyanış', 'content' => fake()->paragraphs(6, true)],
                ['title' => 'Sisin İçinde', 'content' => fake()->paragraphs(6, true)],
                ['title' => 'Fenerin Işığı', 'content' => fake()->paragraphs(6, true)],
            ],
            'Kökler ve Kanatlar' => [
                ['title' => 'İlk Dize', 'content' => fake()->paragraphs(4, true)],
                ['title' => 'Rüzgârın Sesi', 'content' => fake()->paragraphs(4, true)],
            ],
        ];

        foreach ($chapterSets as $title => $chapters) {
            $book = $bookModels[$title];
            foreach ($chapters as $index => $data) {
                Chapter::firstOrCreate(
                    ['book_id' => $book->id, 'order' => $index + 1],
                    ['title' => $data['title'], 'content' => $data['content']]
                );
            }
        }

        // --- Okur: Favoriler / Okuma Listesi / Notlar / Satın Alımlar ---
        if ($reader) {
            $sisler = $bookModels['Sislerin Ardındaki Fener'];
            $kokler = $bookModels['Kökler ve Kanatlar'];

            $reader->favorites()->firstOrCreate([
                'favoritable_type' => Book::class,
                'favoritable_id' => $sisler->id,
            ]);
            $reader->favorites()->firstOrCreate([
                'favoritable_type' => Book::class,
                'favoritable_id' => $kokler->id,
            ]);

            $reader->readingListItems()->firstOrCreate([
                'readable_type' => Book::class,
                'readable_id' => $sisler->id,
            ], [
                'status' => ReadingStatus::Listede,
            ]);
            $reader->readingListItems()->firstOrCreate([
                'readable_type' => Book::class,
                'readable_id' => $kokler->id,
            ], [
                'status' => ReadingStatus::Tamamlandi,
                'completed_at' => now()->subDays(5),
            ]);

            $reader->notes()->firstOrCreate([
                'type' => NoteType::Defter,
                'content' => 'Bugün güzel bir gün, yeni bir kitaba başlamalıyım.',
            ]);
            $reader->notes()->firstOrCreate([
                'type' => NoteType::Not,
                'noteable_type' => Book::class,
                'noteable_id' => $sisler->id,
            ], [
                'content' => 'Bu kitaptaki karakter gelişimi çok başarılı.',
            ]);
            $reader->notes()->firstOrCreate([
                'type' => NoteType::Alinti,
                'noteable_type' => Book::class,
                'noteable_id' => $kokler->id,
            ], [
                'content' => 'Kökleri olmayan kanatlar, sadece rüzgarda savrulur.',
                'location' => 'Sayfa 12',
            ]);

            $reader->purchases()->firstOrCreate([
                'book_id' => $sisler->id,
            ], [
                'amount' => $sisler->price,
                'purchased_at' => now()->subDays(3),
                'payment_status' => 'completed',
            ]);
        }

        $this->command?->info('Demo içerik oluşturuldu: '.count($categories).' kategori, '.count($books).' kitap, '.count($issues).' dergi sayısı, '.count($articles).' makale.');
    }

    /**
     * Bir bildirimi sadece aynı alıcıya, aynı içerik+tip için daha önce gönderilmediyse
     * yollar — seeder tekrar çalıştırıldığında bildirimlerin çoğalmasını engeller.
     */
    private function notifyOnce(User $recipient, \Illuminate\Notifications\Notification $notification): void
    {
        $data = $notification->toArray($recipient);

        $alreadySent = $recipient->notifications()
            ->where('type', $notification::class)
            ->whereJsonContains('data->url', $data['url'])
            ->exists();

        if (! $alreadySent) {
            $recipient->notify($notification);
        }
    }
}
