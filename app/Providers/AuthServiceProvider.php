<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use App\Policies\RolePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class => RolePolicy::class,
    ];

    protected $permissions = [
        // المستخدمين
        'read-User',
        'create-User',
        'update-User',
        'delete-User',

        // الأدوار والصلاحيات
        'read-Role',
        'create-Role',
        'update-Role',
        'delete-Role',
        'manage-Role',

        // الفئات
        'read-Category',
        'create-Category',
        'update-Category',
        'delete-Category',

        // المنتجات
        'read-Product',
        'create-Product',
        'update-Product',
        'delete-Product',

        // الموردين
        'read-Supplier',
        'create-Supplier',
        'update-Supplier',
        'delete-Supplier',

        // فواتير الشراء
        'read-PurchaseInvoice',
        'create-PurchaseInvoice',
        'update-PurchaseInvoice',
        'delete-PurchaseInvoice',

        // العملاء
        'read-Customer',
        'create-Customer',
        'update-Customer',
        'delete-Customer',

        // فواتير المبيعات
        'read-SalesInvoice',
        'create-SalesInvoice',
        'update-SalesInvoice',
        'delete-SalesInvoice',

        // مرتجعات المبيعات
        'read-SalesReturn',
        'create-SalesReturn',
        'update-SalesReturn',
        'delete-SalesReturn',

        // التقارير
        'read-Reports',

        // لوحة التحكم
        'read-Dashboard'
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // تعريفات Gate للصلاحيات
        Gate::define('create-Role', function ($user) {
            return $user->hasPermissionTo('create-Role') || $user->hasRole('admin');
        });

        Gate::define('manage-Role', function ($user) {
            return $user->hasPermissionTo('manage-Role') || $user->hasRole('admin');
        });

        Gate::define('read-User', function ($user) {
            return $user->hasPermissionTo('read-User') || $user->hasRole('admin');
        });

        Gate::define('create-User', function ($user) {
            return $user->hasPermissionTo('create-User') || $user->hasRole('admin');
        });

        Gate::define('update-User', function ($user) {
            return $user->hasPermissionTo('update-User') || $user->hasRole('admin');
        });

        Gate::define('delete-User', function ($user) {
            return $user->hasPermissionTo('delete-User') || $user->hasRole('admin');
        });

        Gate::define('read-Category', function ($user) {
            return $user->hasPermissionTo('read-Category') || $user->hasRole('admin');
        });

        Gate::define('create-Category', function ($user) {
            return $user->hasPermissionTo('create-Category') || $user->hasRole('admin');
        });

        Gate::define('update-Category', function ($user) {
            return $user->hasPermissionTo('update-Category') || $user->hasRole('admin');
        });

        Gate::define('delete-Category', function ($user) {
            return $user->hasPermissionTo('delete-Category') || $user->hasRole('admin');
        });

        Gate::define('read-Product', function ($user) {
            return $user->hasPermissionTo('read-Product') || $user->hasRole('admin');
        });

        Gate::define('create-Product', function ($user) {
            return $user->hasPermissionTo('create-Product') || $user->hasRole('admin');
        });

        Gate::define('update-Product', function ($user) {
            return $user->hasPermissionTo('update-Product') || $user->hasRole('admin');
        });

        Gate::define('delete-Product', function ($user) {
            return $user->hasPermissionTo('delete-Product') || $user->hasRole('admin');
        });

        Gate::define('read-Supplier', function ($user) {
            return $user->hasPermissionTo('read-Supplier') || $user->hasRole('admin');
        });

        Gate::define('create-Supplier', function ($user) {
            return $user->hasPermissionTo('create-Supplier') || $user->hasRole('admin');
        });

        Gate::define('update-Supplier', function ($user) {
            return $user->hasPermissionTo('update-Supplier') || $user->hasRole('admin');
        });

        Gate::define('delete-Supplier', function ($user) {
            return $user->hasPermissionTo('delete-Supplier') || $user->hasRole('admin');
        });

        Gate::define('read-Customer', function ($user) {
            return $user->hasPermissionTo('read-Customer') || $user->hasRole('admin');
        });

        Gate::define('create-Customer', function ($user) {
            return $user->hasPermissionTo('create-Customer') || $user->hasRole('admin');
        });

        Gate::define('update-Customer', function ($user) {
            return $user->hasPermissionTo('update-Customer') || $user->hasRole('admin');
        });

        Gate::define('delete-Customer', function ($user) {
            return $user->hasPermissionTo('delete-Customer') || $user->hasRole('admin');
        });

        Gate::define('read-SalesInvoice', function ($user) {
            return $user->hasPermissionTo('read-SalesInvoice') || $user->hasRole('admin');
        });

        Gate::define('create-SalesInvoice', function ($user) {
            return $user->hasPermissionTo('create-SalesInvoice') || $user->hasRole('admin');
        });

        Gate::define('update-SalesInvoice', function ($user) {
            return $user->hasPermissionTo('update-SalesInvoice') || $user->hasRole('admin');
        });

        Gate::define('delete-SalesInvoice', function ($user) {
            return $user->hasPermissionTo('delete-SalesInvoice') || $user->hasRole('admin');
        });

        Gate::define('read-PurchaseInvoice', function ($user) {
            return $user->hasPermissionTo('read-PurchaseInvoice') || $user->hasRole('admin');
        });

        Gate::define('create-PurchaseInvoice', function ($user) {
            return $user->hasPermissionTo('create-PurchaseInvoice') || $user->hasRole('admin');
        });

        Gate::define('update-PurchaseInvoice', function ($user) {
            return $user->hasPermissionTo('update-PurchaseInvoice') || $user->hasRole('admin');
        });

        Gate::define('delete-PurchaseInvoice', function ($user) {
            return $user->hasPermissionTo('delete-PurchaseInvoice') || $user->hasRole('admin');
        });

        Gate::define('read-SalesReturn', function ($user) {
            return $user->hasPermissionTo('read-SalesReturn') || $user->hasRole('admin');
        });

        Gate::define('create-SalesReturn', function ($user) {
            return $user->hasPermissionTo('create-SalesReturn') || $user->hasRole('admin');
        });

        Gate::define('update-SalesReturn', function ($user) {
            return $user->hasPermissionTo('update-SalesReturn') || $user->hasRole('admin');
        });

        Gate::define('delete-SalesReturn', function ($user) {
            return $user->hasPermissionTo('delete-SalesReturn') || $user->hasRole('admin');
        });

        Gate::define('read-Reports', function ($user) {
            return $user->hasPermissionTo('read-Reports') || $user->hasRole('admin');
        });
    }
}
