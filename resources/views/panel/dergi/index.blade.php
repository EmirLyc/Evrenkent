@extends('layouts.panel')

@section('title', 'Dergi Yönetimi')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Dergi Yönetimi</h1>

    {{-- Aktif Sayı: gerçek bir "hazırlık" checklist'i — her madde gerçekten ölçülebilen
         bir alana bağlı (kapak yüklü mü, editör yazısı girildi mi, makale var mı, bekleyen
         inceleme var mı, admin onayına gönderildi mi). Mockup'taki "Kaynakça"/"Hakem
         Kontrolü" gibi karşılığı olmayan maddeler bilerek atlandı. --}}
    @if (! $activeIssue)
        <div class="card p-12 text-center text-slate-400 mb-8">
            <x-heroicon-o-newspaper class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Şu an hazırlanan bir sayınız yok.
            <div class="mt-3">
                <a href="{{ route('panel.dergi.sayilarim.yeni') }}" class="btn-brand btn-sm">
                    <x-heroicon-o-plus class="w-4 h-4" /> Yeni Sayı Oluştur
                </a>
            </div>
        </div>
    @else
        <div class="card p-6 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-[180px_1fr] gap-6">
                <x-magazine-cover :issue="$activeIssue" class="aspect-[3/4] rounded-lg" />

                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="font-serif text-2xl font-semibold text-slate-900">{{ $activeIssue->title }}</h2>
                        <x-status-badge :status="$activeIssue->status" />
                    </div>
                    <div class="text-sm text-slate-500 mt-1">Sayı {{ $activeIssue->issue_number }}</div>

                    <div class="mt-5">
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="text-slate-500">Hazırlık durumu</span>
                            <span class="font-medium text-brand-700">%{{ $progress }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-brand-500 rounded-full" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        @foreach ($checklist as $item)
                            <span class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium border {{ $item['done'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                @if ($item['done'])
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                @else
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                @endif
                                {{ $item['label'] }}
                                @if (isset($item['meta']))
                                    <span class="text-slate-400">· {{ $item['meta'] }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>

                    {{-- Sayı oluşturma/düzenleme/onaya gönderme burada; onayla/reddet/yayınla
                         Süper Admin'in İçerik Onayları'nda (policy'de zaten sadece ona açık). --}}
                    <div class="flex flex-wrap gap-3 mt-5">
                        <a href="{{ route('dergiler.show', $activeIssue) }}" class="btn-outline btn-sm">
                            <x-heroicon-o-eye class="w-4 h-4" /> Sayıyı Önizle
                        </a>
                        @can('update', $activeIssue)
                            <a href="{{ route('panel.dergi.sayilarim.duzenle', $activeIssue) }}" class="btn-dark btn-sm">
                                Sayı Oluşturucuya Git →
                            </a>
                        @else
                            <span class="text-xs text-slate-400 self-center">Onay bekleniyor, düzenlenemiyor.</span>
                        @endcan
                        @can('submit', $activeIssue)
                            <form method="POST" action="{{ route('panel.dergi.sayilarim.gonder', $activeIssue) }}">
                                @csrf
                                <button type="submit" class="btn-outline-brand btn-sm">
                                    <x-heroicon-o-paper-airplane class="w-4 h-4" /> Yayına Gönder
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="card p-5">
            <div class="text-2xl font-serif font-semibold text-slate-900">{{ $pendingReviewCount }}</div>
            <div class="text-sm text-slate-500 mt-1">İncelemeyi bekleyen makale</div>
        </div>
        <div class="card p-5">
            <div class="text-2xl font-serif font-semibold text-slate-900">{{ $revisionRequestedCount }}</div>
            <div class="text-sm text-slate-500 mt-1">Revizyon istenen makale</div>
        </div>
    </div>

    <div class="flex items-baseline justify-between mb-4">
        <h2 class="font-serif text-lg font-semibold text-slate-900">Makale Havuzu</h2>
        <a href="{{ route('panel.dergi.makale-havuzu') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Tümünü Gör →</a>
    </div>
    @if ($recentArticles->isEmpty())
        <div class="card p-8 text-center text-slate-400 mb-8">Henüz makale gönderilmedi.</div>
    @else
        <div class="mb-8">
            <x-article-pool-table :articles="$recentArticles" />
        </div>
    @endif

    <div class="flex items-baseline justify-between mb-4">
        <h2 class="font-serif text-lg font-semibold text-slate-900">Yayın Takvimi</h2>
        <a href="{{ route('panel.dergi.yayin-takvimi') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Tümünü Gör →</a>
    </div>
    @if ($recentIssues->isEmpty())
        <div class="card p-8 text-center text-slate-400">Henüz bir sayınız yok.</div>
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($recentIssues as $issue)
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-900 truncate">{{ $issue->title }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">
                            {{ $issue->publish_date ? $issue->publish_date->format('d.m.Y') : 'Tarih belirlenmedi' }}
                        </div>
                    </div>
                    <x-status-badge :status="$issue->status" />
                </div>
            @endforeach
        </div>
    @endif
@endsection
