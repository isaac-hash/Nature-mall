<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Products>
 */

use App\Models\Products;

class ProductsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 1000),
            'category_id' => \App\Models\Category::factory(),
            'image_url' => $this->faker->imageUrl(640, 480, 'products', true, 'Faker'),
            'stock' => $this->faker->numberBetween(0, 100),
            
        ];
    }
}
