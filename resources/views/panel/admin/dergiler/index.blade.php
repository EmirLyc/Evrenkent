@extends('layouts.admin-panel')

@section('title', 'Dergiler')

@section('content')
    <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
        <div>
            <h1 class="font-serif text-xl font-semibold text-slate-900">Dergiler</h1>
            <p class="text-sm text-slate-500 mt-1">Dergileri, editörlerini ve makale gönderebilecek yazarlarını yönetin. Sayılar <a href="{{ route('panel.adminpanel.sayilar.index') }}" class="underline">Dergi Sayıları</a>'nda.</p>
        </div>
        <a href="{{ route('panel.adminpanel.dergiler.yeni') }}" class="btn-brand btn-sm">
            <x-heroicon-o-plus class="w-4 h-4" /> Yeni Dergi
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Dergi</th>
                        <th class="px-5 py-3 font-medium">Editör</th>
                        <th class="px-5 py-3 font-medium">Yazar</th>
                        <th class="px-5 py-3 font-medium">Sayı</th>
                        <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($magazines as $magazine)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3 max-w-xs"><div class="text-slate-900 truncate">{{ $magazine->name }}</div></td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($magazine->editor)
                                    <span class="text-slate-600">{{ $magazine->editor->name }}</span>
                                @else
                                    <span class="text-amber-700 text-xs">Editör atanmamış</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $magazine->authors_count }}</td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $magazine->issues_count }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('dergi.show', $magazine) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                        <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                    </a>
                                    <a href="{{ route('panel.adminpanel.dergiler.duzenle', $magazine) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                        <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                    </a>
                                    @if ($magazine->issues_count === 0)
                                        <form method="POST" action="{{ route('panel.adminpanel.dergiler.sil', $magazine) }}" data-turbo-confirm="&quot;{{ $magazine->name }}&quot; kalıcı olarak silinecek. Emin misiniz?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                                <x-heroicon-o-trash class="w-4 h-4" /> Sil
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Henüz dergi yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
