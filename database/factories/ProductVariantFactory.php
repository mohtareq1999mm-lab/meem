<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Marvel\Database\Models\ProductVariant;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition()
    {
        $dimensionInCm = fn () => $this->faker->numberBetween(5, 200) . 'cm';
        $dimensionInM  = fn () => $this->faker->randomFloat(1, 0.1, 5) . 'm';
        $weightInG     = fn () => $this->faker->numberBetween(50, 5000) . 'g';
        $weightInKg    = fn () => $this->faker->randomFloat(1, 0.1, 10) . 'kg';

        return [
            'price' => $this->faker->randomFloat(2, 10, 100),
            'sale_price' => $this->faker->randomFloat(2, 5, 90),
            'quantity' => $this->faker->numberBetween(1, 100),
            'height' => $this->faker->boolean(70) ? $dimensionInCm() : $dimensionInM(),
            'width' => $this->faker->boolean(70) ? $dimensionInCm() : $dimensionInM(),
            'length' => $this->faker->boolean(70) ? $dimensionInCm() : $dimensionInM(),
            'weight' => $this->faker->boolean(70) ? $weightInG() : $weightInKg(),
            'product_id' => null,
        ];
    }
}
