# Evrenkent — Canlıya Alma Öncesi Kontrol Listesi

## 🚂 Railway kurulumu

Kaynak repo: `LycPartners/evrenkent` (ücretli Railway hesabı). Railway'in Laravel rehberindeki yapı: container'lar geçici, kalıcı olan her şey yönetilen servislerde.

| Servis | Ne | Kalıcı veri |
|---|---|---|
| **evrenkent** (web) | `Dockerfile` → FrankenPHP (Caddy + PHP). Açılışta migration + önbellekler. Health check `/up`. Zamanlanmış görevler (planlanan yayınlar her dakika, defter görseli temizliği 04:00) aynı container'da arka planda `schedule:work` ile. | — |
| **MySQL** | Railway veritabanı servisi. | Bütün kayıtlar, oturumlar, önbellek |
| **preserved-ravioli** (Bucket) | Railway Storage Bucket (S3 uyumlu, herkese kapalı). | `public/` kapaklar + defter görselleri, `private/` gömülü belgeler |

Zamanlayıcı ayrı servis değil, çünkü tek replica'lı demo için gereksiz maliyet ve ayar. Web birden fazla replica'ya çıkarılırsa görevler her replica'da çalışır — o zaman ayrı scheduler servisine geçilir (aşağıda 5. madde).

Kuyruk işi yok (`ShouldQueue` kullanılmıyor), bu yüzden worker servisi yok; `QUEUE_CONNECTION=sync`. İleride kuyruklu iş eklenirse aynı imajla üçüncü bir servis (`php artisan queue:work`) eklenir.

Bucket herkese açık olamadığı için kapak/defter görselleri uygulama üzerinden veriliyor: `bucket_public` disk'inin adresi `APP_URL/media/...`, dosyayı `MediaController` bucket'tan akıtıyor (bir yıl tarayıcı önbelleği — dosya adları rastgele, içerik değişmiyor). Belgeler `private/` altında ve sadece `DocumentController` üzerinden, okuma yetkisi olana açılıyor.

**Kurulum (Railway dashboard):**

1. **New Project → Deploy from GitHub repo** → `LycPartners/evrenkent`. Servisin adı **evrenkent** (aşağıdaki `${{evrenkent.…}}` referansları bu ada bakıyor; ad farklıysa referansları ona göre değiştirin).
2. Aynı projede **+ Create → Database → MySQL** ve **+ Create → Bucket** ekleyin.
3. **evrenkent → Settings → Networking → Generate Domain** (port 8080). Müşteriye verilecek adres bu (şu an `evrenkent-production-729d.up.railway.app`).
4. **evrenkent → Variables → Raw Editor**'e yapıştırın — Railway'in "We found these variables in your source code" önerilerini **eklemeyin** (`.env.example`'daki yerel değerler, kurulumu bozar) (`APP_KEY` için yerelde `php artisan key:generate --show`; bir kere üretilip hiç değiştirilmez — değişirse oturumlar düşer):
   ```
   APP_NAME=Evrenkent
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...
   APP_URL=https://${{evrenkent.RAILWAY_PUBLIC_DOMAIN}}
   APP_LOCALE=tr
   APP_FALLBACK_LOCALE=en
   LOG_CHANNEL=stderr
   LOG_LEVEL=info
   DB_CONNECTION=mysql
   DB_URL=${{MySQL.MYSQL_URL}}
   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   MAIL_MAILER=log
   COVERS_DISK=bucket_public
   DOCUMENTS_DISK=bucket_private
   AWS_ACCESS_KEY_ID=${{preserved-ravioli.ACCESS_KEY_ID}}
   AWS_SECRET_ACCESS_KEY=${{preserved-ravioli.SECRET_ACCESS_KEY}}
   AWS_DEFAULT_REGION=${{preserved-ravioli.REGION}}
   AWS_BUCKET=${{preserved-ravioli.BUCKET}}
   AWS_ENDPOINT=${{preserved-ravioli.ENDPOINT}}
   AWS_USE_PATH_STYLE_ENDPOINT=false
   SEED_DEMO=true
   ```
   `MySQL` / `preserved-ravioli` servislerin dashboard'daki adları (bucket adını Railway rastgele veriyor); farklıysa referansları ona göre düzeltin (Raw Editor `${{` yazınca öneriyor). Bucket'ın Credentials sekmesi "path style" diyorsa `AWS_USE_PATH_STYLE_ENDPOINT=true`. Bucket'taki **Add to Service** düğmesi kullanılmıyor — aynı değişkenleri bu blok ekliyor.
5. **Ayrı scheduler servisi (sadece web birden fazla replica'ya çıkarılırsa):** **+ Create → GitHub Repo** → yine `LycPartners/evrenkent`, adı **scheduler**.
   - Settings → **Config-as-code → `railway.scheduler.json`** (health check'siz, her zaman yeniden başlar; `railway.json` web içindir).
   - Settings → Networking: domain **vermeyin**.
   - Variables: web'deki bloğun aynısı + `CONTAINER_ROLE=scheduler`, `SEED_DEMO` olmadan.
   - Web servisine `RUN_SCHEDULER=false` ekleyin — yoksa görevler iki yerde çalışır.
   - ⚠️ `APP_URL` scheduler'da da **web'in adresi** olmalı (`https://${{evrenkent.RAILWAY_PUBLIC_DOMAIN}}` aynen kalır). Defter görseli temizliği notlardaki görselleri bu adrese göre tanıyor; farklı olursa kullanılan görselleri de "kullanılmıyor" sanıp siler.
6. İlk açılışta web migration'ları çalıştırır; `SEED_DEMO=true` ise ve veritabanında hiç kullanıcı yoksa demo hesaplarını ve içeriği bir kereliğine ekler (`demo:seed-if-empty`). Kullanıcı varsa hiçbir şeye dokunmaz — sonradan eklenen/silinen içerik deploy'larda geri gelmez. Demo hesapları: `admin|editor|author|reader@evrenkent.test`, şifre `password`.
7. Özel alan adı (ör. `demo.evrenkent.com`) bağlanırsa `APP_URL`'i o adrese çevirin (ayrı scheduler servisi varsa onda da).
8. **Sonradan demo içerik eklendiyse** (seeder'a yeni kitap/makale): `SEED_DEMO` dolu veritabanında çalışmadığı için Railway'de **evrenkent → Console**: `php artisan db:seed --class=DemoContentSeeder --force`. Seeder `firstOrCreate` kullanıyor — var olanı çoğaltmaz, silmez. `DatabaseSeeder`'ı (düz `db:seed`) dolu veritabanında çalıştırmayın, aynı e-postayla tekrar kullanıcı açmaya çalışıp hata verir.

**Yedek:** MySQL servisi → Backups'tan otomatik yedek açılabilir. Bucket yedeği yok (Railway henüz versiyonlama desteklemiyor).

Bu bir **gösterim/demo** kurulumudur — aşağıdaki "🔴 Kritik" maddeler gerçek canlıya geçmeden önce hâlâ geçerlidir (özellikle mock ödeme, gerçek e-posta servisi ve demo hesaplarının silinmesi).


Bu proje şu an **yerel geliştirme ortamı** için yapılandırılmıştır. Gerçek bir sunucuya (canlı ortama) taşınmadan önce aşağıdaki maddeler mutlaka ele alınmalıdır. Bunlar kod değişikliği değil, ortam/konfigürasyon işleridir.

## 🔴 Kritik (mutlaka yapılmalı)

- [ ] **`APP_DEBUG=false` yap.** Açık kalırsa herhangi bir hatada ziyaretçiye tam stack trace, `.env` değişkenleri ve veritabanı sorguları gösterilir (Ignition hata sayfası) — ciddi bir bilgi sızıntısı riski.
- [ ] **`APP_ENV=production` yap.**
- [ ] **Gerçek bir e-posta servisi bağla** (`MAIL_MAILER`, SMTP bilgileri — SendGrid, Postmark, Mailgun vb.). Şu an `log` sürücüsü kullanılıyor, yani şifre sıfırlama/doğrulama e-postaları **hiç gönderilmiyor**, sadece log dosyasına yazılıyor.
- [ ] **`APP_URL`'i gerçek domain'e güncelle.** Şifre sıfırlama linkleri ve diğer imzalı URL'ler bu değere göre üretiliyor.
- [ ] **Seed edilen tüm hesapların şifresini değiştir veya hesapları sil.** (`admin@evrenkent.test`, `editor@evrenkent.test`, `author@evrenkent.test`, `reader@evrenkent.test`, `elif.nazli@evrenkent.test`, `murat.ekin@evrenkent.test`, `deniz.yalcin@evrenkent.test`, `orhan.demir@evrenkent.test`, `ece.yilmaz@evrenkent.test`, `demo.okur1@evrenkent.test`…`demo.okur6@evrenkent.test` — hepsi `password` şifresiyle oluşturuldu, sadece geliştirme içindir. Demo okurlar "Çok Satanlar" pilini beslemek için satın alma kaydı üretir, canlıda hiç olmamalı.)
- [ ] **`DemoContentSeeder`'ı (ve onun çağırdığı `DictionaryDemoSeeder` / `LibraryDemoSeeder`'ı) canlıda asla çalıştırma.** Sahte kullanıcı/kitap/makale/dergi verisi oluşturur, sadece demo amaçlıdır. `reader@evrenkent.test`'e hesaba özel yüksek sınırlar (`users.quota_overrides`) da verir.
- [ ] **Kitap `average_rating`/`review_count` alanları şu an elle giriliyor (Süper Admin → Kitaplar → Düzenle), gerçek bir yorum sistemi yok.** Demo kitaplardaki örnek puanlar (4.8/128 değerlendirme vb.) canlıya taşınmadan önce ya temizlenmeli ya da gerçek bir yorum/puanlama sistemi kurulup bu alanlar otomatik hesaplanır hale getirilmeli — kullanıcı onayıyla bilinçli bir geçici istisna (bkz. `UI_RESTYLE_NOTES.md` madde 17).
- [ ] **Sepet/satın alma ve premium abonelik hâlâ mock ödeme.** `User::purchase()` (hem tekil "Satın Al" hem sepet checkout'u bunu kullanıyor) ve `User::subscribe()` (Abonelik sayfasındaki aylık/yıllık plan) ödeme sorgusu yapmadan anında "tamamlandı" kaydı oluşturuyor — gerçek bir ödeme gateway'i (Stripe/iyzico) entegre edilmeden asıl parayla satış canlıya alınmamalı. Abonelik sayfasının SSS'sindeki "Ödeme yöntemleri" cevabı da gateway gelince güncellenmeli.
- [ ] **`php artisan migrate:fresh` gibi yıkıcı komutları canlı veritabanında asla çalıştırma.**
- [x] **Kapak görselleri için kalıcı depolama.** Railway'de `COVERS_DISK=bucket_public` (Railway Bucket, `/media/...` üzerinden `MediaController`). Yerelde varsayılan `public` disk. Başka bir S3 uyumlu sağlayıcıya (R2, AWS) geçmek sadece `AWS_*` değişkenlerini değiştirmek.
- [x] **Gömülü belgeler (Faz F2) için kalıcı ve herkese kapalı depolama.** Railway'de `DOCUMENTS_DISK=bucket_private` — aynı bucket'ın `private/` klasörü, `/media`'dan erişilemez (`MediaTest`), sadece `DocumentController::show` üzerinden içeriği okuyabilen kişiye veriliyor.

## 🟠 Önemli

- [x] **`SESSION_SECURE_COOKIE=true`** — Railway değişkenlerinde var.
- [x] **`php artisan storage:link`** — yerel disk kullanılan ortamlar için entrypoint her açılışta çalıştırıyor (Railway'de bucket kullanıldığı için gerekmiyor).
- [x] **Gerçek bir veritabanı** — Railway'de MySQL (`DB_URL=${{MySQL.MYSQL_URL}}`). Bütün migration'lar, demo seeder'ları ve test paketi MySQL 8.4'te de denendi (2026-10-06). Yerelde SQLite kalıyor.
- [x] **Önbellekler** — entrypoint her açılışta `php artisan optimize` (config, route, view, event) çalıştırıyor; imajda OPcache açık.
- [ ] **Kuyruk çalıştırıcısı (queue worker)** — şu an kuyruklu iş yok, Railway'de `QUEUE_CONNECTION=sync`. E-posta/bildirim gibi kuyruklu işler eklenirse `QUEUE_CONNECTION=database` + aynı imajla `php artisan queue:work` çalıştıran ayrı bir servis.
- [x] **Cron (`php artisan schedule:run`)** — Railway'de web container'ında arka planda `schedule:work` (`RUN_SCHEDULER`, varsayılan açık); çok replica'da ayrı scheduler servisi. "Yakında Çıkacaklar" için `content:publish-scheduled` komutu (eski adı `books:publish-scheduled`, takma ad olarak hâlâ çalışıyor) planlanan yayın tarihi gelmiş kitapları, dergi sayılarını (onaylı makaleleriyle birlikte) ve makaleleri otomatik yayınlıyor (`bootstrap/app.php` → `withSchedule()`, her dakika) — Railway dışındaki bir sunucuda `* * * * * php artisan schedule:run >> /dev/null 2>&1` cron girdisi olmadan bu hiç çalışmaz, zamanlanmış içerikler "Onaylandı" durumunda takılı kalır, sitede geri sayım "Yayına giriyor"da bekler. Aynı cron günde bir `notebooks:prune-images`'ı da çalıştırır (hiçbir defterde kullanılmayan Defterim görsellerini siler).

## 🟡 Küçük / Gözden Geçirilmeli

- [ ] 404/500 hata sayfaları hâlâ Laravel varsayılanı — tasarım sistemine uyacak şekilde özelleştirilebilir.
- [ ] **Arama şu an LIKE tabanlı** (Scout/Meilisearch/Algolia kurulu değil) — kitap/dergi/makale sayısı büyüdükçe performans için tam metin arama motoruna geçiş değerlendirilmeli.
- [ ] Favicon eklenmedi.
- [ ] E-posta doğrulama (`MustVerifyEmail`) şu an hiçbir yerde zorunlu kılınmıyor — Breeze'in doğrulama akışı kurulu ama devre dışı, istenirse `User` modeline `implements MustVerifyEmail` eklenip ilgili route'lara `verified` middleware'i eklenerek etkinleştirilebilir.

## ✅ Zaten Kontrol Edildi, Sorun Yok

- `composer audit` ve `npm audit` — bilinen güvenlik açığı yok (2026-09-27'de `league/commonmark` yama sürümüne güncellendi; 2026-09-28'de Filament ve onunla gelen Livewire vb. 20 paket kaldırıldı, ikonlar için `blade-ui-kit/blade-heroicons` doğrudan bağımlılık oldu).
- Blade view'ları `{{ }}` ile otomatik escape ediyor. Tek bilinçli istisna bölüm/makale içeriği (Faz F1, zengin metin): `{!! $x->renderedContent() !!}` — içerik her kayıtta `symfony/html-sanitizer` ile izinli etiketlere indiriliyor (`App\Support\RichText`) ve render'da tekrar temizleniyor; script/olay öznitelikleri/`javascript:` bağlantıları testlerle doğrulanıyor (`RichTextTest`).
- Word (.docx) içe aktarma için gereken `zip` ve `dom` PHP eklentileri Docker imajında var (`zip` Dockerfile'da kuruluyor, `dom` resmi imajda varsayılan); `composer.json`'da `ext-zip`/`ext-dom` olarak da belirtildi. Yüklenen dosya saklanmıyor.
- CSRF koruması tüm formlarda aktif (Laravel varsayılanı).
- Kategori/kitap/makale ilişkilerinde `cascadeOnDelete` doğru kurulu, orphan veri riski yok.
- Giriş formunda (Breeze, `LoginRequest`) yerleşik rate limiting var. Ayrı bir admin girişi yok — Filament paneli 2026-09-28'de kaldırıldı, eski `/admin` adresi kullanıcının kendi paneline yönleniyor.
- Kapak görseli yüklemelerinde dosya boyutu sınırı var (`max:5120`, 5MB).
