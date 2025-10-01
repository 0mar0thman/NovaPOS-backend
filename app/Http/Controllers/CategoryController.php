<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read-Category')->only(['index', 'show']);
        $this->middleware('permission:create-Category')->only('store');
        $this->middleware('permission:update-Category')->only('update');
    }

    public function index()
    {
        return Category::withCount('products')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        return Category::create($validated);
    }

    public function show(Category $category)
    {
        return $category->load('products');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        $category->update($validated);
        return $category->fresh();
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الفئة لوجود منتجات مرتبطة بها'
            ], 422);
        }

        $category->delete();
        return response()->noContent();
    }
}
