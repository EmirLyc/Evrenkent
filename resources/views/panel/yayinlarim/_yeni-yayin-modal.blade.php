{{--
    "Yeni Yayın Oluştur" düğmesi + pop-up ("Yazarın Gözünden" 1.1.0): Kitap / Dergi mi, Sözlük mü?
    Sözlük seçilirse editörde "Kavram" seçeneği çıkar (sözlük maddeleri bir veri nesnesi).
--}}
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="shrink-0">
    <button type="button" @click="open = true" class="btn-dark h-11 px-5 gap-3">
        <x-heroicon-o-plus class="w-5 h-5" />
        Yeni Yayın Oluştur
        <x-heroicon-o-chevron-down class="w-4 h-4 opacity-80" />
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="yeni-yayin-baslik">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="open = false"></div>

            <div x-show="open" x-transition class="relative w-full max-w-xl rounded-xl bg-parchment shadow-2xl px-6 pt-7 pb-8 sm:px-10">
                <button type="button" @click="open = false" class="absolute top-4 right-4 p-1 text-slate-600 hover:text-slate-900" aria-label="Kapat">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>

                <x-reader-ornament />
                <h2 id="yeni-yayin-baslik" class="font-serif text-3xl font-semibold text-navy text-center mt-3">Yeni Yayın Oluştur</h2>
                <p class="text-center text-slate-600 mt-1">Ne yayınlamak istersiniz?</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 mt-7 sm:divide-x divide-y sm:divide-y-0 divide-slate-300/70">
                    <a href="{{ route('panel.yayinlarim.taslaklarim.yeni') }}" class="group block px-4 py-5 text-center rounded-lg hover:bg-white/60 transition-colors">
                        <span class="block font-serif text-3xl font-semibold text-brand-700 group-hover:text-brand-600">Kitap / Dergi</span>
                        <span class="block text-sm text-slate-600 mt-2">Kitap, inceleme, araştırma<br class="hidden sm:inline"> ya da dergi yazısı oluşturun.</span>
                    </a>
                    <a href="{{ route('panel.yayinlarim.taslaklarim.yeni', ['tur' => 'sozluk']) }}" class="group block px-4 py-5 text-center rounded-lg hover:bg-white/60 transition-colors">
                        <span class="block font-serif text-3xl font-semibold text-navy group-hover:text-slate-700">Sözlük</span>
                        <span class="block text-sm text-slate-600 mt-2">Kavram, terim ve tanımlar<br class="hidden sm:inline"> içeren sözlük oluşturun.</span>
                    </a>
                </div>
            </div>
        </div>
    </template>
</div>
