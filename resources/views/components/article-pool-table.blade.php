@props(['articles'])

{{--
    Makale Havuzu tablosu — hem Dergi Yönetimi ana sayfasındaki önizlemede hem tam
    Makale Havuzu sayfasında kullanılıyor, tek yerden değişir. 6 sütun olduğu için
    mobilde overflow-x-auto ile yatay kaydırılabilir (bu genişlikte bir veri tablosunu
    375px'e sığdırmak okunurluğu bozar — yatay scroll burada bilinçli bir istisna).
--}}
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                    <th class="px-5 py-3 font-medium">Yazar</th>
                    <th class="px-5 py-3 font-medium">Makale Başlığı</th>
                    <th class="px-5 py-3 font-medium">Kategori</th>
                    <th class="px-5 py-3 font-medium">Durum</th>
                    <th class="px-5 py-3 font-medium">Gönderim Tarihi</th>
                    <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($articles as $article)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <x-avatar :name="$article->author->name" :id="$article->author->id" />
                                <div>
                                    <div class="font-medium text-slate-900">{{ $article->author->name }}</div>
                                    {{-- Mockup 2.3: editörün kendi yazdığı makale (editör yazar yetkilerine de sahip). --}}
                                    @if ($article->author_id === $article->magazineIssue?->editor_id)
                                        <span class="inline-block mt-0.5 rounded bg-navy/10 px-1.5 py-px text-[11px] font-medium text-navy">Editörün Makalesi</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 max-w-xs">
                            <div class="text-slate-900 truncate">{{ $article->title }}</div>
                            @if ($article->magazineIssue)
                                {{-- Not: demo verideki sayı başlıkları zaten "- Sayı N" formatında yazılmış,
                                     bu yüzden numara burada ayrıca eklenmiyor (tekrar olmasın diye). --}}
                                <div class="text-xs text-slate-400 truncate">{{ $article->magazineIssue->title }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                            {{ $article->categories->pluck('name')->join(', ') ?: '—' }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <x-status-badge :status="$article->status" />
                        </td>
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">
                            {{ $article->created_at->format('d.m.Y') }}
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('panel.dergi.makale-havuzu.goster', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                    <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                </a>
                                @can('review', $article)
                                    <form method="POST" action="{{ route('panel.dergi.makale-havuzu.incele', $article) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                            <x-heroicon-o-check-circle class="w-4 h-4" /> İncele
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
