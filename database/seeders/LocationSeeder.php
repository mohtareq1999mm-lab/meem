<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\City;
use Marvel\Database\Models\Country;
use Marvel\Database\Models\Governorate;
use Marvel\Database\Models\ShippingPrice;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Example country with governorates, cities and shipping prices
        $country = Country::firstOrCreate(
            ['phone_code' => '20'],
            ['name' => ['en' => 'Egypt'], 'status' => true]
        );

        $governorates = [
            ['name' => ['en' => 'Cairo'], 'price' => 50.00, 'estimated_days' => 2, 'free_shipping_over' => 500.00],
            ['name' => ['en' => 'Giza'], 'price' => 60.00, 'estimated_days' => 3, 'free_shipping_over' => 600.00],
        ];

        foreach ($governorates as $gdata) {
            $gov = Governorate::firstOrCreate(
                ['country_id' => $country->id, 'name' => $gdata['name']],
                ['status' => true]
            );

            City::firstOrCreate([
                'governorate_id' => $gov->id,
                'name' => ['en' => $gdata['name']['en'] . ' City'],
            ]);

            ShippingPrice::updateOrCreate(
                ['governorate_id' => $gov->id],
                [
                    'price' => $gdata['price'],
                    'estimated_days' => $gdata['estimated_days'],
                    'free_shipping_over' => $gdata['free_shipping_over'],
                    'status' => true,
                ]
            );
        }
    }
}
