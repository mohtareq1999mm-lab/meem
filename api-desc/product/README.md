# Product Module — API Documentation

## Overview

The Product module manages the entire product catalog including simple and variable products, inventory, pricing with discounts and flash sales, product-media (images), categories, brands, tags, reviews, and more.

## Key Files

| File | Purpose |
|------|---------|
| `ProductController.php` | 5 CRUD endpoints + bulk-delete, destroy-all, import/export, toggle-fast-shipping |
| `ProductRepository.php` | storeProduct, updateProduct, addVariants, syncRelation, pricing, availability |
| `ProductCreateRequest.php` | Validation for create |
| `ProductUpdateRequest.php` | Validation for update (with unique ignore) |
| `ProductResource.php` | Response shape with 40+ fields |
| `ProductCollection.php` | Paginated list response |
| `Product.php` | Model with 59 fillable fields, 25+ relations, SoftDeletes, HasTranslations |
| `ProductPricingService.php` | Pricing logic: discounts, flash sales, variants |
| `ProductFilter.php` | Category, brand, price, attribute, tag, promotion filters |

## Permissions

| Permission | Methods |
|------------|---------|
| `view-products` | index, show |
| `create-product` | store |
| `update-product` | update |
| `delete-product` | destroy, destroyAll, destroyBulk |

## CRUD Routes

| Method | URI | Auth | Purpose |
|--------|-----|------|---------|
| GET | `/products` | Public (index+show) | List paginated products |
| GET | `/products/{id}` | Public | Show product by ID/slug |
| POST | `/products` | auth:sanctum | Create product |
| PUT | `/products/{id}` | auth:sanctum | Update product |
| DELETE | `/products/{id}` | auth:sanctum | Delete product (soft) |

## Additional Endpoints (not CRUD)

| Method | URI | Purpose |
|--------|-----|---------|
| POST | `/products/bulk-delete` | Soft-delete multiple products |
| DELETE | `/products/all` | Delete ALL products |
| PUT | `/products/{id}/fast-shipping` | Toggle fast shipping flag |
| GET | `/products/calculate-rental-price` | Calculate rental price |
| GET | `/popular-products` | Popular products list |
| GET | `/best-selling-products` | Best selling products |
| GET | `/export-products/{shop_id}` | Export products CSV |
| POST | `/import-products` | Import products CSV |
| POST | `/products/import` | Import via Excel (admin) |
| GET | `/products/export` | Export via Excel (admin) |
