<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Brand;
use Marvel\Database\Models\Product;

class BrandProductSeeder extends Seeder
{
    public function run(): void
    {
        $brandCategoryMap = [
            'Apple' => ['ELC'],
            'Samsung' => ['ELC'],
            'Sony' => ['ELC'],
            'LG' => ['ELC', 'HOM'],
            'Nike' => ['CLO', 'SPT'],
            'Adidas' => ['CLO', 'SPT'],
            'Puma' => ['CLO', 'SPT'],
            'Zara' => ['CLO'],
            'H&M' => ['CLO', 'BBY'],
            'IKEA' => ['HOM'],
            'Philips' => ['ELC', 'BEA', 'HOM'],
            'L\'Oréal' => ['BEA'],
            'Nivea' => ['BEA'],
            'Dove' => ['BEA'],
            'Pepsi' => ['DRK'],
            'Coca-Cola' => ['DRK'],
            'Nestlé' => ['DRY', 'SNK', 'BRK', 'BAK'],
            'Kellogg\'s' => ['BRK'],
            'Lay\'s' => ['SNK'],
            'Lindt' => ['SNK'],
            'Cadbury' => ['SNK'],
            'Danone' => ['DRY'],
            'Farm Fresh' => ['VEG', 'FRT'],
            'Barilla' => ['PNT'],
            'Heinz' => ['PNT'],
            'Lipton' => ['DRK'],
            'Nescafé' => ['DRK'],
            'Pantene' => ['BEA'],
            'Colgate' => ['BEA'],
            'Pampers' => ['BBY'],
        ];

        foreach ($brandCategoryMap as $brandName => $prefixes) {
            $brand = Brand::where('name->en', $brandName)->first();
            if (!$brand) {
                continue;
            }

            $productIds = [];
            foreach ($prefixes as $prefix) {
                $ids = Product::where('sku', 'LIKE', $prefix . '-%')->pluck('id')->toArray();
                $productIds = array_merge($productIds, $ids);
            }

            $productIds = array_unique($productIds);

            if (!empty($productIds)) {
                $brand->products()->syncWithoutDetaching($productIds);
            }
        }

        // Assign remaining unassigned brands to random products
        $allBrands = Brand::all();
        $allProductIds = Product::pluck('id');

        foreach ($allBrands as $brand) {
            if ($brand->products()->count() > 0) {
                continue;
            }

            $maxAttach = min(8, $allProductIds->count());
            $minAttach = min(3, $maxAttach);
            $attachCount = random_int($minAttach, $maxAttach);

            $selected = $allProductIds->random($attachCount)->all();
            $brand->products()->syncWithoutDetaching($selected);
        }
    }
}
