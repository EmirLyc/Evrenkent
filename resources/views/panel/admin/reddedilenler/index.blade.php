@extends('layouts.admin-panel')

@section('title', 'Reddedilenler')

@section('content')
    {{-- 2026-09-27, karar A: kalıcı reddedilen tüm içerik (AdminRejectedController). --}}
    <div class="mb-6">
        <h1 class="font-serif text-xl font-semibold text-slate-900">Reddedilenler</h1>
        <p class="text-sm text-slate-500 mt-1">Kalıcı olarak reddedilen kitap, dergi sayısı ve makaleler. Sahipleri görebilir ve silebilir ama düzenleyip tekrar gönderemez. Yanlışlıkla reddedileni revizyona geri açabilirsiniz.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('panel.adminpanel.reddedilenler.index') }}" class="{{ $type === null ? 'pill-active' : 'pill-idle' }}">Tümü ({{ array_sum($counts) }})</a>
        @foreach (['kitaplar' => 'Kitaplar', 'dergiler' => 'Dergi Sayıları', 'makaleler' => 'Makaleler'] as $key => $label)
            <a href="{{ route('panel.adminpanel.reddedilenler.index', ['tur' => $key]) }}" class="{{ $type === $key ? 'pill-active' : 'pill-idle' }}">{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
    </div>

    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-no-symbol class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Reddedilmiş içerik yok.
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($items as $row)
                @php($item = $row['model'])
                <div class="px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs uppercase text-brand-700 font-medium tracking-wide">{{ $row['kind'] }}</span>
                            <x-status-badge :status="$item->status" />
                        </div>
                        <div class="font-medium text-slate-900 break-words">{{ $item->title }}</div>
                        <div class="text-sm text-slate-500">
                            {{ $row['owner']?->name ?? '—' }}
                            @if ($item instanceof \App\Models\MagazineIssue && $item->magazine) · {{ $item->magazine->name }} @endif
                            @if ($item instanceof \App\Models\Article && $item->magazineIssue) · {{ $item->magazineIssue->title }} @endif
                        </div>
                        @if ($row['review'])
                            <p class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-md px-3 py-1.5 max-w-xl break-words">
                                <span class="font-medium">Gerekçe:</span> {{ $row['review']->note }}
                            </p>
                            <div class="text-xs text-slate-400">
                                {{ $row['review']->reviewer?->name ?? 'Sistem' }} · {{ $row['review']->created_at->format('d.m.Y H:i') }}
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="{{ $row['viewUrl'] }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                            <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                        </a>
                        <form method="POST" action="{{ $row['reopenUrl'] }}" data-turbo-confirm="&quot;{{ $item->title }}&quot; revizyona geri açılacak ve sahibine bildirim gidecek. Devam edilsin mi?">
                            @csrf
                            <button type="submit" class="btn-outline btn-sm">Revizyona Geri Aç</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
