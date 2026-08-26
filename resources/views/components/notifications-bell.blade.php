@php
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $latestNotifications = auth()->user()->notifications()->latest()->take(8)->get();
@endphp

<div class="relative inline-flex items-center" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" title="Bildirimler" @click="open = !open" class="relative inline-flex items-center text-slate-500 hover:text-slate-900 transition-colors">
        <x-heroicon-o-bell class="w-5 h-5" />
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-brand-500"></span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right
        class="absolute right-0 top-full mt-3 w-80 card divide-y divide-slate-100 z-30 max-h-96 overflow-y-auto">
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-sm font-medium text-slate-900">Bildirimler</span>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('panel.bildirimler.tumunu-oku') }}">
                    @csrf
                    <button type="submit" class="text-xs text-brand-600 hover:text-brand-700">Tümünü okundu işaretle</button>
                </form>
            @endif
        </div>

        @forelse ($latestNotifications as $notification)
            <form method="POST" action="{{ route('panel.bildirimler.oku', $notification->id) }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors {{ $notification->read_at ? '' : 'bg-brand-50/40' }}">
                    <div class="flex items-start gap-2">
                        @unless ($notification->read_at)
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        @endunless
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-900">{{ $notification->data['title'] }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ $notification->data['body'] }}</div>
                            <div class="text-xs text-slate-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                </button>
            </form>
        @empty
            <div class="px-4 py-8 text-center text-sm text-slate-400">Henüz bildiriminiz yok.</div>
        @endforelse
    </div>
</div>
