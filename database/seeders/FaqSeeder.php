<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Faqs;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Faqs::create([
                'faq_title' => [
                    'ar' => "كيف أرجع المنتج رقم $i ?",
                    'en' => "How to return product $i?",
                ],
                'faq_description' => [
                    'ar' => "تفاصيل إرجاع المنتج رقم $i",
                    'en' => "Details about returning product $i",
                ],
                
            ]);
        }
    }
}