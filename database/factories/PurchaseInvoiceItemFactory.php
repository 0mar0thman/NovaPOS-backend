<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceItemFactory extends Factory
{
    public function definition()
    {
        // return [
        //     'purchase_invoice_id' => \App\Models\PurchaseInvoice::factory(),
        //     'product_id' => \App\Models\Product::factory(),
        //     'quantity' => $this->faker->numberBetween(10, 100),
        //     'unit_price' => $this->faker->numberBetween(5, 50),
        //     'total_price' => function (array $attributes) {
        //         return $attributes['quantity'] * $attributes['unit_price'];
        //     },
        //     'expiry_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
        // ];
    }
}
