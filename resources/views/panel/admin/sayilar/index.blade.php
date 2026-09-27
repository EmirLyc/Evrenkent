@extends('layouts.admin-panel')

@section('title', 'Dergi Sayıları')

@section('content')
    <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
        <div>
            <h1 class="font-serif text-xl font-semibold text-slate-900">Dergi Sayıları</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $issues->total() }} sayı.</p>
        </div>
        <a href="{{ route('panel.adminpanel.sayilar.yeni') }}" class="btn-brand btn-sm">
            <x-heroicon-o-plus class="w-4 h-4" /> Yeni Sayı
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        <input type="text" name="q" value="{{ $q }}" placeholder="Başlıkta ara…" class="w-full sm:w-64 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        <select name="durum" class="rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            <option value="">Tüm Durumlar</option>
            @foreach (\App\Enums\ContentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($durum === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm">Filtrele</button>
        @if ($q || $durum)
            <a href="{{ route('panel.adminpanel.sayilar.index') }}" class="text-sm text-slate-500 hover:text-slate-900 self-center transition-colors">Temizle</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Kapak</th>
                        <th class="px-5 py-3 font-medium">Başlık</th>
                        <th class="px-5 py-3 font-medium">Sayı No</th>
                        <th class="px-5 py-3 font-medium">Dergi / Editör</th>
                        <th class="px-5 py-3 font-medium">Durum</th>
                        <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($issues as $issue)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-2.5">
                                <x-magazine-cover :issue="$issue" class="w-9 aspect-[3/4] rounded" />
                            </td>
                            <td class="px-5 py-2.5 max-w-xs"><div class="text-slate-900 truncate">{{ $issue->title }}</div></td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $issue->issue_number }}</td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <div class="text-slate-700">{{ $issue->magazine?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $issue->editor->name }}</div>
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap"><x-status-badge :status="$issue->status" /></td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('dergiler.show', $issue) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                        <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                    </a>
                                    <a href="{{ route('panel.adminpanel.sayilar.duzenle', $issue) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                        <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                    </a>
                                    <form method="POST" action="{{ route('panel.adminpanel.sayilar.sil', $issue) }}" data-turbo-confirm="&quot;{{ $issue->title }}&quot; kalıcı olarak silinecek. Emin misiniz?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                            <x-heroicon-o-trash class="w-4 h-4" /> Sil
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">Dergi sayısı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">
        {{ $issues->links() }}
    </div>
@endsection
