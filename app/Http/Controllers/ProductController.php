<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')
            ->when($request->search, fn($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
            ->when($request->barcode, fn($q) => $q->where('barcode', $request->barcode))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->min_price, fn($q) => $q->where('sale_price', '>=', $request->min_price))
            ->when($request->max_price, fn($q) => $q->where('sale_price', '<=', $request->max_price))
            ->when($request->standard, fn($q) => $q->where('standard', $request->standard))
            ->when(
                $request->sort_by && $request->sort_order,
                fn($q) => $q->orderBy($request->sort_by, $request->sort_order)
            );

        return $request->per_page ? $query->paginate($request->per_page) : $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0.001',
            'min_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'standard' => 'required|string'
        ]);

        return Product::create($validated);
    }

    public function show(Product $product)
    {
        return $product->load(['category', 'purchaseItems', 'salesItems']);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'barcode' => 'sometimes|string|unique:products,barcode,' . $product->id,
            'category_id' => 'sometimes|exists:categories,id',
            'purchase_price' => 'sometimes|numeric|min:0',
            'sale_price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|numeric|min:0.001',
            'min_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'standard' => 'sometimes|string|max:255',
        ]);

        $product->update($validated);
        return $product->fresh();
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->purchaseItems()->delete();
            $product->salesItems()->delete();
            $product->delete();
        });

        return response()->noContent();
    }

    public function lowStock()
    {
        return Product::whereColumn('stock', '<=', 'min_stock')->get();
    }

    public function findByBarcode(Request $request, $barcode)
    {
        $product = Product::with('category')->where('barcode', $barcode)->first();

        if (!$product) {
            return response()->json([
                'message' => 'لم يتم العثور على منتج بهذا الباركود',
                'barcode' => $barcode
            ], 404);
        }

        return response()->json($product);
    }
}
