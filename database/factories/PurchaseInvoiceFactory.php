<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceFactory extends Factory
{
    public function definition()
    {
        $suppliers = [
            'شركة الأغذية المتحدة', 'مؤسسة الموردين العرب', 'شركة تمور الرياض',
            'مصنع الألبان الحديث', 'شركة الحبوب الذهبية'
        ];

        // return [
        //     'invoice_number' => 'PUR-' . $this->faker->unique()->numberBetween(1000, 9999),
        //     'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        //     'supplier_name' => $this->faker->randomElement($suppliers),
        //     'total_amount' => 0, // سيتم تحديثه بعد إنشاء العناصر
        //     'notes' => $this->faker->optional()->sentence(),
        //     'user_id' => \App\Models\User::factory(),
        // ];
    }
}
