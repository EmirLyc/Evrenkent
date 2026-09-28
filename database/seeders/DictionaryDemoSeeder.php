<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\User;
use App\Support\DictionaryDocument;
use Illuminate\Database\Seeder;

/**
 * Faz G3 demo içeriği ("Sözlüğe Dair" — köprü sistemi): yayındaki bir sözlük ve başka bir
 * yazarın kitabında bu sözlüğe bağlı kelimeler. DemoContentSeeder'dan çağrılır; tek başına da
 * çalışır (mevcut demo verisine dokunmadan ekler):
 *   php artisan db:seed --class=DictionaryDemoSeeder
 */
class DictionaryDemoSeeder extends Seeder
{
    /** Harf bölümleri → [anahtar, kavram, tanım paragrafları]. */
    private const ENTRIES = [
        'A' => [
            ['anarsi', 'Anarşi', [
                'Yunanca "yönetici yokluğu" anlamındaki anarkhia\'dan gelir. Siyaset biliminde iki anlamda kullanılır: bir ülkede merkezi otoritenin çöktüğü düzensizlik hâli ve devlet dahil her türlü zorlayıcı otoriteye karşı çıkan siyasi düşünce akımı.',
                'Uluslararası ilişkiler kuramında ise devletlerin üstünde onları bağlayan bir merkezi otoritenin bulunmadığı sistemi anlatır; bu anlamda anarşi düzensizlik değil, bir yapı özelliğidir.',
            ]],
            ['anayasa', 'Anayasa', [
                'Devletin temel yapısını, organlarını, bu organların yetkilerini ve birbirleriyle ilişkilerini düzenleyen; temel hak ve özgürlükleri güvence altına alan üst hukuk normu.',
                'Anayasa, kanunlardan daha zor değiştirilebilir olması ve normlar hiyerarşisinin en üstünde yer almasıyla ayrılır.',
            ]],
        ],
        'D' => [
            ['demokrasi', 'Demokrasi', [
                'Egemenliğin halka ait olduğu ve yönetenlerin düzenli, serbest ve rekabetçi seçimlerle belirlendiği yönetim biçimi.',
                'Çağdaş anlamıyla demokrasi yalnızca çoğunluğun yönetimi değil; hukukun üstünlüğü, azınlık hakları ve güçler ayrılığıyla birlikte düşünülür.',
            ]],
            ['devlet', 'Devlet', [
                'Belirli bir ülke üzerinde yaşayan insan topluluğunun, egemenlik yetkisine sahip bir siyasi örgütlenme altında birleşmesi. Klasik tanımda ülke, insan topluluğu ve egemenlik devletin üç kurucu unsurudur.',
            ]],
        ],
        'E' => [
            ['egemenlik', 'Egemenlik', [
                'Devletin hem iç hem de dış ilişkilerinde bağımsız ve üstün otoriteye sahip olması. Bu kavram, modern devletin temelini oluşturan ilkelerden biridir.',
                'İç egemenlik, devletin kendi sınırları içerisinde nihai karar mercii olmasıdır; dış egemenlik ise devletin başka bir devlete tabi olmaması, uluslararası alanda eşit bir özne olarak tanınmasıdır.',
            ]],
        ],
        'M' => [
            ['mesruiyet', 'Meşruiyet', [
                'Bir iktidarın, yönetilenler tarafından haklı ve kabul edilebilir görülmesi. Meşruiyet, iktidarın yalnızca zor kullanarak değil rıza ile de işlemesini sağlar.',
                'Max Weber meşruiyetin üç ideal tipini ayırır: geleneksel, karizmatik ve yasal-ussal otorite.',
            ]],
        ],
    ];

    public function run(): void
    {
        $author = User::where('email', 'author@evrenkent.test')->first();
        if (! $author) {
            return;
        }

        $dictionary = Book::firstOrCreate(
            ['slug' => 'siyaset-bilimi-sozlugu'],
            [
                'kind' => Book::KIND_SOZLUK,
                'author_id' => $author->id,
                'title' => 'Siyaset Bilimi Sözlüğü',
                'subtitle' => 'Temel Kavramlar',
                'description' => 'Siyaset biliminin temel kavramlarını kısa ve kaynaklı tanımlarla açıklayan başvuru sözlüğü. Evrenkent\'teki kitaplarda altı noktalı kavramlar bu sözlüğün maddelerine bağlanır.',
                'price' => 95,
                'status' => ContentStatus::Yayinda,
                'published_at' => now()->subDays(3),
                'page_ratio' => '16x24',
                'heading_numbering' => false,
            ]
        );

        if ($category = Category::where('slug', 'tarih')->first()) {
            $dictionary->categories()->syncWithoutDetaching([$category->id]);
        }

        $order = 0;
        foreach (self::ENTRIES as $letter => $entries) {
            $html = '';
            foreach ($entries as [$key, $term, $paragraphs]) {
                $html .= '<p data-concept="'.$key.'">'.e($term).'</p>';
                foreach ($paragraphs as $paragraph) {
                    $html .= '<p>'.e($paragraph).'</p>';
                }
            }
            Chapter::firstOrCreate(['book_id' => $dictionary->id, 'order' => ++$order], ['title' => $letter, 'content' => $html]);
        }

        DictionaryDocument::sync($dictionary);

        // Okur sözlüğü satın almış: kitaptaki kavramdan gelince maddenin tamamını görür. Satın
        // almamış bir kullanıcı (ör. yeni kayıt) önizleme ve "Sözlüğü İncele" görür.
        User::where('email', 'reader@evrenkent.test')->first()?->purchases()->firstOrCreate(
            ['book_id' => $dictionary->id],
            ['amount' => $dictionary->price, 'purchased_at' => now()->subDay(), 'payment_status' => 'completed'],
        );

        // Başka bir yazarın kitabı: seçilen kelimeler sözlüğe bağlı ("Sözlüğe Bağla").
        $book = Book::where('slug', 'sislerin-ardindaki-fener')->first();
        if (! $book) {
            return;
        }

        $link = function (string $key, string $word) use ($dictionary): string {
            $entry = $dictionary->entries()->where('key', $key)->first();

            return $entry ? '<span data-concept-link="'.$entry->id.'" data-concept-term="'.e($entry->term).'">'.e($word).'</span>' : e($word);
        };

        $order = (int) $book->chapters()->max('order') + 1;
        if (! $book->chapters()->where('title', 'Fenercinin Defteri')->exists()) {
            Chapter::create([
                'book_id' => $book->id,
                'order' => $order,
                'title' => 'Fenercinin Defteri',
                'content' => '<p>Fenerci, adanın eski defterini açtığında ilk sayfada dedesinin el yazısını gördü: "Bu kaya kimsenin değildir; ne kralın ne de denizin." O gün adada '.$link('devlet', 'devlet').' diye bir şeyin olmadığını, yalnızca rüzgârın ve suyun kurallarının işlediğini düşündü.</p>'
                    .'<p>Yıllar sonra kıyıya gelen memurlar ise başka bir dil konuşuyordu. Adanın artık bir '.$link('egemenlik', 'egemenlik').' alanının parçası olduğunu, fenerin ışığının da o yetkinin bir işareti sayılacağını söylediler.</p>'
                    .'<p>Köylüler ilk başta itiraz etmedi; ama kuralların neden haklı olduğunu kimse anlatmayınca, gücün '.$link('mesruiyet', 'meşruiyet').'i konusunda fısıltılar başladı.</p>',
            ]);
        }
    }
}
