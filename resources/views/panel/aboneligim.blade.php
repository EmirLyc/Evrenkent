@extends('layouts.panel')

@section('title', 'Aboneliğim')

@section('content')
    @php $user = auth()->user(); @endphp

    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Aboneliğim</h1>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6 items-start">
        <div class="space-y-6 min-w-0">
            <div class="card p-6">
                @if ($user->isPremium())
                    <div class="flex items-center gap-2 text-amber-700 font-medium">
                        <x-heroicon-s-sparkles class="w-5 h-5" /> Premium üyesiniz
                    </div>
                    <p class="text-sm text-slate-600 mt-2">
                        @if ($user->premium_until)
                            Üyeliğiniz <strong>{{ $user->premium_until->translatedFormat('j F Y') }}</strong> tarihine kadar aktif. Otomatik yenileme yok; süre bitince hesabınız ücretsiz hesaba döner, kayıtlarınız silinmez.
                        @else
                            Süresiz premium üyeliğiniz var.
                        @endif
                    </p>
                    <p class="text-sm text-slate-600 mt-2">Kitaplarda premium indirimi ve sınırsız çalışma alanı aktif.</p>
                @else
                    <div class="font-medium text-slate-900">Ücretsiz hesap</div>
                    <p class="text-sm text-slate-500 mt-1">Çalışma alanınızda her alan için bir kayıt sınırı var. Premium ile sınırsız kullanabilir, kitaplarda indirim kazanabilirsiniz.</p>

                    <div class="space-y-3 mt-5">
                        @foreach ($quotaUsage as $usage)
                            @php $ratio = $usage->quota > 0 ? min(100, round($usage->used / $usage->quota * 100)) : 100; @endphp
                            <div>
                                <div class="flex justify-between text-xs text-slate-500 mb-1">
                                    <span>{{ $usage->label }}</span>
                                    <span class="{{ $usage->used >= $usage->quota ? 'text-amber-700 font-medium' : '' }}">{{ $usage->used }} / {{ $usage->quota }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $usage->used >= $usage->quota ? 'bg-amber-500' : 'bg-brand-400' }}" style="width: {{ $ratio }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <a href="{{ route('abonelik') }}" class="inline-block text-sm font-medium text-brand-600 hover:text-brand-700 mt-5">Premium avantajlarını gör →</a>
            </div>

            <div>
                <h2 class="font-serif text-base font-semibold text-slate-900 mb-3">Ödeme Geçmişi</h2>
                @if ($subscriptions->isEmpty())
                    <div class="card p-8 text-center text-sm text-slate-400">Henüz bir abonelik ödemeniz yok.</div>
                @else
                    <div class="card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                                        <th class="px-5 py-3 font-medium">Tarih</th>
                                        <th class="px-5 py-3 font-medium">Plan</th>
                                        <th class="px-5 py-3 font-medium">Dönem</th>
                                        <th class="px-5 py-3 font-medium text-right">Tutar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($subscriptions as $subscription)
                                        <tr>
                                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $subscription->created_at->format('d.m.Y') }}</td>
                                            <td class="px-5 py-2.5 text-slate-900 whitespace-nowrap">{{ $subscription->plan->label() }}</td>
                                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $subscription->starts_at->format('d.m.Y') }} – {{ $subscription->ends_at->format('d.m.Y') }}</td>
                                            <td class="px-5 py-2.5 text-slate-900 text-right whitespace-nowrap">{{ number_format($subscription->amount, 2, ',', '.') }} TL</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <x-premium-plan-card />
    </div>
@endsection
