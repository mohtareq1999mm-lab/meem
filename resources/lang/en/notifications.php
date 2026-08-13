<?php

return [
    'order' => [
        'created' => [
            'title' => 'Order placed',
            'body' => 'Your order #:order_number has been placed successfully.',
        ],
        'delivered' => [
            'title' => 'Order delivered',
            'body' => 'Your order #:order_number has been delivered.',
        ],
        'cancelled' => [
            'title' => 'Order cancelled',
            'body' => 'Your order #:order_number has been cancelled.',
        ],
        'refunded' => [
            'title' => 'Refund approved',
            'body' => 'Your refund for order #:order_number has been approved.',
        ],
    ],
    'payment' => [
        'succeeded' => [
            'title' => 'Payment successful',
            'body' => 'Your payment for order #:order_number was successful.',
        ],
        'failed' => [
            'title' => 'Payment failed',
            'body' => 'Your payment for order #:order_number failed. Please try again.',
        ],
    ],
    'coupon' => [
        'assigned' => [
            'title' => 'Coupon assigned to you',
            'body' => 'The coupon :coupon_code has been assigned to your account.',
        ],
        'available' => [
            'title' => 'New coupon available',
            'body' => 'A new coupon :coupon_code is now available for you.',
        ],
        'used' => [
            'title' => 'Coupon used',
            'body' => 'You have used the coupon :coupon_code.',
        ],
    ],
    'promotion' => [
        'available' => [
            'title' => 'New promotion available',
            'body' => 'Check out :promotion_name and save on your next order.',
        ],
    ],
    'flash_sale' => [
        'available' => [
            'title' => 'Flash sale is live',
            'body' => 'A new flash sale :flash_sale_title has started. Grab the deals before they end!',
        ],
    ],
];
