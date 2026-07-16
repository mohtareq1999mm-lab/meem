<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\PickupLocation;

class PickupLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'store_name' => 'Downtown Main Branch',
                'address' => '123 El Tahrir Street, Downtown, Cairo',
                'phone' => '01000000001',
                'email' => 'downtown@pickup.example.com',
                'latitude' => '30.0444',
                'longitude' => '31.2357',
                'working_hours' => [
                    ['day' => 'Saturday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Sunday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Monday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Tuesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Wednesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Thursday', 'open' => '10:00', 'close' => '20:00'],
                    ['day' => 'Friday', 'open' => '12:00', 'close' => '18:00'],
                ],
                'status' => true,
                'display_order' => 1,
            ],
            [
                'store_name' => 'Heliopolis Branch',
                'address' => '456 El Merghany Street, Heliopolis, Cairo',
                'phone' => '01000000002',
                'email' => 'heliopolis@pickup.example.com',
                'latitude' => '30.0900',
                'longitude' => '31.3300',
                'working_hours' => [
                    ['day' => 'Saturday', 'open' => '10:00', 'close' => '22:00'],
                    ['day' => 'Sunday', 'open' => '10:00', 'close' => '22:00'],
                    ['day' => 'Monday', 'open' => '10:00', 'close' => '22:00'],
                    ['day' => 'Tuesday', 'open' => '10:00', 'close' => '22:00'],
                    ['day' => 'Wednesday', 'open' => '10:00', 'close' => '22:00'],
                    ['day' => 'Thursday', 'open' => '10:00', 'close' => '20:00'],
                    ['day' => 'Friday', 'open' => '12:00', 'close' => '18:00'],
                ],
                'status' => true,
                'display_order' => 2,
            ],
            [
                'store_name' => 'Maadi Branch',
                'address' => '789 Road 9, Maadi, Cairo',
                'phone' => '01000000003',
                'email' => 'maadi@pickup.example.com',
                'latitude' => '29.9600',
                'longitude' => '31.2700',
                'working_hours' => [
                    ['day' => 'Saturday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Sunday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Monday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Tuesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Wednesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Thursday', 'open' => '10:00', 'close' => '20:00'],
                    ['day' => 'Friday', 'open' => 'CLOSED', 'close' => 'CLOSED'],
                ],
                'status' => true,
                'display_order' => 3,
            ],
            [
                'store_name' => 'Alexandria Branch',
                'address' => '321 Corniche Street, Alexandria',
                'phone' => '01000000004',
                'email' => 'alex@pickup.example.com',
                'latitude' => '31.2000',
                'longitude' => '29.9200',
                'working_hours' => [
                    ['day' => 'Saturday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Sunday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Monday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Tuesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Wednesday', 'open' => '09:00', 'close' => '21:00'],
                    ['day' => 'Thursday', 'open' => '10:00', 'close' => '20:00'],
                ],
                'status' => true,
                'display_order' => 4,
            ],
            [
                'store_name' => 'Giza Branch (Temporarily Closed)',
                'address' => '555 Pyramid Street, Giza',
                'phone' => '01000000005',
                'email' => 'giza@pickup.example.com',
                'latitude' => '29.9800',
                'longitude' => '31.1300',
                'working_hours' => [
                    ['day' => 'Saturday', 'open' => '09:00', 'close' => '17:00'],
                    ['day' => 'Sunday', 'open' => '09:00', 'close' => '17:00'],
                ],
                'status' => false,
                'display_order' => 5,
            ],
        ];

        foreach ($locations as $location) {
            PickupLocation::create($location);
        }
    }
}
