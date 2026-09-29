<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Süper Admin'in Kullanıcı/Rol yönetimi (liste/oluştur/düzenle/sil — Faz 5, bkz.
 * UI_RESTYLE_NOTES.md). Sidebar'daki "Kullanıcılar"/"Yazarlar"/"Dergi
 * Editörleri" hepsi aynı listeye, sadece ?rol= filtresiyle geliyor.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q'), '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('rol'), fn ($query) => $query->role($request->string('rol')->toString()))
            ->with('roles')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('panel.admin.kullanicilar.index', [
            'users' => $users,
            'q' => $request->string('q')->toString(),
            'rol' => $request->string('rol')->toString(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    /**
     * Roller ve Yetkiler — gerçek bir izin matrisi düzenleyicisi değil (Spatie
     * burada tekil izin değil sadece 4 sabit rol üzerinden çalışıyor, dinamik
     * rol oluşturma/silme altyapısı yok) ama sahte de değil: her rolün gerçek
     * kullanıcı sayısı ve policy'lerden gelen gerçek yetki özeti burada.
     */
    public function roles(): View
    {
        $this->authorize('viewAny', User::class);

        $roleSummaries = [
            'super_admin' => [
                'label' => 'Süper Admin',
                'description' => 'Tüm içerikleri (Kitap/Dergi Sayısı/Makale) onaylar, reddeder, yayınlar. Kitap/Dergi/Kategori/Kullanıcı yönetimine tam erişimi var.',
            ],
            'dergi_editoru' => [
                'label' => 'Dergi Editörü',
                'description' => 'Yazarın tüm yetkilerine (Yayın Yönetimi) ek olarak: kendi dergi sayılarını oluşturur/düzenler, sayıyı onaya gönderir; kendi sayısındaki makaleleri inceleyip Süper Admin onayına havale eder. Kendi dergisine ayrıca atanmadan makale yazabilir.',
            ],
            'yazar' => [
                'label' => 'Yazar',
                'description' => 'Kendi kitap/makale taslaklarını oluşturur, düzenler, onaya gönderir; onaylanan/reddedilen içeriğini takip eder.',
            ],
            'okur' => [
                'label' => 'Okur',
                'description' => 'Kitap satın alır, favorilere ekler, okuma listesi/defter tutar, not/alıntı kaydeder.',
            ],
        ];

        $counts = User::query()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name, count(*) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'name');

        foreach ($roleSummaries as $name => &$summary) {
            $summary['count'] = $counts->get($name, 0);
        }

        return view('panel.admin.kullanicilar.roller', ['roleSummaries' => $roleSummaries]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('panel.admin.kullanicilar.form', [
            'user' => null,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate($this->validationRules(create: true));

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_premium' => $request->boolean('is_premium'),
            'premium_until' => $data['premium_until'] ?? null,
            'quota_overrides' => $this->quotaOverrides($data),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('panel.adminpanel.kullanicilar.duzenle', $user)->with('status', 'Kullanıcı oluşturuldu.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return view('panel.admin.kullanicilar.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate($this->validationRules(create: false, user: $user));

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_premium' => $request->boolean('is_premium'),
            'premium_until' => $data['premium_until'] ?? null,
            'quota_overrides' => $this->quotaOverrides($data),
            ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('panel.adminpanel.kullanicilar.duzenle', $user)->with('status', 'Kullanıcı güncellendi.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('panel.adminpanel.kullanicilar.index')->with('status', 'Kullanıcı silindi.');
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function validationRules(bool $create, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'password' => $create ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'is_premium' => ['nullable', 'boolean'],
            'premium_until' => ['nullable', 'date'],
            'quota' => ['nullable', 'array'],
            'quota.defter' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quota.not' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quota.alinti' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quota.defter_words' => ['nullable', 'integer', 'min:100', 'max:1000000'],
        ];
    }

    /**
     * Hesaba özel çalışma alanı sınırları — boş bırakılan genel ayarı (Premium Sistemi) kullanır;
     * hepsi boşsa alan tamamen temizlenir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int>|null
     */
    private function quotaOverrides(array $data): ?array
    {
        $overrides = collect(User::QUOTA_OVERRIDES)
            ->keys()
            ->mapWithKeys(fn (string $key) => [$key => $data['quota'][$key] ?? null])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->all();

        return $overrides ?: null;
    }
}
