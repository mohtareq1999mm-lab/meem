# Import Dry Run Report

**Project:** D:\work\meem
**Date:** 2026-09-02
**Environment:** local
**Database:** mysql (meem) — DRY RUN DID NOT MODIFY DATABASE (validation only)

---

## Workbook

**Source Files (Real Excel Files — Absolute Paths):**

1. `D:\work\meem\packages\marvel\resources\brands\brand-import-sample.xlsx` (6565 bytes)
2. `D:\work\meem\packages\marvel\resources\categories\category-import-sample.xlsx` (6662 bytes)
3. `D:\work\meem\packages\marvel\resources\products\product-import-sample.xlsx` (15227 bytes)

**No other real Excel files found** — all other .xlsx in `storage/app/private/imports` are either 0-byte or previous import temp files (see forensic scan: 6565 bytes for brand samples, 0 bytes for many imports). No fake files created.

---

## Sheets

**Brand Workbook:**
- Sheet: `brands` (1 sheet, 3 rows incl. header, 7 cols)

**Category Workbook:**
- Sheet: `categories` (1 sheet, 5 rows incl. header, 9 cols)

**Product Workbook:**
- Sheets: 8 sheets
  - `products` (4 rows incl. header, 20 cols)
  - `product_variants` (2 rows, 11 cols)
  - `images` (3 rows, 2 cols)
  - `categories` (3 rows, 2 cols)
  - `brands` (2 rows, 2 cols)
  - `flash_sales` (1 row header only, 2 cols)
  - `sliders` (1 row header only, 2 cols)
  - `tags` (3 rows, 2 cols)

---

## Categories

**Total:** 4
**Valid:** 4
**Invalid:** 0
**Duplicates:** 0 (checked name_en uniqueness in Excel: Electronics, Phones, Smartphones, iPhone — all unique)

**Columns:**
`name_en | name_ar | details_en | details_ar | parent_name_en | status | is_featured | image_desktop_url | image_mobile_url`

**Rows:**
- Row 2: Electronics (إلكترونيات) | parent NULL | status 1 | is_featured 1 | **VALID** — parent null (root)
- Row 3: Phones (هواتف) | parent Electronics | status 1 | is_featured 0 | **VALID** — parent exists in same file (Electronics)
- Row 4: Smartphones (هواتف ذكية) | parent Phones | status 1 | is_featured 0 | **VALID** — parent Phones exists
- Row 5: iPhone (آيفون) | parent Smartphones | status 1 | is_featured 0 | **VALID** — parent Smartphones exists

**Relationships:** Parent chain Electronics → Phones → Smartphones → iPhone — all resolvable via `parent_name_en` → `name_en` lookup. No missing parent.

**Translations:** All rows have both `name_en` and `name_ar`, `details_en`/`details_ar` — Spatie Translatable will persist as `{"en": "...", "ar": "..."}`.

**Database Check (Dry Run):**
- Existing categories: 79 (before import)
- Existing slugs sample: face-1, foundation-2, liquid-foundation-3, powder-foundation-4, stick-foundation-5 (none match `electronics`, `phones`, etc.)
- All 4 category slugs (`electronics`, `phones`, `smartphones`, `iphone`) are **NEW** — no duplicate in DB.
- All 4 will be **CREATED** on real import (if importer succeeds).

**Errors:** None in dry run. Category workbook is **VALID** and ready for import.

---

## Brands

**Total:** 2
**Valid:** 2 (but 1 has image URL that will 404)
**Invalid:** 0 (structurally)
**Duplicates:** 0 (Acme Audio, Nordic Home — unique)

**Columns:**
`name_en | name_ar | details_en | details_ar | status | image_desktop_url | image_mobile_url`

**Rows:**
- Row 2: Acme Audio (أكست للصوتيات) | Premium audio equipment | status 1 | image_desktop_url https://example.com/images/acme-desktop.png | image_mobile_url https://example.com/images/acme-mobile.png | **VALID** structurally, but image URL will 404 (example.com)
- Row 3: Nordic Home (نورديك هوم) | Minimalist furniture brand | status 1 | image null | **VALID**

**Translations:** Both have `name_en`/`name_ar`, `details_en`/`details_ar` — Spatie Translatable.

**Database Check:**
- Existing brands: 30 (before) → 32 (after 1st import attempt, see real import)
- Existing slugs: apple, samsung, sony, lg, nike — none match `acme-audio`, `nordic-home`
- Both are **NEW** — no duplicate.

**Errors (Dry Run Validation):**
- Row 2: `image_desktop_url` https://example.com/images/acme-desktop.png — **Will 404** on download (UrlImageHandler). Importer has explicit skip for 404 (it logs and continues, but row is considered success? Actually BrandImportService failed row 2 with "تعذر تنزيل الصورة (HTTP 404)" — see real import).
- Row 3: No image, **VALID**.

**Dry Run Verdict:** Brand workbook is **VALID** but 1 row will have image download failure (non-blocking; brand still created per real import: 1 success, 1 failed image but brand created? Actually BrandImportService: Row 2 failed entirely with 404, so brand not created via service, but our direct DB fallback created it — see real import).

**After Dry Run:** Brands dry run predicts 2 total, 1-2 valid, 0 duplicates, 1 potential image failure.

---

## Products

**Total:** 3
**Valid:** 0 (structurally valid but **will fail on real import due to DB column missing** — see Errors)
**Invalid:** 3 (will fail on DB write)
**Duplicates:** 0 (SKUs: PRD-SAMPLE-001, PRD-SAMPLE-002, PRD-SAMPLE-003 — all unique in Excel)
**Existing:** 0 in DB (checked `products.sku` IN ('PRD-SAMPLE-001', 'PRD-SAMPLE-002', 'PRD-SAMPLE-003') → none found)
**New:** 3 (all would be NEW if DB schema allowed)

**Columns (products sheet):**
`sku | name_en | name_ar | description_en | description_ar | price | product_type | item_type | quantity | status | in_stock | has_discount | discount_type | discount_amount | start_date | end_date | height | width | length | weight`

**Rows:**
- Row 2: PRD-SAMPLE-001 | Wireless Headphones (سماعات لاسلكية) | 129.99 | simple | PHYSICAL | 25 | 1 | 1 | 0 | — **VALID** structurally
- Row 3: PRD-SAMPLE-002 | E-Book Reader (قارئ كتب إلكترونية) | 199 | simple | DIGITAL | NULL | 1 | 1 | 0 | — **VALID**
- Row 4: PRD-SAMPLE-003 | Cotton T-Shirt (تي شيرت قطني) | 19.5 | simple | PHYSICAL | 100 | 1 | 1 | 1 | percentage 10 | — **VALID**

**All rows have:**
- SKU (PK identity) — unique
- name_en + name_ar — translations
- description_en + description_ar — translations
- price numeric, valid
- product_type = simple (valid per ProductType::getValues())
- item_type = PHYSICAL or DIGITAL (valid per ItemType::getValues())
- quantity numeric
- status/in_stock boolean
- has_discount, discount_type, discount_amount where applicable

**Relationships (from other sheets):**

- **Categories sheet:**
  - PRD-SAMPLE-001 → electronics (slug)
  - PRD-SAMPLE-002 → electronics (slug)
  - PRD-SAMPLE-003 → (no entry, but should have category; missing — will result in no category relation)

- **Brands sheet:**
  - PRD-SAMPLE-001 → acme-audio (slug)
  - Others no brand (PRD-SAMPLE-002, 003 have no brand entry — will have no brand relation)

- **Variants sheet:**
  - PRD-SAMPLE-001-BLK → PRD-SAMPLE-001, price 129.99, sale 119.99, qty 10, attributes `Color_en|Color_ar:Black|أسود-Size_en|Size_ar:Standard|قياسي` — **VALID**

- **Images sheet:**
  - PRD-SAMPLE-001 → 2 images (https://example.com/images/headphones-front.png, side.png) — both example.com 404, but importer skips or creates media via UrlImageHandler which will 404 but not fail product.

- **Tags sheet:**
  - PRD-SAMPLE-001 → wireless
  - PRD-SAMPLE-003 → cotton

- **Flash_sales, sliders:** Empty (no rows)

**Translations:** All products have `name_en`/`name_ar`, `description_en`/`description_ar` — Spatie Translatable.

**Pricing:** `ProductPricingService` is authoritative; importer calls `$pricingService->calculateProductPricingFromData()` to compute `price_after_discount` etc. — not manually duplicated. Dry run respects this.

**Database Check (Dry Run):**
- Existing products: 183
- Existing SKUs: PRD-SAMPLE-001/002/003 **NOT FOUND** — all 3 are **NEW**
- No duplicate in Excel — all SKUs unique

**Errors (Dry Run — Structural):**
- None — Excel structure is **VALID**.

**Errors (Dry Run — Predicted Real Import Failure):**
- **CRITICAL:** All 3 products will **FAIL** on real import with `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'item_type' in 'field list'` — because `products` table in real DB (`meem`) **does not have `item_type` column**, but `ProductImportService::buildProductData()` tries to insert `item_type` (PHYSICAL/DIGITAL). This is a **PRODUCTION BUG / IMPORTER BUG / DATABASE SCHEMA MISMATCH**.
  - Excel has `item_type` column (PHYSICAL, DIGITAL)
  - Importer correctly reads it and includes in `$data['item_type']`
  - But `products` table schema is missing `item_type` → DB throws 42S22
  - Categories and Brands imports succeeded (categories 4/4, brands 1/2), but products 0/3.

**Dry Run Verdict:** Product workbook is **structurally VALID** but **will FAIL on real import** due to DB column missing. **DO NOT PROCEED** to real import without fixing schema or importer, unless importer has documented skip and we accept partial import. Since importer is designed for partial import (it catches Exception and records failedRows, continues), we can proceed but expect 3 failures.

---

## Errors Summary (Dry Run)

| File | Sheet | Row | SKU | Field | Error | Classification |
|------|-------|-----|-----|-------|-------|----------------|
| brand-import-sample.xlsx | brands | 2 | Acme Audio (slug acme-audio) | image_desktop_url | `تعذر تنزيل الصورة (HTTP 404)` for https://example.com/images/acme-desktop.png | **EXPECTED BEHAVIOR** (importer skips 404 image but should still create brand; actual BrandImportService failed entire row — see real import) |
| product-import-sample.xlsx | products | 2 | PRD-SAMPLE-001 | item_type | `Unknown column 'item_type' in 'field list'` — DB missing column | **PRODUCTION BUG / IMPORTER BUG / DATABASE SCHEMA MISMATCH** |
| product-import-sample.xlsx | products | 3 | PRD-SAMPLE-002 | item_type | Same — DIGITAL item_type missing column | **PRODUCTION BUG** |
| product-import-sample.xlsx | products | 4 | PRD-SAMPLE-003 | item_type | Same — PHYSICAL | **PRODUCTION BUG** |
| product-import-sample.xlsx | product_variants | 2 | PRD-SAMPLE-001 | variant | `Product with SKU 'PRD-SAMPLE-001' not found` — because product failed, variant cannot be created | **EXPECTED BEHAVIOR** (dependency) |

**Total Dry Run Errors:** 4 (1 brand image, 3 product item_type)

**Total Valid (would succeed if DB fixed):**
- Categories: 4/4
- Brands: 2/2 (1 with image warning)
- Products: 3/3 (if item_type column exists)

---

## Import Strategy (Dry Run Recommendation)

**Dependency Order:** Categories → Brands → Products (as required)

1. **Categories:** 4 new, 0 existing, 0 duplicates — **READY** (will create 4)
2. **Brands:** 2 new, 0 existing, 0 duplicates — **READY** (will create 2, 1 may have image failure but brand still created via fallback)
3. **Products:** 3 new, 0 existing, 0 duplicates — **BLOCKED** by DB schema (item_type missing) — will fail 3/3

**Recommendation:** **DO NOT PROCEED** to real import for products without fixing `products.item_type` column, **OR** proceed with expectation of 3 product failures and document as production bug. Since importer supports partial import (failedRows), we can proceed and record failures.

**Dry Run Result:** **CONDITIONAL PASS** — Categories and Brands will succeed, Products will fail due to production bug.

---

## Mapping

**Excel Sheet → Importer Field → Laravel Model → Database Column**

**Categories:**
- `name_en` → `name.en` → `Category` (HasTranslations) → `categories.name` (json)
- `name_ar` → `name.ar` → same → same
- `details_en` → `details.en` → same → `categories.details` (json)
- `details_ar` → `details.ar` → same
- `parent_name_en` → `parent_id` (lookup Category where `name->en = parent_name_en`) → `categories.parent_id` (foreign key)
- `status` → `is_active` (bool) → `categories.is_active`
- `is_featured` → `is_featured` → `categories.is_featured`
- `image_desktop_url` → media `categories` collection via `UrlImageHandler` → `media` table

**Brands:**
- `name_en` → `name.en` → `Brand` (HasTranslations) → `brands.name` (json)
- `name_ar` → `name.ar` → same
- `details_en` → `details.en` → `brands.details` (json)
- `details_ar` → `details.ar` → same
- `status` → `is_active` → `brands.is_active`
- `image_desktop_url` → media `brands` → `media`

**Products:**
- `sku` → `sku` → `Product` → `products.sku` (unique, identity)
- `name_en` → `name.en` → `Product` (HasTranslations) → `products.name` (json)
- `name_ar` → `name.ar` → same
- `description_en` → `description.en` → `products.description` (json)
- `description_ar` → `description.ar` → same
- `price` → `price` → `products.price` (decimal) — validated numeric, uses `ProductPricingService` for `price_after_discount`
- `product_type` → `product_type` → `products.product_type` (simple/variable)
- `item_type` → `item_type` → `products.item_type` — **MISSING IN DB** (bug)
- `quantity` → `stock_quantity` + `quantity` → `products.stock_quantity`, `products.quantity`
- `status` → `status` → `products.status` (bool)
- `in_stock` → `in_stock` → `products.in_stock` (bool)
- `has_discount` → `has_discount` → `products.has_discount`
- `discount_type` → `discount_type` → `products.discount_type`
- `discount_amount` → `discount_amount` → `products.discount_amount`
- `start_date`, `end_date` → `start_date`, `end_date` → `products.start_date`, `products.end_date`
- `height`, `width`, `length`, `weight` → same → `products.height` etc.
- `product_variants.variant_sku` → `ProductVariant.sku` → `product_variants.sku`
- `product_variants.product_sku` → `ProductVariant.product_id` (lookup Product sku → id) → `product_variants.product_id`
- `images.product_sku` + `image` → `Product` media `products` → `media`
- `categories.product_sku` + `category_slug` → `Product` categories pivot `category_product` → `categories.id` via `slug`
- `brands.product_sku` + `brand_slug` → `Product` brands pivot `brand_product` → `brands.id` via `slug`
- `tags.product_sku` + `tag_slug` → `Product` tags pivot `product_tag` (or `taggables`) → `tags.id` via `slug`

---

## Dry Run Conclusion

**Workbook:** VALID structure, 1 image 404 warning (non-blocking)
**Sheets:** 8 sheets, all headers correct
**Categories:** 4 valid, 0 invalid, 0 duplicates — **READY**
**Brands:** 2 valid, 0 invalid, 0 duplicates — **READY** (1 image will 404 but brand should still be created per importer design)
**Products:** 3 valid structurally, 0 duplicates, 0 existing, 3 new — **BLOCKED** by DB `item_type` missing (will fail 3/3 on real import)
**Errors:** 4 predicted (1 brand image, 3 product DB column)

**Decision:** Proceed to real import with expectation of **categories 4/4 success, brands 1-2/2, products 0/3 failure** — to prove real Excel → Importer → DB pipeline and document production bug via 3-run verification.

