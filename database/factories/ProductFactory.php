<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition()
    {
        $foodProducts = [
            'أرز بسمتي', 'سكر ناعم', 'زيت زيتون', 'دقيق قمح', 'معكرونة',
            'حليب كامل الدسم', 'زبدة', 'جبنة شيدر', 'بيض', 'خبز توست',
            'عسل طبيعي', 'تمر نخل', 'قهوة تركية', 'شاي أخضر', 'ماء معدني',
            'عصير برتقال', 'كاتشب', 'مايونيز', 'صلصة طماطم', 'فول مدمس'
        ];

        return [
            'name' => $this->faker->unique()->randomElement($foodProducts),
            'barcode' => $this->faker->unique()->ean13(),
            'category_id' => \App\Models\Category::factory(),
            'purchase_price' => $this->faker->numberBetween(5, 100),
            'sale_price' => $this->faker->numberBetween(10, 150),
            'stock' => $this->faker->numberBetween(0, 500),
            'min_stock' => $this->faker->numberBetween(5, 20),
            'description' => $this->faker->sentence(10),
        ];
    }
}
