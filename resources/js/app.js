import '@hotwired/turbo';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Turbo yalnızca <body>'yi değiştirir, JS ortamı sayfalar arası canlı kalır —
// bu yüzden panel sidebar'ının açık/kapalı durumu Alpine.store'da tutulur.
// Böylece "Kitaplığım -> Favorilerim" gibi bir Turbo geçişinde sidebar
// pozisyonunu kaybetmez (aksi halde her body swap'inde x-data sıfırlanırdı).
// Başlangıç değeri ekran genişliğine göre: masaüstünde (lg ve üstü) açık,
// mobilde kapalı — mobilde sidebar artık push değil overlay (bkz.
// layouts/public.blade.php), açık gelmesi ekranın çoğunu kaplardı.
Alpine.store('ui', {
    sidebarOpen: window.matchMedia('(min-width: 1024px)').matches,
});

// Sepet sayacı (header rozeti) ve "sepete eklendi" toast'ı — sayfa yenilenmeden
// (fetch ile) güncellenebilsin diye Alpine store'da tutuluyor. Her Turbo geçişinde
// header'daki x-init sunucudan gelen gerçek sayıyla senkronlar (bkz. layouts/public.blade.php).
Alpine.store('cart', {
    count: 0,
    toast: { visible: false, title: '', url: '' },
    toastTimeout: null,
    showToast(title, url) {
        this.toast.title = title;
        this.toast.url = url;
        this.toast.visible = true;
        clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            this.toast.visible = false;
        }, 4000);
    },
});

// Yayın geri sayımı (x-countdown bileşeni) — Yakında Çıkacaklar kartları ve tanıtım
// sayfaları. Metin sunucuda da üretiliyor; burada sadece canlı güncelleniyor. Turbo bir
// sayfadan çıkarken elementi DOM'dan kaldırınca Alpine destroy()'u çağırıyor, interval sızmıyor.
Alpine.data('countdown', (iso) => ({
    label: '',
    timer: null,
    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    tick() {
        const seconds = Math.floor((new Date(iso) - new Date()) / 1000);

        if (seconds <= 0) {
            this.label = 'Yayına giriyor';
            clearInterval(this.timer);
            return;
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) {
            this.label = `${days} gün ${hours} saat kaldı`;
        } else if (hours > 0) {
            this.label = `${hours} saat ${minutes} dk kaldı`;
        } else {
            this.label = `${minutes} dk ${seconds % 60} sn kaldı`;
        }
    },
}));

// Sidebar scroll pozisyonu — Turbo her geçişte <body>'yi (dolayısıyla <aside>'ı)
// baştan render ediyor, bu yüzden aşağı kaydırıp bir linke tıklayınca sidebar
// görsel olarak "sıfırlanıp" en başa dönüyordu. scroll event'i bubble etmediği
// için document üzerinde capture:true ile dinleniyor; pozisyon düz bir JS
// değişkeninde tutuluyor (bu da Turbo geçişleri arasında canlı kalıyor, aynı
// yukarıdaki store'lar gibi). Sidebar içeriği (aktif link vurgusu, rozet
// sayıları) yine sunucudan taze geliyor — data-turbo-permanent gibi tüm
// elementi "donduran" bir yöntem kullanılmadı, o zaman hem aktif link vurgusu
// hem "Onay Bekleyenler" rozeti bir önceki sayfadan kalma/bayat kalırdı.
//
// Sadece scrollTop'u turbo:render'da (turbo:load'dan önce) düzeltmek yeterli
// olmadı: Turbo'nun kendi render() döngüsü yeni <body>'yi takmadan önce ve
// sonra en az bir kere nextRepaint() ile tarayıcıya boyama fırsatı veriyor,
// yani hangi event'i dinlersek dinleyelim JS'imiz çalışana kadar tarayıcı
// scrollTop=0 olan taze hâli zaten bir kere boyamış oluyordu (kullanıcının
// bildirdiği "saniyelik en üste gelip düzelme"). Çözüm: yarışı kazanmaya
// çalışmak yerine yanlış durumun hiç boyanmasını engellemek — sidebar,
// yeni body takılırken .js-restoring-scroll ile gizleniyor (bkz. app.css),
// scrollTop doğru değere ayarlanır ayarlanmaz aynı JS görünür kılıyor.
let sidebarScrollTop = 0;
document.addEventListener('scroll', (event) => {
    if (event.target?.classList?.contains('sidebar-scroll')) {
        sidebarScrollTop = event.target.scrollTop;
    }
}, true);
document.addEventListener('turbo:before-render', (event) => {
    const incomingSidebar = event.detail.newBody?.querySelector?.('.sidebar-scroll');
    if (incomingSidebar) {
        incomingSidebar.classList.add('js-restoring-scroll');
    }
});
document.addEventListener('turbo:render', () => {
    const sidebar = document.querySelector('.sidebar-scroll');
    if (sidebar) {
        sidebar.scrollTop = sidebarScrollTop;
        sidebar.classList.remove('js-restoring-scroll');
    }
});

Alpine.start();
