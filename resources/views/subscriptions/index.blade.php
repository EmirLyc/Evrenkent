@extends('layouts.public')

@section('title', 'Abonelik')

@section('content')
    {{-- Mockup: 7-)Abonelik Sayfası.png. Avantaj listesi sadece gerçekten çalışan özelliklerden
         oluşuyor: mockup'taki "Bir dergi hediye" ve "Kitaplara yorum yapabilme" (MVP dışı) hiç
         yok, "Favorilere ekleme" ise toplantı kararıyla herkese sınırsız açık — premium avantajı
         gibi gösterilmiyor. Oranlar/kotalar Süper Admin'in Premium Sistemi ayarlarından. --}}
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-slate-900 border-l-4 border-brand-500 pl-4">Abonelik</h1>
        <p class="text-slate-600 mt-3 max-w-xl">Evrenkent Premium ile okuma deneyiminizi bir üst seviyeye taşıyın.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6 items-start">
        <section class="rounded-xl bg-navy text-white px-6 py-8 sm:px-10 sm:py-10">
            <div class="text-center">
                <div class="text-xs tracking-[0.3em] text-slate-300">EVRENKENT</div>
                <div class="font-serif text-4xl sm:text-5xl font-semibold tracking-wide text-brand-300 mt-1">PREMIUM</div>
                <p class="text-sm text-slate-300 mt-3">Daha fazla keşfet, daha az öde, sınırsız biriktir.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-8 rounded-xl ring-1 ring-white/15 p-5 sm:p-6">
                <div class="flex gap-4">
                    <x-heroicon-o-receipt-percent class="w-9 h-9 text-brand-300 shrink-0" />
                    <div>
                        <div class="font-serif text-lg">Tüm kitaplarda</div>
                        <div class="font-serif text-2xl text-brand-300">%{{ $discountPercent }} indirim</div>
                        <p class="text-sm text-slate-300 mt-1">Kampanyadaki kitaplarda iki indirimden hangisi avantajlıysa o uygulanır.</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <x-heroicon-o-pencil-square class="w-9 h-9 text-brand-300 shrink-0" />
                    <div>
                        <div class="font-serif text-lg">Sınırsız çalışma alanı</div>
                        <p class="text-sm text-slate-300 mt-1">
                            Defter, not ve alıntılarınızı dilediğiniz kadar biriktirin.
                            Ücretsiz hesapta {{ $quotas['Defter'] }} defter (defter başına en fazla {{ number_format($notebookWords, 0, ',', '.') }} kelime), {{ $quotas['Not'] }} not ve {{ $quotas['Alıntı'] }} alıntı; fosfor her hesapta sınırsız.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <x-premium-plan-card />
    </div>

    <section class="mt-12 max-w-3xl" x-data="{ open: null }">
        <h2 class="font-serif text-xl font-semibold text-slate-900 mb-4">Sıkça Sorulan Sorular</h2>
        @php
            $faq = [
                'Aboneliğim ne zaman başlar?' => 'Ödeme tamamlandığı anda başlar; premium avantajları hemen hesabınızda aktif olur.',
                'Aboneliğimi nasıl iptal edebilirim?' => 'Otomatik yenileme yoktur, iptal etmeniz gerekmez. Süreniz bittiğinde hesabınız ücretsiz hesaba döner; defter, not ve alıntılarınız silinmez.',
                'Abonelik avantajları nasıl kullanılır?' => 'İndirim, kitap sayfasında ve sepette kendiliğinden uygulanır. Çalışma alanındaki kayıt sınırı da üyeliğiniz boyunca kalkar.',
                'Ödeme yöntemleri nelerdir?' => 'Ödeme altyapısı entegrasyon aşamasında; canlıya geçişte kredi ve banka kartıyla ödeme desteklenecek.',
            ];
        @endphp
        <div class="space-y-2">
            @foreach ($faq as $question => $answer)
                <div class="card">
                    <button type="button" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left text-sm text-slate-800">
                        {{ $question }}
                        <x-heroicon-o-chevron-down class="w-4 h-4 shrink-0 text-slate-400 transition-transform" ::class="open === {{ $loop->index }} && 'rotate-180'" />
                    </button>
                    <div x-show="open === {{ $loop->index }}" x-cloak class="px-4 pb-4 text-sm text-slate-600">{{ $answer }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
