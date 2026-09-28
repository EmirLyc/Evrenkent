# Evrenkent

"Okumanın yeni bir evreni" — kitap, dergi ve sözlük yayın platformu. Okur eserleri satın alıp
sayfalı okuma modunda okur; yazar eserini platformun editöründe yazar (ya da Word / EPUB'dan
aktarır) ve onaya gönderir; dergi editörü sayıları yönetir; Süper Admin onaylar ve yayınlar.

## Teknoloji

- Laravel 13 (PHP 8.3), Blade, Alpine.js, Turbo, Tailwind CSS, Vite
- Tiptap (yazar editörü), pdf.js (gömülü belgeler)
- Rol ve yetkiler: spatie/laravel-permission (okur, yazar, dergi editörü, süper admin)

## Kurulum

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoContentSeeder   # isteğe bağlı: demo kitap, dergi, sözlük
php artisan storage:link
npm run build
```

## Çalıştırma

```bash
php artisan serve          # http://127.0.0.1:8000
php artisan schedule:work  # zamanlanmış yayınlar ("Yakında Çıkacaklar")
npm run dev                # geliştirirken, varlıkları canlı derlemek için
```

## Demo hesaplar

Seed ve demo verisiyle gelir, şifreleri `password` (sadece geliştirme için):

| Rol | E-posta |
| --- | --- |
| Süper Admin | `admin@evrenkent.test` |
| Dergi Editörü | `editor@evrenkent.test` |
| Yazar | `author@evrenkent.test` |
| Okur | `reader@evrenkent.test` |

## Testler

```bash
php artisan test
```

## Belgeler

- [`UI_RESTYLE_NOTES.md`](UI_RESTYLE_NOTES.md) — yapılan işler ve kararlar, faz faz
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — Railway demo kurulumu ve canlıya çıkmadan önce yapılacaklar
