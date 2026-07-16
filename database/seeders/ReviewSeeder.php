<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Review;
use Marvel\Database\Models\User;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->get();
        $users = User::query()->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No products found. Seed products before reviews.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command?->warn('No users found. Seed users before reviews.');
            return;
        }

        $positiveComments = [
            'Great quality and fast delivery.',
            'Exactly as described. Very satisfied.',
            'Works well and feels durable.',
            'Excellent product and great value for money.',
        ];

        $neutralComments = [
            'It is okay, but there is room for improvement.',
            'Matches the description, but nothing special.',
            'Average quality for the price.',
            'Decent product overall.',
        ];

        $negativeComments = [
            'The product did not meet my expectations.',
            'Quality was below what I hoped for.',
            'It arrived fine, but performance was disappointing.',
            'I would not recommend this item.',
        ];

        foreach ($products as $product) {
            $reviewCount = random_int(1, 3);

            for ($i = 0; $i < $reviewCount; $i++) {
                $rating = $this->randomRating();
                $reviewer = $users->random();

                Review::create([
                    'user_id' => $reviewer->id,
                    'product_id' => $product->id,
                    'comment' => $this->commentForRating($rating, $positiveComments, $neutralComments, $negativeComments),
                    'rating' => $rating,
                    'approved' => $this->randomBool(80),
                ]);
            }
        }

        $this->command?->info('ReviewSeeder completed successfully. Created product reviews.');
    }

    private function randomRating(): int
    {
        $ratings = [5, 5, 5, 4, 4, 4, 3, 3, 2, 1];

        return $ratings[array_rand($ratings)];
    }

    private function commentForRating(int $rating, array $positiveComments, array $neutralComments, array $negativeComments): string
    {
        if ($rating >= 4) {
            return $positiveComments[array_rand($positiveComments)];
        }

        if ($rating === 3) {
            return $neutralComments[array_rand($neutralComments)];
        }

        return $negativeComments[array_rand($negativeComments)];
    }

    private function randomBool(int $truePercent): bool
    {
        return random_int(1, 100) <= $truePercent;
    }
}
