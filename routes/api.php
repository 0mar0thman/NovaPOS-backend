<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PurchaseInvoiceItemController;
use App\Http\Controllers\SalesInvoiceItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // User Management
    Route::get('/get-user', [AuthController::class, 'getUser']);
    Route::apiResource('users', UserController::class);

    // Roles
    Route::apiResource('roles', RoleController::class);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissionToRole']);
    Route::delete('/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermissionFromRole']);

    // Permissions
    Route::apiResource('permissions', PermissionController::class);

    // Users Deletion
    Route::get('/trashed-users', [UserController::class, 'trashed']);

    Route::prefix('users')->group(function () {
        // User Deletion
        Route::get('/{id}/trashed', [UserController::class, 'trashed']);
        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete']);

        Route::post('/{id}/restore', [UserController::class, 'restore']);
        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete']);

        // Assign/Remove Role to User
        Route::post('/{user}/roles', [UserController::class, 'assignRole']);
        Route::delete('/{user}/roles/{role}', [UserController::class, 'removeRole']);

        // Assign/Remove Permission to User
        Route::post('/{user}/permissions', [UserController::class, 'assignPermission']);
        Route::delete('/{user}/permissions/{permission}', [UserController::class, 'removePermission']);
    });


    // Categories
    Route::apiResource('categories', CategoryController::class);

    Route::get('/debug-db', function () {
        try {
            return \App\Models\Category::all();
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    Route::get('/debug-db-connection', function() {
    try {
        \DB::connection()->getPdo();
        return response()->json(['status' => 'DB connected']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});



    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Purchase Invoices
    Route::get('/purchase-invoices/last-invoice-number', [PurchaseInvoiceController::class, 'lastInvoiceNumber']);
    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);
    Route::get('/purchase-invoices/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print']);
    Route::patch('/purchase-invoices/{purchaseInvoice}/payment', [PurchaseInvoiceController::class, 'updatePayment']);
    Route::get('/purchase-invoices/{purchaseInvoice}/versions', [PurchaseInvoiceController::class, 'versions']);

    // Purchase Invoice Items
    Route::post('/purchase-invoices/{purchaseInvoice}/items', [PurchaseInvoiceItemController::class, 'store']);
    Route::put('/purchase-invoices/{purchaseInvoice}/items/{item}', [PurchaseInvoiceItemController::class, 'update']);
    Route::delete('/purchase-invoices/{purchaseInvoice}/items/{item}', [PurchaseInvoiceItemController::class, 'destroy']);

    // Customers
    Route::get('customers/stats', [CustomerController::class, 'stats']);
    Route::get('customers/{customer}/invoices', [CustomerController::class, 'invoices']);
    Route::apiResource('customers', CustomerController::class);

    // Sales Invoices
    Route::apiResource('sales-invoices', SalesInvoiceController::class);
    Route::patch('/sales-invoices/{salesInvoice}/payment', [SalesInvoiceController::class, 'updatePayment']);
    Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print']);
    Route::apiResource('sales-invoice-items', SalesInvoiceItemController::class)->except(['create', 'edit']);
    Route::apiResource('sales-returns', SalesReturnController::class);

    // Reports
    Route::get('/reports/sales-summary', [ReportController::class, 'salesSummary']);
    Route::get('/reports/top-selling-products', [ReportController::class, 'topSellingProducts']);
    Route::get('/reports/purchase-summary', [ReportController::class, 'purchaseSummary']);
    Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLossReport']);
    Route::get('/reports/employee-performance', [ReportController::class, 'employeePerformance']);

    // Dashboard
    Route::get('/dashboard/stats', [ReportController::class, 'dashboardStats']);
});
