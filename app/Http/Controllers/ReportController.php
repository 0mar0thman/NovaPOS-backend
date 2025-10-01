<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * التحقق من صحة المدخلات
     *
     * @param Request $request
     * @param array $rules
     * @return void
     */
    protected function validateRequest(Request $request, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::error('Validation failed for profitLossReport', [
                'errors' => $validator->errors()->all(),
                'input' => $request->all(),
            ]);
            return response()->json(['error' => $validator->errors()->first()], 422);
        }
    }
    /**
     * تقرير إجمالي المبيعات مع تحليل مفصل
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesSummary(Request $request)
    {
        $this->validateRequest($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'group_by' => 'nullable|in:daily,weekly,monthly',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());
        $groupBy = $request->input('group_by', 'daily');
        $userId = $request->input('user_id');

        $query = SalesInvoice::whereBetween('date', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $dateFormat = 'DATE(date)';
        $groupByField = 'day';
        if ($groupBy === 'weekly') {
            $dateFormat = "DATE_FORMAT(date, '%Y-%u')";
            $groupByField = 'week';
        } elseif ($groupBy === 'monthly') {
            $dateFormat = "DATE_FORMAT(date, '%Y-%m')";
            $groupByField = 'month';
        }

        $data = $query->select(
            DB::raw("{$dateFormat} as {$groupByField}"),
            DB::raw('SUM(total_amount) as total_sales'),
            DB::raw('SUM(paid_amount) as total_paid'),
            DB::raw('COUNT(*) as invoices_count'),
            DB::raw('AVG(total_amount) as average_sale'),
            DB::raw('SUM(total_amount - paid_amount) as total_due')
        )
            ->groupBy($groupByField)
            ->orderBy($groupByField)
            ->get();

        $customers = SalesInvoice::whereBetween('date', [$from, $to])
            ->select(
                'customer_name',
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(*) as invoices_count')
            )
            ->groupBy('customer_name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $paymentMethods = SalesInvoice::whereBetween('date', [$from, $to])
            ->select(
                'payment_method',
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as invoices_count')
            )
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'summary' => $data,
            'top_customers' => $customers,
            'payment_methods' => $paymentMethods,
            'from' => $from,
            'to' => $to,
            'group_by' => $groupBy,
        ]);
    }

    /**
     * تقرير المنتجات الأكثر مبيعًا مع تحليل الربحية
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topSellingProducts(Request $request)
    {
        $this->validateRequest($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'category_id' => 'nullable|exists:categories,id',
            'limit' => 'nullable|integer|min:1',
        ]);

        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());
        $categoryId = $request->input('category_id');
        $limit = $request->input('limit', 10);

        $query = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$from, $to]);

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        $products = $query->select(
            'products.id',
            'products.name',
            'products.category_id',
            DB::raw('SUM(sales_invoice_items.quantity) as quantity_sold'),
            DB::raw('SUM(sales_invoice_items.total_price) as total_sales'),
            DB::raw('AVG(sales_invoice_items.unit_price) as average_price'),
            DB::raw('SUM(sales_invoice_items.quantity * products.purchase_price) as total_cost'),
            DB::raw('SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)) as total_profit'),
            DB::raw('IF(SUM(sales_invoice_items.total_price) = 0, 0,
                ROUND((SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)) /
                SUM(sales_invoice_items.total_price) * 100), 2)) as profit_margin')
        )
            ->groupBy('products.id', 'products.name', 'products.category_id')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        $categories = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$from, $to])
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(sales_invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(sales_invoice_items.total_price) as total_sales'),
                DB::raw('SUM(sales_invoice_items.quantity * products.purchase_price) as total_cost'),
                DB::raw('SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)) as total_profit'),
                DB::raw('IF(SUM(sales_invoice_items.total_price) = 0, 0,
                    ROUND((SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)) /
                    SUM(sales_invoice_items.total_price) * 100), 2)) as profit_margin')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sales')
            ->get();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * تقرير المشتريات مع تحليل الموردين
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchaseSummary(Request $request)
    {
        $this->validateRequest($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'supplier' => 'nullable|string|max:255',
        ]);

        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());
        $supplier = $request->input('supplier');

        // الإجمالي حسب الأيام
        $query = PurchaseInvoice::whereBetween('date', [$from, $to]);

        if ($supplier) {
            $query->whereHas('supplier', function ($q) use ($supplier) {
                $q->where('name', 'like', "%{$supplier}%");
            });
        }

        $data = $query->select(
            DB::raw('DATE(date) as day'),
            DB::raw('SUM(total_amount) as total_purchases'),
            DB::raw('SUM(amount_paid) as total_paid'),
            DB::raw('COUNT(*) as invoices_count'),
            DB::raw('SUM(total_amount - amount_paid) as total_due')
        )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // أفضل الموردين
        $suppliers = DB::table('purchase_invoices')
            ->join('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->whereBetween('purchase_invoices.date', [$from, $to])
            ->select(
                'suppliers.id',
                'suppliers.name',
                DB::raw('SUM(purchase_invoices.total_amount) as total_purchases'),
                DB::raw('SUM(purchase_invoices.amount_paid) as total_paid'),
                DB::raw('COUNT(purchase_invoices.id) as invoices_count')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_purchases')
            ->limit(10)
            ->get();

        // أفضل المنتجات
        $products = DB::table('purchase_invoice_items')
            ->join('products', 'purchase_invoice_items.product_id', '=', 'products.id')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereBetween('purchase_invoices.date', [$from, $to])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(purchase_invoice_items.quantity) as quantity_purchased'),
                DB::raw('SUM(purchase_invoice_items.total_price) as total_purchases'),
                DB::raw('AVG(purchase_invoice_items.unit_price) as average_price')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity_purchased')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => $data,
            'top_suppliers' => $suppliers,
            'top_products' => $products,
        ]);
    }


    /**
     * تقرير المخزون مع تحليل البضائع المنتهية الصلاحية
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inventoryReport(Request $request)
    {
        $this->validateRequest($request, [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $categoryId = $request->input('category_id');

        // استعلام المخزون
        $query = Product::with([
            'category' => function ($query) {
                $query->select('id', 'name');
            }
        ]);

        if ($categoryId && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        $inventory = $query->select(
            'id',
            'name',
            'barcode',
            'category_id',
            'stock',
            'min_stock',
            'purchase_price',
            'sale_price'
        )
            ->get()
            ->map(function ($product) {
                return [
                    'id' => (string) $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode ?? '',
                    'category_id' => (string) $product->category_id,
                    'category_name' => $product->category ? $product->category->name : null,
                    'stock' => (int) $product->stock,
                    'min_stock' => (int) $product->min_stock,
                    'purchase_price' => (float) $product->purchase_price,
                    'sale_price' => (float) $product->sale_price,
                    'stock_value' => (float) ($product->stock * $product->purchase_price),
                    'below_min_stock' => (bool) ($product->stock < $product->min_stock),
                ];
            });

        // استعلام المنتجات التي اقترب انتهاء صلاحيتها
        $expiringSoonItems = PurchaseInvoiceItem::with([
            'product' => function ($query) {
                $query->select('id', 'name', 'category_id');
            }
        ])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', $to)
            ->when($categoryId && $categoryId !== 'all', function ($query) use ($categoryId) {
                $query->whereHas('product', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            })
            ->select(
                'product_id',
                'expiry_date',
                DB::raw('SUM(quantity) as quantity')
            )
            ->groupBy('product_id', 'expiry_date')
            ->orderBy('expiry_date')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->product ? $item->product->name : null,
                    'category_name' => $item->product && $item->product->category ? $item->product->category->name : null,
                    'expiry_date' => Carbon::parse($item->expiry_date)->toDateString(),
                    'quantity' => (int) $item->quantity,
                ];
            });

        // استعلام المنتجات التي انتهت صلاحيتها
        $expiredItems = PurchaseInvoiceItem::with([
            'product' => function ($query) {
                $query->select('id', 'name', 'category_id');
            }
        ])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now())
            ->when($categoryId && $categoryId !== 'all', function ($query) use ($categoryId) {
                $query->whereHas('product', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            })
            ->select(
                'product_id',
                'expiry_date',
                DB::raw('SUM(quantity) as quantity')
            )
            ->groupBy('product_id', 'expiry_date')
            ->orderBy('expiry_date')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->product ? $item->product->name : null,
                    'category_name' => $item->product && $item->product->category ? $item->product->category->name : null,
                    'expiry_date' => \Carbon\Carbon::parse($item->expiry_date)->toDateString(),
                    'quantity' => (int) $item->quantity,
                ];
            });

        // استعلام المخزون حسب الفئة
        $inventoryByCategoryQuery = Category::select(
            'categories.id',
            'categories.name',
            DB::raw('COALESCE(SUM(products.stock * products.purchase_price), 0) as total_value'),
            DB::raw('COALESCE(COUNT(products.id), 0) as products_count')
        )
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name');

        if ($categoryId && $categoryId !== 'all') {
            $inventoryByCategoryQuery->where('categories.id', $categoryId);
        }

        $inventoryByCategory = $inventoryByCategoryQuery->get()->map(function ($category) {
            return [
                'id' => (string) $category->id,
                'name' => $category->name,
                'total_value' => (float) $category->total_value,
                'products_count' => (int) $category->products_count,
            ];
        });

        return response()->json([
            'inventory' => $inventory,
            'expiring_soon_items' => $expiringSoonItems,
            'expired_items' => $expiredItems,
            'inventory_by_category' => $inventoryByCategory,
        ]);
    }

    /**
     * تقرير الأرباح والخسائر مع تحليل التكاليف
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function profitLossReport(Request $request)
    {
        $this->validateRequest($request, [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $categoryId = $request->input('category_id');

        // إجمالي المبيعات
        $salesQuery = SalesInvoice::whereBetween('date', [$from, $to]);
        $totalSales = (float) $salesQuery->sum('total_amount');

        // إجمالي المشتريات
        $purchasesQuery = PurchaseInvoice::whereBetween('date', [$from, $to]);
        $totalPurchases = (float) $purchasesQuery->sum('total_amount');

        // تكلفة البضائع المباعة
        $cogsQuery = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$from, $to]);

        if ($categoryId && $categoryId !== 'all') {
            $cogsQuery->where('products.category_id', $categoryId);
        }

        $cogs = (float) $cogsQuery->sum(DB::raw('sales_invoice_items.quantity * products.purchase_price'));

        // الربح الإجمالي
        $grossProfit = (float) ($totalSales - $cogs);

        // الربح حسب الفئة
        $profitByCategoryQuery = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$from, $to]);

        if ($categoryId && $categoryId !== 'all') {
            $profitByCategoryQuery->where('categories.id', $categoryId);
        }

        $profitByCategory = $profitByCategoryQuery
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COALESCE(SUM(sales_invoice_items.total_price), 0) as total_sales'),
                DB::raw('COALESCE(SUM(sales_invoice_items.quantity * products.purchase_price), 0) as total_cost'),
                DB::raw('COALESCE(SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)), 0) as gross_profit'),
                DB::raw('IF(COALESCE(SUM(sales_invoice_items.total_price), 0) = 0, 0,
                    ROUND((COALESCE(SUM(sales_invoice_items.total_price - (sales_invoice_items.quantity * products.purchase_price)), 0) /
                    COALESCE(SUM(sales_invoice_items.total_price), 1)) * 100, 2)) as profit_margin')
            )
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (string) $item->id,
                    'name' => $item->name,
                    'total_sales' => (float) $item->total_sales,
                    'total_cost' => (float) $item->total_cost,
                    'gross_profit' => (float) $item->gross_profit,
                    'profit_margin' => (float) $item->profit_margin,
                ];
            });

        // اتجاه الربح
        $profitTrend = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$from, $to])
            ->select(
                DB::raw("DATE_FORMAT(sales_invoices.date, '%Y-%m') as period"),
                DB::raw('COALESCE(SUM(sales_invoice_items.total_price), 0) as total_sales'),
                DB::raw('COALESCE(SUM(sales_invoice_items.quantity * products.purchase_price), 0) as total_cost')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'total_sales' => (float) $item->total_sales,
                    'total_cost' => (float) $item->total_cost,
                    'gross_profit' => (float) ($item->total_sales - $item->total_cost),
                    'profit_margin' => $item->total_sales > 0
                        ? (float) round((($item->total_sales - $item->total_cost) / $item->total_sales) * 100, 2)
                        : 0.0,
                ];
            });

        return response()->json([
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'profit_by_category' => $profitByCategory,
            'profit_trend' => $profitTrend,
        ]);
    }

    /**
     * إحصائيات لوحة التحكم
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboardStats()
    {
        $today = Carbon::now()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $todaySales = SalesInvoice::whereDate('date', $today)->sum('total_amount');
        $todayPurchases = PurchaseInvoice::whereDate('date', $today)->sum('total_amount');
        $todayInvoices = SalesInvoice::whereDate('date', $today)->count();

        $monthSales = SalesInvoice::whereBetween('date', [$startOfMonth, $endOfMonth])->sum('total_amount');
        $monthPurchases = PurchaseInvoice::whereBetween('date', [$startOfMonth, $endOfMonth])->sum('total_amount');

        $lowStockProducts = Product::whereColumn('stock', '<', 'min_stock')->count();

        $unpaidSalesInvoices = SalesInvoice::whereColumn('total_amount', '>', 'paid_amount')->count();
        $unpaidPurchaseInvoices = PurchaseInvoice::whereColumn('total_amount', '>', 'amount_paid')->count();

        $expiringItems = PurchaseInvoiceItem::whereBetween('expiry_date', [Carbon::now(), Carbon::now()->addDays(30)])
            ->count();

        $topProducts = DB::table('sales_invoice_items')
            ->join('products', 'sales_invoice_items.product_id', '=', 'products.id')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->whereBetween('sales_invoices.date', [$startOfMonth, $endOfMonth])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sales_invoice_items.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        $topCustomers = SalesInvoice::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select(
                'customer_name',
                DB::raw('SUM(total_amount) as total_sales')
            )
            ->groupBy('customer_name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        return response()->json([
            'today_sales' => $todaySales,
            'today_purchases' => $todayPurchases,
            'today_invoices' => $todayInvoices,
            'month_sales' => $monthSales,
            'month_purchases' => $monthPurchases,
            'low_stock_products' => $lowStockProducts,
            'unpaid_sales_invoices' => $unpaidSalesInvoices,
            'unpaid_purchase_invoices' => $unpaidPurchaseInvoices,
            'expiring_items' => $expiringItems,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
        ]);
    }

    /**
     * تقرير تحليلي لأداء الموظفين
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function employeePerformance(Request $request)
    {
        // التحقق من صحة الطلب
        $this->validateRequest($request, [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        // تحديد الفترة الزمنية الافتراضية
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());

        // جلب الموظفين مع أداء المبيعات ككاشير
        $employees = User::withCount([
            'cashierInvoices as total_invoices' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }
        ])
            ->withSum([
                'cashierInvoices as total_sales' => function ($query) use ($from, $to) {
                    $query->whereBetween('date', [$from, $to]);
                }
            ], 'total_amount')
            ->withSum([
                'cashierInvoices as total_paid' => function ($query) use ($from, $to) {
                    $query->whereBetween('date', [$from, $to]);
                }
            ], 'paid_amount')
            ->withAvg([
                'cashierInvoices as average_invoice' => function ($query) use ($from, $to) {
                    $query->whereBetween('date', [$from, $to]);
                }
            ], 'total_amount')
            ->with([
                'cashierInvoices' => function ($query) use ($from, $to) {
                    $query->whereBetween('date', [$from, $to])
                        ->selectRaw('user_id, SUM(total_amount - paid_amount) as total_due')
                        ->groupBy('user_id');
                }
            ])
            ->orderByDesc('total_sales')
            ->get()
            ->map(function ($employee) {
                $employee->total_due = $employee->cashierInvoices->sum('total_due');
                return $employee;
            });

        // جلب الأداء اليومي للكاشير
        $dailyPerformance = SalesInvoice::select(
            'cashier_id as user_id',
            DB::raw('DATE(date) as day'),
            DB::raw('SUM(total_amount) as total_sales'),
            DB::raw('SUM(paid_amount) as total_paid'),
            DB::raw('SUM(total_amount - paid_amount) as total_due'),
            DB::raw('COUNT(*) as invoices_count')
        )
            ->whereBetween('date', [$from, $to])
            ->groupBy('cashier_id', 'day')
            ->get()
            ->groupBy('user_id');

        return response()->json([
            'employees' => $employees,
            'daily_performance' => $dailyPerformance,
        ]);
    }
}
