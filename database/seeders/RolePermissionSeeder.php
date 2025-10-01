<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions list
        $permissions = [
            'manage-All',
            'manage-Role',
            'create-Role',
            'update-Role',
            'delete-Role',
            'manage-Permission',
            'create-User',
            'read-User',
            'update-User',
            'delete-User',
            'read-Dashboard',
            'read-Reports',
            'create-Product',
            'read-Product',
            'update-Product',
            'create-Category',
            'read-Category',
            'update-Category',
            'create-Supplier',
            'read-Supplier',
            'update-Supplier',
            'create-Customer',
            'read-Customer',
            'update-Customer',
            'create-Expense',
            'read-Expense',
            'update-Expense',
            'create-PurchaseInvoice',
            'read-PurchaseInvoice',
            'update-PurchaseInvoice',
            'create-SalesInvoice',
            'read-SalesInvoice',
            'update-SalesInvoice',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'manage-Role',
            'create-Role',
            'update-Role',
            'delete-Role',
            'manage-Permission',
            'create-User',
            'read-User',
            'update-User',
            'delete-User',
            'read-Dashboard',
            'read-Reports',
            'create-Product',
            'read-Product',
            'update-Product',
            'create-Category',
            'read-Category',
            'update-Category',
            'create-Supplier',
            'read-Supplier',
            'update-Supplier',
            'create-Customer',
            'read-Customer',
            'update-Customer',
            'create-Expense',
            'read-Expense',
            'update-Expense',
            'create-PurchaseInvoice',
            'read-PurchaseInvoice',
            'update-PurchaseInvoice',
            'create-SalesInvoice',
            'read-SalesInvoice',
            'update-SalesInvoice',
        ]);

        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'read-Dashboard',
            'read-SalesInvoice',
            'create-SalesInvoice',
            'update-SalesInvoice',
            'read-Category',
            'create-Customer',
            'read-Customer',
            'update-Customer',
        ]);
    }
}
