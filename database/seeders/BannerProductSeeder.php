<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Banner;
use Marvel\Database\Models\Product;

class BannerProductSeeder extends Seeder
{
    public function run(): void
    {
        $bannerIds = Banner::pluck('id')->toArray();
        $products = Product::all();

        if (empty($bannerIds) || $products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $attachedBanners = (array) array_rand(array_flip($bannerIds), rand(1, min(3, count($bannerIds))));
            $product->banners()->attach($attachedBanners);
        }
    }
}
