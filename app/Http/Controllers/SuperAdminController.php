<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\ContentReview;
use App\Models\MagazineIssue;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    /**
     * Mockup'ın "Ana Sayfa"sı — sadece gerçek DB verisiyle doldurulabilen kartlar/
     * listeler burada. Premium/Abonelik/Gelir Paylaşımı/Platform Sağlığı gibi hiç
     * altyapısı olmayan bölümler bilerek yok — sahte veri üretilmiyor.
     */
    public function index(): View
    {
        $pendingStatuses = [ContentStatus::Gonderildi, ContentStatus::Incelemede];

        $pendingBooks = Book::whereIn('status', $pendingStatuses)->count();
        $pendingArticles = Article::whereIn('status', $pendingStatuses)->count();
        $pendingIssues = MagazineIssue::whereIn('status', $pendingStatuses)->count();
        $readyIssues = MagazineIssue::where('status', ContentStatus::Onaylandi)->count();

        $todaySales = Purchase::whereDate('purchased_at', today())->count();
        $todayRevenue = Purchase::whereDate('purchased_at', today())->sum('amount');
        $todayRegistrations = User::whereDate('created_at', today())->count();

        $stats = [
            ['label' => 'Toplam Kitap', 'value' => Book::count(), 'icon' => 'book-open'],
            ['label' => 'Toplam Dergi Sayısı', 'value' => MagazineIssue::count(), 'icon' => 'newspaper'],
            ['label' => 'Toplam Kullanıcı', 'value' => User::count(), 'icon' => 'users'],
            ['label' => 'Premium Üye', 'value' => User::where('is_premium', true)->count(), 'icon' => 'sparkles'],
            ['label' => 'Bugünkü Satış', 'value' => $todaySales, 'icon' => 'shopping-cart'],
            ['label' => 'Bugünkü Gelir', 'value' => number_format((float) $todayRevenue, 2, ',', '.').' TL', 'icon' => 'banknotes'],
        ];

        $todayStatus = [
            ['count' => $pendingBooks, 'label' => 'kitap onay bekliyor'],
            ['count' => $pendingArticles, 'label' => 'makale onay bekliyor'],
            ['count' => $readyIssues, 'label' => 'dergi sayısı yayına hazır'],
            ['count' => $todayRegistrations, 'label' => 'yeni kullanıcı kaydı'],
            ['count' => $todaySales, 'label' => 'yeni kitap satışı'],
        ];

        $trend = $this->salesTrend(30);

        $bestsellers = Purchase::selectRaw('book_id, COUNT(*) as sales_count, SUM(amount) as total_amount')
            ->groupBy('book_id')
            ->orderByDesc('sales_count')
            ->with('book.author')
            ->take(5)
            ->get()
            ->filter(fn ($row) => $row->book !== null);

        $recentIssues = MagazineIssue::published()
            ->latest('publish_date')
            ->take(5)
            ->get();

        $activity = $this->liveActivity(10);

        $pendingApprovals = [
            ['label' => 'Kitap Onayları', 'count' => $pendingBooks, 'route' => route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar'])],
            ['label' => 'Dergi Sayısı Onayları', 'count' => $pendingIssues, 'route' => route('panel.adminpanel.onaylar.index', ['tur' => 'dergiler'])],
            ['label' => 'Makale Onayları', 'count' => $pendingArticles, 'route' => route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler'])],
        ];

        return view('panel.admin.index', [
            'stats' => $stats,
            'todayStatus' => $todayStatus,
            'trend' => $trend,
            'bestsellers' => $bestsellers,
            'recentIssues' => $recentIssues,
            'activity' => $activity,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }

    /**
     * Sidebar'daki, henüz altyapısı olmayan bölümler (Abonelikler, Gelir Merkezi,
     * Premium Sistemi, Sözlükler vb.) için tek bir generic "yakında" sayfası —
     * sahte veri/işlevsellik üretmek yerine dürüstçe "altyapı hazırlanıyor" diyor.
     */
    public function placeholder(string $section): View
    {
        $labels = [
            'sozlukler' => 'Sözlükler',
            'tum-yayinlar' => 'Tüm Yayınlar',
            'roller-yetkiler' => 'Roller ve Yetkiler',
            'istatistik-satislar' => 'Satış İstatistikleri',
            'istatistik-abonelikler' => 'Abonelik İstatistikleri',
            'istatistik-kitaplar' => 'Kitap İstatistikleri',
            'istatistik-dergiler' => 'Dergi İstatistikleri',
            'istatistik-sozlukler' => 'Sözlük İstatistikleri',
            'istatistik-yazarlar' => 'Yazar İstatistikleri',
            'platform-geliri' => 'Platform Geliri',
            'yazar-hakedisleri' => 'Yazar Hakedişleri',
            'odemeler' => 'Ödemeler',
            'faturalar' => 'Faturalar',
            'ana-sayfa-yonetimi' => 'Ana Sayfa Yönetimi',
            'premium-sistemi' => 'Premium Sistemi',
            'bildirimler-sistemi' => 'Bildirimler (Sistem)',
            'sistem-ayarlari' => 'Sistem Ayarları',
            'islem-gecmisi' => 'İşlem Geçmişi',
            'sistem-gunlukleri' => 'Sistem Günlükleri',
        ];

        abort_unless(array_key_exists($section, $labels), 404);

        return view('panel.admin.placeholder', [
            'title' => $labels[$section],
        ]);
    }

    /**
     * Son $days günün günlük satış adedi + geliri — line chart için basit
     * bir nokta dizisi (kütüphanesiz, elle çizilecek inline SVG polyline).
     *
     * @return array{labels: array<string>, sales: array<int>, revenue: array<float>}
     */
    private function salesTrend(int $days): array
    {
        $since = today()->subDays($days - 1);

        $rows = Purchase::selectRaw('DATE(purchased_at) as day, COUNT(*) as sales_count, SUM(amount) as total_amount')
            ->where('purchased_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $labels = [];
        $sales = [];
        $revenue = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i);
            $key = $date->toDateString();
            $row = $rows->get($key);

            $labels[] = $date->format('d.m');
            $sales[] = (int) ($row->sales_count ?? 0);
            $revenue[] = (float) ($row->total_amount ?? 0);
        }

        return ['labels' => $labels, 'sales' => $sales, 'revenue' => $revenue];
    }

    /**
     * Gerçek "Canlı Akış" — onay/red/yayınla/gönder aksiyonları (ContentReview),
     * satın almalar ve yeni kullanıcı kayıtları tek bir kronolojik listede birleşir.
     * Hiçbiri uydurma değil, hepsi gerçek zaman damgalı DB olayları.
     *
     * @return Collection<int, array{icon: string, title: string, subtitle: string, at: Carbon}>
     */
    private function liveActivity(int $limit): Collection
    {
        $reviewLabels = [
            'gonderildi' => 'gönderildi',
            'incelemede' => 'incelemeye alındı',
            'onaylandi' => 'onaylandı',
            'revizyon_istendi' => 'için revizyon istendi',
            'yayinda' => 'yayınlandı',
        ];

        $typeLabels = [
            Book::class => 'Kitap',
            Article::class => 'Makale',
            MagazineIssue::class => 'Dergi sayısı',
        ];

        $reviews = ContentReview::with(['reviewer', 'reviewable'])
            ->latest()
            ->take($limit)
            ->get()
            ->filter(fn ($review) => $review->reviewable !== null)
            ->map(function (ContentReview $review) use ($reviewLabels, $typeLabels) {
                $type = $typeLabels[$review->reviewable_type] ?? 'İçerik';
                $action = $reviewLabels[$review->action] ?? $review->action;

                return [
                    'icon' => match (true) {
                        str_contains($review->action, 'onay') => 'check-circle',
                        str_contains($review->action, 'red') || str_contains($review->action, 'reddedildi') => 'x-circle',
                        str_contains($review->action, 'yayin') => 'megaphone',
                        default => 'arrow-path',
                    },
                    'title' => "\"{$review->reviewable->title}\" {$type} {$action}",
                    'subtitle' => $review->reviewer?->name ?? 'Sistem',
                    'at' => $review->created_at,
                ];
            });

        $purchases = Purchase::with(['user', 'book'])
            ->latest('purchased_at')
            ->take($limit)
            ->get()
            ->filter(fn ($purchase) => $purchase->book !== null)
            ->map(fn (Purchase $purchase) => [
                'icon' => 'shopping-bag',
                'title' => "\"{$purchase->book->title}\" kitabı satıldı",
                'subtitle' => $purchase->user?->name ?? 'Bir kullanıcı',
                'at' => $purchase->purchased_at,
            ]);

        $registrations = User::latest()
            ->take($limit)
            ->get()
            ->map(fn (User $user) => [
                'icon' => 'user-plus',
                'title' => 'Yeni üye kaydı',
                'subtitle' => $user->name,
                'at' => $user->created_at,
            ]);

        return $reviews->concat($purchases)->concat($registrations)
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }
}
