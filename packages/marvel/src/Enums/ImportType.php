<?php

namespace Marvel\Enums;

use BenSampo\Enum\Enum;

final class ImportType extends Enum
{
    const PRODUCT_IMPORT = 'product-import';
    const PRODUCT_EXPORT = 'product-export';
    const CATEGORY_IMPORT = 'category-import';
    const CATEGORY_EXPORT = 'category-export';
    const BRAND_IMPORT = 'brand-import';
    const BRAND_EXPORT = 'brand-export';
}
