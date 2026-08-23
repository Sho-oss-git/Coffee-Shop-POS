<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Category::query()->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
        ]);

        $category = Category::create($validated);

        return back()->with([
            'success' => 'Category added successfully.',
            'created_category' => $category->name,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        $oldName = $category->name;

        DB::transaction(function () use ($category, $validated, $oldName): void {
            $category->update($validated);
            Product::where('category', $oldName)->update(['category' => $validated['name']]);
        });

        return back()->with([
            'success' => 'Category updated successfully.',
            'renamed_category' => ['old' => $oldName, 'new' => $validated['name']],
        ]);
    }

    public function destroy(Category $category): RedirectResponse
    {
        if (Product::where('category', $category->name)->exists()) {
            return back()->with('error', 'Cannot delete a category that is assigned to one or more products.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }
}