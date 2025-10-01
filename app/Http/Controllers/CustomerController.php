<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Customer::query()
                ->when($request->search, function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%")
                        ->orWhere('address', 'like', "%{$request->search}%");
                })
                ->when($request->status, function ($q) use ($request) {
                    if ($request->status === 'active') {
                        $q->whereHas('invoices');
                    } elseif ($request->status === 'inactive') {
                        $q->whereDoesntHave('invoices');
                    }
                })
                ->when($request->sort_by && $request->sort_order, function ($q) use ($request) {
                    $validColumns = ['id', 'name', 'phone', 'email', 'address', 'created_at'];
                    if (in_array($request->sort_by, $validColumns)) {
                        $q->orderBy($request->sort_by, $request->sort_order === 'desc' ? 'desc' : 'asc');
                    }
                })
                ->select([
                    'id',
                    'name',
                    'phone',
                    'email',
                    'address',
                    'notes',
                    DB::raw('(SELECT COUNT(*) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0) as purchases_count'),
                    DB::raw('(SELECT SUM(total_amount) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0) as total_purchases'),
                    DB::raw('(SELECT MAX(updated_at) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0) as updated_at'),
                ]);

            $customers = $request->per_page ? $query->paginate($request->per_page) : $query->get();

            return response()->json(['data' => $customers]);
        } catch (QueryException $e) {
            Log::error('Error fetching customers: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch customers', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            //request layer
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:customers,phone',
                'email' => 'nullable|email|max:255|unique:customers,email',
                'address' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);

            // database layer
            $customer = Customer::create($validated);

            // response layer
            return response()->json(['data' => $customer], 201);
        } catch (\Exception $e) {
            Log::error('Error creating customer: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create customer', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Customer $customer)
    {
        try {
            return response()->json(['data' => $customer->load('invoices.items.product')]);
        } catch (\Exception $e) {
            Log::error('Error fetching customer: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch customer', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Customer $customer)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20|unique:customers,phone,' . $customer->id,
                'email' => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
                'address' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);

            $customer->update($validated);

            return response()->json(['data' => $customer]);
        } catch (\Exception $e) {
            Log::error('Error updating customer: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update customer', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            if ($customer->invoices()->exists()) {
                return response()->json([
                    'message' => 'لا يمكن حذف العميل لوجود فواتير مرتبطة به'
                ], 400);
            }

            $customer->delete();

            return response()->json(['message' => 'تم حذف العميل بنجاح'], 204);
        } catch (\Exception $e) {
            Log::error('Error deleting customer: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete customer', 'error' => $e->getMessage()], 500);
        }
    }

    public function stats()
    {
        try {
            $totalCustomers = Customer::count();
            $activeCustomers = Customer::whereHas('invoices', function ($query) {
                $query->where('total_amount', '>', 0);
            })->count();
            $topCustomers = Customer::select([
                'id',
                'name',
                'phone',
                'email',
                'address',
                'created_at',
                'updated_at',
                'notes',
                DB::raw('COALESCE((SELECT COUNT(*) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0), 0) as purchases_count'),
                DB::raw('COALESCE((SELECT SUM(total_amount) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0), 0) as total_purchases'),
                DB::raw('(SELECT MAX(updated_at) FROM sales_invoices WHERE sales_invoices.customer_id = customers.id AND sales_invoices.total_amount > 0) as updated_at'),
            ])->orderByDesc('total_purchases')->take(5)->get();

            return response()->json([
                'data' => [
                    'total_customers' => $totalCustomers,
                    'active_customers' => $activeCustomers,
                    'top_customers' => $topCustomers,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stats: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch stats', 'error' => $e->getMessage()], 500);
        }
    }

    public function invoices(Customer $customer)
    {
        try {
            $invoices = $customer->invoices()
                ->select([
                    'id',
                    'invoice_number',
                    'date',
                    'total_amount',
                    'paid_amount',
                    'status',
                ])
                ->with(['items.product', 'user'])
                ->latest()
                ->paginate(10);

            return response()->json(['data' => $invoices]);
        } catch (\Exception $e) {
            Log::error('Error fetching customer invoices: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch invoices', 'error' => $e->getMessage()], 500);
        }
    }
}
