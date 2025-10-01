<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition()
    {
      $categories = [
    'معلبات', 'مشروبات', 'حبوب', 'لحوم', 'خضروات',
    'فواكه', 'مثلجات', 'ألبان', 'خبزيات', 'حلويات',
    'صلصات', 'مخبوزات', 'تسالي', 'معكرونة', 'مربى',
    'توابل', 'مكسرات', 'أطعمة جاهزة', 'بيض', 'مياه'
];

        return [
            'name' => $this->faker->randomElement($categories),
            'color' => $this->faker->safeHexColor(), // لون عشوائي آمن
            'description' => $this->faker->sentence(6),
        ];
    }
}
