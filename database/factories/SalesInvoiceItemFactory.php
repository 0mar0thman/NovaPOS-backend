<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SalesInvoiceItemFactory extends Factory
{
    public function definition()
    {
        $product = \App\Models\Product::inRandomOrder()->first() ?? \App\Models\Product::factory()->create();

        // return [
        //     'sales_invoice_id' => \App\Models\SalesInvoice::factory(),
        //     'product_id' => $product->id,
        //     'quantity' => $this->faker->numberBetween(1, 10),
        //     'unit_price' => $product->sale_price,
        //     'total_price' => function (array $attributes) use ($product) {
        //         return $attributes['quantity'] * $product->sale_price;
        //     },
        // ];
    }
}
