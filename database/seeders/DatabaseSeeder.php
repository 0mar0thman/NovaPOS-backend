<?php

namespace Database\Seeders;

use Faker\Generator as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $faker = \Faker\Factory::create();

        // استدعاء RolePermissionSeeder
        $this->call(RolePermissionSeeder::class);

        // إنشاء المستخدم الأول (المسؤول)
        $admin = User::create([
            'name' => 'مدير النظام',
            'email' => 'omar@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('12345678'),
            'role_id' => 1,
        ]);
        $admin->assignRole('admin');

        $admin2 = User::create([
            'name' => 'مستخدم مدير',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12345678'),
            'role_id' => 2,
        ]);
        $admin2->assignRole('manager');

        $user = User::create([
            'name' => 'مستخدم عادي',
            'email' => 'user@user.com',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
        ]);
        $user->assignRole('user');
    }
}
