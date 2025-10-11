<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesInvoice::with([
            'items.product',
            'creator' => function ($q) {
                $q->withTrashed(); // تحميل المستخدمين المحذوفين
            },
            'cashier' => function ($q) {
                $q->withTrashed(); // تحميل المستخدمين المحذوفين
            }
        ])
            ->when($request->date_from, fn($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('date', '<=', $request->date_to))
            ->when($request->customer, fn($q) => $q->where('customer_name', 'LIKE', "%{$request->customer}%"))
            ->when($request->phone, fn($q) => $q->where('phone', 'LIKE', "%{$request->phone}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when(
                $request->sort_by && $request->sort_order,
                fn($q) => $q->orderBy($request->sort_by, $request->sort_order)
            );

        $invoices = $request->per_page ? $query->paginate($request->per_page) : $query->get();

        // استخدام الأسماء المخزنة أو أسماء المستخدمين
        $invoices->each(function ($invoice) {
            $invoice->items->each(function ($item) {
                $item->product_name = $item->product ? $item->product->name : 'منتج غير معروف';
            });

            // استخدام الاسم المخزن أو اسم المستخدم
            $invoice->user_name = $invoice->user_name ?? ($invoice->creator ? $invoice->creator->name : 'مستخدم محذوف');
            $invoice->cashier_name = $invoice->cashier_name ?? ($invoice->cashier ? $invoice->cashier->name : 'مستخدم محذوف');
        });

        return $invoices;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:sales_invoices',
            'date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001', // ✅ تم التعديل هنا
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'user_name' => 'nullable|string|max:255',
            'cashier_name' => 'nullable|string|max:255',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $paidAmount = $validated['paid_amount'] ?? 0;
                $totalAmount = collect($validated['items'])->sum(fn($item) => $item['total_price']);
                $status = $this->determineStatus($totalAmount, $paidAmount);

                $user = auth()->user();

                $invoice = SalesInvoice::create([
                    'invoice_number' => $validated['invoice_number'],
                    'date' => $validated['date'],
                    'customer_id' => $validated['customer_id'] ?? null,
                    'customer_name' => $validated['customer_name'],
                    'phone' => $validated['phone'] ?? null,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $user->id,
                    'cashier_id' => $user->id,
                    'user_name' => $validated['user_name'] ?? $user->name,
                    'cashier_name' => $validated['cashier_name'] ?? $user->name,
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $invoice->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'], // 👈 يقبل ديسمال دلوقتي
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);
                    $product->decrement('stock', $item['quantity']);
                }

                if ($validated['customer_id']) {
                    $customer = Customer::find($validated['customer_id']);
                    $customer->increment('purchases_count');
                    $customer->increment('total_purchases', $totalAmount);
                    $customer->update(['last_purchase_date' => now()]);
                }

                $invoice->load(['items.product', 'creator']);
                if ($validated['customer_id']) {
                    $invoice->load('customer');
                }

                return response()->json($invoice, 201);
            });
        } catch (\Exception $e) {
            Log::error('فشل إنشاء الفاتورة: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'فشل إنشاء الفاتورة: ' . $e->getMessage(),
                'error' => get_class($e),
            ], 500);
        }
    }


    public function show(SalesInvoice $salesInvoice)
    {
        // تحميل العلاقات مع المستخدمين المحذوفين
        $salesInvoice->load([
            'items.product' => function ($query) {
                $query->select('id', 'name', 'barcode', 'category_id');
            },
            'items' => function ($query) {
                $query->select('id', 'sales_invoice_id', 'product_id', 'quantity', 'unit_price', 'total_price');
            },
            'creator' => function ($query) {
                $query->withTrashed()->select('id', 'name');
            },
            'cashier' => function ($query) {
                $query->withTrashed()->select('id', 'name');
            }
        ]);

        // إضافة product_name لكل عنصر
        $salesInvoice->items->each(function ($item) {
            $item->product_name = $item->product ? $item->product->name : 'منتج غير معروف';
        });

        // استخدام الاسم المخزن أو اسم المستخدم
        $salesInvoice->user_name = $salesInvoice->user_name ?? ($salesInvoice->creator ? $salesInvoice->creator->name : 'مستخدم محذوف');
        $salesInvoice->cashier_name = $salesInvoice->cashier_name ?? ($salesInvoice->cashier ? $salesInvoice->cashier->name : 'مستخدم محذوف');

        // تسجيل البيانات المُرجعة
        Log::info('بيانات الفاتورة للطباعة:', $salesInvoice->toArray());

        return response()->json(['data' => $salesInvoice]);
    }

    public function updatePayment(Request $request, SalesInvoice $salesInvoice)
    {
        $validated = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        $newPaidAmount = $salesInvoice->paid_amount + ($validated['paid_amount'] ?? 0);
        $newStatus = $this->determineStatus($salesInvoice->total_amount, $newPaidAmount);

        $notes = trim(($salesInvoice->notes ?? '') . "\n" . ($validated['notes'] ?? ''));

        $salesInvoice->update([
            'paid_amount' => $newPaidAmount,
            'notes' => $notes ?: null,
            'status' => $newStatus,
            'phone' => $validated['phone'] ?? $salesInvoice->phone,
        ]);

        return response()->json([
            'message' => 'تم تحديث الفاتورة بنجاح',
            'status' => $newStatus,
        ]);
    }

    public function destroy(SalesInvoice $salesInvoice)
    {
        DB::transaction(function () use ($salesInvoice) {
            foreach ($salesInvoice->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }

            $salesInvoice->items()->delete();
            $salesInvoice->delete();
        });

        return response()->noContent();
    }

    protected function determineStatus($totalAmount, $paidAmount)
    {
        if ($paidAmount >= $totalAmount) {
            return 'مدفوعة';
        }
        if ($paidAmount > 0) {
            return 'جزئي';
        }
        return 'غير مدفوعة';
    }
}
