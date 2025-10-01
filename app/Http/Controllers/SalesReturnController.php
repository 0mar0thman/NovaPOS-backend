<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|string|in:return_number,date,total_amount',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = SalesReturn::with(['invoice', 'items.product', 'user'])
            ->when($request->date_from, fn($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('date', '<=', $request->date_to))
            ->when($request->has('invoice_number'), function ($q) use ($request) {
                $q->whereHas('invoice', function ($query) use ($request) {
                    $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
                });
            })
            ->when(
                $request->sort_by && $request->sort_order,
                fn($q) => $q->orderBy($request->sort_by, $request->sort_order),
                fn($q) => $q->latest()
            );

        return $query->paginate($request->per_page ?? 15);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_invoice_id' => 'required|exists:sales_invoices,id',
            'date' => 'required|date|before_or_equal:today',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sales_invoice_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $invoice = SalesInvoice::findOrFail($request->sale_invoice_id);
                $saleItemIds = collect($request->items)->pluck('sale_item_id')->toArray();
                $saleItems = $invoice->items()->with('product')->whereIn('id', $saleItemIds)->get()->keyBy('id');

                $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $return = SalesReturn::create([
                    'return_number' => $returnNumber,
                    'sales_invoice_id' => $request->sale_invoice_id,
                    'date' => $request->date,
                    'total_amount' => 0,
                    'notes' => $request->notes,
                    'user_id' => auth()->id(),
                ]);

                $totalAmount = 0;

                foreach ($request->items as $item) {
                    $saleItem = $saleItems->get($item['sale_item_id']);

                    if (!$saleItem) {
                        throw new \Exception("البند رقم {$item['sale_item_id']} لا ينتمي إلى الفاتورة المحددة.");
                    }

                    $maxReturnable = $saleItem->quantity - ($saleItem->returned_quantity ?? 0);
                    if ($item['quantity'] > $maxReturnable) {
                        throw new \Exception("الكمية المراد استرجاعها ({$item['quantity']}) للمنتج {$saleItem->product->name} تتجاوز الكمية القابلة للاسترجاع ({$maxReturnable})");
                    }

                    $returnItem = $return->items()->create([
                        'sales_invoice_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $saleItem->unit_price,
                        'total_price' => $item['quantity'] * $saleItem->unit_price,
                    ]);

                    $saleItem->increment('returned_quantity', $item['quantity']);
                    $saleItem->product->increment('stock', $item['quantity']);

                    $totalAmount += $returnItem->total_price;
                }

                $return->update(['total_amount' => $totalAmount]);

                // مسح التخزين المؤقت
                cache()->forget('invoices_*');

                return response()->json([
                    'message' => 'تم إنشاء إسترجاع المنتجات بنجاح',
                    'data' => $return->load(['invoice', 'items.product', 'user'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء معالجة الإسترجاع',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(SalesReturn $salesReturn)
    {
        return response()->json([
            'data' => $salesReturn->load([
                'invoice',
                'items.product.category',
                'items.salesInvoiceItem',
                'user'
            ])
        ]);
    }

    public function destroy(SalesReturn $salesReturn)
    {
        try {
            DB::transaction(function () use ($salesReturn) {
                foreach ($salesReturn->items as $item) {
                    if ($item->salesInvoiceItem) {
                        $item->salesInvoiceItem->decrement('returned_quantity', $item->quantity);
                    }

                    if ($item->product) {
                        $item->product->decrement('stock', $item->quantity);
                    }
                }

                $salesReturn->items()->delete();
                $salesReturn->delete();
            });

            // مسح التخزين المؤقت
            cache()->forget('invoices_*');

            return response()->json([
                'message' => 'تم حذف إسترجاع المنتجات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف الإسترجاع',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
