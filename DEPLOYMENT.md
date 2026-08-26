# Evrenkent — Canlıya Alma Öncesi Kontrol Listesi

## 🚂 Railway'de gösterim (demo) amaçlı deploy

Bu repo Railway için hazır bir `Dockerfile` + `railway.json` içeriyor (nginx/php-fpm yok, basit `php artisan serve` ile — sadece geliştirme aşamasında başkalarına göstermek için, tam prod kurulumu değil, bkz. aşağıdaki kritik madde listesi).

**Kurulum adımları (railway.app dashboard):**

1. **New Project → Deploy from GitHub repo** → `EmirLyc/Evrenkent` seçin.
2. Railway `Dockerfile`'ı otomatik algılar (builder: DOCKERFILE, `railway.json` sayesinde).
3. Servise şu environment değişkenlerini ekleyin (Settings → Variables):
   - `APP_NAME=Evrenkent`
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_KEY=` → önce boş bırakıp bir deploy yapın, loglarda üretilen key'i görüp buraya **kalıcı olarak** yapıştırın (aksi halde her redeploy'da key değişir, oturumlar/şifreleme bozulur).
   - `APP_URL=https://<railway-verdiği-domain>` (Settings → Networking → Generate Domain sonrası).
   - `DB_CONNECTION=sqlite`
   - `DB_DATABASE=/app/database/database.sqlite`
   - `SESSION_SECURE_COOKIE=true`
   - `MAIL_MAILER=log` (gerçek SMTP eklenene kadar)
4. **Volume ekleyin** (Settings → Volumes → New Volume, mount path: `/app/database`) — böylece SQLite dosyası her redeploy'da silinmez, veriler kalıcı olur. Volume eklemezseniz her deploy'da veritabanı sıfırlanır (demo için bu bile kabul edilebilir olabilir).
5. Networking → **Generate Domain** ile bir `*.up.railway.app` adresi alın, sonra `APP_URL` değişkenini bu adresle güncelleyip yeniden deploy edin.
6. İlk deploy'da `entrypoint.sh` otomatik olarak migration çalıştırır. Demo verisi (`DemoContentSeeder`) istenirse Railway'in Shell/CLI'ından elle tetiklenebilir — otomatik çalışmaz (aşağıdaki kritik maddeye bakın).

Bu bir **gösterim/demo** kurulumudur — aşağıdaki "🔴 Kritik" maddeler gerçek canlıya geçmeden önce hâlâ geçerlidir (özellikle mock ödeme ve gerçek e-posta servisi).


Bu proje şu an **yerel geliştirme ortamı** için yapılandırılmıştır. Gerçek bir sunucuya (canlı ortama) taşınmadan önce aşağıdaki maddeler mutlaka ele alınmalıdır. Bunlar kod değişikliği değil, ortam/konfigürasyon işleridir.

## 🔴 Kritik (mutlaka yapılmalı)

- [ ] **`APP_DEBUG=false` yap.** Açık kalırsa herhangi bir hatada ziyaretçiye tam stack trace, `.env` değişkenleri ve veritabanı sorguları gösterilir (Ignition hata sayfası) — ciddi bir bilgi sızıntısı riski.
- [ ] **`APP_ENV=production` yap.**
- [ ] **Gerçek bir e-posta servisi bağla** (`MAIL_MAILER`, SMTP bilgileri — SendGrid, Postmark, Mailgun vb.). Şu an `log` sürücüsü kullanılıyor, yani şifre sıfırlama/doğrulama e-postaları **hiç gönderilmiyor**, sadece log dosyasına yazılıyor.
- [ ] **`APP_URL`'i gerçek domain'e güncelle.** Şifre sıfırlama linkleri ve diğer imzalı URL'ler bu değere göre üretiliyor.
- [ ] **Seed edilen tüm hesapların şifresini değiştir veya hesapları sil.** (`admin@evrenkent.test`, `editor@evrenkent.test`, `author@evrenkent.test`, `reader@evrenkent.test`, `elif.nazli@evrenkent.test`, `demo.okur1@evrenkent.test`…`demo.okur6@evrenkent.test` — hepsi `password` şifresiyle oluşturuldu, sadece geliştirme içindir. Demo okurlar "Çok Satanlar" pilini beslemek için satın alma kaydı üretir, canlıda hiç olmamalı.)
- [ ] **`DemoContentSeeder`'ı canlıda asla çalıştırma.** Sahte kullanıcı/kitap/makale/dergi verisi oluşturur, sadece demo amaçlıdır.
- [ ] **Kitap `average_rating`/`review_count` alanları şu an elle giriliyor (Filament'ten), gerçek bir yorum sistemi yok.** Demo kitaplardaki örnek puanlar (4.8/128 değerlendirme vb.) canlıya taşınmadan önce ya temizlenmeli ya da gerçek bir yorum/puanlama sistemi kurulup bu alanlar otomatik hesaplanır hale getirilmeli — kullanıcı onayıyla bilinçli bir geçici istisna (bkz. `UI_RESTYLE_NOTES.md` madde 17).
- [ ] **Sepet/satın alma hâlâ mock ödeme.** `User::purchase()` (hem tekil "Satın Al" hem sepet checkout'u bunu kullanıyor) ödeme sorgusu yapmadan anında "tamamlandı" kaydı oluşturuyor — gerçek bir ödeme gateway'i (Stripe/iyzico) entegre edilmeden asıl parayla satış canlıya alınmamalı.
- [ ] **`php artisan migrate:fresh` gibi yıkıcı komutları canlı veritabanında asla çalıştırma.**

## 🟠 Önemli

- [ ] **`SESSION_SECURE_COOKIE=true` yap** (site HTTPS üzerinden çalışacaksa — ki çalışmalı).
- [ ] **`php artisan storage:link` çalıştır** (kapak görsellerinin görünmesi için — sembolik link `.gitignore`'da olduğundan repoya taşınmaz, her ortamda ayrıca oluşturulmalı).
- [ ] **Gerçek bir veritabanına geç** (şu an SQLite kullanılıyor — MySQL/PostgreSQL gibi bir üretim veritabanına geçiş `.env`'de `DB_CONNECTION` değiştirilerek yapılabilir).
- [ ] **`php artisan config:cache`, `route:cache`, `view:cache` çalıştır** (performans için).
- [ ] **Kuyruk çalıştırıcısını (queue worker) kur** — `QUEUE_CONNECTION=database` kullanılıyor, ileride e-posta/bildirim gibi kuyruklu işler eklenirse `php artisan queue:work` bir process manager (Supervisor vb.) ile sürekli çalışır durumda olmalı.
- [ ] **Cron kur (`php artisan schedule:run`).** "Yakında Çıkacaklar" özelliği için eklenen `books:publish-scheduled` komutu planlanan yayın tarihi gelmiş kitapları otomatik yayınlıyor (`bootstrap/app.php` → `withSchedule()`, her dakika çalışacak şekilde kayıtlı) — sunucuda `* * * * * php artisan schedule:run >> /dev/null 2>&1` cron girdisi olmadan bu hiç çalışmaz, kitaplar "Onaylandı" durumunda takılı kalır.

## 🟡 Küçük / Gözden Geçirilmeli

- [ ] 404/500 hata sayfaları hâlâ Laravel varsayılanı — tasarım sistemine uyacak şekilde özelleştirilebilir.
- [ ] **Arama şu an LIKE tabanlı** (Scout/Meilisearch/Algolia kurulu değil) — kitap/dergi/makale sayısı büyüdükçe performans için tam metin arama motoruna geçiş değerlendirilmeli.
- [ ] Favicon eklenmedi.
- [ ] `BookResource`/`MagazineIssueResource`'daki Yazar/Editör seçim kutuları tüm kullanıcıları listeliyor (role'e göre filtrelenmemiş) — veri girişinde kafa karıştırabilir, güvenlik açığı değil.
- [ ] E-posta doğrulama (`MustVerifyEmail`) şu an hiçbir yerde zorunlu kılınmıyor — Breeze'in doğrulama akışı kurulu ama devre dışı, istenirse `User` modeline `implements MustVerifyEmail` eklenip ilgili route'lara `verified` middleware'i eklenerek etkinleştirilebilir.

## ✅ Zaten Kontrol Edildi, Sorun Yok

- `composer audit` ve `npm audit` — bilinen güvenlik açığı yok.
- Tüm Blade view'ları `{{ }}` ile otomatik escape ediyor, kaçışsız (`{!! !!}`) çıktı hiçbir yerde kullanılmıyor — XSS riski yok.
- CSRF koruması tüm formlarda aktif (Laravel varsayılanı).
- Kategori/kitap/makale ilişkilerinde `cascadeOnDelete` doğru kurulu, orphan veri riski yok.
- Filament admin girişinde yerleşik rate limiting var.
- Kapak görseli yüklemelerinde dosya boyutu sınırı var (`->maxSize(5120)`, 5MB).
