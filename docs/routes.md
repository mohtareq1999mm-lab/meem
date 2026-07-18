# API Routes Reference

All routes are inside the `/api/v1/` prefix group.

---

## Brands

All brands endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

### Route Registration Note

`GET /brands` and `GET /brands/{brand}` are registered **twice**:
1. **First** at line 190 — outside any auth middleware group, with no route middleware
2. **Second** at line 644 — inside the `auth:sanctum` + `verified` group

Laravel resolves the **first** registration (line 190). The `permission:view-brands` controller middleware (line 23-24 of `BrandController.php`) enforces authentication and authorization regardless of which route is matched. Behavior is identical in both cases. This is **Technical Debt** — a redundant registration, not a production bug.

| Method | URI | Controller | Action | Route Middleware | Controller Middleware | Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|------------|-------------|
| GET | `/brands` | `BrandController` | `index` | None (resolves line 190; also at line 644 behind `auth:sanctum`, `verified`) | `permission:view-brands` | `view-brands` | Lines 190, 644 |
| GET | `/brands/{brand}` | `BrandController` | `show` | None (resolves line 190; also at line 644 behind `auth:sanctum`, `verified`) | `permission:view-brands` | `view-brands` | Lines 190, 644 |
| POST | `/brands` | `BrandController` | `store` | `auth:sanctum`, `verified` | `permission:create-brand` | `create-brand` | Line 644 |
| PUT | `/brands/{brand}` | `BrandController` | `update` | `auth:sanctum`, `verified` | `permission:update-brand` | `update-brand` | Line 644 |
| DELETE | `/brands/{brand}` | `BrandController` | `destroy` | `auth:sanctum`, `verified` | `permission:delete-brand` | `delete-brand` | Line 644 |
| PUT | `/brands/reorder` | `BrandController` | `reorder` | `auth:sanctum`, `verified` | `permission:update-brand` | `update-brand` | Line 643 |

---

## Banners

All banners endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

### Duplicate Route Registration

`GET /banners` and `GET /banners/{banner}` are registered **twice**:
1. **First** at line 251 — outside any auth middleware group, with no route middleware
2. **Second** at line 493 — inside the `auth:sanctum` + `verified` group

Laravel resolves the **first** registration (line 251). The `permission:view-banners` controller middleware (BannerController line 20) enforces authentication and authorization regardless of which route is matched. This is **Technical Debt** — a redundant registration, not a production bug.

| Method | URI | Controller | Action | Route Middleware | Controller Middleware | Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|------------|-------------|
| GET | `/banners` | `BannerController` | `index` | None (resolves line 251; also at line 493 behind `auth:sanctum`, `email.verified`) | `permission:view-banners` | `view-banners` | Lines 251, 493 |
| GET | `/banners/{banner}` | `BannerController` | `show` | None (resolves line 251; also at line 493 behind `auth:sanctum`, `email.verified`) | `permission:view-banners` | `view-banners` | Lines 251, 493 |
| POST | `/banners` | `BannerController` | `store` | `auth:sanctum`, `email.verified` | `permission:create-banners` | `create-banners` | Line 493 |
| PUT | `/banners/{banner}` | `BannerController` | `update` | `auth:sanctum`, `email.verified` | `permission:update-banners` | `update-banners` | Line 493 |
| DELETE | `/banners/{banner}` | `BannerController` | `destroy` | `auth:sanctum`, `email.verified` | `permission:delete-banners` | `delete-banners` | Line 493 |
| POST | `/banner/change-status` | `BannerController` | `changeStatus` | `auth:sanctum`, `email.verified` | `permission:update-banners` | `update-banners` | Line 489 |
| POST | `/banner/reorder` | `BannerController` | `reorder` | `auth:sanctum`, `email.verified` | `permission:update-banners` | `update-banners` | Line 490 |

---

## Sliders

All sliders endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

### Duplicate Route Registration

`GET /sliders` is registered **twice**:
1. **First** at line 254 — outside any auth middleware group, with no route middleware
2. **Second** at line 494 — inside the `auth:sanctum` + `verified` group

Laravel resolves the **first** registration (line 254). The `permission:view-slider` controller middleware (SliderController line 22) enforces authentication and authorization regardless of which route is matched. This is **Technical Debt** — a redundant registration, not a production bug.

| Method | URI | Controller | Action | Route Middleware | Controller Middleware | Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|------------|-------------|
| GET | `/sliders` | `SliderController` | `index` | None (resolves line 254; also at line 494 behind `auth:sanctum`, `email.verified`) | `permission:view-slider` | `view-slider` | Lines 254, 494 |
| GET | `/sliders/{slider}` | `SliderController` | `show` | `auth:sanctum`, `email.verified` | `permission:view-slider` | `view-slider` | Line 494 |
| POST | `/sliders` | `SliderController` | `store` | `auth:sanctum`, `email.verified` | `permission:create-slider` | `create-slider` | Line 494 |
| PUT | `/sliders/{slider}` | `SliderController` | `update` | `auth:sanctum`, `email.verified` | `permission:update-slider` | `update-slider` | Line 494 |
| DELETE | `/sliders/{slider}` | `SliderController` | `destroy` | `auth:sanctum`, `email.verified` | `permission:delete-slider` | `delete-slider` | Line 494 |
| PATCH | `/sliders/change-status` | `SliderController` | `changeStatus` | `auth:sanctum`, `email.verified` | `permission:update-slider` | `update-slider` | Line 491 |
| PUT | `/sliders/reorder` | `SliderController` | `reorder` | `auth:sanctum`, `email.verified` | `permission:update-slider` | `update-slider` | Line 492 |

---

## Categories

All routes require `auth:sanctum` and `verified` middleware for write operations.  
`GET /categories` and `GET /categories/{category}` have controller-level `permission:view-categories` middleware.

| Method | URI | Controller | Action | Route Middleware | Permission Middleware | Purpose |
|--------|-----|------------|--------|-----------------|-----------------------|---------|
| GET | `/categories` | `CategoryController` | `index` | `auth:sanctum`, `verified` | `view-categories` | List categories with filters and pagination |
| POST | `/categories` | `CategoryController` | `store` | `auth:sanctum`, `verified` | `create-category` | Create a new category |
| GET | `/categories/{category}` | `CategoryController` | `show` | `auth:sanctum`, `verified` | `view-categories` | Show a single category |
| PUT | `/categories/{category}` | `CategoryController` | `update` | `auth:sanctum`, `verified` | `update-category` | Update a category |
| DELETE | `/categories/{category}` | `CategoryController` | `destroy` | `auth:sanctum`, `verified` | `delete-category` | Soft-delete a category |
| GET | `/featured-categories` | `CategoryController` | `fetchFeaturedCategories` | Public | None | List top featured categories |
| PUT | `/categories/feature` | `CategoryController` | `addOrRemoveCategoryFromFeature` | `auth:sanctum`, `verified` | `update-category` | Toggle category featured flag |

---

## Attributes

All attribute endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

### Duplicate Route Registration

`GET /attributes`, `GET /attributes/{attribute}`, `GET /attribute-values`, and `GET /attribute-values/{attribute_value}` are registered **twice**:
1. **First** at lines 224-229 — outside any auth middleware group, with no route middleware
2. **Second** at lines 463-468 — inside the `auth:sanctum` + `verified` group

Laravel resolves the **first** registration (lines 224-229). The `permission:view-attributes` controller middleware (AttributeController line 54, AttributeValueController line 22) enforces authentication and authorization regardless of which route is matched. This is **Technical Debt** — a redundant registration, not a production bug.

| Method | URI | Controller | Action | Route Middleware | Controller Middleware | Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|------------|-------------|
| GET | `/attributes` | `AttributeController` | `index` | None (resolves line 224; also at line 463 behind `auth:sanctum`, `verified`) | `permission:view-attributes` | `view-attributes` | Lines 224, 463 |
| GET | `/attributes/{attribute}` | `AttributeController` | `show` | None (resolves line 224; also at line 463 behind `auth:sanctum`, `verified`) | `permission:view-attributes` | `view-attributes` | Lines 224, 463 |
| GET | `/attribute-values` | `AttributeValueController` | `index` | None (resolves line 227; also at line 466 behind `auth:sanctum`, `verified`) | `permission:view-attributes` | `view-attributes` | Lines 227, 466 |
| GET | `/attribute-values/{attribute_value}` | `AttributeValueController` | `show` | None (resolves line 227; also at line 466 behind `auth:sanctum`, `verified`) | `permission:view-attributes` | `view-attributes` | Lines 227, 466 |
| POST | `/attributes` | `AttributeController` | `store` | `auth:sanctum`, `verified` | `permission:create-attribute` | `create-attribute` | Line 463 |
| PUT | `/attributes/{attribute}` | `AttributeController` | `update` | `auth:sanctum`, `verified` | `permission:update-attribute` | `update-attribute` | Line 463 |
| DELETE | `/attributes/{attribute}` | `AttributeController` | `destroy` | `auth:sanctum`, `verified` | `permission:delete-attribute` | `delete-attribute` | Line 463 |
| POST | `/attribute-values` | `AttributeValueController` | `store` | `auth:sanctum`, `verified` | `permission:create-attribute` | `create-attribute` | Line 466 |
| PUT | `/attribute-values/{attribute_value}` | `AttributeValueController` | `update` | `auth:sanctum`, `verified` | `permission:update-attribute` | `update-attribute` | Line 466 |
| DELETE | `/attribute-values/{attribute_value}` | `AttributeValueController` | `destroy` | `auth:sanctum`, `verified` | `permission:delete-attribute` | `delete-attribute` | Line 466 |
| POST | `/import-attributes` | `AttributeController` | `importAttributes` | `auth:sanctum`, `throttle:uploads` | None (no permission middleware) | N/A | Line 140 |
| GET | `/export-attributes/{shop_id}` | `AttributeController` | `exportAttributes` | `auth:sanctum` | None (no permission middleware) | N/A | Line 146 |

---

## Admin Users

All routes require `auth:sanctum` and `email.verified` middleware. Per-method permission middleware is applied at the controller level.

| Method | URI | Controller | Action | Permission Middleware | Purpose |
|--------|-----|------------|--------|-----------------------|---------|
| GET | `/users` | `UserController` | `index` | `view-users` | List all users with filters |
| GET | `/users/{user}` | `UserController` | `show` | `view-users` | Show a single user |
| DELETE | `/users/{user}` | `UserController` | `destroy` | `delete-user` | Delete a user (legacy resource route) |
| POST | `/admin-users/add` | `UserController` | `adminAddUsers` | `create-user` | Create admin user with roles |
| PUT | `/admin-users/update-activation` | `UserController` | `adminUpdateActivationUsers` | `edit-user` | Toggle user activation status |
| DELETE | `/admin-users/delete/{id}` | `UserController` | `adminDeleteUsers` | `delete-user` | Delete a user (with guards) |
| PUT | `/admin-users/restore/{id}` | `UserController` | `adminRestoreUser` | `restore-user` | Restore a soft-deleted user |
| DELETE | `/admin-users/delete-forever/{id}` | `UserController` | `adminDeleteUsersForever` | `delete-user` | Force delete a soft-deleted user |
| POST | `/users/block-user` | `UserController` | `banUser` | `ban-user` | Ban/deactivate a user |
| POST | `/users/unblock-user` | `UserController` | `activeUser` | `activate-user` | Unban/activate a user |
| POST | `/users/make-admin` | `UserController` | `makeOrRevokeAdmin` | `super_admin` (method-level) | Toggle SUPER_ADMIN permission on a user |
| POST | `/add-points` | `UserController` | `addPoints` | `add-points` | Add wallet points to a customer |

---

## Content Pages

All content-pages, sections, and section-types endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

### Route Registration Note

All routes are inside a single `Route::group()` at line 320 with middleware:
- `role:super_admin|editor`
- `auth:sanctum`
- `email.verified`

Controller-level permission middleware is defined in each controller's constructor for granular per-action access.

| Method | URI | Controller | Action | Route Middleware | Controller Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|-------------|
| GET | `/content-pages` | `ContentPageController` | `index` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-content-pages` | 331 |
| POST | `/content-pages` | `ContentPageController` | `store` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `create-content-pages` | 331 |
| GET | `/content-pages/{content_page}` | `ContentPageController` | `show` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-content-pages` | 331 |
| PUT | `/content-pages/{content_page}` | `ContentPageController` | `update` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-content-pages` | 331 |
| DELETE | `/content-pages/{content_page}` | `ContentPageController` | `destroy` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `delete-content-pages` | 331 |
| POST | `/content-pages/{content_page}/attach-sections` | `ContentPageController` | `attachSections` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-content-pages` | 329 |
| PATCH | `/content-pages/{content_page}/toggle-active` | `ContentPageController` | `toggleActive` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-content-pages` | 330 |

## Sections

| Method | URI | Controller | Action | Route Middleware | Controller Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|-------------|
| GET | `/sections` | `SectionController` | `index` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-sections` | 335 |
| POST | `/sections` | `SectionController` | `store` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `create-sections` | 335 |
| GET | `/sections/{section}` | `SectionController` | `show` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-sections` | 335 |
| PUT | `/sections/{section}` | `SectionController` | `update` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-sections` | 335 |
| DELETE | `/sections/{section}` | `SectionController` | `destroy` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `delete-sections` | 335 |
| POST | `/sections/reorder` | `SectionController` | `reorder` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-sections` | 332 |
| PATCH | `/sections/{section}/toggle-active` | `SectionController` | `toggleStatus` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-sections` | 334 |
| GET | `/sections/types` | `SectionController` | `getTypeSection` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-sections` | 333 |

## Section Types

| Method | URI | Controller | Action | Route Middleware | Controller Permission | Source Line |
|--------|-----|------------|--------|-----------------|----------------------|-------------|
| GET | `/section-types` | `SectionTypeController` | `index` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-section-types` | 336 |
| POST | `/section-types` | `SectionTypeController` | `store` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `create-section-types` | 336 |
| GET | `/section-types/{section_type}` | `SectionTypeController` | `show` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-section-types` | 336 |
| PUT | `/section-types/{section_type}` | `SectionTypeController` | `update` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-section-types` | 336 |
| DELETE | `/section-types/{section_type}` | `SectionTypeController` | `destroy` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `delete-section-types` | 336 |
| POST | `/section-types/{type}/settings` | `SectionTypeController` | `updateSettings` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `update-section-types` | 337 |
| GET | `/section-types/{type}/settings` | `SectionTypeController` | `settings` | `role:super_admin\|editor`, `auth:sanctum`, `email.verified` | `view-section-types` | 338 |

## Cart

All cart endpoints are in `packages/marvel/src/Rest/Routes.php`. Routes are loaded via `RestAPIServiceProvider::loadRoutes()` with prefix `/api/v1` and middleware `api`.

All routes require `auth:sanctum` and `throttle:cart` (20 req/min per user) middleware.

| Method | URI | Controller | Action | Source Line | Purpose |
|--------|-----|------------|--------|-------------|---------|
| GET | `/cart` | `CartController` | `index` | 798 | List user's cart with items, products, and variant details |
| POST | `/cart` | `CartController` | `store` | 799 | Add a single item to cart (mode: add — quantity accumulates) |
| GET | `/cart/{id}` | `CartController` | `show` | 800 | Show a specific cart with ownership check |
| POST | `/cart/bulk-items` | `CartController` | `pluckItemsToCart` | 801 | Add multiple items in a single transaction |
| PUT | `/cart/update-item` | `CartController` | `update` | 802 | Update item quantity (mode: set — absolute value) |
| DELETE | `/cart/delete-item/{itemId}` | `CartController` | `deleteItemFromCart` | 803 | Remove a single item and release reserved inventory |
| DELETE | `/cart/delete-items` | `CartController` | `destroy` | 804 | Clear entire cart and release all reserved inventory |

## Coupon

| Method | URI | Controller | Action | Route Middleware | Source Line | Purpose |
|--------|-----|------------|--------|-----------------|-------------|---------|
| POST | `/coupons/add-to-cart` | `CouponController` | `addCouponToCart` | `auth:sanctum` | 223 | Apply a coupon code to the active cart |

## Architecture Note

The Application layer (`app/`) identifies admins exclusively via `type = 'admin'`. Route authorization uses per-method Spatie permission middleware for backward compatibility. See `docs/cms-endpoints/admin-users.md` for details.
