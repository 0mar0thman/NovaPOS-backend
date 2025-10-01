<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceVersion;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'include' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
            'sort' => 'nullable|in:latest,oldest',
            'payment_status' => 'nullable|in:fully_paid,partially_paid,unpaid',
            'date_filter' => 'nullable|in:today,week,month',
            'specific_date' => 'nullable|date',
            'specific_week' => 'nullable|string',
            'specific_month' => 'nullable|string',
            'cashier_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات غير صالحة',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = PurchaseInvoice::with([
            'items.product.category' => function($query) {
                $query->select('id', 'name', 'color');
            },
            'creator' => function($query) {
                $query->withTrashed()->select('id', 'name', 'deleted_at');
            },
            'updater' => function($query) {
                $query->withTrashed()->select('id', 'name', 'deleted_at');
            },
            'supplier' => function($query) {
                $query->select('id', 'name', 'phone');
            },
            'cashier' => function($query) {
                $query->withTrashed()->select('id', 'name', 'deleted_at');
            }
        ]);

        // البحث
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // حالة الدفع
        if ($payment_status = $request->input('payment_status')) {
            switch ($payment_status) {
                case 'fully_paid':
                    $query->whereColumn('amount_paid', '>=', 'total_amount');
                    break;
                case 'partially_paid':
                    $query->where('amount_paid', '>', 0)
                        ->whereColumn('amount_paid', '<', 'total_amount');
                    break;
                case 'unpaid':
                    $query->where('amount_paid', 0);
                    break;
            }
        }

        // التصفية حسب التاريخ
        if ($date_filter = $request->input('date_filter')) {
            switch ($date_filter) {
                case 'today':
                    if ($specific_date = $request->input('specific_date')) {
                        $query->whereDate('date', $specific_date);
                    } else {
                        $query->whereDate('date', today());
                    }
                    break;

                case 'week':
                    if ($specific_week = $request->input('specific_week')) {
                        [$start, $end] = explode('_', $specific_week);
                        $query->whereBetween('date', [$start, $end]);
                    } else {
                        $query->whereBetween('date', [
                            now()->startOfWeek(),
                            now()->endOfWeek()
                        ]);
                    }
                    break;

                case 'month':
                    if ($specific_month = $request->input('specific_month')) {
                        $query->whereYear('date', substr($specific_month, 0, 4))
                            ->whereMonth('date', substr($specific_month, 5, 2));
                    } else {
                        $query->whereYear('date', now()->year)
                            ->whereMonth('date', now()->month);
                    }
                    break;
            }
        }

        // الترتيب
        $sort = $request->input('sort', 'latest');
        $query->orderBy('created_at', $sort === 'latest' ? 'desc' : 'asc');

        $perPage = $request->input('per_page', 10);
        $invoices = $query->paginate($perPage);

        // إضافة الحقول المحسوبة مع المنطق الجديد
        $invoices->getCollection()->transform(function ($invoice) {
            // المنطق لاسم الكاشير
            $cashierName = $invoice->cashier_name ?? ($invoice->cashier ? $invoice->cashier->name : 'مستخدم محذوف');
            $isCashierDeleted = $invoice->cashier ? $invoice->cashier->deleted_at !== null : ($invoice->cashier_name ? true : false);
            $invoice->cashier_display_name = $isCashierDeleted ? $cashierName . ' (محذوف)' : $cashierName;

            // المنطق لاسم المستخدم
            $userName = $invoice->user_name ?? ($invoice->creator ? $invoice->creator->name : 'مستخدم محذوف');
            $isCreatorDeleted = $invoice->creator ? $invoice->creator->deleted_at !== null : ($invoice->user_name ? true : false);
            $invoice->user_display_name = $isCreatorDeleted ? $userName . ' (محذوف)' : $userName;

            return $invoice;
        });

        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:purchase_invoices',
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.number_of_units' => 'required|numeric|min:1',
            'items.*.amount_paid' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $totalAmount = collect($validated['items'])->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'] * $item['number_of_units'];
                });

                $amountPaid = collect($validated['items'])->sum('amount_paid');

                if ($amountPaid > $totalAmount) {
                    return response()->json([
                        'message' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الفاتورة'
                    ], 422);
                }

                $user = auth()->user();

                $invoiceData = [
                    'invoice_number' => $validated['invoice_number'],
                    'date' => $validated['date'],
                    'supplier_id' => $validated['supplier_id'],
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $user->id,
                    'updated_by' => $user->id,
                    'cashier_id' => $user->id,
                    'cashier_name' => $user->name,
                    'user_name' => $user->name,
                ];

                $invoice = PurchaseInvoice::create($invoiceData);

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $totalPrice = $item['quantity'] * $item['unit_price'] * $item['number_of_units'];

                    if ($item['amount_paid'] > $totalPrice) {
                        throw new \Exception('المبلغ المدفوع للبند يتجاوز إجمالي البند');
                    }

                    $invoice->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'number_of_units' => $item['number_of_units'],
                        'amount_paid' => $item['amount_paid'],
                        'total_price' => $totalPrice,
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);

                    // تحديث المخزون
                    $product->increment('stock', $item['quantity'] * $item['number_of_units']);
                }

                $this->saveInvoiceVersion($invoice, 'initial');

                // تحميل العلاقات للاستجابة
                $invoice->load([
                    'items.product.category',
                    'creator' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'updater' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'supplier' => fn($q) => $q->select('id', 'name', 'phone'),
                    'cashier' => fn($q) => $q->withTrashed()->select('id', 'name')
                ]);

                return response()->json([
                    'message' => 'تم إنشاء فاتورة الشراء بنجاح',
                    'data' => $invoice
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Error creating purchase invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load([
            'items.product.category',
            'creator' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at'),
            'updater' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at'),
            'supplier' => fn($q) => $q->select('id', 'name', 'phone'),
            'cashier' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at'),
            'versions.creator',
            'versions.updater'
        ]);

        // إضافة الحقول المحسوبة مع المنطق الجديد
        $cashierName = $purchaseInvoice->cashier_name ?? ($purchaseInvoice->cashier ? $purchaseInvoice->cashier->name : 'مستخدم محذوف');
        $isCashierDeleted = $purchaseInvoice->cashier ? $purchaseInvoice->cashier->deleted_at !== null : ($purchaseInvoice->cashier_name ? true : false);
        $purchaseInvoice->cashier_display_name = $isCashierDeleted ? $cashierName . ' (محذوف)' : $cashierName;

        $userName = $purchaseInvoice->user_name ?? ($purchaseInvoice->creator ? $purchaseInvoice->creator->name : 'مستخدم محذوف');
        $isCreatorDeleted = $purchaseInvoice->creator ? $purchaseInvoice->creator->deleted_at !== null : ($purchaseInvoice->user_name ? true : false);
        $purchaseInvoice->user_display_name = $isCreatorDeleted ? $userName . ' (محذوف)' : $userName;

        return response()->json(['data' => $purchaseInvoice]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:purchase_invoices,invoice_number,' . $purchaseInvoice->id,
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.number_of_units' => 'required|numeric|min:1',
            'items.*.amount_paid' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date|after_or_equal:today',
        ]);

        try {
            return DB::transaction(function () use ($validated, $purchaseInvoice) {
                $totalAmount = collect($validated['items'])->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'] * $item['number_of_units'];
                });

                $amountPaid = $validated['amount_paid'] ?? collect($validated['items'])->sum('amount_paid');

                if ($amountPaid > $totalAmount) {
                    return response()->json([
                        'message' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الفاتورة'
                    ], 422);
                }

                $user = auth()->user();

                $updateData = [
                    'invoice_number' => $validated['invoice_number'],
                    'date' => $validated['date'],
                    'supplier_id' => $validated['supplier_id'],
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => $user->id,
                    'cashier_name' => $purchaseInvoice->cashier_name ?? $user->name,
                    'user_name' => $purchaseInvoice->user_name ?? $user->name,
                ];

                $this->saveInvoiceVersion($purchaseInvoice, 'update');

                $purchaseInvoice->update($updateData);

                $existingItems = $purchaseInvoice->items->keyBy('product_id');

                foreach ($validated['items'] as $item) {
                    $totalPrice = $item['quantity'] * $item['unit_price'] * $item['number_of_units'];

                    if (($item['amount_paid'] ?? 0) > $totalPrice) {
                        throw new \Exception('المبلغ المدفوع للبند يتجاوز إجمالي البند');
                    }

                    $oldStock = isset($existingItems[$item['product_id']])
                        ? $existingItems[$item['product_id']]->quantity * $existingItems[$item['product_id']]->number_of_units
                        : 0;

                    $newStock = $item['quantity'] * $item['number_of_units'];
                    $stockDifference = $newStock - $oldStock;

                    if (isset($existingItems[$item['product_id']])) {
                        $existingItems[$item['product_id']]->update([
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'number_of_units' => $item['number_of_units'],
                            'amount_paid' => $item['amount_paid'] ?? 0,
                            'total_price' => $totalPrice,
                            'expiry_date' => $item['expiry_date'] ?? null,
                        ]);
                        unset($existingItems[$item['product_id']]);
                    } else {
                        $purchaseInvoice->items()->create([
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'number_of_units' => $item['number_of_units'],
                            'amount_paid' => $item['amount_paid'] ?? 0,
                            'total_price' => $totalPrice,
                            'expiry_date' => $item['expiry_date'] ?? null,
                        ]);
                    }

                    // تحديث المخزون
                    if ($stockDifference != 0) {
                        Product::where('id', $item['product_id'])
                            ->increment('stock', $stockDifference);
                    }
                }

                // حذف العناصر المتبقية
                foreach ($existingItems as $item) {
                    Product::where('id', $item->product_id)
                        ->decrement('stock', $item->quantity * $item->number_of_units);
                    $item->delete();
                }

                $purchaseInvoice->load([
                    'items.product.category',
                    'creator' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'updater' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'supplier' => fn($q) => $q->select('id', 'name', 'phone'),
                    'cashier' => fn($q) => $q->withTrashed()->select('id', 'name')
                ]);

                return response()->json([
                    'message' => 'تم تحديث الفاتورة بنجاح',
                    'data' => $purchaseInvoice
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error updating purchase invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        try {
            DB::transaction(function () use ($purchaseInvoice) {
                $this->saveInvoiceVersion($purchaseInvoice, 'delete', true);

                // استرجاع الكميات من المخزون
                foreach ($purchaseInvoice->items as $item) {
                    Product::where('id', $item->product_id)
                        ->decrement('stock', $item->quantity * $item->number_of_units);
                }

                $purchaseInvoice->items()->delete();
                $purchaseInvoice->delete();
            });

            return response()->json([
                'message' => 'تم حذف الفاتورة بنجاح'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting purchase invoice: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    public function versions(PurchaseInvoice $purchaseInvoice)
    {
        try {
            $versions = PurchaseInvoiceVersion::where('purchase_invoice_id', $purchaseInvoice->id)
                ->with([
                    'creator' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at'),
                    'updater' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at'),
                    'supplier' => fn($q) => $q->select('id', 'name', 'phone'),
                    'cashier' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at')
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($version) {
                    $items = json_decode($version->items, true) ?? [];

                    $processedItems = array_map(function ($item) {
                        return [
                            'product_id' => $item['product_id'] ?? null,
                            'quantity' => $item['quantity'] ?? 0,
                            'unit_price' => $item['unit_price'] ?? 0,
                            'number_of_units' => $item['number_of_units'] ?? 1,
                            'amount_paid' => $item['amount_paid'] ?? 0,
                            'total_price' => $item['total_price'] ?? 0,
                            'expiry_date' => $item['expiry_date'] ?? null,
                            'product' => $item['product'] ?? [
                                    'id' => $item['product_id'] ?? null,
                                    'name' => 'غير محدد',
                                    'category' => null
                                ]
                        ];
                    }, $items);

                    $cashierName = $version->cashier_name ?? ($version->cashier ? $version->cashier->name : 'مستخدم محذوف');
                    $isCashierDeleted = $version->cashier ? $version->cashier->deleted_at !== null : ($version->cashier_name ? true : false);
                    $version->cashier_display_name = $isCashierDeleted ? $cashierName . ' (محذوف)' : $cashierName;

                    $userName = $version->user_name ?? ($version->creator ? $version->creator->name : 'مستخدم محذوف');
                    $isCreatorDeleted = $version->creator ? $version->creator->deleted_at !== null : ($version->user_name ? true : false);
                    $version->user_display_name = $isCreatorDeleted ? $userName . ' (محذوف)' : $userName;

                    return [
                        'id' => $version->id,
                        'purchase_invoice_id' => $version->purchase_invoice_id,
                        'invoice_number' => $version->invoice_number,
                        'date' => $version->date,
                        'supplier_id' => $version->supplier_id,
                        'supplier_name' => $version->supplier->name ?? null,
                        'supplier_phone' => $version->supplier->phone ?? null,
                        'total_amount' => $version->total_amount,
                        'amount_paid' => $version->amount_paid,
                        'notes' => $version->notes,
                        'items' => $processedItems,
                        'creator' => $version->creator ? [
                            'id' => $version->creator->id,
                            'name' => $version->creator->name,
                        ] : null,
                        'updater' => $version->updater ? [
                            'id' => $version->updater->id,
                            'name' => $version->updater->name,
                        ] : null,
                        'cashier' => $version->cashier ? [
                            'id' => $version->cashier->id,
                            'name' => $version->cashier->name,
                        ] : null,
                        'cashier_name' => $version->cashier_name,
                        'user_name' => $version->user_name,
                        'is_cashier_deleted' => $isCashierDeleted,
                        'is_creator_deleted' => $isCreatorDeleted,
                        'created_at' => $version->created_at,
                        'version_type' => $version->version_type,
                        'is_deleted' => $version->is_deleted ?? false,
                    ];
                });

            return response()->json($versions);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice versions: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب سجل التعديلات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePayment(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($validated, $purchaseInvoice) {
                $total = $purchaseInvoice->total_amount;

                if ($validated['amount_paid'] > $total) {
                    return response()->json([
                        'message' => 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الفاتورة'
                    ], 422);
                }

                $this->saveInvoiceVersion($purchaseInvoice, 'payment_update');

                $purchaseInvoice->update([
                    'amount_paid' => $validated['amount_paid'],
                    'updated_by' => auth()->id(),
                ]);

                $purchaseInvoice->load([
                    'items.product.category',
                    'creator' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'updater' => fn($q) => $q->withTrashed()->select('id', 'name'),
                    'supplier' => fn($q) => $q->select('id', 'name', 'phone'),
                    'cashier' => fn($q) => $q->withTrashed()->select('id', 'name')
                ]);

                return response()->json([
                    'message' => 'تم تحديث المبلغ المدفوع بنجاح',
                    'data' => $purchaseInvoice
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error updating payment: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث المبلغ المدفوع: ' . $e->getMessage()
            ], 500);
        }
    }

    public function lastInvoiceNumber()
    {
        try {
            $lastInvoice = PurchaseInvoice::orderBy('id', 'desc')->first();

            if (!$lastInvoice) {
                return response()->json([
                    'success' => true,
                    'next_invoice_number' => 'PINV-001'
                ]);
            }

            $lastNumber = $lastInvoice->invoice_number;
            preg_match('/PINV-(\d+)/', $lastNumber, $matches);
            $lastNumberInt = isset($matches[1]) ? (int) $matches[1] : 0;

            $nextNumberInt = $lastNumberInt + 1;
            $nextNumber = 'PINV-' . str_pad($nextNumberInt, 7, '0', STR_PAD_LEFT);

            while (PurchaseInvoice::where('invoice_number', $nextNumber)->exists()) {
                $nextNumberInt++;
                $nextNumber = 'PINV-' . str_pad($nextNumberInt, 7, '0', STR_PAD_LEFT);
            }

            return response()->json([
                'success' => true,
                'next_invoice_number' => $nextNumber
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching last invoice number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب رقم الفاتورة الأخير: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function saveInvoiceVersion(PurchaseInvoice $invoice, string $versionType, bool $isDeleted = false)
    {
        $user = auth()->user();

        return PurchaseInvoiceVersion::create([
            'purchase_invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'date' => $invoice->date,
            'supplier_id' => $invoice->supplier_id,
            'total_amount' => $invoice->total_amount,
            'amount_paid' => $invoice->amount_paid,
            'notes' => $invoice->notes,
            'user_id' => $invoice->user_id,
            'updated_by' => $invoice->updated_by,
            'cashier_id' => $invoice->cashier_id,
            'cashier_name' => $invoice->cashier_name,
            'user_name' => $invoice->user_name,
            'items' => json_encode($invoice->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'number_of_units' => $item->number_of_units,
                    'amount_paid' => $item->amount_paid,
                    'total_price' => $item->total_price,
                    'expiry_date' => $item->expiry_date,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'category' => $item->product->category ? [
                            'id' => $item->product->category->id,
                            'name' => $item->product->category->name,
                            'color' => $item->product->category->color,
                        ] : null,
                    ],
                ];
            })->toArray()),
            'created_at' => now(),
            'updated_by' => $user->id,
            'version_type' => $versionType,
            'is_deleted' => $isDeleted,
        ]);
    }
}
