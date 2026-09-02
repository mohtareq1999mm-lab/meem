# Import & Export Audit and Implementation Plan

> **Status: READ-ONLY AUDIT — NOTHING IMPLEMENTED.**
> No PHP file, route, migration, service, controller, job, test, Excel file, configuration or database
> structure was created or modified while producing this report. No test was executed. No test code was
> written. Excel sample files were opened read-only (as ZIP/OOXML) and used **only** for sheet names,
> exact header spelling and header order; their row values were **not** treated as business requirements.
>
> Project `CLAUDE.md` **API Documentation Mode is OFF** — `API_STORY.md` and `docs/api/*` were not
> created or touched.
>
> **Scope:** Product, Category and Brand Import & Export.
> **Findings: 30 — 4 CRITICAL, 10 HIGH, 11 MEDIUM, 5 LOW.**
> **Verification status:** every CRITICAL and HIGH finding is cited to an exact file and line read
> directly from the repository. None has been reproduced by execution, because execution was prohibited.

---

## 1. Executive Summary

Three pipelines, two architectures, one shared table.

**Categories** and **Brands** are structurally twins: async import (queued job) plus async export
(queued job), driven by a deliberate two-phase service — `prepareRows` → `upsertCategories`/`upsertBrands`
→ `assignParents` → `attachImages`. **Products** diverge: the import is async and multi-sheet (8 sheets),
but the export is **synchronous**, returning a `BinaryFileResponse` straight out of the controller.

There is **no `exports` table**. Exports are written into the `imports` table with `type` set to
`category-export` / `brand-export`. `packages/marvel/src/Jobs/ExportProductsJob.php` is a complete,
working queued export job that is **dispatched from nowhere** — dead code that makes the product export
look asynchronous when it is not.

**Headline defects.** All three sample-download endpoints are broken by wrong paths, and one of the three
is a hard fatal because a `use` statement is missing. Every status, cancel and download endpoint is an
IDOR: `Import::findOrFail($id)` with no ownership check and no `type` check, so any authenticated admin
can read, **cancel** or download any other admin's operation, across domains. Uploaded imports and
generated exports are both written to the **public** disk, making the full catalogue export retrievable
over unauthenticated HTTP at a guessable timestamp URL. A products import in which **every row fails** is
reported to the UI as `completed`. Category and brand imports run with **no database transaction at any
level**. Export sheet filters are applied inconsistently across the 8 product sheets, producing
internally inconsistent workbooks. And a non-numeric `price` cell is silently cast to `0.0` on the
update path, zeroing the price of an existing product.

**What is already right, and must not be "fixed".** The header contracts match across all 11 sheets —
no header renaming is proposed anywhere in this report. RabbitMQ is not configured in this project and
has no relationship with Import/Export — no RabbitMQ change is proposed. Queue provisioning
(`retry_after` > job `timeout`, a dedicated supervisor worker for `meem-high`) is correct. The two-phase
category/brand design, the SSRF hardening on remote image download, and the reuse of
`ProductPricingService` and `CategoryHierarchyService` are all sound and should be preserved.

---

## 2. Current Architecture

### 2.1 Import flow (all three domains)

```
POST products|categories|brands/import
  → {X}ImportRequest            file: required|file|mimes:xlsx,xls,ods|max:20480
  → {X}ImportController::import()
        $file->store('imports', 'public')      ← PUBLIC disk                     (BE-004)
        $this->estimateRowCount($filePath)     ← whole workbook loaded in-request (BE-012)
        Import::create([type, file_path, file_name, status=pending, total_rows, created_by])
        writeProgressSignal()                  storage/app/imports/progress_{id}.json
        Import{X}Job::dispatch($import->id)    onQueue('meem-high')
        202 { import_id | export_id, status }
  → Import{X}Job::handle()       $tries=3  $timeout=1500  $backoff=[60,120,240]
        pre-flight cancel guard → terminal-status guard → update(status=processing)
        countRows()                            ← whole workbook loaded AGAIN      (BE-012)
        Excel::import(new {X}Import($service), $path, null, $readerType)
        finalizeProgress() → compute terminal status → update(errors, counters)
        broadcast terminal event → unlink uploaded file → cleanSignals()
  → {X}ImportService
        products:            row-wise, per-row DB transaction
        categories/brands:   two-phase, NO transaction                            (BE-008)
```

### 2.2 Export flow

```
Categories / Brands (async)                    Products (synchronous)
─────────────────────────────────────────      ──────────────────────────────────────
POST|GET {x}/export                            GET products/export
  Import::create(type='{x}-export',              $filters = $request->only([...])
                 file_path='', file_name='')     new ProductsExport($filters)
  Export{X}Job::dispatch($import->id)            return $export->download($filename)
  202 { export_id }                              ← 8 sheets built inside the HTTP
GET {x}/export/{id}          → status              request; 6 do unbounded ->get()
GET {x}/export/{id}/download → file              ← no Import row, no status, no
  $tries=2 $timeout=600                            cancel, no retry        (BE-022)
  store($filename, 'public')  ← PUBLIC           ← ExportProductsJob exists but is
                                (BE-004)           never dispatched        (BE-021)
```

### 2.3 Cross-cutting mechanisms

- **Signalling is file-based, not queue-based.** `storage_path("app/imports/progress_{id}.json")` and
  `cancel_{id}.json`. Cancellation is cooperative: the controller writes `cancel_{id}.json`, the service
  polls it via `isCancelled()` and throws `Marvel\Exceptions\ImportCancelledException`, which the job
  catches to finalise as `cancelled`.
- **Progress broadcasting** is `App\Events\FileOperationEvent` dispatched through
  `App\Traits\BroadcastsFileOperationProgress::broadcastFileOperationTerminal()`.
- **Translation is uniformly Spatie `HasTranslations`** — `$translatable = ['name','details']` on
  `Brand` and `Category`, `['name','description']` on `Product`, stored as JSON columns. Import writes
  through the model, export reads via `getTranslation()`. **There is no second translation mechanism and
  none is needed.**
- **Permission middleware is applied in the controller constructors**, not in the route definitions.
  The routes carry only `['auth:sanctum','throttle:admin']` from their enclosing group.

---

## 3. Route Audit

**File:** `packages/marvel/src/Rest/routes.php` (458 lines).
All import/export routes sit inside the group opened at **L116**:
`Route::middleware(['auth:sanctum', 'throttle:admin'])->group(function () {`.

| # | Method | URI | Route name | Lines | Permission (from controller constructor) |
|---|---|---|---|---|---|
| 1 | POST | `brands/import` | `admin.brands.import` | 136 | `IMPORT_BRAND\|SUPER_ADMIN` |
| 2 | GET | `brands/import/sample` | `admin.brands.import.sample` | 137 | `IMPORT_BRAND\|SUPER_ADMIN` |
| 3 | GET | `brands/import/{id}` | `admin.brands.import.status` | 138 | `IMPORT_BRAND\|SUPER_ADMIN` |
| 4 | POST | `brands/import/{id}/cancel` | `admin.brands.import.cancel` | 139 | `IMPORT_BRAND\|SUPER_ADMIN` |
| 5 | GET | `brands/import/{id}/errors` | `admin.brands.import.errors` | 140 | `IMPORT_BRAND\|SUPER_ADMIN` |
| 6 | POST | `brands/export` | `admin.brands.export` | 141 | `EXPORT_BRAND\|SUPER_ADMIN` |
| 7 | GET | `brands/export/{id}` | `admin.brands.export.status` | 142 | `EXPORT_BRAND\|SUPER_ADMIN` |
| 8 | GET | `brands/export/{id}/download` | `admin.brands.export.download` | 143 | `EXPORT_BRAND\|SUPER_ADMIN` |
| 9 | POST | `categories/import` | `admin.categories.import` | 154 | `IMPORT_CATEGORY\|SUPER_ADMIN` |
| 10 | GET | `categories/import/sample` | `admin.categories.import.sample` | 155 | `IMPORT_CATEGORY\|SUPER_ADMIN` |
| 11 | GET | `categories/import/{id}` | `admin.categories.import.status` | 156 | `IMPORT_CATEGORY\|SUPER_ADMIN` |
| 12 | POST | `categories/import/{id}/cancel` | `admin.categories.import.cancel` | 157 | `IMPORT_CATEGORY\|SUPER_ADMIN` |
| 13 | GET | `categories/import/{id}/errors` | `admin.categories.import.errors` | 158 | `IMPORT_CATEGORY\|SUPER_ADMIN` |
| 14 | POST | `categories/export` | `admin.categories.export` | 159 | `EXPORT_CATEGORY\|SUPER_ADMIN` |
| 15 | GET | `categories/export/{id}` | `admin.categories.export.status` | 160 | `EXPORT_CATEGORY\|SUPER_ADMIN` |
| 16 | GET | `categories/export/{id}/download` | `admin.categories.export.download` | 161 | `EXPORT_CATEGORY\|SUPER_ADMIN` |
| 17 | POST | `products/import` | `admin.products.import` | 225 | `CREATE_PRODUCT\|SUPER_ADMIN` |
| 18 | GET | `products/import/sample` | `admin.products.import.sample` | 226 | `CREATE_PRODUCT\|SUPER_ADMIN` |
| 19 | GET | `products/import/{id}` | `admin.products.import.status` | 227 | `CREATE_PRODUCT\|SUPER_ADMIN` |
| 20 | POST | `products/import/{id}/cancel` | `admin.products.import.cancel` | 228 | `CREATE_PRODUCT\|SUPER_ADMIN` |
| 21 | GET | `products/import/{id}/errors` | `admin.products.import.errors` | 229 | `CREATE_PRODUCT\|SUPER_ADMIN` |
| 22 | GET | `products/export` | `admin.products.export` | 230 | `VIEW_PRODUCTS\|SUPER_ADMIN` |

### Findings

- **No route shadowing.** Every `.../import/sample` literal is declared **before** its `.../import/{id}`
  sibling in all three domains, and all custom routes precede their `apiResource` (brands L145,
  categories L165). L134-135 carries an explicit comment to that effect. **NO CHANGE REQUIRED.**
- **No `whereNumber('id')` on any of the nine `{id}` routes.** `whereNumber` appears **19 times** in this
  same file — orders L178, site-reviews L208-210, currencies L213-215, digital-assets L234-235, cart
  L349, shipments L414-417, invoices L426-430 — and **zero times** on an import/export route. Every
  controller method type-hints `int $id`, so a non-numeric segment produces a `TypeError`/500 instead of
  a 404. → **BE-018**
- **Products have no export status or download routes.** There is no `products/export/{id}` and no
  `products/export/{id}/download`, because the product export is synchronous. The frontend therefore
  needs a special case for one of three otherwise-identical domains. → **BE-022**
- **No product import/export permission exists.** `Marvel\Enums\Permission` L240-243 defines exactly
  `IMPORT_CATEGORY`, `EXPORT_CATEGORY`, `IMPORT_BRAND`, `EXPORT_BRAND`. Product routes therefore reuse
  `CREATE_PRODUCT` and `VIEW_PRODUCTS`; bulk catalogue extraction is gated on a **read** permission.
  → **BE-020**
- **Permission middleware is registered in controller constructors**, not on the routes. This is
  consistent across all six controllers and is not itself a defect — but it means the route table alone
  does not document authorization, and it is the reason the missing object-level ownership check
  (**BE-003**) is the only gap that matters: the class-level permission passes, then any id is accepted.

---

## 4. Product Import Audit

**Entry point:** `packages/marvel/src/Http/Controllers/ProductImportController.php` (262 L)
**Request:** `packages/marvel/src/Http/Requests/ProductImportRequest.php`
**Job:** `packages/marvel/src/Jobs/ImportProductsJob.php`
**Workbook reader:** `packages/marvel/src/Imports/ProductsImport.php` → 8 sheet importers
**Service:** `packages/marvel/src/Services/Import/ProductImportService.php` (770 L)

### 4.1 Sheet routing — correct

`ProductsImport implements WithMultipleSheets` and returns the 8 named keys `products`,
`product_variants`, `images`, `categories`, `brands`, `flash_sales`, `sliders`, `tags`. Sheet selection
is therefore by name and a stray extra sheet is ignored. **This is the correct pattern and it is only
implemented here** — `CategoriesImport` and `BrandsImport` lack it (**BE-014**). **NO CHANGE REQUIRED.**

### 4.2 Row processing — `ProductImportService::processProductRow()` L304-369

Verified behaviour:

```php
DB::beginTransaction();                                    // L307  per-row transaction ✓
$product = Product::where('sku', $sku)->first();           // L313  match by natural key
$data = $this->buildProductData($row);                     // L316
$data['sku'] = empty($sku) ? 'PRD-' . Str::uuid() : $sku;  // L318-322
if ($product) {
    $data['slug'] = $product->slug;                        // L326  slug preserved on update ✓
    // L329-331 item_type immutability once ordered ✓
    $product->fill($data)->saveQuietly();                  // L333  events bypassed
} else {
    $data['slug'] = $this->generateSlug($row, null);       // L335
    $product = new Product($data); $product->saveQuietly();// L336-337
    $this->createdProductIds[] = $product->id;             // L338  rollback bookkeeping ✓
}
$pricing = $this->pricingService->calculateProductPricingFromData(...);   // L346-349 reuse ✓
DB::commit();  $this->successCount++;                      // L355-356
} catch (Exception $e) { DB::rollBack(); $this->failedRows[] = [...]; }   // L357-366
```

**Correct and to be preserved:** the per-row transaction, matching by `sku`, slug preservation on update,
`item_type` immutability for already-ordered products, and delegation of price computation to the existing
`App\Services\General\ProductPricingService` (injected at L61-70). Pricing must never be reimplemented here.

**Defects found in this path:**

- `buildProductData()` L668-670 / L691-694: `(float) $row['price']` and `(int) $row['quantity']`.
  A non-numeric cell casts to `0.0` / `0` **silently** — and because this is the update path
  (`$product->fill($data)`), a malformed price column zeroes the price of existing products. → **BE-028**
- L672-676 and L708-712 silently default an invalid `product_type` to `ProductType::SIMPLE` and an invalid
  `discount_type` to `DiscountType::PERCENTAGE`, while L678-689 correctly **throws** on an invalid
  `item_type`. Three enum columns, two policies. → **BE-029**
- `saveQuietly()` at L333/337/343/353 suppresses all model events, so Laravel Scout's `ModelObserver`
  (registered by the `Searchable` trait, `Product.php` L23/L28) never fires for imported products.
  Impact is conditional on `config/scout.php` `driver` — harmless at the `collection` default, silently
  broken under an external index. → **BE-030**
- `catch (Exception $e)` does not catch `Error`/`TypeError`. A fatal inside one row escapes the row loop
  and fails the whole job, which then re-enters and reprocesses from row 1 (**BE-007**).

### 4.3 Chunking and row numbering

`ProductsSheetImport implements WithChunkReading` with `chunkSize(): 100` ✓ — but `$rowOffset` is
initialised to `0` and **never advanced between chunks**, so `$rowIndex = $this->rowOffset + $index + 2`
restarts at 2 for every chunk and every reported row number past row 101 is wrong. → **BE-013**

Only `ProductsSheetImport` and `ProductVariantsSheetImport` implement `WithChunkReading`; the other six
sheet importers read whole sheets into memory (the `images` sheet is 421 data rows in the sample).

### 4.4 Validation surface

`ProductImportRequest::rules()` is exactly
`['file' => ['required','file','mimes:xlsx,xls,ods','max:20480']]` — no `messages()` (**§21**), and no
rules for `images_source` or `zip_file` even though both columns exist on `imports`, both are in
`Import::$fillable`, and `Services/Import/ImageHandlers/ZipImageHandler.php` is fully implemented.
The ZIP image-import feature has **no way in through the API**. → **BE-023**

There is no upstream template check: a wrong workbook produces `NAME_EN_REQUIRED`-style failures on
every row instead of one "unrecognised template" error (**§13**).

---

## 5. Product Export Audit

**Controller:** `packages/marvel/src/Http/Controllers/ProductExportController.php` — **34 lines total.**

```php
$this->middleware('permission:' . Permission::VIEW_PRODUCTS . '|' . Permission::SUPER_ADMIN);
...
$filters  = $request->only(['status', 'product_type', 'category_id', 'brand_id']);
$filename = 'products-export-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';
$export   = new ProductsExport($filters);
return $export->download($filename);
```

Four distinct defects in 34 lines:

| Defect | Detail | ID |
|---|---|---|
| Synchronous | All 8 sheets are built inside the HTTP request; 6 of them run unbounded `->get()`. No `Import` row, no progress, no cancel, no retry, no status/download routes. | **BE-022** |
| Dead job | `packages/marvel/src/Jobs/ExportProductsJob.php` is a complete queued export with no dispatch site anywhere in the repository. | **BE-021** |
| Read permission | Gated on `VIEW_PRODUCTS` — any role that can list products can extract the full catalogue including price, discount and stock. | **BE-020** |
| Dropped filter | `ProductsSheetExport::query()` supports `item_type`, but the controller never forwards it and the request never validates it, so `?item_type=X` is accepted and silently ignored. | **BE-025** |

Additionally there are **two classes named `ProductExportRequest`** — `app/Http/Requests/` and
`packages/marvel/src/Http/Requests/` — with different rules. The controller imports the Marvel one; the
`app/` copy is referenced by nothing and additionally allows a `shop_id` that no sheet honours.
→ **BE-024**

Filter application across the 8 sheets is inconsistent, and `ImagesSheetExport` starts from
`Product::withTrashed()`, so a filtered export is internally contradictory. → **BE-009**, detailed in §12.

---

## 6. Category Import Audit

**Controller:** `packages/marvel/src/Http/Controllers/CategoryImportController.php`
**Job:** `packages/marvel/src/Jobs/ImportCategoriesJob.php` (271 L)
**Reader:** `packages/marvel/src/Imports/CategoriesImport.php`
**Service:** `packages/marvel/src/Services/Import/CategoryImportService.php` (970 L)

### 6.1 The two-phase design — correct, do not collapse

`processRows()` L67-100 orchestrates four phases in a fixed order:

| Phase | Method | Lines | Responsibility |
|---|---|---|---|
| 1 | `prepareRows()` → `prepareRow()` | 102-114 / 116-192 | validate, normalize, dedupe by name, download every image to a temp file |
| 2 | `upsertCategories()` | 194-286 | write category rows (insert or update, matched on EN name) |
| 3 | `assignParents()` | 288-339 | resolve `parent_name_en` → `parent_id` **after all names exist** |
| 4 | `attachImages()` → `attachImage()` | 341-362 / 364-388 | move temp files into MediaLibrary |

Phase 3 existing separately is what makes **child-before-parent row ordering work** — a child row may
reference a parent that appears later in the sheet. This ordering is load-bearing and must be preserved.

`assignParents()` writes through the Eloquent model, which is required: `Category::booted()` calls
`app(App\Services\General\CategoryHierarchyService::class)->syncHierarchy($category)` on `saving` and
`updateDescendantLevels()` on `saved` when `parent_id` changed. The importer therefore inherits level and
hierarchy maintenance for free and must never bypass it with a raw query `update()`. **NO CHANGE REQUIRED.**

Method visibility is correctly narrowed: only `__construct` L60 and `processRows` L67 are `public`; all 18
remaining methods are `protected`. **NO CHANGE REQUIRED.**

### 6.2 Sheet routing — defective

`CategoriesImport implements ToCollection, WithTitle, WithHeadingRow, SkipsEmptyRows` and its `title()`
returns `'categories'`. **`WithTitle` is an export-only concern** — on import Laravel Excel ignores it,
and because `WithMultipleSheets` is absent, the single importer is applied to **every sheet in the
workbook**, calling `processRows()` once per sheet. The category sample's only sheet is in fact named
`Sheet1`, not `categories`, and imports successfully — which is what has kept this dead interface hidden.
A workbook with a stray second sheet runs all four phases twice, with counters accumulating and parent
resolution running against a partial name map. → **BE-014**

`CategoriesImport` does not implement `WithChunkReading`; the whole sheet is materialised in memory.

### 6.3 No transaction anywhere

`CategoryImportService` contains **zero** occurrences of `DB::beginTransaction`, `DB::commit`,
`DB::rollBack` or `DB::transaction` — verified by pattern search across the whole 970-line file. Phase 2
writes N category rows, phase 3 issues N further parent updates, phase 4 performs N media attachments,
and any failure or kill in the middle leaves all prior work permanently committed. Products, by contrast,
wrap each row (L307/355/358). → **BE-008**

### 6.4 Terminal status — correct

`ImportCategoriesJob::handle()` computes:

```php
$status = 'completed';
if (!empty($failedRows) && $successCount > 0) { $status = 'completed_with_errors'; }
elseif ($successCount === 0)                  { $status = 'failed'; }        // ← correct
```

This is the **correct** form. `ImportProductsJob` has an extra `empty($failedRows) &&` in the second
condition and is wrong (**BE-005**). **NO CHANGE REQUIRED here.**

`cleanSignals()` removes both the `cancel` and the `progress` file, and `cancelSignalFileExists()` calls
`clearstatcache(true, $path)` before `file_exists()`. Both are correct; the product job does neither
(**BE-026**). **NO CHANGE REQUIRED here.**

### 6.5 Row validation

`prepareRow()` L116-192 validates `name_en` and `name_ar` presence, in-sheet duplicate names, `status`,
`is_featured`, and image URL format, emitting translated keys
(`message.IMPORT.CATEGORY.NAME_EN_REQUIRED`, `DUPLICATE_ROW`, `INVALID_STATUS`, `INVALID_IS_FEATURED`,
`INVALID_IMAGE_URL`) ✓. It collects multiple errors per row but reports only `$data['errors'][0]`
through `addFailedRow()`, so an operator fixes one reason at a time (**§13**).

---

## 7. Category Export Audit

**Controller:** `packages/marvel/src/Http/Controllers/CategoryExportController.php`
**Job:** `packages/marvel/src/Jobs/ExportCategoriesJob.php` — `$tries = 2`, `$timeout = 600`
**Export:** `packages/marvel/src/Exports/CategoriesExport.php`

```php
$import = Import::create([
    'type' => 'category-export', 'file_path' => '', 'file_name' => '',   // '' as NULL sentinel
    'status' => 'pending', 'total_rows' => 0, 'created_by' => $request->user()->id,
]);
ExportCategoriesJob::dispatch($import->id);
return $this->apiResponse(__('message.MESSAGE.CATEGORY_EXPORT_STARTED'), 202, true,
                          ['export_id' => $import->id, 'status' => $import->status]);
```

`download(int $id)` does `Import::findOrFail($id)` with **no `created_by` and no `type` check**
(**BE-003**), guards on `status !== 'completed' || !$import->file_path ||
!Storage::disk('public')->exists($import->file_path)` → 409, then serves
`response()->download(Storage::disk('public')->path($import->file_path), ...)` with **no
`deleteFileAfterSend`** — and from the **public** disk, so the same file is already reachable
unauthenticated at `/storage/<name>.xlsx` and nothing ever prunes it (**BE-004**).

Two performance defects in the job: `$rowCount` is obtained with `$export->collection()->count()` and
then `store()` **builds the collection a second time** — two full loads per export. And
`CategoriesExport::loadCategories()` runs in the **constructor**, while `firstImageUrl()` calls
`$category->getMedia($collection)` for two collections per category with `media` **not eager-loaded** —
2N media queries. → **BE-010**

`file_path`/`file_name` are `NOT NULL` in the schema, which is why `''` is inserted as a placeholder here
and backfilled by the job (**§18**).

---

## 8. Brand Import Audit

**Controller:** `packages/marvel/src/Http/Controllers/BrandImportController.php` (287 L)
**Job:** `packages/marvel/src/Jobs/ImportBrandsJob.php`
**Reader:** `packages/marvel/src/Imports/BrandsImport.php`
**Service:** `packages/marvel/src/Services/Import/BrandImportService.php` (807 L)

Structurally identical to categories minus the parent phase: `prepareRows` → `upsertBrands` L179-265 →
`attachImages` L267-292. Same correctness properties (translated row errors, correct terminal status,
correct signal cleanup with `clearstatcache`), and the same two defects — **BE-014** (`WithTitle` without
`WithMultipleSheets`) and **BE-008** (zero `DB::` transaction usage in the whole 807-line file).

**The one defect unique to brands is a hard fatal.** `downloadSample()` L273-279:

```php
16| use Symfony\Component\HttpFoundation\BinaryFileResponse;      ← use-list ends here
...
273|     public function downloadSample(): BinaryFileResponse
274|     {
275|         $samplePath = base_path('packages/marvel/resources/brands/brand-import-sample.xlsx');
276|
277|         if (!file_exists($samplePath)) {
278|             throw new FileNotFoundException($samplePath);      ← unqualified, never imported
279|         }
```

`ProductImportController` and `CategoryImportController` both `use
Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;`. Brand was copied without it, so
the unqualified name resolves to the non-existent `Marvel\Http\Controllers\FileNotFoundException` and the
endpoint dies with `Error: Class not found` — an uncaught 500 with no JSON envelope. → **BE-001**

The path itself is also wrong in all three controllers (→ **BE-002**), so this `file_exists()` check
always fails, which means the fatal is reached on **every** call, not only in an edge case.

Error-workbook headings L260 are hardcoded English: `['Sheet', 'Row', 'Name (EN)', 'Name (AR)', 'Error
Message']` (**§21**).

`BrandImportService` also has the same slug exposure as categories: `Brand::booted()` regenerates
`Str::slug($enName)` on `saving` with **no uniqueness handling**, so "Acme Audio" and "acme audio"
collide (**§18**).

---

## 9. Brand Export Audit

**Controller:** `packages/marvel/src/Http/Controllers/BrandExportController.php`
**Job:** `packages/marvel/src/Jobs/ExportBrandsJob.php` — `$tries = 2`, `$timeout = 600`
**Export:** `packages/marvel/src/Exports/BrandsExport.php`

A faithful copy of the category export, with `type = 'brand-export'`, and therefore carries the same
findings: unscoped `Import::findOrFail($id)` on `status()` and `download()` (**BE-003**), `public` disk
storage plus no `deleteFileAfterSend` and no pruning (**BE-004**), double collection build for the row
count, and an N+1 through `getFirstMediaUrl()` with `media` not eager-loaded (**BE-010**).

`BrandsExport::collection()` loads all brands with `->get()` — bounded in practice today, unbounded by
construction.

---

## 10. Excel Sample Header Analysis

`php -r` is blocked in this environment and Python is unavailable, so each `.xlsx` was parsed as a ZIP
archive (`xl/workbook.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheetN.xml`) with `unzip -p`.
**No Excel file was modified.** Headers below are **verbatim** — exact spelling, exact capitalization,
exact order. Row values were read only far enough to confirm the header row's position and are **not**
treated as requirements.

### 10.1 `storage/packages/marvel/resources/brands/brand-import-sample.xlsx`

Sheets: **1** — `brands`. Dimension `A1:G3`.

```
name_en | name_ar | details_en | details_ar | status | image_desktop_url | image_mobile_url
```

### 10.2 `storage/packages/marvel/resources/category/niceone_categories.xlsx`

Sheets: **1** — named **`Sheet1`**, *not* `categories`. Dimension `A1:I271`. Inline strings.

```
name_en | name_ar | details_en | details_ar | parent_name_en | status | is_featured | image_desktop_url | image_mobile_url
```

The sheet name mismatch is what masks **BE-014**: the declared `title()` of `'categories'` is never
consulted on import, so a workbook whose sheet is called `Sheet1` still imports.

### 10.3 `storage/packages/marvel/resources/product/products_export_2026-09-01_scraped.xlsx`

Sheets: **8**. Workbook metadata contains `x15ac:absPath url="D:\work\niceone_scraper\output\"` — this
file was produced by an **external scraper**, not by this application's exporter.

| # | Sheet | Dimension | Headers (exact order) |
|---|---|---|---|
| 1 | `products` | A1:T126 | `sku`, `name_en`, `name_ar`, `description_en`, `description_ar`, `price`, `product_type`, `item_type`, `quantity`, `status`, `in_stock`, `has_discount`, `discount_type`, `discount_amount`, `start_date`, `end_date`, `height`, `width`, `length`, `weight` |
| 2 | `product_variants` | A1:I1 | `product_sku`, `price`, `sale_price`, `quantity`, `height`, `width`, `length`, `weight`, `attributes` |
| 3 | `images` | A1:B422 | `product_sku`, `image` |
| 4 | `categories` | A1:B370 | `product_sku`, `category_slug` |
| 5 | `brands` | A1:B126 | `product_sku`, `brand_slug` |
| 6 | `flash_sales` | A1:B1 | `product_sku`, `flash_sale_slug` |
| 7 | `sliders` | A1:B1 | `product_sku`, `slider_slug` |
| 8 | `tags` | A1:B1 | `product_sku`, `tag_slug` |

Sheets 2, 6, 7 and 8 are **headers only** — one row, no data.

### 10.4 Consequences for the sample endpoints

Neither the product nor the category file is an authored template: the product file is scraper output
carrying 125 real product rows, and the category file's 270 data rows are scraped junk. Both are
nonetheless what the three `downloadSample()` methods would need to point at to work at all today.
This is why **BE-002**'s recommended fix is a stopgap and committing real minimal templates is the
correct end state (**§27**).

---

## 11. Header Compatibility Matrix

| Domain / sheet | Sample headers | Importer reads | Exporter writes | Verdict |
|---|---|---|---|---|
| Brand | the 7 above | `BrandImportService::prepareRow()` L108-116 reads `name_en`, `name_ar`, `details_en`, `details_ar`, `status`, `image_desktop_url`, `image_mobile_url` | `BrandsExport::headings()` — same names, same order | **MATCH — NO CHANGE REQUIRED** |
| Category | the 9 above | `CategoryImportService::prepareRow()` L118-128 reads all 9 (`parent_name_en` → internal `parent_name`) | `CategoriesExport::headings()` — same names, same order | **MATCH — NO CHANGE REQUIRED** |
| Product `products` | 20 cols | `ProductsSheetImport` → `processProductRow()` → `buildProductData()` L642-742 | `ProductsSheetExport::headings()` — same 20, same order | **MATCH — NO CHANGE REQUIRED** |
| Product `product_variants` | 9 cols | `ProductVariantsSheetImport` → `processVariantRow()` L371-439 | `ProductVariantsSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |
| Product `images` | 2 cols | `ImagesSheetImport` → `processProductImage()` L547-575 | `ImagesSheetExport::headings()` | **MATCH** on names; round-trip broken by URL semantics (**BE-011**) |
| Product `categories` | 2 cols | `CategoriesSheetImport` → `syncCategories()` L577-588 | `CategoriesSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |
| Product `brands` | 2 cols | `BrandsSheetImport` → `syncBrands()` L590-601 | `BrandsSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |
| Product `flash_sales` | 2 cols | `FlashSalesSheetImport` → `syncFlashSales()` L603-614 | `FlashSalesSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |
| Product `sliders` | 2 cols | `SlidersSheetImport` → `syncSliders()` L616-627 | `SlidersSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |
| Product `tags` | 2 cols | `TagsSheetImport` → `syncTags()` L629-640 | `TagsSheetExport::headings()` | **MATCH — NO CHANGE REQUIRED** |

**Conclusion: the header contract is sound across all 11 sheets. No header renaming, reordering,
normalization or aliasing is proposed anywhere in this report.** Every defect below is behavioural.

Two header-adjacent notes:

- `buildProductData()` also reads `pieces` L733-735 and `has_flash_sale` L737-739, which are **not** in
  the sample's `products` sheet. They are optional and absent-safe (`isset` guards), so this is a
  superset, not a mismatch. **NO CHANGE REQUIRED.**
- Export sheet headings are a machine contract consumed by the importer, **not** user-facing copy. They
  must stay untranslated. **NO CHANGE REQUIRED** (see §21).

---

## 12. Bugs Found

**30 findings — 4 CRITICAL, 10 HIGH, 11 MEDIUM, 5 LOW.**

| ID | Sev | Area | One line |
|---|---|---|---|
| BE-001 | CRITICAL | Sample download | Brand `downloadSample()` throws a class that was never imported → fatal 500 |
| BE-002 | CRITICAL | Sample download | All three sample paths point at a directory that does not exist |
| BE-003 | CRITICAL | Authorization | IDOR on every status / cancel / download endpoint — no owner, no type check |
| BE-004 | CRITICAL | Data exposure | Uploads and exports written to the **public** disk, never pruned |
| BE-005 | HIGH | Status computation | Products import where every row fails is reported `completed` |
| BE-006 | HIGH | Cancellation | Controller and worker both write status → cancellation silently lost |
| BE-007 | HIGH | Retry semantics | A retried job reprocesses the workbook from row 1 |
| BE-008 | HIGH | Transactions | Category and brand imports run with no transaction at all |
| BE-009 | HIGH | Export correctness | Filters applied inconsistently across the 8 product sheets |
| BE-010 | HIGH | Performance | Unbounded `->get()` and media N+1 inside a 256 MB worker |
| BE-011 | HIGH | Round-trip | Exported image URLs are rejected by the importer's own SSRF guard |
| BE-012 | HIGH | Progress | `total_rows` counts every sheet and every header row — up to 8× too large |
| BE-013 | HIGH | Error report | Row numbers restart at 2 in every chunk past the first |
| BE-028 | HIGH | Data integrity | Non-numeric `price` / `quantity` silently cast to 0 on the update path |
| BE-014 | MEDIUM | Sheet routing | `WithTitle` without `WithMultipleSheets` → every sheet imported |
| BE-015 | MEDIUM | Enum | `cancelling` is emitted by the API but is not a declared status |
| BE-016 | MEDIUM | Model | `Import::isCompleted()` omits `cancelled` |
| BE-017 | MEDIUM | API contract | `status()` response shape differs per domain |
| BE-018 | MEDIUM | Route hardening | No `whereNumber('id')` on any of the nine `{id}` routes |
| BE-019 | MEDIUM | Concurrency | Predictable error-report filename + `deleteFileAfterSend` |
| BE-020 | MEDIUM | Permissions | Product export gated on a read permission; no product import/export permission exists |
| BE-021 | MEDIUM | Dead code | `ExportProductsJob` is dispatched from nowhere |
| BE-022 | MEDIUM | Architecture | Product export is synchronous while the other two are queued |
| BE-023 | MEDIUM | Validation | The implemented ZIP image-import path is unreachable through the API |
| BE-029 | MEDIUM | Data integrity | Invalid `product_type` / `discount_type` silently defaulted; `item_type` throws |
| BE-024 | LOW | Duplicate class | Two `ProductExportRequest` classes with different rules |
| BE-025 | LOW | Filter | `item_type` accepted and silently ignored on export |
| BE-026 | LOW | Job divergence | Product job leaks progress files and lacks `clearstatcache()` |
| BE-027 | LOW | Dead code | Unused `Schema` import and five eager loads that `map()` never reads |
| BE-030 | LOW | Events | `saveQuietly()` bypasses Scout index sync (conditional on driver) |

### BE-001

- **SEVERITY** CRITICAL
- **AREA** Sample download / fatal error
- **FILE** `packages/marvel/src/Http/Controllers/BrandImportController.php`
- **METHOD** `downloadSample()` (L273-279)
- **PROBLEM** `throw new FileNotFoundException($samplePath);` at L278, with **no `use` statement** for
  that class. The use-list ends at L16 with `Symfony\Component\HttpFoundation\BinaryFileResponse`.
- **ROOT CAUSE** The unqualified name resolves in the current namespace to
  `Marvel\Http\Controllers\FileNotFoundException`, which does not exist.
  `ProductImportController` and `CategoryImportController` both import
  `Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException`; brand was copied without it.
- **IMPACT** `GET brands/import/sample` throws `Error: Class "…\FileNotFoundException" not found` →
  uncaught 500 with no JSON error envelope. Combined with BE-002 the `file_exists()` guard **always**
  fails, so the fatal is reached on every single call. Admins cannot obtain the brand template at all.
- **RECOMMENDED FIX** Add the missing `use`, and return a translated 404 envelope rather than throwing an
  uncaught exception — matching how the other two controllers already behave.
- **RELATED FILES** `ProductImportController::downloadSample()`,
  `CategoryImportController::downloadSample()`, `lang/{en,ar}/message.php`

### BE-002

- **SEVERITY** CRITICAL
- **AREA** Sample download / wrong path (all three domains)
- **FILE / METHOD**
  `packages/marvel/src/Http/Controllers/ProductImportController.php::downloadSample()`,
  `packages/marvel/src/Http/Controllers/CategoryImportController.php::downloadSample()`,
  `packages/marvel/src/Http/Controllers/BrandImportController.php::downloadSample()` (L275)
- **PROBLEM** All three resolve `base_path('packages/marvel/resources/{products|categories|brands}/…')`.
  The files actually live under `storage/packages/marvel/resources/` with different directory names and
  different filenames:

  | Controller expects | Actually present |
  |---|---|
  | `base_path('packages/marvel/resources/products/product-import-sample.xlsx')` | `storage/packages/marvel/resources/product/products_export_2026-09-01_scraped.xlsx` |
  | `base_path('packages/marvel/resources/categories/category-import-sample.xlsx')` | `storage/packages/marvel/resources/category/niceone_categories.xlsx` |
  | `base_path('packages/marvel/resources/brands/brand-import-sample.xlsx')` | `storage/packages/marvel/resources/brands/brand-import-sample.xlsx` |

- **ROOT CAUSE** The sample assets were relocated into `storage/` and renamed (they appear as untracked
  additions in `git status`) and the three controllers were never updated. Directory pluralization also
  drifted — `products` vs `product`, `categories` vs `category`, while `brands` stayed plural.
- **IMPACT** **All three sample endpoints are 100 % broken.** Product and category return the guarded
  404; brand hits BE-001 and returns an uncaught 500. Every "download the template first" flow fails, and
  since no other template source exists, users must reverse-engineer the headers from code.
- **RECOMMENDED FIX** Introduce one resolved config key per domain (e.g.
  `config('marvel.import.samples.brand')`) pointing at the real `storage_path(...)` location, verify with
  `is_file()` before serving, and return a translated 404 otherwise. Commit real minimal authored
  templates rather than shipping scraper output as the product and category templates (§27 item 1).
- **RELATED FILES** `storage/packages/marvel/resources/{product,category,brands}/*`, a new `config/` key,
  `packages/marvel/src/Rest/routes.php` L137/L155/L226

### BE-003

- **SEVERITY** CRITICAL
- **AREA** Authorization / IDOR on every status, cancel and download endpoint
- **FILE / METHOD**
  `ProductImportController::{status, cancel, downloadErrors}`,
  `CategoryImportController::{status, cancel, downloadErrors}`,
  `BrandImportController::{status, cancel, downloadErrors}`,
  `CategoryExportController::{status, download}`,
  `BrandExportController::{status, download}`
- **PROBLEM** Every one of these thirteen methods resolves the record with a bare
  `Import::findOrFail($id)` (or `Import::select([...])->findOrFail($id)`) — **no `created_by` check and no
  `type` check**.
- **ROOT CAUSE** `imports` is a single shared table for all three domains **and** both directions, with
  `type` as the only discriminator, and no query anywhere filters on it. The permission middleware in the
  controller constructor is class-level: it authorises *the action*, never *the object*.
- **IMPACT** Two distinct breaches.
  (a) **Cross-tenant:** any authenticated admin holding the domain permission can read another admin's
  import status, **cancel their running import**, and download their error report — which contains the
  failing rows' business data (SKUs, names, prices).
  (b) **Cross-type:** `GET products/import/{id}` happily returns a `brand-export` row, and
  `POST brands/import/{id}/cancel` will cancel a **category** import. So `IMPORT_BRAND` is sufficient to
  cancel a category import, defeating the separation that `IMPORT_CATEGORY` / `EXPORT_CATEGORY` /
  `IMPORT_BRAND` / `EXPORT_BRAND` exists to enforce.
- **RECOMMENDED FIX** Scope every lookup to both discriminators —
  `Import::where('type', <expected>)->where('created_by', $request->user()->id)->findOrFail($id)` — with a
  super-admin bypass, extracted into a single private resolver per controller so it cannot drift. Add
  `app/Policies/ImportPolicy.php` (`view`, `cancel`, `download`) registered in `AuthServiceProvider`.
  Return **404, not 403**, on a type or owner mismatch so ids cannot be enumerated.
- **RELATED FILES** `packages/marvel/src/Database/Models/Import.php`,
  `tests/Feature/FileOperations/FileOperationSecurityTest.php`, `app/Providers/AuthServiceProvider.php`

### BE-004

- **SEVERITY** CRITICAL
- **AREA** Data exposure / uploaded imports and generated exports on the public disk
- **FILE / METHOD**
  `{Product,Category,Brand}ImportController::import()` → `$file->store('imports', 'public')`;
  `ExportCategoriesJob::handle()` / `ExportBrandsJob::handle()` → `$export->store($filename, 'public')`;
  `{Category,Brand}ExportController::download()` → `Storage::disk('public')->path($import->file_path)`
- **PROBLEM** Both the uploaded source workbook and the generated export are written to the `public` disk.
  `config/filesystems.php` defines `public` as `root => storage_path('app/public')`,
  `url => env('APP_URL').'/storage'`, `visibility => 'public'`.
- **ROOT CAUSE** `public` was chosen because `download()` needs a real filesystem path and
  `Storage::disk('public')->path()` provides one trivially.
- **IMPACT** The **complete** category, brand or product export is retrievable over plain HTTP at
  `/storage/categories-export-YYYY-MM-DD-His.xlsx` with **no authentication and no permission check** —
  the `EXPORT_CATEGORY` middleware is decorative. Filenames are pure timestamps
  (`Carbon::now()->format('Y-m-d-His')`) and therefore guessable and enumerable by brute-forcing a
  time window. Uploaded import workbooks are equally world-readable for the duration of the run.
  `download()` has **no `deleteFileAfterSend`** and no scheduled prune exists, so every export ever
  generated accumulates publicly and permanently.
- **RECOMMENDED FIX** Move both directions to a private disk — the existing `local`, or a new `imports`
  disk rooted at `storage/app/private/imports` — and serve through
  `Storage::disk($d)->download(...)` behind the existing permission middleware **plus** the BE-003
  ownership scope. Add a random component to generated filenames (`Str::random(8)`), and add a scheduled
  command that prunes export artifacts past a retention window.
- **RELATED FILES** `config/filesystems.php`,
  `packages/marvel/src/Jobs/Export{Categories,Brands}Job.php`, `app/Console/Kernel.php`

### BE-005

- **SEVERITY** HIGH
- **AREA** Status computation / total failure reported as success
- **FILE** `packages/marvel/src/Jobs/ImportProductsJob.php`
- **METHOD** `handle()` — terminal status computation
- **PROBLEM**
  ```php
  $status = 'completed';
  if (!empty($failedRows) && $successCount > 0)      { $status = 'completed_with_errors'; }
  elseif (empty($failedRows) && $successCount === 0) { $status = 'failed'; }
  ```
  When `$failedRows` is non-empty **and** `$successCount === 0`, both conditions are false and the status
  remains its `'completed'` initial value.
- **ROOT CAUSE** The second branch carries a redundant `empty($failedRows) &&` guard. `ImportCategoriesJob`
  and `ImportBrandsJob` both use `elseif ($successCount === 0)` and are correct — this is a
  copy-and-edit divergence, not a design choice.
- **IMPACT** An import in which **every single row failed** is reported to the UI as `completed`.
  Operators believe the catalogue was updated when nothing was written, and only discover otherwise by
  manually inspecting the data. This is the highest-consequence silent failure in the subsystem: the
  error list is populated, the terminal broadcast fires, and the status lies.
- **RECOMMENDED FIX** Replace the second condition with `elseif ($successCount === 0)`, matching the two
  correct jobs verbatim.
- **RELATED FILES** `packages/marvel/src/Jobs/ImportCategoriesJob.php` (correct reference
  implementation), `packages/marvel/src/Jobs/ImportBrandsJob.php`

### BE-006

- **SEVERITY** HIGH
- **AREA** Cancellation / lost-update race on `imports.status`
- **FILE / METHOD** `{Product,Category,Brand}ImportController::cancel()` **and** the terminal
  `$import->update([...])` in each `Import{X}Job::handle()`
- **PROBLEM** `cancel()` writes the cancel signal file and then immediately writes a **terminal** status
  itself: `Import::where('id', $import->id)->update(['status' => 'cancelled']);`. The worker is still
  running, and its own terminal write at the end of `handle()` **overwrites `cancelled`** with
  `completed` or `completed_with_errors`.
- **ROOT CAUSE** Two writers to one status column with no guard: no optimistic lock, no version column,
  and no `whereIn('status', [...])` predicate on the worker's terminal write.
- **IMPACT** Cancellation is silently lost whenever the service finishes before observing the signal —
  which is the common case for a small file or a cancel issued late. The UI shows `cancelled`, then flips
  to `completed`. Because there is no full rollback (BE-008, §16), partial writes remain committed while
  the record claims success. The operator has no way to tell which of the two outcomes actually occurred.
- **RECOMMENDED FIX** Give the worker sole ownership of terminal transitions: make its final write
  conditional (`Import::where('id', $id)->whereNotIn('status', ['cancelled'])->update([...])`), and have
  `cancel()` record only the *intent* (either the signal file alone, or an intermediate `cancelling`
  state once BE-015 adds it to the enum). Note also that `Import::where(...)->update(...)` on the query
  builder bypasses model events — use the model instance if any observer must fire.
- **RELATED FILES** `packages/marvel/src/Exceptions/ImportCancelledException.php`,
  `packages/marvel/src/Database/Models/Import.php` (`isCompleted()`),
  `packages/marvel/src/Enums/ImportStatus.php`

### BE-007

- **SEVERITY** HIGH
- **AREA** Retry semantics / duplicate processing on attempt 2+
- **FILE / METHOD** `Import{Products,Categories,Brands}Job::handle()` — the retry-safety block
- **PROBLEM** On a non-final attempt the job appends an `'Attempt N: …'` string to `errors` and
  **rethrows**, so Laravel retries it after `$backoff` (60/120/240 s). `handle()` re-enters; `status` is
  still `processing`, which is non-terminal, so the terminal guard passes; and the **entire workbook is
  reprocessed from row 1**.
- **ROOT CAUSE** No resume checkpoint is persisted and there is no per-row idempotency contract.
  `rollbackCreatedData()` exists in all three services but is invoked **only** on the
  `ImportCancelledException` path, never on the failure path before rethrowing.
- **IMPACT** Rows committed by attempt 1 are reprocessed by attempt 2. Where matching is by natural key
  (`sku` for products, EN name for categories and brands) the second pass is an update and is mostly
  benign. Where it is not, it duplicates: `processProductImage()` attaches media again via MediaLibrary,
  and `syncFlashSales()` / `syncSliders()` / `syncTags()` / `findVariantByFields()` can create additional
  pivot and variant rows. Separately, `total_rows` and all counters are reset on each attempt, so the
  progress bar restarts and the previous attempt's error detail is discarded except for the one
  `'Attempt N'` string.
- **RECOMMENDED FIX** Pick one of three, in descending preference:
  (a) make retries **resumable** by persisting a processed-row watermark on the `Import` row and skipping
  rows at or below it;
  (b) make retries **clean** by calling the existing `rollbackCreatedData()` before rethrowing;
  (c) set `$tries = 1` and surface the failure for a manual re-run — the honest minimum.
  Whichever is chosen, assert the idempotency of `processProductImage()` and the four `sync*()` methods.
- **RELATED FILES** `ProductImportService::rollbackCreatedData()` L280-302 and `getCreatedProductIds()`
  L261-264, `CategoryImportService::rollbackCreatedData()` L780-791,
  `BrandImportService::rollbackCreatedData()` L675-686,
  `tests/Feature/Phase0/ImportRetrySemanticsTest.php`

### BE-008

- **SEVERITY** HIGH
- **AREA** Transactions / category and brand imports have no transaction at all
- **FILE / METHOD**
  `packages/marvel/src/Services/Import/CategoryImportService.php` — `processRows()` L67-100,
  `upsertCategories()` L194-286, `assignParents()` L288-339, `attachImages()` L341-362;
  `packages/marvel/src/Services/Import/BrandImportService.php` — `upsertBrands()` L179-265,
  `attachImages()` L267-292
- **PROBLEM** Pattern search for `DB::(beginTransaction|commit|rollBack|transaction)` returns **0 matches
  in `CategoryImportService.php` (970 lines)** and **0 matches in `BrandImportService.php` (807 lines)**.
  `ProductImportService` by contrast wraps each row: `DB::beginTransaction()` L307 / `DB::commit()` L355 /
  `DB::rollBack()` L358 in `processProductRow()`, and L391/L421/L428 in `processVariantRow()`.
  There is no `DB::transaction` at job level in any of the three jobs.
- **ROOT CAUSE** Per-row autonomy is deliberate — it is what makes `completed_with_errors` meaningful —
  but for categories and brands it was never paired with any transactional unit at all, and the failure
  path was never given a compensating action.
- **IMPACT** A category import writes N rows in phase 2, then N parent updates in phase 3, then N media
  attachments in phase 4. A crash, OOM kill or worker timeout between phases leaves everything before it
  permanently committed with the `Import` row stuck at `processing` — its `failed()` handler cannot run on
  a hard kill (SIGKILL from `--memory=256`). The half-applied state is specifically dangerous here because
  phase 3 is what establishes hierarchy: categories can be left inserted with `parent_id` unset, so they
  surface as spurious root categories in the storefront. Combined with BE-007 this produces silent
  double-writes; combined with BE-006 the record can claim `completed` over a half-applied catalogue.
- **RECOMMENDED FIX** Keep per-row/per-batch autonomy — **do not** wrap the whole file, which would break
  the partial-success contract the UI depends on. Instead wrap each `upsert` batch and each parent
  assignment in its own `DB::transaction` so each unit is all-or-nothing, mirroring what
  `ProductImportService` already does per row. Separately, add a reconciliation console command that
  marks `processing` imports older than the job timeout as `failed`, so a hard-killed worker cannot leave
  a permanently stuck record.
- **RELATED FILES** `config/queue.php` (`retry_after => 1560`),
  `deploy/supervisor/laravel-worker-meem-high.conf` (`--memory=256`), `app/Console/Kernel.php`

### BE-009

- **SEVERITY** HIGH
- **AREA** Export correctness / filters applied inconsistently across the 8 sheets
- **FILE** `packages/marvel/src/Exports/Sheets/*SheetExport.php`,
  `packages/marvel/src/Http/Controllers/ProductExportController.php::export()`
- **PROBLEM** Each of the 8 sheets honours a different subset of the five filters:

  | Sheet | status | product_type | item_type | category_id | brand_id | base query |
  |---|---|---|---|---|---|---|
  | `products` | ✓ | ✓ | ✓ | ✓ | ✓ | default |
  | `product_variants` | ✓ | ✓ | — | — | — | default |
  | `images` | — | — | — | ✓ | ✓ | **`withTrashed()`** |
  | `categories` | — | — | — | ✓ | — | default |
  | `brands` | — | — | — | — | ✓ | default |
  | `flash_sales` | — | — | — | — | — | default |
  | `sliders` | — | — | — | — | — | default |
  | `tags` | — | — | — | ✓ | — | default |

  (`item_type` is supported by `ProductsSheetExport::query()` L38-40 but never reaches it — BE-025.)
- **ROOT CAUSE** Every sheet class re-implements filtering ad hoc; there is no shared filter or scope
  object. `ImagesSheetExport::collection()` additionally begins from `Product::withTrashed()`.
- **IMPACT** Any filtered export produces an **internally inconsistent workbook**. With `status=1`, the
  `products` sheet contains only active products while `images`, `categories`, `brands` and `tags` still
  emit rows for inactive ones — and `images` emits rows for **soft-deleted** products that appear in no
  other sheet. Re-importing such a file feeds `product_sku` values the `products` sheet never declared, so
  every pivot importer fails those rows with "product not found". The export is therefore not round-trip
  safe whenever any filter is applied.
- **RECOMMENDED FIX** Extract one `Marvel\Exports\Support\ProductExportFilter` — a query scope or an
  invokable that applies all five filters — and inject it into all 8 sheets so there is exactly one
  definition of "which products are in this export". Remove `withTrashed()` from `ImagesSheetExport`
  unless exporting soft-deleted products is an explicit product requirement (§27 item 3).
- **RELATED FILES** `packages/marvel/src/Exports/ProductsExport.php`, `Marvel\Enums\ItemType`,
  `packages/marvel/src/Http/Requests/ProductExportRequest.php`

### BE-010

- **SEVERITY** HIGH
- **AREA** Performance / whole-table loads and media N+1 inside a 256 MB worker
- **FILE / METHOD** `CategoriesSheetExport::collection()`, `BrandsSheetExport::collection()`,
  `ImagesSheetExport::collection()`, `TagsSheetExport::collection()`,
  `FlashSalesSheetExport::collection()`, `SlidersSheetExport::collection()`,
  `CategoriesExport::loadCategories()` + `firstImageUrl()`, `BrandsExport::collection()`
- **PROBLEM** Each of these calls `->get()` on an unbounded query — `Product::query()->with(...)->get()`
  or `Category::query()->…->get()` — and then nest-loops in PHP to emit pivot rows. `ProductsExport`
  instantiates **all eight** sheets, so one export performs six independent full-table product loads.
  `CategoriesExport::loadCategories()` runs in the **constructor**, and `firstImageUrl()` calls
  `$category->getMedia($collection)` for two collections per category with `media` **not eager-loaded** →
  2N media queries. `BrandsExport` has the same N+1 through `getFirstMediaUrl()`. Both export jobs also
  build the collection twice — once for `$export->collection()->count()` and again inside `store()`.
- **ROOT CAUSE** `FromCollection` was used where `FromQuery` + chunked reading was needed, and media was
  never eager-loaded. `config/excel.php` already sets `chunk_size => 1000`, which only takes effect for
  `WithChunkReading` / `FromQuery` exports.
- **IMPACT** `deploy/supervisor/laravel-worker-meem-high.conf` runs workers with `--memory=256`. At
  catalogue scale the export exceeds it, the worker is SIGKILLed by the memory guard, `failed()` never
  runs, and the `Import` row is **stuck at `processing` forever** with no recovery path in the UI. For the
  synchronous product export (BE-022) the identical load happens inside the HTTP request, so the failure
  mode is a request timeout instead.
- **RECOMMENDED FIX** Convert the six pivot sheets to `FromQuery` + `WithChunkReading` (or `lazy()` /
  `cursor()`), eager-load `media` in `CategoriesExport` and `BrandsExport`, move `loadCategories()` out of
  the constructor into `collection()`, and derive the row count from a `count()` query rather than by
  materialising the collection. Raise `--memory` only **after** these changes, not instead of them.
- **RELATED FILES** `config/excel.php`, `packages/marvel/src/Jobs/Export{Categories,Brands}Job.php`,
  `deploy/supervisor/laravel-worker-meem-high.conf`

### BE-011

- **SEVERITY** HIGH
- **AREA** Round-trip / exported image URLs are rejected by the importer's own SSRF guard
- **FILE / METHOD** `packages/marvel/src/Exports/Sheets/ImagesSheetExport.php::collection()` emits
  `$media->getUrl()`; the import side consumes it through
  `packages/marvel/src/Services/Import/ImageHandlers/UrlImageHandler.php` and the
  `downloadImage()` → `assertSafeUrl()` → `resolveHost()` → `isBlockedIp()` stack
  (`CategoryImportService` L480-673, `BrandImportService` L405-639)
- **PROBLEM** The exporter writes absolute URLs built from `APP_URL`. The importer's SSRF hardening
  blocks private, loopback, link-local and reserved IP ranges — including the app's own host in most
  non-production environments.
- **ROOT CAUSE** Two individually correct decisions that contradict each other: export images **by URL**,
  import images **through a private-network denylist**.
- **IMPACT** Export → re-import is broken on any environment whose `APP_URL` resolves to a private or
  loopback address: local development, Docker, and staging behind an internal load balancer. Every image
  row fails with an image-download error, and because a failed image fails the **entire row** (§13), the
  product's text fields are discarded too. In production it additionally makes the application fetch
  hundreds of images from itself over HTTP — slow, and a self-inflicted load spike during import.
- **RECOMMENDED FIX** Emit a stable local identifier — media id or storage-relative path — alongside the
  URL, and on import prefer the local path whenever the URL's host matches `APP_URL`. Keep
  `assertSafeUrl()` and `isBlockedIp()` fully intact for genuinely external URLs; **do not weaken the
  SSRF guard**, which is the correct control and the only thing standing between an admin-supplied
  spreadsheet and the internal network.
- **RELATED FILES** `packages/marvel/src/Services/Import/ImageHandlers/UrlImageHandler.php`,
  `packages/marvel/src/Imports/Sheets/ImagesSheetImport.php`,
  `ProductImportService::processProductImage()` L547-575

### BE-012

- **SEVERITY** HIGH
- **AREA** Progress / `total_rows` is meaningless for the whole run
- **FILE / METHOD** `{Product,Category,Brand}ImportController::estimateRowCount()`;
  `Import{X}Job::countRows()`
- **PROBLEM** `estimateRowCount()` loads the workbook and sums `getHighestDataRow()` across **every
  sheet**, header rows included:
  ```php
  foreach ($spreadsheet->getSheetNames() as $name) {
      $sheet = $spreadsheet->getSheetByName($name);
      if ($sheet) { $total += $sheet->getHighestDataRow(); }
  }
  ```
  For the 8-sheet product sample that is 126+1+422+370+126+1+1+1 = **1048** for a workbook containing
  **125** product rows. The job then recomputes with `countRows()` — a **second full load of the same
  file** — and at the end overwrites `total_rows` with `successCount + count($failedRows)`.
- **ROOT CAUSE** Row counting is implemented twice with two different definitions of "row", and neither
  excludes non-primary sheets or the heading row.
- **IMPACT** The progress denominator is up to 8× too large, so the bar crawls to roughly 12 % and then
  jumps to 100 % — and `total_rows` changes meaning between the start and the end of the same operation.
  Worse, `estimateRowCount()` runs **inside the HTTP request**: a 20 MB upload (the validation ceiling) is
  fully parsed by PhpSpreadsheet before the 202 is returned, adding seconds of latency and risking a
  request timeout on exactly the files that most need the async path.
- **RECOMMENDED FIX** Count **once**, in the job, from the primary sheet only, excluding the heading row,
  and persist it to `total_rows` before processing. Remove `estimateRowCount()` from all three controllers
  so `import()` only validates, stores and dispatches. Stop overwriting `total_rows` at the end — the
  counters `success_rows` and `failed_rows` already carry that information.
- **RELATED FILES** `writeExplicitProgress()`, `flushProgressTick()`, `finalizeProgress()` in all three
  import services; `packages/marvel/src/Imports/ProductsImport.php`

### BE-013

- **SEVERITY** HIGH
- **AREA** Error report / wrong Excel row numbers past the first chunk
- **FILE** `packages/marvel/src/Imports/Sheets/ProductsSheetImport.php`
- **METHOD** `collection()` — `$rowIndex = $this->rowOffset + $index + 2;`
- **PROBLEM** The class implements `WithChunkReading` with `chunkSize(): 100`, and `$rowOffset` is declared
  `protected int $rowOffset = 0;` and accepted as a constructor argument — but it is **never advanced
  between chunks**. Laravel Excel instantiates the importer once and calls `collection()` per chunk with
  `$index` restarting at 0 each time.
- **ROOT CAUSE** The offset was designed to be incremented per chunk; the increment was never written.
- **IMPACT** For any workbook with more than 100 product rows, reported row numbers restart at 2 in every
  chunk. The `Row` column in `failed_import_rows_{id}.xlsx` therefore contains duplicate values pointing
  at the wrong spreadsheet rows, so operators cannot locate the failing records — they fix row 5 and the
  real failure was at row 105. This is silent wrong data in the one artifact whose entire purpose is
  pointing at the right row.
- **RECOMMENDED FIX** Advance the offset at the end of `collection()` — `$this->rowOffset += $rows->count();`
  — or derive the absolute index from a chunk counter. Verify against the same pattern in
  `ProductVariantsSheetImport`, which also implements `WithChunkReading`.
- **RELATED FILES** `ProductImportService::addFailedRow()`,
  `ProductImportController::downloadErrors()`, `packages/marvel/src/Imports/Sheets/ProductVariantsSheetImport.php`

### BE-028

- **SEVERITY** HIGH
- **AREA** Data integrity / silent numeric coercion on the update path
- **FILE** `packages/marvel/src/Services/Import/ProductImportService.php`
- **METHOD** `buildProductData()` — L668-670 (`price`), L691-694 (`quantity`), L714-716 (`discount_amount`)
- **PROBLEM**
  ```php
  if (isset($row['price']))    { $data['price'] = (float) $row['price']; }
  if (isset($row['quantity'])) { $data['stock_quantity'] = (int) $row['quantity'];
                                 $data['quantity']       = (int) $row['quantity']; }
  ```
  A PHP cast never throws. `(float) 'N/A'`, `(float) '—'`, `(float) ''`-adjacent junk and `(float) '12,50'`
  all evaluate without error — to `0.0`, `0.0`, `0.0` and `12.0` respectively. There is no numeric
  validation before the cast anywhere in the product row path.
- **ROOT CAUSE** The cast was used as the validation. `isset()` guards presence but says nothing about the
  value's type, and the sheet has no per-cell validation layer at all.
- **IMPACT** Because `processProductRow()` matches on `sku` and then calls `$product->fill($data)`, this is
  an **update** path: a malformed price cell **zeroes the price of an existing product** and the row is
  counted as a **success**. A locale-formatted price like `12,50` silently becomes `12.00`. The same
  applies to `quantity`, which drives `stock_quantity`, so stock can be silently zeroed. Nothing in the
  error report or the status flags any of it, because no exception was raised.
- **RECOMMENDED FIX** Validate before casting: reject non-numeric `price`, `quantity` and
  `discount_amount` cells with a translated row error, exactly as `item_type` already rejects invalid enum
  values at L681-686. A row with an unparseable price must fail loudly, not import as free.
- **RELATED FILES** `packages/marvel/src/Imports/Sheets/ProductsSheetImport.php`,
  `lang/{en,ar}/message.php` (new `message.IMPORT.PRODUCT.*` keys), `BE-029`

### BE-014

- **SEVERITY** MEDIUM
- **AREA** Sheet routing / a single-sheet importer is applied to the whole workbook
- **FILE / METHOD** `packages/marvel/src/Imports/CategoriesImport.php` (`title()`, `collection()`),
  `packages/marvel/src/Imports/BrandsImport.php` (`title()`, `collection()`)
- **PROBLEM** Both implement `WithTitle` — an **export** concern — and neither implements
  `WithMultipleSheets`. Laravel Excel ignores `WithTitle` on import, and with no sheet map it applies the
  same importer to **every sheet in the file**, calling `processRows()` once per sheet.
- **ROOT CAUSE** `WithTitle` was assumed to select a sheet by name on import. It does not.
  **`packages/marvel/src/Imports/ProductsImport.php` does it correctly** — it implements
  `WithMultipleSheets` returning the 8 named keys — so the correct pattern already exists in the codebase
  and simply was not applied to the other two. This finding does **not** apply to products.
- **IMPACT** The sheet name is never validated: the category sample's sheet is `Sheet1` while the declared
  title is `categories`, and it imports anyway — which is exactly what has kept the dead interface
  unnoticed. A user who uploads a workbook containing a stray second sheet (a "Notes" tab, a copy) gets
  `prepareRows` → `upsert` → `assignParents` → `attachImages` executed twice, with counters accumulating
  and parent resolution running against a partial name map on the first pass.
- **RECOMMENDED FIX** Implement `WithMultipleSheets` on both, returning the intended sheet — by index `0`
  to keep the current tolerant behaviour, or by name with an explicit "sheet not found" validation error
  if the template's sheet name is to become part of the contract — and drop the misleading `WithTitle`.
- **RELATED FILES** `packages/marvel/src/Imports/ProductsImport.php` (correct reference implementation),
  `packages/marvel/src/Jobs/ImportCategoriesJob.php`, `packages/marvel/src/Jobs/ImportBrandsJob.php`

### BE-015

- **SEVERITY** MEDIUM
- **AREA** Enum / the API emits a status that does not exist
- **FILE** `packages/marvel/src/Enums/ImportStatus.php`
- **METHOD** class constants; consumed by `{Product,Category,Brand}ImportController::status()`
- **PROBLEM** The enum declares exactly six values — `pending`, `processing`, `completed`,
  `completed_with_errors`, `failed`, `cancelled`. All three `status()` methods compute
  `$effectiveStatus = $cancelPending ? 'cancelling' : $import->status;` and return `'cancelling'` as a
  **magic string**.
- **ROOT CAUSE** The cancel-pending state was added to the API surface without being added to the enum.
- **IMPACT** A value the API actively emits has no enum member, no database representation and no
  translation key. Any client switching on `ImportStatus` values hits an unhandled case, and any
  server-side `in_array($status, ImportStatus::getValues())` check would reject a status the server itself
  produced. Directly contrary to project `CLAUDE.md` Phase 10 (no magic strings; use enums for fixed values).
- **RECOMMENDED FIX** Add `const CANCELLING = 'cancelling';` to `ImportStatus`, reference the constant in
  all three controllers, and add the matching translation key. No migration is required — the column is a
  string, and `cancelling` is never persisted, only computed.
- **RELATED FILES** all six import/export controllers, `lang/en/message.php`, `lang/ar/message.php`

### BE-016

- **SEVERITY** MEDIUM
- **AREA** Model / two competing definitions of "terminal"
- **FILE** `packages/marvel/src/Database/Models/Import.php`
- **METHOD** `isCompleted()`
- **PROBLEM**
  ```php
  public function isCompleted(): bool {
      return in_array($this->status, [
          ImportStatus::COMPLETED, ImportStatus::COMPLETED_WITH_ERRORS, ImportStatus::FAILED,
      ]);   // CANCELLED is missing
  }
  ```
  Meanwhile every controller and job inlines
  `in_array($status, ['completed','completed_with_errors','failed','cancelled'], true)` — the four-value
  version — in roughly nine places.
- **ROOT CAUSE** `isCompleted()` predates `cancelled` and was never updated; callers worked around it by
  inlining their own arrays instead of fixing the model.
- **IMPACT** A cancelled import is not "completed" according to the model, so any caller relying on
  `isCompleted()` treats a finished operation as still running — the terminal guard in `handle()` would
  re-enter a cancelled import on retry. The nine duplicated inline arrays are a DRY violation and a
  standing invitation for the next status to be added in eight places and missed in the ninth.
- **RECOMMENDED FIX** Add `CANCELLED` to `isCompleted()`, or better add `isTerminal()` with all four
  values and keep `isCompleted()` for the narrower "finished successfully" question if any caller needs
  it. Replace every inline array with the single model method (or an `ImportStatus::terminal()` helper).
- **RELATED FILES** `Import{Products,Categories,Brands}Job::handle()` terminal guards,
  `{Category,Brand}ExportController::status()`, all three `ImportController::status()`

### BE-017

- **SEVERITY** MEDIUM
- **AREA** API contract / `status()` response shape differs per domain
- **FILE / METHOD** `ProductImportController::status()` vs `CategoryImportController::status()` and
  `BrandImportController::status()`
- **PROBLEM** Product returns `success_rows` / `failed_rows` and **omits** `created_at`, `completed_at`
  and `error_count`. Category and brand return `successful_rows` plus all three of those keys, including
  `'error_count' => is_array($import->errors) ? count($import->errors) : 0` and
  `'completed_at' => $isTerminal ? … : null`.
- **ROOT CAUSE** Independent evolution of three controllers with no shared API Resource.
- **IMPACT** The frontend needs three code paths for one conceptual endpoint, and `success_rows` vs
  `successful_rows` is a silent `undefined` in any shared progress component — the bar renders zero
  instead of erroring. Violates project `CLAUDE.md` Phase 6 (one standard response envelope) and Phase 4
  (controllers return a Resource).
- **RECOMMENDED FIX** One `ImportStatusResource` used by all six controllers, emitting the union of keys
  under stable names. `success_rows` → `successful_rows` is a **breaking** change: coordinate with the
  frontend, or emit both keys for one release and drop the old one after (§27 item 4).
- **RELATED FILES** `packages/marvel/src/Http/Resources/`, `{Category,Brand}ExportController::status()`

### BE-018

- **SEVERITY** MEDIUM
- **AREA** Route hardening / unconstrained `{id}` against `int` type-hints
- **FILE** `packages/marvel/src/Rest/routes.php`
- **METHOD** route definitions L138-143, L156-161, L227-229
- **PROBLEM** None of the nine import/export `{id}` routes constrain the parameter, while `whereNumber`
  is used **19 times** elsewhere in the same file (orders L178, site-reviews L208-210, currencies
  L213-215, digital-assets L234-235, cart L349, shipments L414-417, invoices L426-430). All six
  controllers type-hint `int $id`.
- **ROOT CAUSE** The import/export routes were added without following the file's own established pattern.
- **IMPACT** `GET products/import/abc` produces a `TypeError` during route binding → 500 with a stack
  trace instead of a clean 404. It also leaves the routes fragile against any future literal segment
  added after them, since an unconstrained `{id}` matches anything.
- **RECOMMENDED FIX** Append `->whereNumber('id')` to all nine routes, matching the sibling precedent in
  the same file. This also complements BE-003, where the ownership scope should likewise 404 rather than
  reveal existence.
- **RELATED FILES** all six import/export controllers

### BE-019

- **SEVERITY** MEDIUM
- **AREA** Concurrency / predictable error-report path plus destructive send
- **FILE / METHOD** `{Product,Category,Brand}ImportController::downloadErrors()`
- **PROBLEM** Each writes a fixed path — `failed_import_rows_{$id}.xlsx`,
  `failed_category_import_rows_{$id}.xlsx`, `failed_brand_import_rows_{$id}.xlsx` — to the `local` disk
  via `Excel::store()`, then serves it with `->deleteFileAfterSend(true)`.
- **ROOT CAUSE** The filename is derived solely from the import id, so it is stable across concurrent
  requests for the same import.
- **IMPACT** Two concurrent downloads for the same import collide on one path: the first response's
  `deleteFileAfterSend` removes the file the second is still streaming, producing a truncated download or
  a 500. A double-clicked download button is enough to trigger it. The path is also fully predictable
  within `storage/app`.
- **RECOMMENDED FIX** Add a random component to the filename (`Str::random(8)`), or better stream directly
  with `Excel::download()` and skip the intermediate file entirely — the error set is already in memory
  from the `errors` column.
- **RELATED FILES** `packages/marvel/src/Database/Models/Import.php` (`errors` cast), BE-004

### BE-020

- **SEVERITY** MEDIUM
- **AREA** Permissions / bulk catalogue extraction gated on a read permission
- **FILE** `packages/marvel/src/Rest/routes.php` L225-230, `packages/marvel/src/Enums/Permission.php` L240-243
- **METHOD** route middleware; `ProductExportController::__construct()`, `ProductImportController::__construct()`
- **PROBLEM** `GET products/export` requires `VIEW_PRODUCTS|SUPER_ADMIN`, and `POST products/import`
  requires `CREATE_PRODUCT|SUPER_ADMIN`. `Permission` defines `IMPORT_CATEGORY`, `EXPORT_CATEGORY`,
  `IMPORT_BRAND`, `EXPORT_BRAND` at L240-243 but **no product equivalents**, so the product routes had
  nothing correct to reference.
- **ROOT CAUSE** The product pipeline was built before the dedicated import/export permissions existed and
  was never revisited when categories and brands got theirs.
- **IMPACT** Any role that can view the product list can exfiltrate the **entire catalogue** — all 20
  columns including `price`, `has_discount`, `discount_amount` and `quantity`, plus every category, brand
  and image association — in a single request. Bulk extraction is a materially different privilege from
  paginated reads, and the current gate does not distinguish them. Symmetrically, "create one product"
  and "overwrite the catalogue from a file" share one permission.
- **RECOMMENDED FIX** Add `const IMPORT_PRODUCT = 'import-product';` and
  `const EXPORT_PRODUCT = 'export-product';` to `Permission`, apply them in the two controller
  constructors, and **seed them onto the roles that currently rely on `CREATE_PRODUCT`/`VIEW_PRODUCTS`**
  so no existing operator is locked out by the change. The seeding step is not optional — without it this
  fix is a production outage for staff accounts.
- **RELATED FILES** permission seeder, `tests/Feature/Categories/CategoryPermissionTest.php` (the pattern
  to mirror for the new cases)

### BE-021

- **SEVERITY** MEDIUM
- **AREA** Dead code / a complete queued export job that nothing dispatches
- **FILE** `packages/marvel/src/Jobs/ExportProductsJob.php`
- **METHOD** entire class
- **PROBLEM** A full `ShouldQueue` product-export job exists — constructor, `handle()`, status updates,
  broadcast, `failed()` — and a repository-wide search finds references **only** in its own declaration
  and in the Composer classmap. There is no dispatch site and no route that would reach one.
- **ROOT CAUSE** The async product export was started, then `ProductExportController::export()` was
  implemented synchronously instead, and the job was left in place.
- **IMPACT** Misleading dead code: a reader who greps for `ExportProductsJob` concludes product export is
  queued like the other two, which hides the real asymmetry (BE-022) behind plausible-looking
  infrastructure. It also drifts — the job is not exercised by any test, so it will silently rot against
  the exporters it calls.
- **RECOMMENDED FIX** Decide one way: either wire it up as part of BE-022 (preferred — it is most of the
  work already done) or delete it. Do not leave a queued job dispatched from nowhere.
- **RELATED FILES** `packages/marvel/src/Http/Controllers/ProductExportController.php`,
  `packages/marvel/src/Jobs/ExportCategoriesJob.php` (the working reference)

### BE-022

- **SEVERITY** MEDIUM
- **AREA** Architecture / product export is synchronous while the other two are queued
- **FILE / METHOD** `packages/marvel/src/Http/Controllers/ProductExportController.php` → `export()`
- **PROBLEM** The method ends in
  `return (new ProductsExport($filters))->download($filename);` — it builds all **8 sheets**, six of which
  perform unbounded `->get()` loads (BE-010), **inside the HTTP request**, and returns the binary
  response directly. Categories and brands instead create an `Import` row, dispatch a job, return `202`
  with an `export_id`, and expose `status` and `download` routes.
- **ROOT CAUSE** Two architectures adopted at different times; the async path was never back-ported to
  products (and its job was abandoned mid-flight — BE-021).
- **IMPACT** Guaranteed request timeout or memory exhaustion at catalogue scale, with no progress
  reporting, no cancellation, and no retry — the admin gets a spinner and eventually a 502. Because there
  are no `products/export/{id}` status or download routes, the frontend must special-case products for
  what is conceptually the same operation, and the operator has no artifact to re-download if the
  browser drops the response.
- **RECOMMENDED FIX** Move product export onto the async path using the existing `ExportProductsJob`
  (BE-021), and add `GET products/export/{id}` and `GET products/export/{id}/download` mirroring
  `CategoryExportController`. Keep a synchronous path only behind an explicit small-result guard, if at
  all. Fix BE-010 first or the job will simply OOM on the worker instead of in the request.
- **RELATED FILES** `packages/marvel/src/Rest/routes.php`, `{Category,Brand}ExportController`,
  `packages/marvel/src/Exports/ProductsExport.php`

### BE-023

- **SEVERITY** MEDIUM
- **AREA** Validation / an implemented feature has no way in through the API
- **FILE** `packages/marvel/src/Http/Requests/ProductImportRequest.php`
- **METHOD** `rules()`
- **PROBLEM** The rule set is only
  `['file' => ['required','file','mimes:xlsx,xls,ods','max:20480']]`. There are no rules for
  `images_source` or `zip_file` — yet the `imports` table has `images_source` and `zip_file_path`
  columns, `Import::$fillable` lists both, and
  `packages/marvel/src/Services/Import/ImageHandlers/ZipImageHandler.php` is fully implemented.
- **ROOT CAUSE** The ZIP image path was built bottom-up (column, handler, fillable) and the request/
  controller wiring at the top was never completed.
- **IMPACT** A whole implemented feature — importing product images from an uploaded ZIP rather than by
  remote URL — is unreachable. `images_source` and `zip_file_path` are always `null`, `ZipImageHandler` is
  effectively dead, and the only working image path is the URL one, which is exactly the path that breaks
  on round-trip (BE-011). Operators on a private network have no usable image import at all.
- **RECOMMENDED FIX** Add validated `images_source` (`nullable|in:url,zip`) and
  `zip_file` (`required_if:images_source,zip|file|mimes:zip|max:…`) rules, store the ZIP alongside the
  workbook on the private disk (BE-004), pass both values into `Import::create()`, and select the handler
  from `images_source`. If the feature is not wanted, delete the two columns and the handler instead —
  but do not leave it half-wired.
- **RELATED FILES** `ProductImportController::import()`,
  `packages/marvel/src/Services/Import/ImageHandlers/UrlImageHandler.php`,
  `database/migrations/2026_06_27_000001_create_imports_table.php`

### BE-029

- **SEVERITY** MEDIUM
- **AREA** Data integrity / silent enum fallback on `product_type` and `discount_type`
- **FILE** `packages/marvel/src/Services/Import/ProductImportService.php`
- **METHOD** `buildProductData()` L672-676, L708-712
- **PROBLEM** Invalid enum-ish values are silently replaced with a default instead of failing the row:
  ```php
  672| if (isset($row['product_type'])) {
  673|     $data['product_type'] = in_array($row['product_type'], [ProductType::SIMPLE, ProductType::VARIABLE], true)
  675|         ? $row['product_type'] : ProductType::SIMPLE;      // silent fallback
  676| }
  708| if (isset($row['discount_type'])) { … : DiscountType::PERCENTAGE; }   // silent fallback
  ```
  The neighbouring `item_type` check in the same method (L678-684) does the opposite and correctly
  `throw new \InvalidArgumentException` on an unknown value — so the file contains both behaviours,
  three lines apart.
- **ROOT CAUSE** Two different authors' instincts in one method, with no shared "coerce or reject" policy
  for enum columns in the importer.
- **IMPACT** A spreadsheet that says `product_type = variable` but misspells it (`variabel`, `Variable `
  with a trailing space) converts an existing variable product to **`simple`**, orphaning its variations
  from the storefront, and the row is reported as a **success**. `discount_type` silently becoming
  `percentage` turns a "50 SAR off" intent into "50% off" — a pricing error that reaches customers. The
  operator gets no error row and no signal that anything was substituted.
- **RECOMMENDED FIX** Make both consistent with `item_type`: validate against
  `ProductType::getValues()` / `DiscountType::getValues()` and fail the row with a translated message
  (`message.IMPORT.PRODUCT.INVALID_PRODUCT_TYPE`, `…INVALID_DISCOUNT_TYPE`). A silent default is only
  acceptable for an **absent** cell, never for a present-but-invalid one.
- **RELATED FILES** `Marvel\Enums\ProductType`, `Marvel\Enums\DiscountType`, `Marvel\Enums\ItemType`,
  BE-028 (same method, same class of defect on numeric columns)

### BE-024

- **SEVERITY** LOW
- **AREA** Duplicate class / two `ProductExportRequest` in two namespaces
- **FILE** `app/Http/Requests/ProductExportRequest.php` **and**
  `packages/marvel/src/Http/Requests/ProductExportRequest.php`
- **METHOD** `rules()` in both
- **PROBLEM** Same class name in two namespaces with different rule sets — the `app/` copy additionally
  accepts `shop_id`. `ProductExportController` imports the **Marvel** one; nothing in the repository
  references the `app/` one.
- **ROOT CAUSE** The request was first written in `app/`, then re-created inside the package when the
  controller moved there; the original was never removed.
- **IMPACT** No runtime effect, but a real maintenance trap: editing the `app/` copy appears to change
  nothing, and a reader cannot tell which file governs the endpoint without checking the controller's
  `use` statements. `shop_id` is a leftover from the multi-vendor lineage and is honoured by no sheet
  exporter.
- **RECOMMENDED FIX** Delete `app/Http/Requests/ProductExportRequest.php`. Confirm with
  `grep -rn "ProductExportRequest"` that only the Marvel namespace remains referenced before deleting.
- **RELATED FILES** `packages/marvel/src/Http/Controllers/ProductExportController.php`

### BE-025

- **SEVERITY** LOW
- **AREA** Filtering / a supported filter is silently dropped at the controller boundary
- **FILE / METHOD** `packages/marvel/src/Http/Controllers/ProductExportController.php` → `export()`
- **PROBLEM** The controller collects filters with
  `$request->only(['status','product_type','category_id','brand_id'])` — `item_type` is absent.
  `ProductsSheetExport::query()` L38-40 **does** implement `item_type`, validated against
  `Marvel\Enums\ItemType`, and `ProductExportRequest` does not validate the key either.
- **ROOT CAUSE** `item_type` was added to the sheet exporter without being threaded back through the
  request and the controller's allow-list.
- **IMPACT** `GET products/export?item_type=X` returns `200` with a **completely unfiltered** export. The
  caller has no way to detect that the filter was ignored — the worst shape of failure for a filter,
  because the response looks successful. Low severity only because the data returned is a superset the
  caller is already authorised to read.
- **RECOMMENDED FIX** Add `item_type` to `ProductExportRequest::rules()` as
  `['nullable', Rule::in(ItemType::getValues())]` and to the controller's `only()` list.
- **RELATED FILES** `packages/marvel/src/Exports/Sheets/ProductsSheetExport.php`,
  `Marvel\Enums\ItemType`, BE-009 (the same filter must then be applied to all 8 sheets)

### BE-026

- **SEVERITY** LOW
- **AREA** Job consistency / product job diverges from the two that were written later
- **FILE** `packages/marvel/src/Jobs/ImportProductsJob.php`
- **METHOD** `cleanSignals()`, `cancelSignalFileExists()`
- **PROBLEM** Two divergences from `ImportCategoriesJob` / `ImportBrandsJob`:
  1. `cleanSignals()` unlinks only `cancel_{id}.json`; the other two unlink `cancel_` **and**
     `progress_{id}.json`.
  2. `cancelSignalFileExists()` calls `file_exists()` **without** the preceding
     `clearstatcache(true, $path)` that the other two both perform.
- **ROOT CAUSE** The product job was written first; the fixes went into the later two and were never
  back-ported.
- **IMPACT** (1) Orphan `progress_{id}.json` files accumulate in `storage/app/imports` after every
  product import — unbounded growth of small files in a directory the app also scans. (2) PHP's stat
  cache can hide a `cancel_` file written moments earlier in the same worker tick, so a product-import
  cancel issued just after a stat can be missed for the remainder of the run. Both are real but bounded.
- **RECOMMENDED FIX** Make all three identical — ideally by extracting the shared signal handling into
  one collaborator (§23) rather than by copying the two lines a third time.
- **RELATED FILES** `packages/marvel/src/Jobs/ImportCategoriesJob.php`,
  `packages/marvel/src/Jobs/ImportBrandsJob.php`, BE-006

### BE-027

- **SEVERITY** LOW
- **AREA** Dead code / unused import and unread eager loads on the largest export
- **FILE** `packages/marvel/src/Exports/Sheets/ProductsSheetExport.php`
- **METHOD** file header L5; `query()` L28; `map()` L60+
- **PROBLEM** `use Illuminate\Support\Facades\Schema;` (L5) is never used anywhere in the class.
  `query()` eager-loads `['variations','categories','brands','flash_sales','sliders']` (L28), but `map()`
  returns only scalar product columns and references **none** of those five relations.
- **ROOT CAUSE** The eager loads were presumably copied from a controller listing; the `map()`
  implementation never needed them, and the sibling sheets load the same relations independently.
- **IMPACT** Five relations are hydrated and discarded for every exported product — five extra queries
  plus the full relation graph in memory, on the sheet that already carries the most rows. This is a
  direct contributor to the OOM risk in BE-010, not merely cosmetic.
- **RECOMMENDED FIX** Remove the unused `Schema` import and reduce `with()` to exactly what `map()`
  reads (currently: nothing). Verify by diffing the produced workbook before and after — the output must
  be byte-identical.
- **RELATED FILES** BE-010, `packages/marvel/src/Exports/ProductsExport.php`

### BE-030

- **SEVERITY** LOW
- **AREA** Search index / `saveQuietly()` bypasses the Scout observer
- **FILE** `packages/marvel/src/Services/Import/ProductImportService.php`
- **METHOD** `processProductRow()` L333, L337
- **PROBLEM** Both the update and create paths persist with `saveQuietly()`:
  ```php
  333| $product->fill($data)->saveQuietly();     // update path
  337| $product->saveQuietly();                  // create path
  ```
  `Product` uses `Laravel\Scout\Searchable` (`packages/marvel/src/Database/Models/Product.php` L23, L28),
  and Scout keeps the index current through `ModelObserver`, which listens to model events.
  `saveQuietly()` suppresses all model events, so no `saved` event fires and the index is never touched.
- **ROOT CAUSE** `saveQuietly()` was chosen to avoid the `creating` SKU hook (L106-111) — a legitimate
  need, since the service assigns its own `PRD-{uuid}` SKU at L319 — but it suppresses *every* observer,
  not just that one hook. The SKU concern is therefore compensated; the Scout concern is not.
- **IMPACT** **Conditional.** `config/scout.php` L19 sets `'driver' => env('SCOUT_DRIVER', 'collection')`;
  the `collection` driver queries the database directly and holds no external index, so on the default
  configuration there is nothing to become stale — which is why this is LOW and not HIGH. On any
  deployment that sets `SCOUT_DRIVER` to a real engine (Algolia, Meilisearch, database+index), every
  imported product is **invisible to search** until an unrelated save or a manual
  `php artisan scout:import` runs. A bulk import is exactly the operation most likely to be followed by
  "why can't I find the products I just imported".
- **RECOMMENDED FIX** Keep `saveQuietly()` (the SKU hook must stay bypassed) and call
  `$product->searchable()` explicitly after a successful row — or collect the created/updated ids and
  call `Product::whereIn('id', $ids)->searchable()` once per chunk, which is cheaper and matches Scout's
  batching. Guard it behind a config check if imports on the `collection` driver should skip the call.
- **RELATED FILES** `packages/marvel/src/Database/Models/Product.php` L23/L28/L102-119,
  `config/scout.php` L19, BE-028 and BE-029 (same method family)

---

## 13. Error Handling Analysis

### 13.1 How errors are collected and surfaced

Row-level failures are collected in-service by `addFailedRow($data, $message)` — present in all three
services — and surfaced through **two** channels:

| Channel | Mechanism | Consumer |
|---|---|---|
| `imports.errors` | JSON column, cast to `array` on the model, written once at the end of `handle()` | `status()` (`error_count`), UI |
| Error workbook | `downloadErrors()` builds an export from `$import->errors` | operator, `GET …/import/{id}/errors` |

Error **messages** are translated at the point of collection —
`__('message.IMPORT.CATEGORY.NAME_EN_REQUIRED')`, `__('message.IMPORT.BRAND.DUPLICATE_ROW')`,
`translateImageError()` for download failures. **This is correct and must be preserved** — the
translation happens where the domain context exists, not at the presentation layer.

### 13.2 The ideal error-file structure (described only — not implemented)

The audit request asked for the ideal shape to be *described*. Against the current three-way divergence,
one canonical row would be:

| Field | Purpose | Present today? |
|---|---|---|
| `row_number` | absolute 1-based Excel row, matching what the operator sees in the spreadsheet | yes, but wrong past chunk 1 (BE-013) |
| `identifier` | the natural key of the row — `sku` for products, `name_en` for categories/brands | yes, but under a domain-specific column name |
| `error_type` | a stable machine code (`VALIDATION`, `IMAGE_DOWNLOAD`, `ENUM_INVALID`, `DB_CONSTRAINT`) | **no** — only a free-text message exists |
| `error_message` | the translated human message | yes |
| `original_values` | the row as read, so the operator can correct and re-upload without re-deriving it | **no** — only selected columns are echoed |

`error_type` is the significant gap: today an operator cannot filter "all image failures" from "all
validation failures", and no automated retry can distinguish a transient network failure from a
permanent data error.

### 13.3 Defects in the error path

1. **Retry history is destroyed.** The terminal `$import->update(['errors' => $failedRows])`
   **replaces** the whole array. The `'Attempt N: …'` string appended by a failed earlier attempt is
   overwritten by the successful attempt's error set, so a partially-failing retry sequence leaves no
   trace of what happened on attempt 1 (BE-007).
2. **Only the first validation error per row is reported.** `prepareRow()` accumulates into
   `$data['errors']`, then `addFailedRow()` records `$data['errors'][0]` only. A row failing three
   validations reports one reason; the operator fixes it, re-uploads, and fails again — three round
   trips for one row.
3. **Error-workbook headings are hardcoded English** while the messages inside them are translated —
   `['Sheet','Row','SKU','Error Message']` (product),
   `['Sheet','Row','Name (EN)','Name (AR)','Error Message']` (brand),
   `['Sheet','Row','Name (EN)','Name (AR)','Parent Name (EN)','Error Message']` (category). An Arabic
   operator gets Arabic messages under English column headers (§21).
4. **A failed image fails the whole row.** Valid text fields are discarded because one image URL was
   unreachable. Whether that is correct is a **product decision, not a defect** — flagged in §27 item 2,
   not filed as a BE.
5. **No template guard.** `$row['name_en'] ?? null` degrades a wrong-template upload into
   `NAME_EN_REQUIRED` on *every* row: 270 identical errors instead of one "unrecognised template —
   expected columns: …" message. A single header check before row processing would convert the worst
   error report in the system into the clearest one.
6. **Unbounded `errors` column.** A 10,000-row import in which every row fails writes 10,000 entries
   into one JSON column (§18).

### 13.4 What is correct here

- Translated messages at the collection point — **NO CHANGE REQUIRED**.
- Partial-success semantics (`completed_with_errors`) with the successful rows committed — this is a
  deliberate, correct product decision for bulk import and must not be replaced by all-or-nothing
  (see §20).
- `try/catch` per row in `ProductImportService::processProductRow()` with `DB::rollBack()` in the catch —
  correct, and the model the other two services should follow (§20).

---

## 14. Import Lifecycle

### 14.1 State machine

```
        ┌─────────┐   job picked up    ┌────────────┐
        │ pending │ ─────────────────► │ processing │
        └─────────┘                    └──────┬─────┘
             │                                │
             │ cancel before pickup           ├──► completed              (0 failed rows)
             │                                ├──► completed_with_errors  (some failed, some ok)
             ▼                                ├──► failed                 (successCount === 0)
        ┌───────────┐                         └──► cancelled              (ImportCancelledException)
        │ cancelled │ ◄───────────────────────────
        └───────────┘
```

`cancelling` is **emitted by the API but is not a member of `ImportStatus`** and is never persisted — it
is computed in `status()` from the presence of the cancel signal file (BE-015).

### 14.2 Phase-by-phase trace

| # | Where | Action |
|---|---|---|
| 1 | `{X}ImportController::import()` | validate → `$file->store('imports','public')` (BE-004) → `estimateRowCount()` (BE-012) → `Import::create(status: pending)` → write `progress_{id}.json` → `dispatch(Import{X}Job)->onQueue('meem-high')` → `202 {import_id}` |
| 2 | `Import{X}Job::handle()` pre-flight | cancel-signal guard → if cancelled, unlink upload and return; terminal-status guard → if already terminal, return |
| 3 | `handle()` entry | `status = processing`, `writeExplicitProgress(1.0)` |
| 4 | `countRows()` | second full workbook load (BE-012), `writeExplicitProgress(2.0)` |
| 5 | `Excel::import({X}Import, $path, null, $readerType)` | delegates to the service; per-row or per-phase processing; `flushProgressTick()` during |
| 6 | service | products: row-wise with per-row transaction. categories/brands: `prepareRows` → `upsert` → `assignParents` → `attachImages` |
| 7 | `handle()` terminal | `writeExplicitProgress(99.0)` → `finalizeProgress()` → compute status (BE-005 for products) → `update([status, total_rows, successful_rows, failed_rows, errors, completed_at])` (BE-006 race) → `broadcastFileOperationTerminal()` → `cleanSignals()` (BE-026) → `unlink` upload |

### 14.3 Progress signalling

Progress is **file-based, not queue-based**: `storage_path("app/imports/progress_{id}.json")`, written at
fixed checkpoints (1.0 → 2.0 → per-tick → 99.0 → finalize) and mirrored to the client through
`App\Events\FileOperationEvent` via `App\Traits\BroadcastsFileOperationProgress`. The controller's
`status()` reads the file to compute `progress` and the `cancelling` pseudo-status.

This design is **deliberate and appropriate** — it survives a worker restart, needs no shared cache, and
keeps the HTTP status endpoint free of queue introspection. **NO CHANGE REQUIRED** to the mechanism
itself; the defects around it are the denominator (BE-012), the orphaned files (BE-026), and the missing
`clearstatcache()` (BE-026).

### 14.4 The two-phase category/brand design — deliberate, do not collapse

`prepareRows()` validates every row and downloads **all** images to temp files → `upsertCategories()` /
`upsertBrands()` writes the rows → `assignParents()` resolves `parent_name_en` → `parent_id` **after all
names exist** → `attachImages()` moves temp files into MediaLibrary.

Phase 3 is why a child row may appear **before** its parent in the spreadsheet — a genuinely useful
property for hand-maintained files. Phase 1 downloading images up-front is why a network failure is
discovered before any row is written. **This is good design. NO CHANGE REQUIRED.** Any future refactor
must preserve the ordering, and `assignParents()` must keep writing through the model so
`CategoryHierarchyService` continues to fire (§23).

---

## 15. Export Lifecycle

### 15.1 Categories and brands (async)

| # | Where | Action |
|---|---|---|
| 1 | `{Category,Brand}ExportController::export()` | `Import::create(['type' => '{domain}-export', 'status' => pending, 'file_path' => '', 'file_name' => ''])` — empty strings as NULL sentinels (§18) → `dispatch(Export{X}Job)` → `202 {export_id}` |
| 2 | `Export{X}Job::handle()` | `status = processing` → `$rowCount = $export->collection()->count()` → `$export->store($filename, 'public')` — **the collection is built a second time** (BE-010) |
| 3 | `handle()` terminal | `update([status: completed, file_path, file_name, total_rows, successful_rows, completed_at])` → broadcast |
| 4 | `{X}ExportController::status()` | `Import::findOrFail($id)` — unscoped (BE-003) |
| 5 | `{X}ExportController::download()` | requires `status === 'completed'` and file existence, then serves `Storage::disk('public')->path(...)` (BE-004), **without** `deleteFileAfterSend` |

Job configuration: `$tries = 2`, `$timeout = 600` — deliberately lower than the importers' `3` / `1500`,
which is reasonable for a read-only operation. Both have a `failed()` handler that flips `processing` →
`failed`; that handler cannot run on a hard OOM kill (BE-010).

### 15.2 Products (synchronous)

There is **no lifecycle**: `ProductExportController::export()` builds all 8 sheets in-request and returns
a `BinaryFileResponse`. No `Import` row, no status endpoint, no download endpoint, no cancellation, no
retry, no progress (BE-022). `ExportProductsJob` — a complete queued implementation — sits unused
(BE-021).

### 15.3 Retention

Nothing prunes generated exports. They accumulate on the **public** disk indefinitely under guessable
timestamp-based names (BE-004). Any fix must pair the private disk with a scheduled prune; the
application already has `app/Console/Kernel.php` scheduling and several `app/Console/Commands/*` as
precedent, so no new infrastructure is needed.

---

## 16. Cancellation Analysis

Cancellation is **cooperative and file-based**, and the design is sound: `cancel()` writes
`cancel_{id}.json`; the services poll it via `isCancelled()` at row/phase boundaries and throw
`Marvel\Exceptions\ImportCancelledException`; `handle()` catches that specific exception and finalises
the record as `cancelled` rather than `failed`.

### 16.1 The three paths

| Path | Behaviour | Verdict |
|---|---|---|
| Cancel **before** the job is picked up | `handle()` pre-flight guard sees the signal, unlinks the upload, returns without touching data | **correct — NO CHANGE REQUIRED** |
| Cancel **during** processing | service throws at the next poll point; job finalises `cancelled`; products call `rollbackCreatedData()` | correct in shape, defective in practice — BE-006 |
| Cancel **after** the terminal write | terminal guard rejects it; the record stays terminal | **correct — NO CHANGE REQUIRED** |

### 16.2 Defects

1. **Lost update (BE-006).** `cancel()` writes `status = 'cancelled'` itself, and the still-running
   worker's terminal `update()` overwrites it with `completed` / `completed_with_errors`. The UI shows
   `cancelled`, then flips back. Two writers, one column, no guard.
2. **Missed signal on product imports (BE-026).** `cancelSignalFileExists()` omits `clearstatcache()`.
3. **Rollback is partial by design, and undocumented.** `rollbackCreatedData()` exists in **all three**
   services (`CategoryImportService` L780-791, `BrandImportService` L675-686, `ProductImportService`
   L280-302), but it only soft-deletes records the run **created**. Rows that already existed and were
   **updated** are never restored — there is no before-image. So a cancelled import over an existing
   catalogue leaves every already-processed update permanently applied. For categories and brands,
   cancelling between `upsert` and `attachImages` additionally leaves rows written with no images.

   This is a legitimate engineering trade-off (keeping before-images for a bulk update is expensive),
   but it is **nowhere documented**, and the API tells the operator only `cancelled` — which reads as
   "nothing happened". At minimum the response and the UI should say *how much* was applied; the
   counters needed for that are already on the record.

---

## 17. Security Findings

| # | Finding | Severity | Evidence |
|---|---|---|---|
| S-1 | **IDOR on every status/cancel/download endpoint** — `Import::findOrFail($id)` with no `created_by` and no `type` predicate, in all 9 methods across 6 controllers | CRITICAL | BE-003 |
| S-2 | **Uploads and exports on the `public` disk** — the whole catalogue is fetchable at `/storage/<name>.xlsx` with no auth; the permission middleware is decorative | CRITICAL | BE-004 |
| S-3 | **Bulk extraction gated on a read permission** — `VIEW_PRODUCTS` grants full catalogue export including price, discount and stock | MEDIUM | BE-020 |
| S-4 | **Predictable artifact paths** — timestamp-based export names and `failed_import_rows_{id}.xlsx` are both enumerable | MEDIUM | BE-004, BE-019 |
| S-5 | **Unconstrained `{id}` against `int` type-hints** — a non-numeric id produces a 500 with a stack trace rather than a 404 | MEDIUM | BE-018 |
| S-6 | **Cross-type object confusion** — a `brand-export` id is accepted by `products/import/{id}`, so the permission checked is not the permission that matters | CRITICAL | BE-003 |

### 17.1 What is already correct — do not "harden" these

- **SSRF defence on remote image download is genuinely thorough**: `assertSafeUrl()` → scheme allow-list,
  `resolveHost()` → DNS resolution before connect, `isBlockedIp()` → private/loopback/link-local/reserved
  range denylist, `resolveRedirectUrl()` → re-validation after each redirect,
  `detectMime()` + `isActualImage()` → content sniffing rather than trusting the extension, plus a size
  cap. **Do not weaken any of this to fix the round-trip problem (BE-011)** — fix the exporter's URL
  instead. **NO CHANGE REQUIRED.**
- **Upload validation** is present and correct in shape: `required|file|mimes:xlsx,xls,ods|max:20480`.
  MIME-based, not extension-based.
- **Authentication and rate limiting**: every route is inside
  `Route::middleware(['auth:sanctum','throttle:admin'])`. **NO CHANGE REQUIRED.**
- **Permission middleware** is applied in each controller's constructor (`permission:X|super_admin`) —
  unusual placement versus the route file, but functional and consistent across all six controllers.
  Object-level authorization is what is missing (S-1), not gate-level.
- **Mass assignment**: importers build explicit `$data` arrays and models declare `$fillable`; no
  `$request->all()` reaches a model.

### 17.2 Priority

S-1, S-2 and S-6 are the same fix session: an `ImportPolicy` plus a scoped lookup helper, and a private
disk. Neither requires a schema change, and both are small, contained edits to code that is already
structured to receive them.

---

## 18. Database Findings

### 18.1 Table: `imports`

- **Purpose** — one row per import **or export** operation, for all three domains and both directions.
- **Migration** — `database/migrations/2026_06_27_000001_create_imports_table.php`
- **Relevant columns** — `id`, `type`, `status`, `file_path`, `file_name`, `total_rows`,
  `successful_rows`, `failed_rows`, `errors` (JSON, cast `array`), `images_source`, `zip_file_path`,
  `created_by`, `completed_at`, timestamps
- **Primary key** — `id`
- **Foreign keys** — `created_by` → `users.id`
- **Unique constraints** — none
- **Indexes** — none beyond the primary key and the implicit `created_by` FK index
- **Status fields** — `status` (string, values from `Marvel\Enums\ImportStatus`), `type` (string,
  free-form: `product-import`, `category-import`, `brand-import`, `category-export`, `brand-export`)
- **Relationships** — `creator()` → `User`

### 18.2 Findings

1. **No `exports` table.** Exports reuse `imports` with a `-export` type suffix. This is **acceptable and
   preferred** — the row shape is genuinely the same (an operation with a status, a file and counters),
   and a second near-identical table would double the code. It is only acceptable if `type` becomes a
   first-class discriminator: **indexed** and **enum-validated**. Today it is an unindexed free string
   that no query ever filters on — which is precisely what makes BE-003's cross-type confusion possible.
2. **No indexes on `type`, `status` or `(created_by, created_at)`.** The UI polls `status()` repeatedly
   during every import, and any admin listing filters on `type`. Both are full scans today. Needed:
   composite `(type, status)` and `(created_by, created_at)`.
3. **`file_path` and `file_name` are NOT NULL, but exports insert `''`.** Empty string as a NULL
   sentinel — the schema says "always present", the code says "not yet". Either make them nullable
   (preferred; the column genuinely has no value until the job finishes) or move export bookkeeping to
   its own columns.
4. **`errors` is an unbounded JSON column.** It holds the entire failed-row set. A 10,000-row import
   where every row fails writes 10,000 structures into one column, which is then read in full by
   `status()` (for `error_count`) and by `downloadErrors()`. A `failed_import_rows` child table, or a
   stored artifact file with only a count on the parent, is the scalable shape. Not urgent at current
   volumes; will become urgent before the row counts do.
5. **`images_source` and `zip_file_path` are in `$fillable` but no request ever populates them** —
   BE-023.
6. **No idempotency guard on submission.** No unique constraint and no `ShouldBeUnique` on the jobs, so a
   double-clicked upload creates two `Import` rows and two workers processing the same file concurrently
   against the same natural keys. `ShouldBeUnique` keyed on the upload hash is the cheap fix.
7. **Slug uniqueness must be verified before BE-007 is fixed.** `Brand::booted()` generates
   `Str::slug($enName)` with **no collision handling**, so "Acme Audio" and "acme audio" produce the
   same slug. If `brands.slug` / `categories.slug` carry unique indexes this surfaces mid-import as a
   `QueryException` — which, given BE-008, aborts the run with rows already committed. The `Category`
   model's `retrieved` hook that repairs JSON-encoded slugs
   (`str_starts_with($category->slug, '{')`) is evidence of prior damage in this area.
   **This is a verification item, not a change to make blindly** (§27).

### 18.3 Tables read but not modified by this audit

`products`, `product_variations`, `categories`, `brands`, `media`, and the pivots
(`category_product`, `brand_product`, `flash_sale_products`, `product_tag`, `slider_product`) are all
written through their models by the importers. **No schema change to any of them is required by this
audit.**

---

## 19. Queue Findings

### 19.1 RabbitMQ — NO CHANGE REQUIRED

`config/queue.php` defines `default => env('QUEUE_CONNECTION','database')` and the connections `sync`,
`database`, `beanstalkd`, `sqs`, `redis`. **There is no RabbitMQ connection, no AMQP driver and no
RabbitMQ package in `composer.json`.**

Import/Export therefore has **no relationship with RabbitMQ whatsoever**, and per the audit constraints
**no RabbitMQ change is proposed. NO CHANGE REQUIRED.**

### 19.2 Queue routing and provisioning — correct

- All six jobs call `$this->onQueue('meem-high')`, which is **not** the `database` connection's default
  queue (`default`), so a dedicated worker is mandatory.
- `deploy/supervisor/laravel-worker-meem-high.conf` provides exactly that:
  `--queue=meem-high`, `numprocs=2`, `--tries=5 --timeout=1200 --memory=256 --max-jobs=1000
  --max-time=3600`. **Provisioning is correct — NO CHANGE REQUIRED.**
- `config/queue.php` `retry_after => 1560` **exceeds** the importers' `$timeout = 1500`, so there is no
  window in which a still-running job is re-dispatched. **This is correct and easy to break — do not
  raise `$timeout` without raising `retry_after` too. NO CHANGE REQUIRED.**

### 19.3 Real mismatches

1. **Job `$timeout` (1500 s) exceeds the worker's `--timeout` (1200 s) and `stopwaitsecs` (1230).** Job
   properties win over CLI flags for the job's own limit, but supervisor's `stopwaitsecs` does not: a
   graceful restart during a long import sends `SIGTERM`, waits 1230 s, then `SIGKILL`s a job that
   believes it has 1500 s. The result is a hard kill mid-row → `failed()` never runs → the record is
   stuck at `processing` forever (BE-008). **Align all three numbers.**
2. **`--tries=5` vs `$tries = 3`** — harmless (the job property wins) but misleading to a reader
   diagnosing retry behaviour.
3. **`--memory=256` is the binding constraint behind BE-010.** Raising it would mask the unbounded
   `->get()` loads rather than fix them; fix the exporters first, then reconsider.
4. **Bulk imports share a queue with latency-sensitive customer work.** The conf header lists
   password-reset email, webhooks, invoices and OTP on `meem-high`. A multi-minute import occupies one of
   two worker processes and delays OTP delivery behind it. A dedicated `meem-bulk` queue is the right
   shape, and `deploy/supervisor/laravel-worker-meem-medium.conf` already exists as the precedent for
   adding one. This is a **deploy** change, not an application change — the only application-side edit is
   the `onQueue()` argument.

### 19.4 Retry configuration

`$tries = 3` with `$backoff = [60, 120, 240]` on the importers, `$tries = 2` / `$timeout = 600` on the
exporters. The values themselves are reasonable. What is unsafe is what a retry *does* — a full
reprocess from row 1 with no watermark and no rollback (BE-007). **Fix BE-007 before trusting `$tries`
> 1.**

---

## 20. Transaction & Data Integrity Findings

### 20.1 The actual state of transactions — measured, not assumed

A single pattern search for `DB::(beginTransaction|commit|rollBack|transaction)` across all three import
services gives an exact picture:

| Service | Lines | Matches | Where |
|---|---|---|---|
| `ProductImportService` | — | **6** | `processProductRow()` L307 / L355 / L358; `processVariantRow()` L391 / L421 / L428 |
| `CategoryImportService` | 970 | **0** | — |
| `BrandImportService` | 807 | **0** | — |

So the picture is the **opposite** of a uniform gap: **products are transactional per row; categories and
brands have no transaction at all.**

`ProductImportService::processProductRow()` L304-369 is the correct pattern already present in the
repository:

```php
304| protected function processProductRow(array $row, int $rowIndex): void
305| {
307|     DB::beginTransaction();
     |     try {
319|         // SKU assignment, PRD-{uuid} when absent
329|         // item_type immutability guard on the update path
333|         $product->fill($data)->saveQuietly();
346|         // ProductPricingService reuse — not reimplemented
355|         DB::commit();
     |     } catch (\Throwable $e) {
357|         DB::rollBack();
     |         $this->addFailedRow(...);
     |     }
369| }
```

### 20.2 Findings

| # | Finding | Severity | Evidence |
|---|---|---|---|
| T-1 | `CategoryImportService` and `BrandImportService` have **zero** transactions — `upsertCategories()` / `upsertBrands()`, `assignParents()` and `attachImages()` all write row-by-row uncommitted-as-a-unit | HIGH | BE-008 |
| T-2 | No job-level transaction anywhere — **deliberate and correct**, required by the `completed_with_errors` contract | — | **NO CHANGE REQUIRED** |
| T-3 | A hard kill (OOM, `SIGKILL` after `stopwaitsecs`) leaves rows committed and the record stuck at `processing`; `failed()` cannot run | HIGH | BE-008, §19.3 |
| T-4 | Retry reprocesses from row 1 with no watermark, so committed rows are re-applied; benign for natural-key upserts, duplicating for media and pivots | HIGH | BE-007 |
| T-5 | `rollbackCreatedData()` compensates only **created** records — updates to pre-existing rows are never restored, in all three services | MEDIUM | §16.2 |
| T-6 | `Import::$errors` and the counters are written once at the end, so a hard kill loses all error detail for the run | MEDIUM | BE-008, §13.3 |
| T-7 | No idempotency key on submission — a double-click yields two concurrent workers on the same file | MEDIUM | §18.2 item 6 |

### 20.3 The correct target shape

- **Keep** per-row (or per-batch) transactions, **not** a whole-file transaction. Wrapping the file would
  destroy partial success, which the UI and the `completed_with_errors` status exist to express.
- **Extend** the product pattern to categories and brands: wrap each `upsert` batch and each
  `assignParents` pass in `DB::transaction`, so an exception mid-batch cannot leave half a batch written.
- **Add** a reconciliation command that marks `processing` imports older than the job timeout as
  `failed` — the only defence against T-3, since no in-process handler can survive a `SIGKILL`.
- **Add** a processed-row watermark so a retry resumes rather than replays (T-4), or accept `$tries = 1`
  as the honest minimum until the watermark exists.

---

## 21. Translation Findings

### 21.1 What is correct — NO CHANGE REQUIRED

| Area | State |
|---|---|
| Row-level import error messages | Fully keyed: `message.IMPORT.{CATEGORY,BRAND}.{NAME_EN_REQUIRED, NAME_AR_REQUIRED, DUPLICATE_ROW, INVALID_STATUS, INVALID_IS_FEATURED, INVALID_IMAGE_URL}` plus `translateImageError()` |
| `CategoryImportRequest` / `BrandImportRequest` | Both define `messages()` mapping `file.required` / `file.mimes` / `file.max` to `message.IMPORT.VALIDATION.*` |
| Translated model content | `Spatie\Translatable\HasTranslations` with `$translatable = ['name','details']` on `Brand` and `Category`; importers write through the model, exporters read via `getTranslation()` / `getTranslations()` |
| Export sheet headings (`name_en`, `sku`, `product_sku`, …) | A **machine contract** consumed by the importer, not user-facing copy. Translating them would break every round trip. **NO CHANGE REQUIRED — and must not be changed.** |

There is **one** translation mechanism app-wide (Spatie), and import/export uses it. No second mechanism
exists and none is proposed.

### 21.2 Gaps

1. **`ProductImportRequest` has no `messages()`.** Product upload validation returns Laravel's default
   English strings (`The file must be a file of type: xlsx, xls, ods.`) while the other two domains return
   localised messages for the identical rules. Contra `CLAUDE.md` Phase 9.
2. **`ProductExportRequest` (both copies) has no `messages()`** either.
3. **Error-workbook column headings are hardcoded English** in all three `downloadErrors()` methods —
   `'Sheet'`, `'Row'`, `'SKU'`, `'Name (EN)'`, `'Name (AR)'`, `'Parent Name (EN)'`, `'Error Message'`.
   These are read by a human operator, in the same file whose message column is translated, so an Arabic
   admin gets Arabic errors under English headers. Keys needed in `lang/en` **and** `lang/ar`.
4. **No product-domain import message keys.** `message.IMPORT.CATEGORY.*` and `message.IMPORT.BRAND.*`
   exist; `message.IMPORT.PRODUCT.*` does not, so product row errors are raw English strings from
   `ProductImportService`. This is the largest of the four gaps in operator impact.
5. **`cancelling` has no translation key** because it has no enum member (BE-015). Any UI label for it is
   currently a client-side literal.

### 21.3 Fix rule

Every key added must be added to **`lang/en/message.php` and `lang/ar/message.php` in the same change**,
under the existing nested `IMPORT.*` structure. Do not introduce a new top-level namespace.

---

## 22. Performance Findings

### 22.1 Measured issues

| # | Location | Issue | Effect |
|---|---|---|---|
| P-1 | `{Product,Category,Brand}ImportController::estimateRowCount()` | Full workbook load **inside the HTTP request** | Seconds of request latency; timeout risk on a 20 MB upload — the maximum the validation allows |
| P-2 | `Import{X}Job::countRows()` | A **second** full workbook load of the same file | Duplicated I/O and peak memory per import |
| P-3 | 6 of 8 product export sheets (`CategoriesSheetExport`, `BrandsSheetExport`, `ImagesSheetExport`, `TagsSheetExport`, `FlashSalesSheetExport`, `SlidersSheetExport`) | Unbounded `->get()` then nest-loop in PHP; `ProductsExport` instantiates all eight, so one export performs **six independent full-table product loads** | OOM at `--memory=256`; worker killed; `failed()` never runs; record stuck `processing` |
| P-4 | `CategoriesExport::loadCategories()` | Runs in the **constructor**; `firstImageUrl()` calls `getMedia()` per category for two collections with `media` **not eager-loaded** | 2N media queries; work happens at instantiation, before anyone asked for data |
| P-5 | `BrandsExport::collection()` | `getFirstMediaUrl()` per brand, `media` not eager-loaded | N+1 |
| P-6 | `Export{Categories,Brands}Job::handle()` | `collection()->count()` for the row count, then `store()` **rebuilds the same collection** | 2× the query and memory cost of every export |
| P-7 | `ProductsSheetExport::query()` L28 | Eager-loads five relations `map()` never reads | Five wasted queries plus the relation graph in memory on the largest sheet (BE-027) |
| P-8 | `imports` table | No `(type, status)` index while the UI polls `status()` throughout every import | Full scan per poll (§18) |
| P-9 | `CategoriesSheetImport`, `BrandsSheetImport`, `ImagesSheetImport`, and the four pivot sheet importers | No `WithChunkReading` | Whole sheet in memory — 370 / 422 rows today, unbounded later |
| P-10 | `ProductExportController::export()` | All of P-3 happens **in-request** rather than on a worker | Guaranteed request timeout at catalogue scale (BE-022) |

`grep -ln WithChunkReading` over `Imports/*.php` and `Imports/Sheets/*.php` matches exactly **two** files:
`ProductsSheetImport.php` and `ProductVariantsSheetImport.php`. Those two are correct
(`chunkSize(): 100`) — though `ProductsSheetImport` then mis-tracks the absolute row number across
chunks (BE-013).

### 22.2 Correct by construction — NO CHANGE REQUIRED

- `ProductsSheetExport` uses `FromQuery`, which is the right base class; only its `with()` list is wrong.
- `config/excel.php` sets `chunk_size => 1000` and a real `local_path`
  (`storage_path('framework/cache/laravel-excel')`) — correct.
- `ProductsSheetImport` / `ProductVariantsSheetImport` implement `WithChunkReading`.
- Per-row transactions in `ProductImportService` are cheap and correctly scoped — they are not a
  performance problem and must not be widened for "efficiency".

### 22.3 Order of attack

P-3 and P-6 first (they are the OOM), then P-1/P-2 (they are the user-visible latency), then P-9
(future-proofing), then P-8 (cheap index). Raise `--memory` only **after** P-3 — otherwise the fix is
concealment.

---

## 23. Existing Architecture Reuse

### 23.1 Already reused correctly — do not duplicate, do not "improve"

| Existing component | How import/export uses it | Verdict |
|---|---|---|
| `App\Services\General\ProductPricingService` | Constructor-injected into `ProductImportService` (`__construct(?int $importId = null, ?ProductPricingService $pricingService = null)`) and called at L346-347 | **Correct. Pricing must never be recomputed inside the importer.** |
| `App\Services\General\CategoryHierarchyService` | `Category::booted()` calls `syncHierarchy()` on `saving` and `updateDescendantLevels()` on `saved` when `parent_id` changed. `assignParents()` writes through the model, so hierarchy maintenance is inherited for free | **Correct. `assignParents()` must never switch to a raw query-builder `update()`.** |
| `Spatie\Translatable\HasTranslations` | The single translation mechanism; importers write and exporters read through it | **Correct — no second mechanism needed (§21).** |
| Slug generation | `Brand::booted()` / `Category::booted()` regenerate the slug from the EN name on `saving` when `name` is dirty and `slug` is not | **Correct. Importers must not compute slugs themselves** (but see §18.2 item 7 on collisions). |
| `Spatie\MediaLibrary` | Collections `categories-desktop` / `categories-mobile`, `brands-desktop` / `brands-mobile`, `products` | Correct. |
| `App\Events\FileOperationEvent` + `App\Traits\BroadcastsFileOperationProgress` | `broadcastFileOperationTerminal()` is the existing seam for progress and terminal notifications, used by all six jobs | **Correct — this is the seam to extend, not to replace.** |
| `Marvel\Exceptions\ImportCancelledException` | Dedicated exception so `handle()` can distinguish cancellation from failure | Correct. |
| `Marvel\Enums\{ImportStatus, Permission, ProductType, DiscountType, ItemType}` | Used rather than string literals — with the three exceptions in BE-015, BE-029 | Mostly correct. |
| `ProductImportService::processProductRow()` per-row transaction | The right pattern, already in the repository | **This is the model for the BE-008 fix — copy it, don't invent something.** |
| Method visibility in the import services | In `CategoryImportService` only `__construct` (L60) and `processRows` (L67) are `public`; the other 18 methods are `protected` | **Correct encapsulation. NO CHANGE REQUIRED.** |

### 23.2 The one real DRY violation — duplicated *infrastructure*, not business logic

`CategoryImportService` (970 lines) and `BrandImportService` (807 lines) each contain a **verbatim copy**
of three method families:

| Concern | Duplicated methods |
|---|---|
| SSRF-safe remote image download | `downloadImage`, `assertSafeUrl`, `resolveHost`, `isBlockedIp`, `resolveRedirectUrl`, `detectMime`, `mimeToExtension`, `isActualImage`, `translateImageError`, `isValidUrlFormat`, `cleanupTempImages` |
| Signals and progress | `signalPath`, `writeSignal`, `isCancelled`, `writeExplicitProgress`, `flushProgressTick`, `finalizeProgress`, `publishProgress` |
| Row helpers | `normalizeText`, `parseBooleanField`, `addFailedRow` |

`ProductImportService` holds a **third** copy of the signal/progress family.

The security consequence outweighs the line count: the SSRF guard exists in **two independent copies**, so
any future patch to `isBlockedIp()` must be applied twice or one domain silently remains vulnerable.

**Extract two collaborators and inject them into all three services** — approximately 400 duplicated
lines removed, and one place to patch:

- `Services\Import\Support\RemoteImageDownloader` — the download + SSRF family
- `Services\Import\Support\ImportSignals` — the signal/progress family

**Check `packages/marvel/src/Services/Import/ImageHandlers/UrlImageHandler.php` and `ZipImageHandler.php`
before creating anything** — they already exist and are the natural home for the download stack. Reusing
them is preferable to a new class (`CLAUDE.md` Phase 2).

### 23.3 What must NOT be created

- No new translation mechanism (§21).
- No `exports` table (§18).
- No RabbitMQ anything (§19).
- No reimplementation of pricing, hierarchy, slug or media logic — all four already have owners.
- No new broadcasting mechanism — `FileOperationEvent` is the seam.
- No abstract base class for the three services purely to share the three method families; composition
  via the two injected collaborators is the correct shape (`CLAUDE.md` Phase 3, composition over
  inheritance).

---

## 24. Exact File-Level Changes

Every entry below names the exact file and method. **Nothing in this section is executed under this
audit** — this is the specification a future implementation phase would follow. Entries are grouped by
phase; the phases are sequenced in §28.

### Phase A — restore basic function (CRITICAL)

#### A-1

- **FILE** `packages/marvel/src/Http/Controllers/BrandImportController.php`
- **METHOD** `downloadSample()` (L273-279)
- **CURRENT PROBLEM** `throw new FileNotFoundException($samplePath);` with no `use` for that class; the
  use-list ends at L16 (`Symfony\Component\HttpFoundation\BinaryFileResponse`).
- **ROOT CAUSE** The unqualified name resolves to `Marvel\Http\Controllers\FileNotFoundException`, which
  does not exist. The controller was copied from `CategoryImportController` without its
  `Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException` import.
- **REQUIRED CHANGE** Add the missing `use`. Then replace the raw throw in all three controllers with a
  translated JSON 404 envelope (`message.IMPORT.SAMPLE_NOT_FOUND`) so the endpoint never emits a stack
  trace.
- **WHY** A missing `use` is a fatal `Error`, not a catchable exception — the route returns an HTML 500
  with no envelope, so the client cannot even show a message.
- **DEPENDENCIES** New translation key in `lang/en/message.php` and `lang/ar/message.php` (§21).
- **RISK** None. Adding an import cannot change behaviour on any path that currently works, because no
  path currently works.
- **RELATED FILES** `ProductImportController::downloadSample()`, `CategoryImportController::downloadSample()`

#### A-2

- **FILE** `packages/marvel/src/Http/Controllers/{Product,Category,Brand}ImportController.php`
- **METHOD** `downloadSample()` in each
- **CURRENT PROBLEM** All three resolve `base_path('packages/marvel/resources/{products|categories|brands}/…')`.
  The files actually live under `storage/packages/marvel/resources/{product|category|brands}/` with
  different names — note the singular/plural drift:
  | Expected | Actual |
  |---|---|
  | `products/product-import-sample.xlsx` | `product/products_export_2026-09-01_scraped.xlsx` |
  | `categories/category-import-sample.xlsx` | `category/niceone_categories.xlsx` |
  | `brands/brand-import-sample.xlsx` | `brands/brand-import-sample.xlsx` — correct name, wrong root |
- **ROOT CAUSE** Sample assets were relocated into `storage/` and renamed; the three controllers were
  never updated, and directory pluralization diverged in the move.
- **REQUIRED CHANGE** Add one config key per domain (e.g. `config('marvel.import.samples.brand')`)
  resolving to the real `storage_path(...)`; read it in `downloadSample()`; guard with `is_file()` before
  constructing the response. Do not hardcode paths in the controllers again.
- **WHY** Three hardcoded paths in three files is what allowed the drift. A config key makes the
  relocation a one-line change and makes the expected location discoverable.
- **DEPENDENCIES** New config entries; A-1's translated 404; §27 item 1 must be answered first — the
  current product and category files are **scraper output, not authored templates**.
- **RISK** Low mechanically. The product decision is the risk: shipping scraper output as the official
  template ships junk data as an example.
- **RELATED FILES** `storage/packages/marvel/resources/*`, `config/marvel.php`

#### A-3

- **FILE** `packages/marvel/src/Jobs/ImportProductsJob.php`
- **METHOD** `handle()` — terminal status computation
- **CURRENT PROBLEM**
  ```php
  $status = 'completed';
  if (!empty($failedRows) && $successCount > 0)      { $status = 'completed_with_errors'; }
  elseif (empty($failedRows) && $successCount === 0) { $status = 'failed'; }
  ```
  With `$failedRows` non-empty **and** `$successCount === 0`, both conditions are false and the status
  stays `completed`.
- **ROOT CAUSE** The second predicate should test `$successCount === 0` alone. `ImportCategoriesJob` and
  `ImportBrandsJob` already use exactly `elseif ($successCount === 0)` and are correct.
- **REQUIRED CHANGE** `elseif ($successCount === 0) { $status = ImportStatus::FAILED; }`
- **WHY** An import in which **every row failed** currently reports success. This is the highest-
  consequence silent failure in the subsystem: the operator believes the catalogue was updated when
  nothing was written.
- **DEPENDENCIES** None. One-line change; the correct form already exists in two sibling files.
- **RISK** None — it can only move records from a wrong terminal state to the right one.
- **RELATED FILES** `ImportCategoriesJob::handle()`, `ImportBrandsJob::handle()`

### Phase B — close the security holes (CRITICAL / HIGH)

#### B-1

- **FILE** New `app/Policies/ImportPolicy.php`; `app/Providers/AuthServiceProvider.php`;
  `packages/marvel/src/Http/Controllers/{Product,Category,Brand}ImportController.php`;
  `packages/marvel/src/Http/Controllers/{Category,Brand}ExportController.php`
- **METHOD** `status()`, `cancel()`, `downloadErrors()`, `download()` — nine methods across six
  controllers
- **CURRENT PROBLEM** Every one is `Import::findOrFail($id)` with **no `created_by` predicate and no
  `type` predicate**.
- **ROOT CAUSE** `imports` is a shared table for three domains and two directions; `type` is the only
  discriminator and no query filters on it.
- **REQUIRED CHANGE** Two parts.
  1. `ImportPolicy` with `view`, `cancel`, `download` — owner or super-admin — registered in
     `AuthServiceProvider::$policies`.
  2. Replace each lookup with a type-scoped one:
     `Import::where('type', ImportType::PRODUCT_IMPORT)->findOrFail($id)`, then
     `$this->authorize('view', $import)`. Return **404, not 403**, on a type mismatch so ids cannot be
     enumerated across domains.
- **WHY** Today any authenticated admin can read another admin's import status, **cancel their running
  import**, and download their error report — and `products/import/{id}` accepts a `brand-export` id, so
  the permission the middleware checked is not the permission that governs the object.
- **DEPENDENCIES** Introduce an `ImportType` enum for the six `type` values (currently free strings) —
  `CLAUDE.md` Phase 10. Super-admin bypass must be verified against how `permission:X|super_admin` already
  resolves, so behaviour for super-admins is unchanged.
- **RISK** Medium — this is an authorization tightening. If any existing operational flow relies on one
  admin resuming another's import, it will break. That flow should be made explicit (a policy ability)
  rather than left as an accident.
- **RELATED FILES** `packages/marvel/src/Database/Models/Import.php`,
  `tests/Feature/FileOperations/FileOperationSecurityTest.php`

#### B-2

- **FILE** `config/filesystems.php`; `{Product,Category,Brand}ImportController::import()`;
  `packages/marvel/src/Jobs/Export{Categories,Brands}Job.php` → `handle()`;
  `{Category,Brand}ExportController::download()`
- **METHOD** as listed
- **CURRENT PROBLEM** Uploads use `$file->store('imports','public')` and exports use
  `$export->store($filename,'public')`. `config/filesystems.php` defines `public` as
  `root => storage_path('app/public')`, `url => env('APP_URL').'/storage'`,
  `visibility => 'public'`. `download()` then serves `Storage::disk('public')->path(...)`.
- **ROOT CAUSE** `public` was chosen because it made `download()` able to resolve a filesystem path.
- **REQUIRED CHANGE** Add a private `imports` disk (`root => storage_path('app/private/imports')`,
  `visibility => 'private'`, no `url`). Store uploads and exports there. Serve with
  `Storage::disk('imports')->download($path, $name)` behind the existing permission middleware plus B-1's
  ownership check. Add a random component to generated filenames. Add a scheduled prune command for
  expired artifacts.
- **WHY** The complete product/category/brand catalogue export is currently retrievable over plain HTTP
  at `/storage/categories-export-YYYY-MM-DD-His.xlsx` with **no authentication whatsoever** — the
  `EXPORT_CATEGORY` permission is decorative. Names are timestamp-based and therefore guessable, nothing
  prunes them, and `download()` has no `deleteFileAfterSend`, so every export ever generated is still
  publicly readable.
- **DEPENDENCIES** B-1 (ownership); `app/Console/Kernel.php` for the prune schedule; a one-off migration
  of existing files out of `storage/app/public` (operational, not schema).
- **RISK** Medium. Any frontend that currently deep-links a `/storage/...` export URL will break — that
  is the point of the change, but it must be coordinated. Verify no Blade view or client build embeds
  those URLs before shipping.
- **RELATED FILES** BE-019 (same random-suffix fix applies to the error workbook)

#### B-3

- **FILE** `packages/marvel/src/Enums/Permission.php` (L240-243); `packages/marvel/src/Rest/routes.php`
  (L225-230); the permission seeder
- **METHOD** enum constants; route middleware; seeder
- **CURRENT PROBLEM** `products/export` requires `VIEW_PRODUCTS|SUPER_ADMIN` and `products/import`
  requires `CREATE_PRODUCT|SUPER_ADMIN`, because `Permission` defines only `IMPORT_CATEGORY`,
  `EXPORT_CATEGORY`, `IMPORT_BRAND`, `EXPORT_BRAND` — there is **no product equivalent**.
- **ROOT CAUSE** Product import/export was built before the dedicated permissions pattern existed, and
  reused the nearest CRUD permission.
- **REQUIRED CHANGE** Add `IMPORT_PRODUCT = 'import-product'` and `EXPORT_PRODUCT = 'export-product'`;
  apply them to the product routes; **seed them onto every role that currently holds `CREATE_PRODUCT` /
  `VIEW_PRODUCTS`**.
- **WHY** Any role that can view the product list can currently exfiltrate the entire catalogue — 20
  columns including price, discount and stock — in one request. Bulk extraction is a materially different
  privilege from paginated reads.
- **DEPENDENCIES** **The seeding step is not optional.** Adding the permission to the route without
  granting it to existing roles is an immediate production outage for every staff account.
- **RISK** High if seeding is skipped, low if it is not. Ship the seeder in the same deploy, and verify
  against a copy of production role data.
- **RELATED FILES** `tests/Feature/Categories/CategoryPermissionTest.php` (the pattern to mirror)

#### B-4

- **FILE** `packages/marvel/src/Rest/routes.php`
- **METHOD** route definitions L138-143 (brands), L156-161 (categories), L227-229 (products)
- **CURRENT PROBLEM** None of the nine import/export `{id}` routes constrain the parameter, while
  `whereNumber` appears **19 times** elsewhere in the same file (orders L178, site-reviews L208-210,
  currencies L213-215, digital-assets L234-235, cart L349, shipments L414-417, invoices L426-430).
  Controllers type-hint `int $id`.
- **ROOT CAUSE** Omission; the surrounding convention is already established.
- **REQUIRED CHANGE** Append `->whereNumber('id')` to all nine routes.
- **WHY** `GET products/import/abc` currently produces a `TypeError` → HTTP 500 with a stack trace instead
  of a 404. It also protects the routes from being shadowed if a literal segment is added after them
  later.
- **DEPENDENCIES** None.
- **RISK** None.
- **RELATED FILES** B-1 (which returns 404 on type mismatch for the same enumeration reason)

### Phase C — correctness (HIGH)

#### C-1

- **FILE** `{Product,Category,Brand}ImportController::cancel()`; `Import{Products,Categories,Brands}Job::handle()`
- **METHOD** as listed
- **CURRENT PROBLEM** `cancel()` writes the signal file **and** `Import::where('id',$id)->update(['status' => 'cancelled'])`.
  The still-running worker's terminal `update()` then overwrites `cancelled` with `completed` /
  `completed_with_errors`.
- **ROOT CAUSE** Two writers to one column, with no guard and no optimistic lock on the terminal write.
- **REQUIRED CHANGE** The worker owns the terminal transition. `cancel()` writes the signal and an
  intermediate marker only. Make the worker's terminal write conditional:
  `Import::where('id',$id)->whereNotIn('status',[ImportStatus::CANCELLED])->update([...])`. Also note that
  `->update()` on a query builder bypasses model events — use the model instance if any observer must fire.
- **WHY** Cancellation is silently lost whenever the service does not observe the signal before finishing.
  The UI shows `cancelled`, then flips to `completed`, over a partially-applied catalogue (§16, §20).
- **DEPENDENCIES** BE-015 (`CANCELLING` enum member) makes the intermediate marker expressible.
- **RISK** Low. Verify the `whereNotIn` does not strand a record at `processing` when a cancel signal was
  written but never observed — the reconciliation command in C-2 is the backstop.
- **RELATED FILES** `packages/marvel/src/Exceptions/ImportCancelledException.php`, `Import::isCompleted()`

#### C-2

- **FILE** `packages/marvel/src/Services/Import/CategoryImportService.php`;
  `packages/marvel/src/Services/Import/BrandImportService.php`; new `app/Console/Commands/…`;
  `app/Console/Kernel.php`
- **METHOD** `upsertCategories()` / `upsertBrands()`, `assignParents()`; new command `handle()`
- **CURRENT PROBLEM** `CategoryImportService` (970 lines) and `BrandImportService` (807 lines) contain
  **zero** `DB::beginTransaction` / `DB::transaction` calls. `ProductImportService` has six. Separately, a
  hard kill leaves the record stuck at `processing` because `failed()` cannot run.
- **ROOT CAUSE** Per-row transactions were added to products and never back-ported. No reconciliation
  exists for records orphaned by a `SIGKILL`.
- **REQUIRED CHANGE** Wrap each `upsert` batch and each `assignParents` pass in `DB::transaction`,
  mirroring `ProductImportService::processProductRow()` L307/L355/L358. Add a scheduled command marking
  `processing` imports older than the job timeout as `failed`. **Do not** wrap the whole file in one
  transaction.
- **WHY** A crash mid-batch currently leaves a partial batch committed. And a whole-file transaction would
  destroy the `completed_with_errors` partial-success contract the UI depends on — that contract is
  correct and must be preserved.
- **DEPENDENCIES** §19.3 — align job `$timeout` (1500) with the worker `--timeout` (1200) and
  `stopwaitsecs` (1230) first, or the reconciliation threshold has no coherent value.
- **RISK** Low for the transactions. The reconciliation command must not race a legitimately long-running
  import — derive its threshold from the job timeout, never hardcode minutes.
- **RELATED FILES** `config/queue.php` (`retry_after => 1560`), `deploy/supervisor/laravel-worker-meem-high.conf`

#### C-3

- **FILE** `packages/marvel/src/Jobs/Import{Products,Categories,Brands}Job.php`
- **METHOD** `handle()` — the retry-safety block
- **CURRENT PROBLEM** On a non-final attempt the job appends `'Attempt N: …'` and **rethrows**. `handle()`
  re-enters with `status` still `processing` (so the terminal guard passes) and reprocesses the **entire
  workbook from row 1**. `total_rows` and the counters reset to 0 each attempt, and the terminal
  `update(['errors' => …])` discards the earlier attempt's error set.
- **ROOT CAUSE** No resume checkpoint and no per-row idempotency contract. `rollbackCreatedData()` exists
  in all three services but is invoked only on cancellation, never on failure.
- **REQUIRED CHANGE** Choose one, in order of preference:
  (a) persist a processed-row watermark on the `Import` row and skip rows below it on re-entry;
  (b) call the existing `rollbackCreatedData()` before rethrowing, so each attempt starts clean;
  (c) set `$tries = 1` and surface the failure for a manual re-run.
  Whichever is chosen, assert idempotency of `processProductImage()` and the `syncFlashSales()` /
  `syncSliders()` / `syncTags()` family.
- **WHY** Rows committed by attempt 1 are re-applied by attempt 2. For natural-key upserts (SKU,
  `name_en`) that is a harmless update; for MediaLibrary attachment and the pivot syncs it **duplicates
  media and pivot rows**. Note that `rollbackCreatedData()` only compensates records the run *created* —
  updates to pre-existing rows are never restored, so (b) is partial by nature (§16.2).
- **DEPENDENCIES** (a) needs a nullable watermark column on `imports` (§25). (c) is the honest minimum and
  needs nothing.
- **RISK** (a) is the most code and the most correct. (c) changes operator experience — a transient
  network blip now requires a manual re-upload. Decide explicitly rather than by default.
- **RELATED FILES** `ProductImportService::rollbackCreatedData()` L280-302, `getCreatedProductIds()`,
  `tests/Feature/Phase0/ImportRetrySemanticsTest.php`

#### C-4

- **FILE** New `packages/marvel/src/Exports/Support/ProductExportFilter.php`; all eight
  `packages/marvel/src/Exports/Sheets/*SheetExport.php`
- **METHOD** `collection()` / `query()` in each sheet
- **CURRENT PROBLEM** Each sheet honours a different subset of the five filters, and
  `ImagesSheetExport::collection()` additionally starts from `Product::withTrashed()`:
  | Sheet | status | product_type | item_type | category_id | brand_id | scope |
  |---|---|---|---|---|---|---|
  | `products` | ✓ | ✓ | ✓ | ✓ | ✓ | default |
  | `product_variants` | ✓ | ✓ | — | — | — | default |
  | `images` | — | — | — | ✓ | ✓ | **`withTrashed()`** |
  | `categories` | — | — | — | ✓ | — | default |
  | `brands` | — | — | — | — | ✓ | default |
  | `flash_sales` | — | — | — | — | — | default |
  | `sliders` | — | — | — | — | — | default |
  | `tags` | — | — | — | ✓ | — | default |
- **ROOT CAUSE** Eight independent ad-hoc implementations with no shared filter object.
- **REQUIRED CHANGE** Extract one `ProductExportFilter` (a query scope or an invokable applying all five
  filters) and inject it into all eight sheets. Remove `withTrashed()` from `ImagesSheetExport` unless
  soft-deleted products are an explicit requirement (§27 item 3).
- **WHY** Any filtered export currently produces an **internally inconsistent workbook**: `status=1`
  filters `products` but `images` / `categories` / `brands` / `tags` still emit rows for inactive
  products, and `images` emits rows for **soft-deleted** products that appear in no other sheet.
  Re-importing such a file feeds `product_sku` values the `products` sheet never declared.
- **DEPENDENCIES** BE-025 — `item_type` must reach the controller before it can be applied uniformly.
- **RISK** Low, but the output changes: exports will contain **fewer** rows than before. That is the fix,
  and it should be announced rather than discovered.
- **RELATED FILES** `packages/marvel/src/Exports/ProductsExport.php`, `Marvel\Enums\ItemType`

#### C-5

- **FILE** `packages/marvel/src/Exports/Sheets/{Categories,Brands,Images,Tags,FlashSales,Sliders}SheetExport.php`;
  `packages/marvel/src/Exports/CategoriesExport.php`; `packages/marvel/src/Exports/BrandsExport.php`
- **METHOD** `collection()` in each; `CategoriesExport::loadCategories()` and `firstImageUrl()`;
  `BrandsExport::collection()`
- **CURRENT PROBLEM** Six of the eight product sheets call `->get()` on an unbounded query and nest-loop
  in PHP; `ProductsExport` instantiates all eight, so **one export performs six independent full-table
  product loads**. `CategoriesExport::loadCategories()` runs in the **constructor** and `firstImageUrl()`
  calls `getMedia()` per category for two collections with `media` **not eager-loaded**.
  `BrandsExport` has the same N+1 through `getFirstMediaUrl()`.
- **ROOT CAUSE** `FromCollection` was used where `FromQuery` + chunking was needed; `media` was never
  eager-loaded.
- **REQUIRED CHANGE** Convert the pivot sheets to `FromQuery` + `WithChunkReading` (or `lazy()` /
  `cursor()`); eager-load `media` in `CategoriesExport` and `BrandsExport`; move `loadCategories()` out of
  the constructor into `collection()`. In `Export{Categories,Brands}Job::handle()`, stop building the
  collection twice — derive the count from the same pass that writes the file, or from a `count()` query.
- **WHY** Workers run with `--memory=256`. At catalogue scale the export OOMs, the worker is killed by the
  memory guard, `failed()` never runs, and the record is **stuck at `processing` forever** with no UI
  recovery path. Raising `--memory` conceals this rather than fixing it.
- **DEPENDENCIES** C-4 (the filter must be applied through whatever query shape replaces `->get()`).
  BE-022 depends on this: moving product export onto a worker without this fix just relocates the OOM.
- **RISK** Medium — this rewrites the query layer of eight classes. Verify byte-identical output on a
  fixed dataset before and after.
- **RELATED FILES** `config/excel.php` (`chunk_size => 1000`), BE-027 (the wasted eager loads)

#### C-6

- **FILE** `packages/marvel/src/Imports/Sheets/ProductsSheetImport.php`
- **METHOD** `collection()` — `$rowIndex = $this->rowOffset + $index + 2;`
- **CURRENT PROBLEM** `chunkSize()` returns 100, but `$rowOffset` defaults to 0 and is **never advanced
  between chunks**.
- **ROOT CAUSE** The offset was designed to be incremented per chunk; the increment was never written.
- **REQUIRED CHANGE** `$this->rowOffset += $rows->count();` at the end of `collection()`, or derive the
  absolute index from a chunk counter.
- **WHY** For any workbook over 100 product rows the reported row numbers restart at 2 in every chunk, so
  the `Row` column in the error workbook collides and points at the wrong spreadsheet rows. The operator
  cannot locate the failing records — silent wrong data, not a crash.
- **DEPENDENCIES** None.
- **RISK** None.
- **RELATED FILES** `ProductImportService::addFailedRow()`, `ProductImportController::downloadErrors()`

#### C-7

- **FILE** `{Product,Category,Brand}ImportController::import()` / `estimateRowCount()`;
  `Import{X}Job::countRows()`
- **METHOD** as listed
- **CURRENT PROBLEM** `estimateRowCount()` sums `getHighestDataRow()` across **all** sheets, header rows
  included — for the 8-sheet product workbook that is 126+1+422+370+126+1+1+1 = **1048** for a file with
  **125** product rows. The job then recomputes with `countRows()` — a **second full load of the same
  workbook** — and finally overwrites `total_rows` with `successCount + count(failedRows)`.
- **ROOT CAUSE** Row counting is implemented twice with two different definitions of "row", and neither
  excludes non-primary sheets or the heading row.
- **REQUIRED CHANGE** Count **once**, in the job, from the primary sheet only, excluding the heading row.
  Delete `estimateRowCount()` from the controllers so `POST …/import` only validates, stores and
  dispatches.
- **WHY** The progress denominator is up to 8× too large, so the bar crawls to ~12% and then jumps to
  100%. More seriously, the controller's load happens **inside the HTTP request** on a file up to the
  20 MB validation limit — seconds of latency and a real timeout risk.
- **DEPENDENCIES** None.
- **RISK** Low. `total_rows` will be `null`/`0` between dispatch and the job's first checkpoint; the UI
  must tolerate that (it already tolerates `progress` being absent before the first tick).
- **RELATED FILES** `writeExplicitProgress()`, `flushProgressTick()`, `finalizeProgress()` in all three services

#### C-8

- **FILE** `packages/marvel/src/Exports/Sheets/ImagesSheetExport.php`;
  `packages/marvel/src/Services/Import/ImageHandlers/UrlImageHandler.php`
- **METHOD** `collection()` (emits `$media->getUrl()`); the `downloadImage()` / `assertSafeUrl()` /
  `isBlockedIp()` stack
- **CURRENT PROBLEM** The exporter writes absolute URLs built from `APP_URL`. The importer's SSRF guard
  blocks private, loopback and link-local addresses. On any environment whose `APP_URL` resolves to such
  an address, **every** image row fails on re-import.
- **ROOT CAUSE** Two individually correct decisions in conflict: export by URL, import with a
  private-network denylist.
- **REQUIRED CHANGE** Emit a stable media identifier (media id, or a storage-relative path) alongside the
  URL, and on import prefer the local reference when the URL host matches `APP_URL`. **Keep
  `assertSafeUrl()` and `isBlockedIp()` exactly as they are** for genuinely external URLs.
- **WHY** Export → re-import is broken on local, Docker and any staging behind an internal load balancer.
  It also makes production fetch from itself over HTTP for every image — slow and fragile even when it
  works.
- **DEPENDENCIES** Adding a column to the `images` sheet changes the header contract (§11), so the
  importer must accept both shapes for at least one release.
- **RISK** **Do not weaken the SSRF guard.** That is the tempting one-line "fix" and it would convert a
  round-trip inconvenience into a server-side request forgery vulnerability.
- **RELATED FILES** `ZipImageHandler.php`, §17.1

#### C-9

- **FILE** `packages/marvel/src/Services/Import/ProductImportService.php`
- **METHOD** `buildProductData()` L672-676 (`product_type`), L708 (`discount_type`); numeric coercion on
  the update path (BE-028); `processProductRow()` L333/L337 (`saveQuietly`)
- **CURRENT PROBLEM** Three silent-coercion defects in one method family: an invalid `product_type` falls
  back to `SIMPLE`, an invalid `discount_type` falls back to `PERCENTAGE`, and numeric fields coerce to 0
  on the update path — while `item_type` three lines away at L678-684 correctly
  `throw new \InvalidArgumentException`.
- **ROOT CAUSE** Defensive defaulting applied to *present-but-invalid* cells rather than to *absent* ones.
- **REQUIRED CHANGE** Validate every enum-backed field against `ProductType::getValues()` /
  `DiscountType::getValues()` and **fail the row** with a translated message, matching the `item_type`
  precedent already in the file. Distinguish "cell absent" (default is fine) from "cell present and
  invalid" (must fail). Fix the numeric coercion so a blank cell does not overwrite an existing price
  with 0. Call `$product->searchable()` (or batch `Product::whereIn('id',$ids)->searchable()`) after
  successful rows to compensate for `saveQuietly()` suppressing Scout's observer.
- **WHY** A misspelled `product_type` currently converts a variable product to `simple` and reports
  success; `discount_type` silently becoming `percentage` turns "50 SAR off" into "50% off" — a pricing
  error reported as a successful import. A silent default is acceptable for an **absent** cell, never for
  a present-but-invalid one.
- **DEPENDENCIES** New `message.IMPORT.PRODUCT.*` keys in `lang/en` and `lang/ar` (§21 gap 4).
- **RISK** Low-medium: rows that previously "succeeded" with coerced values will now fail. That is
  correct, but it will look like a regression to anyone re-running an old file. Announce it.
- **RELATED FILES** `Marvel\Enums\{ProductType,DiscountType,ItemType}`, `config/scout.php` L19,
  `packages/marvel/src/Database/Models/Product.php` L23/L28

### Phase D — consistency and cleanup (MEDIUM / LOW)

#### D-1

- **FILE** `packages/marvel/src/Imports/CategoriesImport.php`, `packages/marvel/src/Imports/BrandsImport.php`
- **METHOD** `title()`, `collection()`
- **CURRENT PROBLEM** Both implement `WithTitle` — an **export** concern — and neither implements
  `WithMultipleSheets`, so Laravel Excel applies the same importer to **every** sheet in the uploaded
  workbook and calls the service's `processRows()` once per sheet.
- **ROOT CAUSE** `WithTitle` was assumed to select a sheet by name on import. It does not.
- **REQUIRED CHANGE** Implement `WithMultipleSheets` returning the intended sheet (by index `0`, or by
  name with an explicit "sheet not found" validation error) and drop the misleading `WithTitle`.
  **`ProductsImport` already does this correctly with eight named keys — copy that pattern.**
- **WHY** A workbook with a stray second sheet runs `prepareRows` → `upsert` → `assignParents` →
  `attachImages` twice, with counters accumulating and parent resolution running against a partial name
  map. The category sample's sheet is literally named `Sheet1` while the declared title is `categories`,
  and it still imports — which is what has masked the dead interface.
- **DEPENDENCIES** None. **Does not apply to products** — `ProductsImport` is already correct.
- **RISK** Low, but selecting by name would reject the existing `Sheet1` sample; select by index, or accept
  both.
- **RELATED FILES** `ImportCategoriesJob::handle()`, `ImportBrandsJob::handle()`, `Marvel\Imports\ProductsImport`

#### D-2

- **FILE** `packages/marvel/src/Enums/ImportStatus.php`; `packages/marvel/src/Database/Models/Import.php`;
  the ~9 sites inlining the terminal array
- **METHOD** enum constants; `isCompleted()`; terminal guards in all six controllers and all six jobs
- **CURRENT PROBLEM** `ImportStatus` has no `CANCELLING`, yet all three `status()` methods return
  `$effectiveStatus = $cancelPending ? 'cancelling' : $import->status;`. Separately `isCompleted()` omits
  `CANCELLED`:
  ```php
  public function isCompleted(): bool {
      return in_array($this->status, [
          ImportStatus::COMPLETED, ImportStatus::COMPLETED_WITH_ERRORS, ImportStatus::FAILED,
      ]);   // CANCELLED is missing
  }
  ```
  while ~9 other places inline
  `in_array($status, ['completed','completed_with_errors','failed','cancelled'], true)`.
- **ROOT CAUSE** Two competing definitions of "terminal" — one on the model, one copied inline — and a
  status the API emits that the enum never learned about.
- **REQUIRED CHANGE** Add `const CANCELLING = 'cancelling';` and reference it in the controllers. Add
  `CANCELLED` to `isCompleted()` or introduce `isTerminal()`, and replace every inline array with it.
- **WHY** A value the API emits has no enum member, no DB representation and no translation key, so any
  client switching on `ImportStatus` hits an unknown case; and a cancelled import is currently "not
  completed" to any caller using the model method. Contra `CLAUDE.md` Phase 10.
- **DEPENDENCIES** **No migration needed** — the column is a string and `cancelling` is never persisted.
  C-1 uses the new member for the intermediate marker.
- **RISK** Low. Auditing all ~9 inline sites is the actual work; a missed one preserves the divergence.
- **RELATED FILES** `lang/{en,ar}/message.php` (a label for `cancelling`)

#### D-3

- **FILE** New `packages/marvel/src/Http/Resources/ImportStatusResource.php`; all six controllers
- **METHOD** `status()`
- **CURRENT PROBLEM** Product returns `success_rows` / `failed_rows` and **omits** `created_at`,
  `completed_at` and `error_count`. Category and brand return `successful_rows` plus all three, including
  `'error_count' => is_array($import->errors) ? count($import->errors) : 0`.
- **ROOT CAUSE** Independent evolution with no shared API Resource.
- **REQUIRED CHANGE** One `ImportStatusResource` used by all six controllers, emitting the union of keys
  under stable names.
- **WHY** The frontend needs three code paths for one conceptual endpoint, and `success_rows` versus
  `successful_rows` is a silent `undefined` in any shared component. Contra `CLAUDE.md` Phase 6 (standard
  envelope) and Phase 4 (controllers return a Resource).
- **DEPENDENCIES** The rename is a **breaking API change** — emit both keys for one release, or coordinate
  with the frontend (§27 item 4).
- **RISK** Medium purely because of the client contract, not the code.
- **RELATED FILES** `packages/marvel/src/Http/Resources/`

#### D-4

- **FILE** `packages/marvel/src/Http/Controllers/ProductExportController.php`;
  `packages/marvel/src/Jobs/ExportProductsJob.php`; `packages/marvel/src/Rest/routes.php`
- **METHOD** `export()`; new `status()` and `download()`
- **CURRENT PROBLEM** `export()` ends in `return (new ProductsExport($filters))->download($filename);` —
  eight sheets, six unbounded loads, inside the HTTP request. `ExportProductsJob` is a **complete queued
  implementation** (constructor, `handle()`, status updates, broadcast, `failed()`) that is **dispatched
  from nowhere** — referenced only by its own declaration and the Composer classmap.
- **ROOT CAUSE** The async path was started, the synchronous one was implemented instead, and the job was
  left in the tree.
- **REQUIRED CHANGE** Wire `ExportProductsJob` up: create the `Import` row with
  `type = 'product-export'`, dispatch, return 202 with `export_id`, and add `products/export/{id}` and
  `products/export/{id}/download` mirroring the category controller. Or delete the job — but do not leave
  it dispatched from nowhere.
- **WHY** Guaranteed request timeout and memory exhaustion at catalogue scale, with no progress, no
  cancellation and no retry; and the frontend needs a products-only special case because the status and
  download routes do not exist.
- **DEPENDENCIES** **Fix C-5 first** — moving the export to a worker without fixing the unbounded loads
  simply relocates the OOM from the request to the worker, where `--memory=256` is *lower* than a typical
  PHP-FPM limit.
- **RISK** Medium: this changes the endpoint's response shape from a file download to a 202. It is a
  breaking client change and must be coordinated.
- **RELATED FILES** `{Category,Brand}ExportController`, `Export{Categories,Brands}Job`

#### D-5

- **FILE** `packages/marvel/src/Http/Requests/ProductImportRequest.php`;
  `{Product,Category,Brand}ImportController::import()`
- **METHOD** `rules()`, `messages()`; `import()`
- **CURRENT PROBLEM** `rules()` is only `['file' => ['required','file','mimes:xlsx,xls,ods','max:20480']]`
  and there is no `messages()`. Meanwhile `imports.images_source`, `imports.zip_file_path`,
  `Import::$fillable` and a fully implemented `ZipImageHandler.php` all exist.
- **ROOT CAUSE** The ZIP image path was built end-to-end except for the request layer, so it has no API
  entry point.
- **REQUIRED CHANGE** Add `images_source` (`nullable|in:url,zip`) and
  `zip_file` (`required_if:images_source,zip|file|mimes:zip|max:…`); pass both into `Import::create()`;
  add a `messages()` block mapping to `message.IMPORT.VALIDATION.*` as the other two requests already do.
  **Or** remove the columns and the handler — but decide, rather than leaving a dead feature.
- **WHY** An entire implemented capability is unreachable, and product upload validation returns English
  defaults while category and brand upload validation is localised (§21 gap 1).
- **DEPENDENCIES** Translation keys in `lang/en` and `lang/ar`.
- **RISK** Low. A ZIP upload path needs its own size and traversal review before it is exposed — check
  `ZipImageHandler` for zip-slip handling before enabling it.
- **RELATED FILES** `UrlImageHandler.php`, `database/migrations/2026_06_27_000001_create_imports_table.php`

#### D-6 — small cleanups

| # | FILE / METHOD | REQUIRED CHANGE | WHY | RISK |
|---|---|---|---|---|
| a | `app/Http/Requests/ProductExportRequest.php` | **Delete the file** | Duplicate class name in two namespaces; the controller imports the Marvel one and nothing references this copy, so editing it appears to do nothing (BE-024) | None — verify with `grep -rn ProductExportRequest` first |
| b | `packages/marvel/src/Http/Requests/ProductExportRequest.php` → `rules()`; `ProductExportController::export()` → `$request->only([...])` | Add `item_type`, validated as `Rule::in(ItemType::getValues())` | `ProductsSheetExport::query()` L38-40 already implements it; today the parameter is accepted and **silently ignored** (BE-025) | None |
| c | `packages/marvel/src/Jobs/ImportProductsJob.php` → `cleanSignals()`, `cancelSignalFileExists()` | Unlink `progress_` as well as `cancel_`; add `clearstatcache(true,$path)` before `file_exists()` | Orphan progress files accumulate after every product import; the stat cache can hide a just-written cancel file (BE-026) | None |
| d | `packages/marvel/src/Exports/Sheets/ProductsSheetExport.php` L5, L28 | Remove `use Illuminate\Support\Facades\Schema;` and reduce `with()` to what `map()` reads | Five relations hydrated and discarded per product on the largest sheet (BE-027) | None — diff the workbook before/after to confirm byte-identical output |
| e | `packages/marvel/src/Http/Controllers/{Product,Category,Brand}ImportController.php` → `downloadErrors()` | Add `Str::random()` to the generated filename, or stream with `Excel::download()` and skip the intermediate file | Two concurrent downloads of the same import collide on one path and `deleteFileAfterSend` removes the file the second is still streaming — a double-clicked button is enough (BE-019) | None |
| f | `downloadErrors()` heading arrays in all three controllers | Replace the hardcoded `'Sheet'`, `'Row'`, `'SKU'`, `'Name (EN)'`, `'Error Message'` with translation keys | An Arabic operator gets Arabic messages under English headers (§21 gap 3) | None |

#### D-7

- **FILE** New `packages/marvel/src/Services/Import/Support/RemoteImageDownloader.php` and
  `…/Support/ImportSignals.php`; `CategoryImportService`, `BrandImportService`, `ProductImportService`
- **METHOD** the three duplicated method families listed in §23.2
- **CURRENT PROBLEM** `CategoryImportService` (970 L) and `BrandImportService` (807 L) each hold a
  **verbatim copy** of the SSRF download family (11 methods), the signal/progress family (7 methods) and
  the row helpers (3 methods). `ProductImportService` holds a third copy of the signal/progress family.
- **ROOT CAUSE** The second service was created by copying the first.
- **REQUIRED CHANGE** Extract the two collaborators and constructor-inject them into all three services.
  **Check `Services/Import/ImageHandlers/UrlImageHandler.php` and `ZipImageHandler.php` first** — they
  already exist and are the natural home for the download stack (`CLAUDE.md` Phase 2: reuse before
  create).
- **WHY** The line count is the smaller problem. The **SSRF guard exists in two independent copies**, so
  any future patch to `isBlockedIp()` must be applied twice or one domain silently stays vulnerable.
- **DEPENDENCIES** Should land **after** Phase C, so behavioural fixes are not entangled with a
  ~400-line move.
- **RISK** Medium — it is a large mechanical refactor of security-relevant code. Do it as a pure move with
  no behaviour change, in its own commit.
- **RELATED FILES** §23.2, §17.1

#### D-8 — deploy (not application code)

- **FILE** `deploy/supervisor/laravel-worker-meem-high.conf`; a new
  `deploy/supervisor/laravel-worker-meem-bulk.conf`; the `onQueue()` argument in all six jobs
- **CURRENT PROBLEM** Job `$timeout = 1500` exceeds the worker's `--timeout=1200` and
  `stopwaitsecs=1230`; and multi-minute imports share `meem-high` with password-reset email, webhooks,
  invoices and OTP across only two worker processes.
- **REQUIRED CHANGE** Align the three timeout numbers, and move import/export to a dedicated `meem-bulk`
  queue — `deploy/supervisor/laravel-worker-meem-medium.conf` already exists as the precedent. Raise
  `--memory` only **after** C-5.
- **WHY** A graceful supervisor restart currently `SIGKILL`s a job that believes it has 300 s left,
  leaving the record stuck at `processing`; and a bulk import delays OTP delivery behind it.
- **DEPENDENCIES** C-2's reconciliation threshold derives from these numbers.
- **RISK** Deployment-only. The one application-side edit is the `onQueue()` string, which must ship
  together with the new supervisor config or the jobs will queue with no consumer.
- **RELATED FILES** `config/queue.php` (`retry_after => 1560` — must stay above `$timeout`)

---

## 25. Database Changes

Deliberately minimal. The schema is workable; the gaps are **indexes** and **honesty about nullability**.
No change is proposed to `products`, `categories`, `brands`, `media` or any pivot table — this audit found
no requirement for one.

### 25.1 Proposed migrations

| # | Migration | Change | Why | Risk |
|---|---|---|---|---|
| DB-1 | new | `imports`: add composite index `(type, status)` | Every UI status poll and every "my running imports" listing is a full table scan today; `type` is the *only* discriminator between six logical operations and it is unindexed (§18) | None. Additive; on a table of this size the `ALTER` is instant |
| DB-2 | new | `imports`: add composite index `(created_by, created_at)` | Required by the ownership scope introduced in B-1 — that change turns `findOrFail($id)` into a `where('created_by', …)` query on an unindexed column | None |
| DB-3 | new | `imports`: make `file_path` and `file_name` **nullable**; backfill existing `''` rows to `NULL` | Both export controllers insert `''` as a placeholder that the job fills in later — an empty string used as a NULL sentinel, so "no file yet" and "file named empty" are indistinguishable (§18) | Low. Any code doing `if ($import->file_path)` is unaffected; code doing `strlen()` or `=== ''` must be found first |
| DB-4 | new, **only if C-3 option (a) is chosen** | `imports`: add a nullable `processed_row_watermark` (or reuse a JSON `progress` payload) | A resumable retry needs somewhere to persist the last committed row index (C-3) | None. Skip this migration entirely if C-3 option (b) or (c) is chosen |

### 25.2 Verify before changing — not a proposed change

**Slug uniqueness must be established before BE-007 / C-3 is implemented.** `Brand::booted()` generates
`Str::slug($enName)` with **no collision handling**, so `"Acme Audio"` and `"acme audio"` produce the same
slug. Whether that is a mid-import `QueryException` or a silent duplicate depends on whether
`brands.slug` and `categories.slug` carry unique indexes — which this audit did not resolve, because
adding a unique index to a table that already contains duplicates is a data-integrity decision, not a
refactor. The `Category` model's `retrieved` hook repairing `str_starts_with($category->slug, '{')` is
evidence that this area has been damaged before.

Establish the current state first. If the columns **are** unique-indexed, slug generation needs collision
handling before retries can be made safe. If they are **not**, that is a separate decision to raise
explicitly — not something to fix silently inside an import change (§27 item 5).

### 25.3 Explicitly not proposed

- **No `exports` table.** Reusing `imports` with an indexed, enum-validated `type` discriminator is the
  smaller change and matches the existing model. Creating a second near-identical table would duplicate
  the lifecycle, the broadcasting and the status vocabulary. **NO CHANGE REQUIRED** beyond DB-1.
- **No change to the `errors` JSON column type.** It is unbounded and that is a real concern (§18), but
  moving failed rows to their own table is a larger design change than this audit's findings justify. If
  it becomes a problem, cap the stored array and keep the full set only in the downloadable workbook.
- **No foreign key or column change on `products`, `categories`, `brands`, `media`, or the pivots.**
  **NO CHANGE REQUIRED.**

---

## 26. Testing Plan

> **DESCRIPTIVE ONLY.** No test code is written, no test file is created or modified, and no test is
> executed under this audit. Existing tests were **inspected read-only** to establish current coverage.
> Per the brief, the extracted testing plan is a separate deliverable to be requested explicitly.

### 26.1 Existing coverage (inspected, not run, not modified)

| Suite | Files |
|---|---|
| Products | `tests/Feature/ProductImportTest.php`, `tests/Feature/ProductExportTest.php` |
| Brands | `tests/Feature/Brands/BrandImportExportTest.php` |
| Categories | `tests/Feature/Categories/CategoryImportTest.php`, `CategoryExportTest.php`, `CategoryImportProgressBroadcastTest.php`, `CategoryImportProgressRealPusherTest.php`, `CategoryPermissionTest.php`, `CategoryTranslationTest.php` |
| File operations | `tests/Feature/FileOperations/BrandImportBroadcastTest.php`, `ProductImportBroadcastTest.php`, `ExportBroadcastTest.php`, `BroadcastFailureIsolationTest.php`, `FileOperationSecurityTest.php`, `FileOperationBroadcastTestCase.php` |
| Retry semantics | `tests/Feature/Phase0/ImportRetrySemanticsTest.php` |

**Shape of the existing coverage.** Broadcasting is the best-tested area by a wide margin — six dedicated
files including a shared base case and a failure-isolation test. Authorization has one file
(`FileOperationSecurityTest.php`) and `CategoryPermissionTest.php` covers route permissions rather than
object ownership. Retry has one file. **Coverage is weakest precisely where the CRITICAL and HIGH findings
sit:** authorization *objects*, retry idempotency, export filter consistency, and status computation.

`FileOperationSecurityTest.php` and `ImportRetrySemanticsTest.php` are the natural homes for most of the
cases below and should be read before anything new is created (`CLAUDE.md` Phase 2).

### 26.2 Described cases

Each entry: **Test area / Scenario / Expected behavior / Priority / Suggested location.**

#### Sample download

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Sample download | `GET brands/import/sample` as an authorized admin | 200, `Content-Type` is an xlsx mime, body is a readable workbook. **Must not be a 500** | CRITICAL (BE-001) | new `tests/Feature/FileOperations/ImportSampleDownloadTest.php` |
| Sample download | `GET {products,categories,brands}/import/sample` — all three | 200 with a readable workbook for each | CRITICAL (BE-002) | same file |
| Sample download | Configured sample file absent from disk | Translated 404 envelope, never an uncaught exception | HIGH | same file |
| Sample download | Unauthenticated request | 401 | MEDIUM | same file |

#### Authorization and ownership

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Ownership | Admin B calls `GET products/import/{id}` for admin A's import | 404 (not 403 — avoid enumeration) | CRITICAL (BE-003) | `tests/Feature/FileOperations/FileOperationSecurityTest.php` |
| Ownership | Admin B calls `POST {domain}/import/{id}/cancel` for admin A's running import | 404, and A's import continues to a normal terminal state | CRITICAL (BE-003) | same |
| Ownership | Admin B calls `GET {domain}/import/{id}/errors` for admin A's import | 404, no error rows disclosed | CRITICAL (BE-003) | same |
| Type scoping | `GET products/import/{id}` where `{id}` is a `brand-export` row | 404 | CRITICAL (BE-003) | same |
| Type scoping | `POST brands/import/{id}/cancel` where `{id}` is a category import | 404, and the category import is unaffected | CRITICAL (BE-003) | same |
| Super admin | Super admin reads and cancels another admin's import | 200 — the bypass is intentional and must not regress | HIGH | same |
| Permissions | Role holding only `VIEW_PRODUCTS` calls `GET products/export` | 403 once `EXPORT_PRODUCT` exists | MEDIUM (BE-020) | `tests/Feature/ProductExportTest.php` |
| Permissions | Role holding `EXPORT_PRODUCT` calls `GET products/export` | 200/202 — proves the seeding step of B-3 actually ran | MEDIUM (BE-020) | same |
| Route hardening | `GET products/import/abc` | 404, **not** a `TypeError` 500 | MEDIUM (BE-018) | `FileOperationSecurityTest.php` |

#### File exposure

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Disk privacy | Upload an import, then assert the stored path | File is **not** under `storage/app/public` and has no public URL | CRITICAL (BE-004) | `FileOperationSecurityTest.php` |
| Disk privacy | Complete a category export, then request the guessed public URL `/storage/categories-export-*.xlsx` | 404 | CRITICAL (BE-004) | same |
| Download authz | `GET categories/export/{id}/download` without the permission, and with the permission but not the owner | 403 and 404 respectively | CRITICAL (BE-003, BE-004) | same |
| Concurrency | Two simultaneous `GET {domain}/import/{id}/errors` for the same import | Both responses are complete and well-formed; neither is truncated | MEDIUM (BE-019) | new `tests/Feature/FileOperations/ErrorReportDownloadTest.php` |

#### Status computation and lifecycle

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Status | Product import where **every** row fails | Terminal status is `failed`. **Regression test — this is the highest-consequence silent failure in the subsystem** | HIGH (BE-005) | `tests/Feature/ProductImportTest.php` |
| Status | Product import with some rows failing and some succeeding | `completed_with_errors` | HIGH | same |
| Status | Import with no failures | `completed` | MEDIUM | same |
| Status | Same three scenarios for category and brand | Identical semantics across all three domains | HIGH (BE-005) | `CategoryImportTest.php`, `BrandImportExportTest.php` |
| Enum | Every status the API can emit, including `cancelling` | Each value is an `ImportStatus` member and has a translation key | MEDIUM (BE-015) | new `tests/Feature/FileOperations/ImportStatusContractTest.php` |
| Model | `isCompleted()` / `isTerminal()` against all six statuses | `cancelled` counts as terminal | MEDIUM (BE-016) | unit test alongside the model |
| API contract | `GET {products,categories,brands}/import/{id}` on equivalent records | Identical key sets and key names across the three payloads | MEDIUM (BE-017) | `ImportStatusContractTest.php` |

#### Cancellation

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Cancel | Cancel while the worker is mid-file, then let the worker finish | Final persisted status is `cancelled` and **stays** `cancelled` after the worker's terminal write | HIGH (BE-006) | new `tests/Feature/FileOperations/ImportCancellationTest.php` |
| Cancel | Cancel before the job is picked up | Worker exits early, uploaded file is deleted, status `cancelled`, nothing written to the catalogue | MEDIUM | same |
| Cancel | Cancel after the import has already reached a terminal state | Rejected with a clear message; terminal status unchanged | MEDIUM | same |
| Cancel | Cancel a product import immediately after the signal is written | The signal is observed — covers the missing `clearstatcache()` | LOW (BE-026) | same |
| Cancel | `GET .../import/{id}` while a cancel signal exists but the DB is still `processing` | Response reports `cancelling` | MEDIUM (BE-015) | same |
| Signals | After any product import completes | No orphan `progress_{id}.json` remains in `storage/app/imports` | LOW (BE-026) | same |

#### Retry and transactions

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Idempotency | Run the same import job twice over the same file | No duplicate media, no duplicate pivot rows, no duplicate variants | HIGH (BE-007) | `tests/Feature/Phase0/ImportRetrySemanticsTest.php` |
| Retry history | Attempt 1 fails mid-file, attempt 2 succeeds | The final `errors` payload retains attempt 1's failures rather than replacing them | HIGH (BE-007) | same |
| Watermark | If C-3 option (a) is chosen: attempt 2 after a mid-file failure | Rows below the watermark are not reprocessed | HIGH (BE-007) | same |
| Row atomicity | Exception raised part-way through a single category row | That row leaves **no** partial write; earlier rows stay committed | HIGH (BE-008) | `CategoryImportTest.php` |
| Reconciliation | An `Import` left at `processing` older than the job timeout | The scheduled command marks it `failed` | HIGH (BE-008) | new `tests/Feature/FileOperations/StaleImportReconciliationTest.php` |
| Partial success | Mid-file failure with earlier rows committed | Committed rows remain — this is the `completed_with_errors` contract and must **not** regress into a whole-file rollback | HIGH (§20 T-2) | `CategoryImportTest.php` |

#### Export correctness

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Filter consistency | Export with `status=1` | Every `product_sku` appearing in any of the eight sheets also appears in the `products` sheet | HIGH (BE-009) | `tests/Feature/ProductExportTest.php` |
| Soft deletes | A soft-deleted product with images, then export | Its SKU appears in **no** sheet, including `images` | HIGH (BE-009) | same |
| Filter plumbing | `GET products/export?item_type=…` | The result is actually filtered, not silently unfiltered | LOW (BE-025) | same |
| Filter plumbing | Each of `status`, `product_type`, `category_id`, `brand_id` individually | Each measurably narrows the output | MEDIUM (BE-009) | same |
| Header contract | Every exporter's `headings()` for all 11 sheets | Byte-equal to the sample headers, in the same order, with no renaming or normalization | HIGH (§11) | new `tests/Feature/FileOperations/ExportHeaderContractTest.php` |
| Async export | If D-4 is implemented: `GET products/export` | 202 with an `export_id`; `status` and `download` routes behave as the category ones do | MEDIUM (BE-022) | `ProductExportTest.php` |
| Dead code | `ExportProductsJob` | Either reachable from a dispatch site, or absent from the tree | MEDIUM (BE-021) | architecture assertion, `ProductExportTest.php` |

#### Progress and error reporting

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Row count | Import the 8-sheet product workbook | `total_rows` equals the `products` sheet data-row count (125), **not** the 1048 sum of all sheets including headers | HIGH (BE-012) | `ProductImportTest.php` |
| Request latency | `POST products/import` with a large workbook | The request does not parse the workbook — only validate, store, dispatch | HIGH (BE-012) | same |
| Error rows | A failing import of more than 100 product rows | Reported Excel row numbers are strictly increasing and point at the correct spreadsheet rows across chunk boundaries | HIGH (BE-013) | same |
| Error detail | A row failing several validations at once | All reasons are reported, not only the first | MEDIUM (§13) | `CategoryImportTest.php` |
| Wrong template | Upload a workbook whose headers do not match | **One** clear "unrecognised template" error, not N identical `NAME_EN_REQUIRED` failures | MEDIUM (§13) | same |
| Error workbook | Download the error report in Arabic | Column headings are localised, matching the localised messages inside them | LOW (§21) | `ErrorReportDownloadTest.php` |

#### Round-trip, sheets and data coercion

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Round-trip | Export categories, re-import the produced file | Row counts and both translations match; **no image-download errors**, including when `APP_URL` resolves to a private address | HIGH (BE-011) | new `tests/Feature/FileOperations/ExportImportRoundTripTest.php` |
| SSRF | Import a row whose image URL is an external private/loopback address | Still rejected — the guard must **not** have been weakened to fix the round trip | HIGH (BE-011, §17.1) | `FileOperationSecurityTest.php` |
| Multi-sheet | Upload a category workbook containing a stray second sheet | The intended sheet is imported exactly **once**; counters are not doubled | MEDIUM (BE-014) | `CategoryImportTest.php` |
| Multi-sheet | Upload a category workbook whose sheet is named `Sheet1` | Still imports — sheet selection must not regress the existing sample | MEDIUM (BE-014) | same |
| Coercion | `product_type` present but invalid | Row **fails** with a translated message; the product is not silently converted to `simple` | HIGH (BE-029) | `ProductImportTest.php` |
| Coercion | `discount_type` present but invalid | Row **fails**; not silently defaulted to `percentage` | HIGH (BE-029) | same |
| Coercion | `item_type` present but invalid | Row fails — existing correct behavior, guard against regression | MEDIUM | same |
| Coercion | Update path with a blank numeric cell | The existing price/quantity is preserved, not overwritten with 0 | HIGH (BE-028) | same |
| Search index | Import a product with `SCOUT_DRIVER` set to a real engine | The product becomes searchable despite `saveQuietly()` | LOW (BE-030) | same |
| ZIP images | If D-5 is implemented: `images_source=zip` with a `zip_file` | Validates, persists both columns, and reaches `ZipImageHandler` | MEDIUM (BE-023) | new `tests/Feature/FileOperations/ZipImageImportTest.php` |
| Validation i18n | `POST products/import` with an invalid file, `Accept-Language: ar` | Validation messages are localised, matching category and brand behavior | MEDIUM (§21) | `ProductImportTest.php` |

#### Performance (bounded, deterministic)

| Test area | Scenario | Expected behavior | Priority | Suggested location |
|---|---|---|---|---|
| Query count | Export categories with N categories having media | Query count does not grow with N (no 2N media queries) | HIGH (BE-010) | new `tests/Feature/FileOperations/ExportPerformanceTest.php` |
| Query count | Export products | The eight sheets do not each perform an independent full-table load | HIGH (BE-010) | same |
| Memory | Export a synthetic large catalogue under a 256 MB limit | Completes without exhausting memory | HIGH (BE-010) | same — assert against a bounded fixture, not production volume |

### 26.3 What must not be tested into existence

Three behaviors are correct today and a new test should **lock them in**, not change them:

1. The **`completed_with_errors` partial-success contract** — committed rows survive a later failure. Do
   not write a test that asserts whole-file rollback (§20 T-2).
2. The **SSRF denylist** — private, loopback, link-local and reserved ranges stay blocked, including
   across redirects (§17.1).
3. **Export sheet headings stay untranslated** — they are a machine contract consumed by the importer, not
   user-facing copy (§21.1).

---

## 27. Assumptions and Open Questions

These are flagged, not resolved. Items 1–5 are decisions for a product or data owner; item 6 is a
limitation of the audit method itself. **None should be resolved silently inside an implementation
commit.**

### Item 1 — The product and category "samples" are scraper output, not authored templates

`storage/packages/marvel/resources/**` is **untracked** — it appears in `git status` as a new, uncommitted
path. Two of the three files there are not templates:

- `product/products_export_2026-09-01_scraped.xlsx` — its workbook metadata carries
  `x15ac:absPath url="D:\work\niceone_scraper\output\"`, i.e. it was produced by an external scraper, and
  its `products` sheet holds 125 rows of scraped data.
- `category/niceone_categories.xlsx` — 270 data rows of scraped content, on a sheet named `Sheet1`.
- `brands/brand-import-sample.xlsx` — the only file that is actually shaped like a sample (2 data rows).

**Question.** Fixing BE-002 by pointing the three `downloadSample()` methods at these files makes the
endpoints work, but it also ships scraped junk as the official template an admin downloads and fills in.
Is that acceptable as a stopgap, or should minimal authored templates be committed to the repository
first? The header contracts (§10, §11) are correct in all three files, so an authored template can be
produced from them without any code change. **The correct end state is committed, minimal, tracked
templates.**

### Item 2 — Should a failed image download fail the whole row?

Today it does. A category or brand row whose `image_desktop_url` cannot be fetched is recorded as failed
and **its valid text fields are discarded** — the name and details are not written at all. This is
consistent between the two services and looks deliberate.

**Question.** Is that the intended behavior, or should the row be written and the image failure reported
as a warning? This is a **product decision, not a defect**, which is why it carries no `BE-` number
(§13). It matters more once BE-011 is fixed, because a same-host URL that resolves locally will stop
failing and the observable behavior will change either way.

### Item 3 — Do soft-deleted products belong in an export?

`ImagesSheetExport::collection()` is the only sheet that starts from `Product::withTrashed()`. The other
seven do not. C-4 proposes removing it for consistency.

**Question.** Was `withTrashed()` deliberate — e.g. to preserve media references for restoration — or a
copy-paste artifact? If deliberate, the fix is the reverse: apply it uniformly across all eight sheets and
add a `deleted_at` column to the `products` sheet so a re-import can tell the difference. The current
state, where exactly one sheet includes deleted products, is not defensible either way.

### Item 4 — `success_rows` → `successful_rows` is a breaking API change

D-3 unifies the three `status()` payloads behind one Resource. The product endpoint currently emits
`success_rows` / `failed_rows`; category and brand emit `successful_rows`. Whichever name survives, one
existing client contract breaks.

**Question.** Coordinate with the frontend, or emit both keys for one release and remove the old one
after? This audit recommends the second — it is the only option that does not require a synchronized
deploy — but the deprecation must actually be scheduled, or both keys become permanent.

### Item 5 — Slug uniqueness state is unresolved

Whether `brands.slug` and `categories.slug` carry unique indexes was not established (§25.2). `Brand::booted()`
generates `Str::slug($enName)` with no collision handling.

**Question.** Are duplicate slugs currently possible, and are there existing duplicates? The answer
determines whether C-3 (retry safety) needs slug collision handling as a prerequisite, and whether a
unique index can be added at all. The `Category` model's `retrieved` hook repairing
`str_starts_with($category->slug, '{')` is evidence of past damage in this area, so assume nothing.

### Item 6 — No finding was reproduced by execution

All findings in §12 are **read-only observations of the code**. Per the audit constraints no test was run
and no code was executed, so nothing here has been demonstrated at runtime.

**Provable by inspection alone** — the defect is visible in the source and needs no runtime confirmation:
BE-001 (missing `use`), BE-002 (path mismatch), BE-005 (unreachable branch), BE-013 (offset never
advanced), BE-018 (missing constraint), BE-021 (no dispatch site), BE-024 (duplicate class), BE-025
(missing key in `only()`), BE-026 (divergent method bodies), BE-027 (unused import and eager loads),
BE-029 (fallback vs. throw, three lines apart in one method).

**Behavioural conclusions from the code paths cited** — high confidence, but they should be confirmed by
the corresponding described test in §26 during implementation rather than treated as established fact:
BE-003, BE-004, BE-006, BE-007, BE-008, BE-009, BE-010, BE-011, BE-012, BE-014, BE-015, BE-016, BE-017,
BE-019, BE-020, BE-022, BE-023, BE-028, BE-030.

---

## 28. Implementation Order

Sequenced by dependency and by consequence-of-not-doing-it, not by severity alone. Each phase is
independently deployable.

### Phase A — restore basic function

**A-1 → A-2 → A-3.** Three small, isolated edits with no dependencies between them beyond A-1 preceding
A-2 (the translated 404 A-2 relies on is added in A-1).

Rationale: all three sample-download endpoints are broken today, one of them fatally, so the documented
"download the template first" flow cannot be completed by anyone. A-3 is a one-line change to a comparison
that currently reports a total-failure import as `completed`. **Highest value per line of change in the
entire plan.** Answer §27 item 1 before A-2 lands, or the fix ships scraper output as the official
template.

### Phase B — close the security holes

**B-1 → B-2 → B-3 → B-4**, with DB-1 and DB-2 (§25) landing alongside B-1.

Rationale: B-2 is the widest exposure — the full catalogue export is currently retrievable over
unauthenticated HTTP at a guessable `/storage/…` URL, which makes the `EXPORT_CATEGORY` middleware
decorative. B-1 is the deepest — every status, cancel and download endpoint is an IDOR. B-1 first because
B-2's private download path needs the ownership check to be meaningful.

B-3's **seeding step is not optional**: adding `EXPORT_PRODUCT` to a route without granting it to the roles
that currently rely on `VIEW_PRODUCTS` is an immediate production outage for every staff account. B-4 is
free and should ride along.

Before deploying B-2, grep the frontend build and Blade views for embedded `/storage/` export URLs.

### Phase C — correctness

Order matters here.

1. **C-6, C-7** — self-contained, no dependencies, immediate operator benefit (correct error row numbers,
   honest progress, no workbook parse inside the HTTP request).
2. **C-9** — stop silently coercing invalid enum values and blank numerics. Needs the new
   `message.IMPORT.PRODUCT.*` translation keys.
3. **C-1 → C-2** — cancellation correctness, then per-row transactions for category and brand plus the
   stale-`processing` reconciliation command. C-1 first because C-2's reconciliation threshold assumes a
   single writer to the status column. D-8 (timeout alignment) should land with C-2, since the
   reconciliation threshold derives from those numbers.
4. **C-5 → C-4** — fix the unbounded loads, then apply the shared filter through the new query shape.
   Doing C-4 first would mean writing the filter twice.
5. **C-3** — retry semantics, **after** §27 item 5 (slug uniqueness) is answered and after C-2's
   transactions exist. Option (c), `$tries = 1`, is the honest minimum and can ship immediately if a
   decision on (a) is not forthcoming.
6. **C-8** — export/import round trip, last in the phase because it changes the `images` sheet header
   contract and needs a release where the importer accepts both shapes. **Do not weaken the SSRF guard.**

### Phase D — consistency and cleanup

1. **D-6 (a–f)** — six independent small cleanups, each safe in isolation. Ship first; they cost nothing
   and remove noise from later diffs.
2. **D-2** — `CANCELLING` and a single terminal-status definition. C-1 already depends on the enum member,
   so if Phase C ships first this is partly done.
3. **D-1** — `WithMultipleSheets` for categories and brands, copying the `ProductsImport` pattern.
4. **D-5** — either expose the ZIP image path properly or delete it and its columns. Review
   `ZipImageHandler` for zip-slip before exposing it.
5. **D-3** — the unified `ImportStatusResource`. Gated on §27 item 4 (frontend coordination).
6. **D-7** — extract `RemoteImageDownloader` and `ImportSignals`. Deliberately last: it is a ~400-line
   move through security-relevant code and must not be entangled with behavioural fixes. Pure move, no
   behaviour change, its own commit. Check `UrlImageHandler` / `ZipImageHandler` first.
7. **D-4** — async product export. **Strictly after C-5**, or it relocates the OOM from a PHP-FPM process
   to a worker with a *lower* memory limit. Breaking client change; coordinate.

### Verification at each phase boundary

Described, not executed — see §26. At minimum, per phase: run the `Import|Export`, `FileOperations` and
`Phase0` suites; `php artisan route:list --path=import` and `--path=export` to confirm constraints and
permissions; after Phase B confirm nothing appears under `storage/app/public` and that a direct
`/storage/<name>.xlsx` request 404s; after Phase C exercise a real export → re-import round trip and prove
BE-010 with a large export under `--memory=256` rather than by raising the limit.

### What not to do

- Do not batch phases. Each one changes observable behavior; a combined deploy makes attribution
  impossible.
- Do not raise `--memory` before C-5. That converts a diagnosable OOM into an undiagnosed slow leak.
- Do not start with D-7. Refactoring duplicated code before fixing the bugs in it means fixing each bug
  twice — once in the duplicate, once in the extraction.

---

## 29. Out of Scope

Explicitly untouched by this audit, and not to be changed by the implementation it describes.

| Area | Status | Reason |
|---|---|---|
| `docs/api/*`, `API_STORY.md` | **Not created, not modified** | Project `CLAUDE.md` Phase 17/20: API Documentation Mode is **OFF** by default and activates only on an explicit command phrase, which was not given. That rule has higher priority than any documentation-related instruction |
| RabbitMQ | **NO CHANGE REQUIRED** | Not installed, not configured, no AMQP driver, no package. `config/queue.php` defines only `sync`, `database`, `beanstalkd`, `sqs`, `redis`. Import/Export has no relationship with it (§19.1) |
| Excel sample files | **Not modified** | Opened read-only, as ZIP archives, to read sheet names and headers only. Sample row values were not treated as business requirements |
| Sheet names, column headers, header order, header spelling | **Unchanged** | Reported verbatim (§10). Not normalized, not renamed. The one header addition contemplated anywhere is the optional media reference in C-8, which is additive and backwards-compatible |
| `App\Services\General\ProductPricingService` | **Reused, never duplicated** | Already injected into `ProductImportService` and called at L346-347. Pricing logic must not be reimplemented in an importer (§23.1) |
| `App\Services\General\CategoryHierarchyService` | **Reused, never duplicated** | Inherited automatically through `Category::booted()`. `assignParents()` must keep writing through the model, never a raw builder `update()`, or level maintenance is silently skipped (§23.1) |
| Spatie Translatable / MediaLibrary | **Reused as-is** | The single app-wide translation and media mechanism. No second mechanism proposed (§23.1) |
| `FileOperationEvent` / `BroadcastsFileOperationProgress` | **Extended, not replaced** | The correct existing seam for progress and terminal notification |
| The file-based progress/cancel signal mechanism | **NO CHANGE REQUIRED** | Deliberate and appropriate for cross-process signalling without a queue round trip (§14.3) |
| Two-phase category/brand import | **NO CHANGE REQUIRED** | `prepareRows` → `upsert` → `assignParents` → `attachImages` is what makes child-before-parent row order work. Must not be collapsed (§14.4) |
| The SSRF download guard | **NO CHANGE REQUIRED — do not weaken** | Correct as written. C-8 works around it; it does not relax it (§17.1) |
| Job-level transaction absence | **NO CHANGE REQUIRED** | Required by the `completed_with_errors` partial-success contract (§20 T-2) |
| Method visibility in the import services | **NO CHANGE REQUIRED** | Already correct encapsulation — only the constructor and `processRows()` are public (§23.1) |
| `products`, `categories`, `brands`, `media`, pivot tables | **No schema change** | This audit found no requirement for one (§25.3) |
| An `exports` table | **Not proposed** | Reusing `imports` with an indexed `type` discriminator is the smaller change (§25.3) |
| Test code | **None written, none modified, none executed** | Per the audit constraints. §26 is descriptive only |
| Application code | **Nothing modified** | This document is the only file created by this audit |

---

## End of report

**Scope delivered.** 29 sections. 30 findings — 4 CRITICAL, 10 HIGH, 11 MEDIUM, 5 LOW — each tied to an
exact file and method. Exact file-level changes in four phases (§24), database changes (§25), a
descriptive testing plan (§26), open questions (§27), implementation order (§28) and explicit scope
boundaries (§29).

**Nothing was implemented.** No PHP file, route, migration, service, controller, job, test, configuration
or Excel file was created or modified. No test was run. No command with side effects was executed. The
Excel workbooks were opened read-only as ZIP archives to read sheet names and header rows.

**Next step requires explicit confirmation.** Per the brief, implementation begins only when requested,
and the extracted testing plan is a separate deliverable.
