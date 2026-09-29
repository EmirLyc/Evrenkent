<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Süper Admin'in "Premium Sistemi" sayfası (Faz C, 2026-09-27 kararları): aylık/yıllık
 * plan fiyatları, premium indirim oranı ve ücretsiz hesabın çalışma alanı kotaları.
 * Önceden admin sidebar'ında "yakında" placeholder'ıydı.
 */
class AdminPremiumController extends Controller
{
    public function edit(): View
    {
        return view('panel.admin.premium.edit', [
            'settings' => collect(array_keys(PlatformSettings::DEFAULTS))
                ->mapWithKeys(fn (string $key) => [$key => PlatformSettings::get($key)]),
            'activePremiumCount' => User::premium()->count(),
            'last30Count' => Subscription::where('created_at', '>=', now()->subDays(30))->count(),
            'last30Revenue' => Subscription::where('created_at', '>=', now()->subDays(30))->sum('amount'),
            'recentSubscriptions' => Subscription::with('user')->latest()->take(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'premium_monthly_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'premium_yearly_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'premium_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'quota_defter' => ['required', 'integer', 'min:1', 'max:1000'],
            'quota_defter_words' => ['required', 'integer', 'min:100', 'max:1000000'],
            'quota_not' => ['required', 'integer', 'min:1', 'max:1000'],
            'quota_alinti' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        PlatformSettings::set($data);

        return redirect()->route('panel.adminpanel.premium.edit')->with('status', 'Premium ayarları kaydedildi.');
    }
}
