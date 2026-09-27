@extends('layouts.public')

@section('title', $issue->title)

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
        <x-magazine-cover :issue="$issue" class="aspect-[3/4] rounded-lg border border-slate-200 shadow-sm" icon-class="w-10 h-10" />

        <div class="min-w-0">
            <x-detail-header
                :title="$issue->title"
                :byline="$issue->editor->name"
                :meta="['Sayı ' . $issue->issue_number, $issue->publish_date?->translatedFormat('d M Y')]"
            >
                {{-- Kitap sayfasıyla tutarlı: yayındaki sayıda "Yayında" rozeti gereksiz. --}}
                @if ($issue->status !== \App\Enums\ContentStatus::Yayinda && ! $isUpcoming)
                    <x-status-badge :status="$issue->status" />
                @endif
            </x-detail-header>

            @if ($isUpcoming)
                {{-- Zamanlanmış sayı: tanıtım (Yakında Çıkacak) — makaleler yayın anında açılır. --}}
                <div class="card p-5 mt-8 max-w-md">
                    <div class="flex items-center gap-2 text-brand-700 text-sm font-medium">
                        <x-heroicon-o-clock class="w-4 h-4" /> Yakında Çıkacak
                    </div>
                    <div class="text-lg font-serif font-semibold text-slate-900 mt-2">
                        {{ $issue->scheduled_publish_at->format('d.m.Y H:i') }} tarihinde yayınlanacak
                    </div>
                    <x-countdown :at="$issue->scheduled_publish_at" class="block text-2xl font-serif font-semibold text-brand-700 mt-1" />
                </div>
            @endif

            <div class="mt-9">
                <h2 class="font-serif text-base font-semibold text-slate-900 mb-3">Bu Sayıdaki Makaleler</h2>

                @if ($isUpcoming && $articles->isEmpty())
                    <div class="card p-8 text-center text-slate-400">
                        <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-3 text-slate-300" />
                        Makaleler sayının yayın tarihinde açılacak.
                    </div>
                @elseif ($articles->isEmpty())
                    <div class="card p-8 text-center text-slate-400">
                        <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-3 text-slate-300" />
                        Bu sayıda henüz yayınlanmış bir makale yok.
                    </div>
                @else
                    <div class="card divide-y divide-slate-100">
                        @foreach ($articles as $article)
                            <a href="{{ route('makaleler.show', $article) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50 transition-colors">
                                <x-heroicon-o-document-text class="w-5 h-5 text-slate-300 mt-0.5 shrink-0" />
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900">{{ $article->title }}</div>
                                    <div class="text-sm text-slate-500 mt-0.5">{{ $article->author->name }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
