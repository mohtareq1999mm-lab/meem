<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Slider;

class SliderProductSeeder extends Seeder
{
    public function run(): void
    {
        $sliderIds = Slider::pluck('id')->toArray();
        $products = Product::all();

        if (empty($sliderIds) || $products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $attachedSliders = (array) array_rand(array_flip($sliderIds), rand(1, min(3, count($sliderIds))));
            $product->sliders()->attach($attachedSliders);
        }
    }
}
