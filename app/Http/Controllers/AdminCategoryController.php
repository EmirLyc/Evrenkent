<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Süper Admin'in Kategoriler yönetimi (liste/oluştur/düzenle/sil — Faz 4, bkz.
 * UI_RESTYLE_NOTES.md; AdminBookController'daki desenle tutarlı, ama kategori
 * sadece iki alanlı olduğu için çok daha basit).
 */
class AdminCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::withCount(['books', 'articles'])
            ->orderBy('name')
            ->paginate(20);

        return view('panel.admin.kategoriler.index', ['categories' => $categories]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('panel.admin.kategoriler.form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validate($this->validationRules());

        Category::create($data);

        return redirect()->route('panel.adminpanel.kategoriler.index')->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('panel.admin.kategoriler.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $request->validate($this->validationRules($category));

        $category->update($data);

        return redirect()->route('panel.adminpanel.kategoriler.index')->with('status', 'Kategori güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()->route('panel.adminpanel.kategoriler.index')->with('status', 'Kategori silindi.');
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function validationRules(?Category $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
        ];
    }
}
