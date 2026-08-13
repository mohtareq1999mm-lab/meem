<?php

return [
    'order' => [
        'created' => [
            'title' => 'تم تأكيد الطلب',
            'body' => 'تم تقديم طلبك رقم :order_number بنجاح.',
        ],
        'delivered' => [
            'title' => 'تم توصيل الطلب',
            'body' => 'تم توصيل طلبك رقم :order_number.',
        ],
        'cancelled' => [
            'title' => 'تم إلغاء الطلب',
            'body' => 'تم إلغاء طلبك رقم :order_number.',
        ],
        'refunded' => [
            'title' => 'تمت الموافقة على الاسترداد',
            'body' => 'تمت الموافقة على استرداد مبلغ طلبك رقم :order_number.',
        ],
    ],
    'payment' => [
        'succeeded' => [
            'title' => 'تم الدفع بنجاح',
            'body' => 'تمت عملية الدفع لطلبك رقم :order_number بنجاح.',
        ],
        'failed' => [
            'title' => 'فشل الدفع',
            'body' => 'فشل الدفع لطلبك رقم :order_number. يرجى المحاولة مرة أخرى.',
        ],
    ],
    'coupon' => [
        'assigned' => [
            'title' => 'تم إسناد قسيمة إليك',
            'body' => 'تم إسناد القسيمة :coupon_code إلى حسابك.',
        ],
        'available' => [
            'title' => 'قسيمة جديدة متاحة',
            'body' => 'أصبحت قسيمة جديدة :coupon_code متاحة لك.',
        ],
        'used' => [
            'title' => 'تم استخدام القسيمة',
            'body' => 'لقد استخدمت القسيمة :coupon_code.',
        ],
    ],
    'promotion' => [
        'available' => [
            'title' => 'عرض جديد متاح',
            'body' => 'تحقق من :promotion_name ووفر في طلبك القادم.',
        ],
    ],
    'flash_sale' => [
        'available' => [
            'title' => 'البيع المفاجئ مباشر الآن',
            'body' => 'بدأ بيع مفاجئ جديد :flash_sale_title. اغتنم العروض قبل انتهائها!',
        ],
    ],
];
