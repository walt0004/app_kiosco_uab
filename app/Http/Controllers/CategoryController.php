<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use App\Support\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTable::of(Category::query()->withCount('products'), $request)
            ->searchable(['name'])
            ->orderable([0 => 'name', 1 => 'products_count', 2 => 'is_active'])
            ->defaultOrder('name')
            ->response(fn (Category $category) => [
                'name' => e($category->name),
                'products_count' => (int) $category->products_count,
                'status' => view('components.status-badge', ['active' => $category->is_active])->render(),
                'actions' => view('categories._actions', ['category' => $category])->render(),
            ]);
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', "Categoría «{$category->name}» creada correctamente.");
    }

    public function edit(Category $category): View
    {
        return view('categories.form', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', $category->is_active
            ? "«{$category->name}» ahora está activa."
            : "«{$category->name}» fue desactivada: ya no se puede elegir en productos nuevos.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', "No se puede eliminar «{$category->name}» porque tiene productos. Desactívala o mueve sus productos a otra categoría.");
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Categoría eliminada.');
    }
}
