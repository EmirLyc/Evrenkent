@extends('layouts.admin-panel')

@section('title', 'İçerik Onayları')

@section('content')
    <div class="mb-6">
        <h1 class="font-serif text-xl font-semibold text-slate-900">İçerik Onayları</h1>
        <p class="text-sm text-slate-500 mt-1">Onay bekleyen ve onaylanıp yayın bekleyen kitap/dergi sayısı/makaleler.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        @foreach (['kitaplar' => 'Kitaplar ('.$books->count().')', 'dergiler' => 'Dergi Sayıları ('.$issues->count().')', 'makaleler' => 'Makaleler ('.$articles->count().')'] as $key => $label)
            <a href="{{ route('panel.adminpanel.onaylar.index', ['tur' => $key]) }}" class="{{ $tab === $key ? 'pill-active' : 'pill-idle' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($tab === 'kitaplar')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Başlık</th>
                            <th class="px-5 py-3 font-medium">Yazar</th>
                            <th class="px-5 py-3 font-medium">Durum</th>
                            <th class="px-5 py-3 font-medium">Güncellenme</th>
                            <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($books as $book)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-3 max-w-xs">
                                    <div class="text-slate-900 truncate">{{ $book->title }}</div>
                                    @if ($book->status === \App\Enums\ContentStatus::Onaylandi && $book->scheduled_publish_at)
                                        <div class="text-xs text-brand-700">{{ $book->scheduled_publish_at->format('d.m.Y H:i') }} tarihinde yayınlanacak</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $book->author->name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap"><x-status-badge :status="$book->status" /></td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $book->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('kitaplar.show', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                            <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                        </a>
                                        @can('approve', $book)
                                            <a href="{{ route('panel.adminpanel.onaylar.kitap.onayla-form', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-emerald-700 hover:text-emerald-800 transition-colors">
                                                <x-heroicon-o-check-circle class="w-4 h-4" /> Onayla
                                            </a>
                                        @endcan
                                        <a href="{{ route('panel.mesajlar.kitap', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                            <x-heroicon-o-chat-bubble-oval-left class="w-4 h-4" /> Mesajlar
                                        </a>
                                        @can('reject', $book)
                                            <a href="{{ route('panel.adminpanel.onaylar.kitap.reddet-form', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                                <x-heroicon-o-x-circle class="w-4 h-4" /> Reddet
                                            </a>
                                        @endcan
                                        @can('publish', $book)
                                            <form method="POST" action="{{ route('panel.adminpanel.onaylar.kitap.yayinla', $book) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                                    <x-heroicon-o-globe-alt class="w-4 h-4" /> Yayınla
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Onay bekleyen kitap yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($tab === 'dergiler')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Başlık</th>
                            <th class="px-5 py-3 font-medium">Editör</th>
                            <th class="px-5 py-3 font-medium">Durum</th>
                            <th class="px-5 py-3 font-medium">Güncellenme</th>
                            <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($issues as $issue)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-3 max-w-xs">
                                    <div class="text-slate-900 truncate">{{ $issue->title }}</div>
                                    @if ($issue->status === \App\Enums\ContentStatus::Onaylandi && $issue->scheduled_publish_at)
                                        <div class="text-xs text-brand-700">{{ $issue->scheduled_publish_at->format('d.m.Y H:i') }} tarihinde yayınlanacak</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $issue->editor->name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap"><x-status-badge :status="$issue->status" /></td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $issue->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('dergiler.show', $issue) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                            <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                        </a>
                                        @can('approve', $issue)
                                            <a href="{{ route('panel.adminpanel.onaylar.dergi.onayla-form', $issue) }}" class="inline-flex items-center gap-1.5 text-sm text-emerald-700 hover:text-emerald-800 transition-colors">
                                                <x-heroicon-o-check-circle class="w-4 h-4" /> Onayla
                                            </a>
                                        @elseif (in_array($issue->status, [\App\Enums\ContentStatus::Gonderildi, \App\Enums\ContentStatus::Onaylandi], true) && ! $issue->hasApprovedArticles())
                                            {{-- Kural 2: boş sayı yayınlanamaz/zamanlanamaz — buton sessizce kaybolmasın, nedeni görünsün. --}}
                                            <span class="text-xs text-slate-400" title="Sayı, en az bir onaylı makale olmadan yayınlanamaz. Önce Makaleler sekmesinden makaleleri onaylayın.">Onaylı makale yok</span>
                                        @endcan
                                        @can('reject', $issue)
                                            <a href="{{ route('panel.adminpanel.onaylar.dergi.reddet-form', $issue) }}" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                                <x-heroicon-o-x-circle class="w-4 h-4" /> Reddet
                                            </a>
                                        @endcan
                                        @can('publish', $issue)
                                            <form method="POST" action="{{ route('panel.adminpanel.onaylar.dergi.yayinla', $issue) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                                    <x-heroicon-o-globe-alt class="w-4 h-4" /> Yayınla
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Onay bekleyen dergi sayısı yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($tab === 'makaleler')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Başlık</th>
                            <th class="px-5 py-3 font-medium">Yazar</th>
                            <th class="px-5 py-3 font-medium">Durum</th>
                            <th class="px-5 py-3 font-medium">Güncellenme</th>
                            <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($articles as $article)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-3 max-w-xs">
                                    <div class="text-slate-900 truncate">{{ $article->title }}</div>
                                    <div class="text-xs text-slate-400 truncate">{{ $article->magazineIssue?->title ?? 'Sayısız' }}</div>
                                    @if ($article->status === \App\Enums\ContentStatus::Onaylandi && $article->scheduled_publish_at)
                                        <div class="text-xs text-brand-700">{{ $article->scheduled_publish_at->format('d.m.Y H:i') }} tarihinde yayınlanacak</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $article->author->name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap"><x-status-badge :status="$article->status" /></td>
                                <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $article->updated_at->format('d.m.Y H:i') }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('makaleler.show', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                            <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                        </a>
                                        @can('approve', $article)
                                            <a href="{{ route('panel.adminpanel.onaylar.makale.onayla-form', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-emerald-700 hover:text-emerald-800 transition-colors">
                                                <x-heroicon-o-check-circle class="w-4 h-4" /> Onayla
                                            </a>
                                        @endcan
                                        <a href="{{ route('panel.mesajlar.makale', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                            <x-heroicon-o-chat-bubble-oval-left class="w-4 h-4" /> Mesajlar
                                        </a>
                                        @can('reject', $article)
                                            <a href="{{ route('panel.adminpanel.onaylar.makale.reddet-form', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                                <x-heroicon-o-x-circle class="w-4 h-4" /> Reddet
                                            </a>
                                        @endcan
                                        @can('publish', $article)
                                            <form method="POST" action="{{ route('panel.adminpanel.onaylar.makale.yayinla', $article) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                                    <x-heroicon-o-globe-alt class="w-4 h-4" /> Yayınla
                                                </button>
                                            </form>
                                        @elseif ($article->status === \App\Enums\ContentStatus::Onaylandi)
                                            {{-- Kural 3: sayısı yayında değilse makale tek başına yayınlanamaz. --}}
                                            <span class="text-xs text-slate-400" title="Makale, sayısı yayınlandığında onunla birlikte yayına girer.">Sayıyla yayınlanacak</span>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Onay bekleyen makale yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
