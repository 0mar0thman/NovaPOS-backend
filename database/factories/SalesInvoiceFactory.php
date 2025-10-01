<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SalesInvoiceFactory extends Factory
{
    public function definition()
    {
        $customers = [
            'أحمد محمد', 'سارة عبدالله', 'خالد علي', 'نورة سعيد',
            'محمد حسن', 'فاطمة عمر', 'عبدالرحمن إبراهيم', 'هناء وائل'
        ];

        $paymentMethods = ['cash', 'credit', 'card'];

        // return [
        //     'invoice_number' => 'SAL-' . $this->faker->unique()->numberBetween(1000, 9999),
        //     'date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        //     'customer_name' => $this->faker->randomElement($customers),
        //     'total_amount' => 0, // سيتم تحديثه بعد إنشاء العناصر
        //     'paid_amount' => function (array $attributes) {
        //         return $this->faker->numberBetween(0, $attributes['total_amount']);
        //     },
        //     // 'payment_method' => $this->faker->randomElement($paymentMethods),
        //     'status' => function (array $attributes) {
        //         if ($attributes['paid_amount'] == 0) return 'unpaid';
        //         if ($attributes['paid_amount'] >= $attributes['total_amount']) return 'paid';
        //         return 'partial';
        //     },
        //     'notes' => $this->faker->optional()->sentence(),
        //     'user_id' => \App\Models\User::factory(),
        //     // 'cashier_id' => \App\Models\User::factory(),
        // ];
    }
}
