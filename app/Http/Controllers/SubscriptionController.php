<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Enums\SubscriptionPlan;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Premium abonelik (Faz C, 2026-09-27 revizesi): herkese açık Abonelik sayfası (mockup:
 * 7-)Abonelik Sayfası.png), girişli kullanıcının Aboneliğim sayfası ve mock satın alma.
 */
class SubscriptionController extends Controller
{
    public function index(): View
    {
        return view('subscriptions.index', [
            'discountPercent' => PlatformSettings::get('premium_discount_percent'),
            'quotas' => collect(NoteType::cases())->mapWithKeys(fn (NoteType $type) => [$type->label() => PlatformSettings::noteQuota($type)]),
        ]);
    }

    /** Aboneliğim — üyelik durumu, ödeme geçmişi, ücretsiz hesapta kota kullanımı. */
    public function mine(Request $request): View
    {
        $user = $request->user();

        return view('panel.aboneligim', [
            'subscriptions' => $user->subscriptions()->latest()->get(),
            'quotaUsage' => collect(NoteType::cases())->map(fn (NoteType $type) => (object) [
                'label' => $type->label(),
                'used' => $user->notes()->where('type', $type)->count(),
                'quota' => PlatformSettings::noteQuota($type),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
        ]);

        $user = $request->user();

        // Süper Admin'in elle verdiği süresiz premium (premium_until boş) satın alımla
        // süreli bir üyeliğe dönüşmesin.
        if ($user->isPremium() && $user->premium_until === null) {
            return redirect()->route('panel.aboneligim')->with('status', 'Süresiz premium üyesiniz, ayrıca abonelik almanıza gerek yok.');
        }

        $subscription = $user->subscribe(SubscriptionPlan::from($data['plan']));

        return redirect()->route('panel.aboneligim')->with(
            'status',
            "Premium üyeliğiniz {$subscription->ends_at->translatedFormat('j F Y')} tarihine kadar aktif."
        );
    }
}
