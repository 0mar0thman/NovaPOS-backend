<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceItemController extends Controller
{
    public function index()
    {
        return PurchaseInvoiceItem::with([
            'product.category' => fn($query) => $query->select('id', 'name', 'category_id'),
            'invoice.creator' => fn($query) => $query->select('id', 'name')
        ])->get();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_invoice_id' => 'required|exists:purchase_invoices,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'number_of_units' => 'required|integer|min:1',
            'amount_paid' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $totalPrice = $request->quantity * $request->unit_price * $request->number_of_units;

                if ($request->amount_paid > $totalPrice) {
                    return response()->json(['message' => 'المبلغ المدفوع للبند يتجاوز إجمالي البند'], 422);
                }

                $item = PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $request->purchase_invoice_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'unit_price' => $request->unit_price,
                    'number_of_units' => $request->number_of_units,
                    'amount_paid' => $request->amount_paid,
                    'total_price' => $totalPrice,
                    'expiry_date' => $request->expiry_date,
                ]);

                // تحديث المخزون
                Product::where('id', $request->product_id)
                       ->increment('stock', $request->quantity * $request->number_of_units);

                // تحديث إجمالي الفاتورة
                $invoice = $item->invoice;
                $invoice->update([
                    'total_amount' => $invoice->items->sum('total_price'),
                    'amount_paid' => $invoice->items->sum('amount_paid'),
                    'updated_by' => auth()->id(),
                ]);

                return response()->json($item->load([
                    'product.category' => fn($query) => $query->select('id', 'name', 'category_id'),
                    'invoice.creator' => fn($query) => $query->select('id', 'name')
                ]), 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء البند: ' . $e->getMessage()], 500);
        }
    }

    public function show(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        return response()->json($purchaseInvoiceItem->load([
            'product.category' => fn($query) => $query->select('id', 'name', 'category_id'),
            'invoice.creator' => fn($query) => $query->select('id', 'name')
        ]));
    }

    public function update(Request $request, PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'number_of_units' => 'required|integer|min:1',
            'amount_paid' => 'nullable|numeric|min:0', // تم التعديل من required إلى nullable
            'expiry_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request, $purchaseInvoiceItem) {
                $totalPrice = $request->quantity * $request->unit_price * $request->number_of_units;
                $amountPaid = $request->amount_paid ?? 0; // تمت إضافة التحقق من القيمة الفارغة

                if ($amountPaid > $totalPrice) {
                    return response()->json(['message' => 'المبلغ المدفوع للبند يتجاوز إجمالي البند'], 422);
                }

                $oldTotal = $purchaseInvoiceItem->quantity * $purchaseInvoiceItem->number_of_units;
                $newTotal = $request->quantity * $request->number_of_units;
                $difference = $newTotal - $oldTotal;

                $purchaseInvoiceItem->update([
                    'quantity' => $request->quantity,
                    'unit_price' => $request->unit_price,
                    'number_of_units' => $request->number_of_units,
                    'amount_paid' => $amountPaid,
                    'total_price' => $totalPrice,
                    'expiry_date' => $request->expiry_date ?? $purchaseInvoiceItem->expiry_date,
                ]);

                // تحديث المخزون
                if ($difference != 0) {
                    Product::where('id', $purchaseInvoiceItem->product_id)
                           ->increment('stock', $difference);
                }

                // تحديث إجمالي الفاتورة
                $invoice = $purchaseInvoiceItem->invoice;
                $invoice->update([
                    'total_amount' => $invoice->items->sum('total_price'),
                    'amount_paid' => $invoice->items->sum('amount_paid'),
                    'updated_by' => auth()->id(),
                ]);

                return response()->json($purchaseInvoiceItem->load([
                    'product.category' => fn($query) => $query->select('id', 'name', 'category_id'),
                    'invoice.creator' => fn($query) => $query->select('id', 'name')
                ]));
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ أثناء تحديث البند: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        try {
            DB::transaction(function () use ($purchaseInvoiceItem) {
                // تحديث المخزون
                Product::where('id', $purchaseInvoiceItem->product_id)
                       ->decrement('stock', $purchaseInvoiceItem->quantity * $purchaseInvoiceItem->number_of_units);

                // تحديث إجمالي الفاتورة
                $invoice = $purchaseInvoiceItem->invoice;
                $purchaseInvoiceItem->delete();
                $invoice->update([
                    'total_amount' => $invoice->items->sum('total_price'),
                    'amount_paid' => $invoice->items->sum('amount_paid'),
                    'updated_by' => auth()->id(),
                ]);
            });
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ أثناء حذف البند: ' . $e->getMessage()], 500);
        }
    }
}
