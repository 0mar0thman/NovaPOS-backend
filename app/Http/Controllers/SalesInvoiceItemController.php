<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;

class SalesInvoiceItemController extends Controller
{
    public function index()
    {
        return SalesInvoiceItem::with(['product', 'invoice'])->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'sales_invoice_id' => 'required|exists:sales_invoices,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'الكمية غير متوفرة في المخزون'
            ], 422);
        }

        $totalPrice = $request->quantity * $request->unit_price;

        $item = SalesInvoiceItem::create([
            'sales_invoice_id' => $request->sales_invoice_id,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'unit_price' => $request->unit_price,
            'total_price' => $totalPrice,
        ]);

        // تحديث المخزون
        $product->decrement('stock', $request->quantity);

        // مسح التخزين المؤقت
        cache()->forget('invoices_*');

        return $item->load(['product', 'invoice']);
    }

    public function show(SalesInvoiceItem $salesInvoiceItem)
    {
        return $salesInvoiceItem->load(['product', 'invoice']);
    }

    public function update(Request $request, SalesInvoiceItem $salesInvoiceItem)
    {
        $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'sometimes|numeric|min:0',
        ]);

        $oldQuantity = $salesInvoiceItem->quantity;
        $newQuantity = $request->quantity ?? $oldQuantity;

        $product = Product::findOrFail($salesInvoiceItem->product_id);

        if ($product->stock + $oldQuantity < $newQuantity) {
            return response()->json([
                'message' => 'الكمية غير متوفرة في المخزون'
            ], 422);
        }

        $salesInvoiceItem->update([
            'quantity' => $newQuantity,
            'unit_price' => $request->unit_price ?? $salesInvoiceItem->unit_price,
            'total_price' => $newQuantity * ($request->unit_price ?? $salesInvoiceItem->unit_price),
        ]);

        // تعديل المخزون
        if ($oldQuantity != $newQuantity) {
            $diff = $oldQuantity - $newQuantity;
            $product->increment('stock', $diff);
        }

        // مسح التخزين المؤقت
        cache()->forget('invoices_*');

        return $salesInvoiceItem->load(['product', 'invoice']);
    }

    public function destroy(SalesInvoiceItem $salesInvoiceItem)
    {
        // استعادة المخزون
        Product::where('id', $salesInvoiceItem->product_id)
            ->increment('stock', $salesInvoiceItem->quantity);

        $salesInvoiceItem->delete();

        // مسح التخزين المؤقت
        cache()->forget('invoices_*');

        return response()->noContent();
    }
}
