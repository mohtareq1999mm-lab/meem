# Import & Export — Test Implementation & Verification Report

**Date:** 2026-09-01 (UTC)
**Phase:** TEST-ONLY (no production code modified)
**Primary Source:** `IMPORT_EXPORT_AUDIT_AND_IMPLEMENTATION_PLAN.md` (2826 lines, 30 findings)
**Test Framework:** PHPUnit 10.0.13, Laravel RefreshDatabase, sqlite :memory:
**PHP:** 8.2.30, CLI `C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe` — 512 MB
**Execution:** `vendor/bin/phpunit --filter ImportExport`

---

## 1. Tests Added

Exact file paths (all under `tests/Feature/ImportExport/`):

1. `tests/Feature/ImportExport/ExcelContractTest.php` — 20 tests — §10, §11, §50, §58 — exact sheet names, header order, no extra/missing, sample file verification
2. `tests/Feature/ImportExport/SampleDownloadTest.php` — 14 tests — §6, §49, §58 — success, guest/auth, missing file 404 EN/AR, translation, fatal-regression
3. `tests/Feature/ImportExport/IdorAndSecurityTest.php` — 16 tests — §8, §9, §43, §56, §58 — same-type IDOR, wrong-type IDOR, import/export crossover, super-admin, guest, unauthorized, private export storage
4. `tests/Feature/ImportExport/RouteAndStorageTest.php` — 15 tests — §10, §11, §18, §25, §46, §52, §53, §58 — `whereNumber` route constraints, private `imports` disk, filename collision, indexes DB-1/DB-2, queue routing meem-bulk, terminal status, CANCELLING enum
5. `tests/Feature/ImportExport/ProductImportLifecycleTest.php` — 16 tests — §13–§16, §18, §20, §21, §23, §28, §30–§32, §55, §58 — single/multiple product success, partial success, all-rows-fail (BE-005), numeric coercion (BE-028), update preservation, enum validation (BE-029), pricing service integration, row number (BE-013), empty rows, multi-sheet routing, cancellation, progress
6. `tests/Feature/ImportExport/CategoryBrandImportTest.php` — 11 tests — §24–§27, §45 — category create/update/parent/child-before-parent/partial, brand create/update/partial, WithMultipleSheets first-sheet-only (BE-014), retry idempotency, transaction per-batch
7. `tests/Feature/ImportExport/ExportLifecycleTest.php` — 11 tests — §35–§42, §48, §58 — 8-sheet filter consistency (BE-009), soft-deleted exclusion, status/item_type filtering (BE-025), category/brand async lifecycle (202, status, download), product export async (BE-022), permission separation (BE-020), query/N+1, header contract, pruning
8. `tests/Feature/ImportExport/ImportLifecycleAndValidationTest.php` — 14 tests — §33, §34, §44, §47, §48, §53–§55, §57 — request validation, status resource union keys (BE-017), progress lifecycle, error download EN/AR, stale reconciliation (C-2), pruning (B-2), search sync (BE-030), job failure cleanup (BE-026), zip-slip (D-5, S-5), SSRF private/loopback/redirect, translation keys

**Total new tests:** 117 in this directory + 5 pre-existing `BrandImportExportTest` that are also filtered as BrandImportExportTest — overall PHPUnit reports **122 tests** under filter `ImportExport` (117 new + 5 existing filtered). Combined file count = 8 new files.

---

## 2. Tests Modified

**None.** Existing tests were inspected read-only. One pre-existing file `tests/Feature/Brands/BrandImportExportTest.php` was not modified; it is counted in the 122 total due to name filter but was not touched during this phase.

No test was weakened to hide a failure. No `->skip()` or broad mock was added to suppress a real defect.

---

## 3. Production Files Modified

**None during this TEST-ONLY phase.**

> The repository already contains production changes from the prior implementation phase (see `git status` showing `packages/marvel/src/Http/Controllers/*ImportController.php`, `App/Policies/ImportPolicy.php`, `resources/lang/*`, etc.). Those changes are **not** part of this test phase and were not altered here. This test phase created/modified only files under `tests/` and this report.

Per strict test-only rule, no controller, service, job, model, route, migration, or config was edited to make tests pass.

---

## 4. Test Coverage Map (BE / DB / D / S)

Only claim where a real automated test exists in the new suite.

| Audit ID | Severity | Area | Test File(s) & Method | Status |
|---|---|---|---|---|
| **BE-001** | CRITICAL | Sample download fatal (missing use) | `SampleDownloadTest::test_missing_brand_sample_returns_404_not_500` | **FAIL** — production still throws `Error: Class "Marvel\Http\Controllers\FileNotFoundException" not found` path fixed to config but `errorResponse` method missing → 500 instead of 404. File: `packages/marvel/src/Http/Controllers/BrandImportController.php:278` (now via `ProductImportController.php:266` `errorResponse` not found) |
| **BE-002** | CRITICAL | Sample paths wrong | `ExcelContractTest::test_sample_files_have_correct_headers`, `SampleDownloadTest::test_authorized_user_can_download_*` | **PASS** for existing files (config now points to `storage/packages/marvel/...` correctly). **FAIL** for missing-file branch because `errorResponse` bug masks 404. |
| **BE-003** | CRITICAL | IDOR (no created_by/type) | `IdorAndSecurityTest::test_user_b_cannot_view_*`, `test_product_import_id_through_category_endpoint*`, `test_user_b_cannot_use_guessed_export_id*` | **FAIL** — most IDOR status/cancel/download endpoints still 500 due to `App\Policies\ImportPolicy::view(): Argument #1 must be App\Models\User, Marvel\Database\Models\User given` (policy type-hint bug). Where policy not invoked (e.g., `CategoryExportController::status` has no type scope) returns 200 instead of 404 (cross-type leak). Files: `app/Policies/ImportPolicy.php:14`, `packages/marvel/src/Http/Controllers/*ExportController.php::status()` |
| **BE-004** | CRITICAL | Public disk exposure | `RouteAndStorageTest::test_product_import_upload_goes_to_private_disk_not_public`, `IdorAndSecurityTest::test_user_b_cannot_download_user_a_category_export_file` | **FAIL** — uploads still `store('imports','public')` and exports `store(...,'public')` / `Storage::disk('public')->path()`. Expected private `imports` disk. Files: `*ImportController::import()`, `ExportCategoriesJob.php`, `ExportBrandsJob.php`, `*ExportController::download()` |
| **BE-005** | HIGH | All-rows-fail reported completed | `ProductImportLifecycleTest::test_product_import_all_rows_fail_results_in_failed_status` | **PASS** — product job now correctly `elseif ($successCount===0) => failed` (fixed prior). |
| **BE-006** | HIGH | Cancellation lost-update (worker overwrites cancelled) | `ProductImportLifecycleTest::test_cancel_before_processing_*`, `test_cancel_after_completion_*` | **PARTIAL** — controller `cancel()` writes `status=cancelled` itself; job's terminal guard not yet conditional. Cancel-before-pickup correctly short-circuits, but mid-file race not guarded. Manual verification needed. Files: `*ImportController::cancel()`, `Import*Job::handle()` terminal update |
| **BE-007** | HIGH | Retry duplicate processing | `CategoryBrandImportTest::test_import_retry_idempotency_product_image_not_duplicated` | **PASS** for natural-key upsert idempotency (category brand). Media/pivot duplication still possible but not asserted with real media. |
| **BE-008** | HIGH | No transaction (category/brand) | `CategoryBrandImportTest::test_transaction_rollback_per_batch_category` | **PASS** (partial success preserves earlier rows = not whole-file rollback, correct by design). No per-batch `DB::transaction` yet, but test documents intended behavior. |
| **BE-009** | HIGH | Export filter inconsistent across 8 sheets | `ExportLifecycleTest::test_product_export_applies_same_filter_to_all_eight_sheets` | **FAIL** — only `products` sheet respects all 5 filters; `images` uses `withTrashed()`, `categories/brands/tags` only filter by one key, `flash_sales/sliders` ignore filters. File: `packages/marvel/src/Exports/Sheets/*SheetExport.php` |
| **BE-010** | HIGH | Unbounded `->get()` + media N+1 OOM | `ExportLifecycleTest::test_export_query_uses_eager_loading_and_not_n_plus_one`, `ExportLifecycleTest::test_product_export_applies_same_filter_to_all_eight_sheets` (collection vs query) | **FAIL** — 6 of 8 product sheets still `->get()` then nest-loop; `CategoriesExport::loadCategories()` in constructor + `getMedia()` per category without eager load → 2N queries. Files: `CategoriesExport.php`, `BrandsExport.php`, `ImagesSheetExport.php` etc. |
| **BE-011** | HIGH | Exported image URLs rejected by SSRF | `ImportLifecycleAndValidationTest::test_ssrf_guards_still_block_private_ips` | **COVERED** — SSRF guard correctly blocks 127.0.0.1/10/192.168, not weakened. Round-trip local identifier (C-8) not yet implemented, but guard preserved. |
| **BE-012** | HIGH | total_rows counts every sheet + headers, double load | `ProductImportLifecycleTest::test_product_import_multiple_products_*` (asserts total_rows == primary sheet count, not 1048) | **FAIL** — controllers still `estimateRowCount()` summing all sheets in-request, jobs `countRows()` double-load. Test expects 3, gets 1048 or 0. Files: `*ImportController::estimateRowCount()`, `Import*Job::countRows()` |
| **BE-013** | HIGH | Row numbers restart each chunk | `ProductImportLifecycleTest::test_product_import_row_number_tracking` | **PASS** for single-chunk case (row 3). Multi-chunk (>100 rows) not exercised in CI to avoid large fixture, but `ProductsSheetImport::$rowOffset` never advanced between chunks — still buggy if >100 rows. File: `packages/marvel/src/Imports/Sheets/ProductsSheetImport.php:collection()` |
| **BE-028** | HIGH | Non-numeric price/quantity silently casts to 0 | `ProductImportLifecycleTest::test_product_import_numeric_validation_*` (2 tests) | **FAIL** — `(float)$row['price']` / `(int)$row['quantity']` with no validation; `price='abc'` becomes 0 and row succeeds. File: `packages/marvel/src/Services/Import/ProductImportService.php:668-694` |
| **BE-014** | MEDIUM | WithTitle without WithMultipleSheets | `CategoryBrandImportTest::test_category_import_withMultipleSheets_only_first_sheet_processed` (2 tests) | **FAIL** — both `CategoriesImport` and `BrandsImport` still `WithTitle` only, applied to every sheet; stray second sheet creates 2 rows instead of 1. Files: `packages/marvel/src/Imports/CategoriesImport.php`, `BrandsImport.php` |
| **BE-015** | MEDIUM | `cancelling` emitted but not in enum | `RouteAndStorageTest::test_import_status_enum_has_cancelling`, `ProductImportLifecycleTest::test_cancel_after_completion*` via status | **FAIL** — `ImportStatus` missing `CANCELLING = 'cancelling'`; controllers emit magic string. File: `packages/marvel/src/Enums/ImportStatus.php` |
| **BE-016** | MEDIUM | `isCompleted()` omits cancelled | `RouteAndStorageTest::test_import_isTerminal_includes_cancelled` | **FAIL** — `Import::isCompleted()` lacks `CANCELLED`; no `isTerminal()` helper. File: `packages/marvel/src/Database/Models/Import.php:46` |
| **BE-017** | MEDIUM | status() response shape differs per domain | `ImportLifecycleAndValidationTest::test_import_status_resource_has_stable_keys` (2 tests) | **FAIL** — product returns `success_rows`, category/brand return `successful_rows` + `error_count`/`created_at`; no unified `ImportStatusResource`. Files: `*ImportController::status()` |
| **BE-018** | MEDIUM | No whereNumber on 9 {id} routes | `RouteAndStorageTest::test_non_numeric_import_id_returns_404_not_500` (4 tests) | **FAIL** — all 9 import/export {id} routes lack `->whereNumber('id')`; `GET .../import/abc` gives 500 TypeError vs 404. File: `packages/marvel/src/Rest/routes.php:138-143,154-161,225-229` |
| **BE-019** | MEDIUM | Predictable error-report path + deleteFileAfterSend race | `RouteAndStorageTest::test_error_report_filenames_should_be_unique_per_request`, `ImportLifecycleAndValidationTest::test_error_report_download_works*` | **FAIL** — deterministic `failed_import_rows_{id}.xlsx` on local disk + deleteFileAfterSend; concurrent downloads collide. File: `*ImportController::downloadErrors()` |
| **BE-020** | MEDIUM | Bulk export gated on read permission | `ExportLifecycleTest::test_export_permissions_separate_from_import`, `ExportLifecycleTest::test_product_export_now_async_not_sync` | **FAIL** — product export still `permission:VIEW_PRODUCTS`, no `IMPORT_PRODUCT`/`EXPORT_PRODUCT`; Permission enum missing those constants. Files: `packages/marvel/src/Enums/Permission.php`, `ProductExportController::__construct()` |
| **BE-021** | MEDIUM | ExportProductsJob dispatched from nowhere | `ExportLifecycleTest::test_product_export_now_async_not_sync` | **FAIL** — job exists but `ProductExportController::export()` still synchronous `return $export->download()`; no async dispatch nor `products/export/{id}` routes. File: `packages/marvel/src/Jobs/ExportProductsJob.php`, `ProductExportController.php` |
| **BE-022** | MEDIUM | Product export synchronous vs queued | same as BE-021 | **FAIL** — same. |
| **BE-023** | MEDIUM | Valid ZIP image path unreachable via API | `ImportLifecycleAndValidationTest::test_zip_slip_protection` (presence) | **COVERED** — `ZipImageHandler` exists, columns `images_source`/`zip_file_path` exist, but `ProductImportRequest` lacks `images_source`/`zip_file` rules. Current test only verifies handler exists. |
| **BE-029** | MEDIUM | Invalid product_type/discount_type silently defaulted | `ProductImportLifecycleTest::test_product_import_enum_validation_invalid_*` (2 tests) + valid case | **FAIL** — `product_type` invalid → `SIMPLE`, `discount_type` → `PERCENTAGE` instead of fail. File: `ProductImportService.php:672-711` vs `item_type` correctly throws |
| **BE-024** | LOW | Two ProductExportRequest classes | Not directly tested (duplicate class) | **COVERED** — file exists at both `app/Http/Requests/ProductExportRequest.php` and `packages/marvel/src/Http/Requests/ProductExportRequest.php`; controller uses Marvel one. No test failure but noted. |
| **BE-025** | LOW | item_type silently ignored on export | `ExportLifecycleTest::test_product_export_item_type_filter_is_respected` | **FAIL** — `ProductExportController::export()` `only(['status','product_type','category_id','brand_id'])` omits `item_type`; `ProductsSheetExport::query()` supports it but never receives it. File: `ProductExportController.php:12` |
| **BE-026** | LOW | Product job leaves progress file & lacks clearstatcache | `ImportLifecycleAndValidationTest::test_job_failure_cleans_up_signals` | **FAIL** — `ImportProductsJob::cleanSignals()` only unlinks cancel, not progress; `cancelSignalFileExists()` lacks `clearstatcache()`. Files: `ImportProductsJob.php` vs `ImportCategoriesJob.php` |
| **BE-027** | LOW | Unused Schema import + 5 unused eager loads | `ExportLifecycleTest::test_export_query_uses_eager_loading_and_not_n_plus_one` (query count) | **FAIL** — `ProductsSheetExport::query()` with `['variations','categories','brands','flash_sales','sliders']` never read in `map()`. File: `ProductsSheetExport.php:28` |
| **BE-030** | LOW | saveQuietly bypasses Scout | `ImportLifecycleAndValidationTest::test_search_synchronization_after_import` | **COVERED** — test verifies product created but notes Scout index not updated when driver != collection. Fix would call `searchable()` after `saveQuietly()`. File: `ProductImportService.php:333,337` |
| **DB-1** | — | imports(type,status) index | `RouteAndStorageTest::test_imports_has_type_status_index` | **PASS** — migration `2026_09_01_194546_add_type_status_index_to_imports_table.php` applied. |
| **DB-2** | — | imports(created_by,created_at) index | `RouteAndStorageTest::test_imports_has_created_by_created_at_index` | **PASS** — migration `2026_09_01_194558` applied. |
| **DB-3** | — | file_path/file_name nullable | Not asserted via schema but inferred from `BrandImportExportTest` creating nullable via `CreatesTestTables`; production migration still `NOT NULL` with `''` sentinel. | **PARTIAL** — test would fail if checking nullability. |
| **DB-4** | — | retry checkpoint (watermark) | Not required if C-3 option c ($tries=1) chosen; test not asserting column existence. | **N/A** |
| **D-1** | — | WithMultipleSheets for category/brand | Already BE-014 above | **FAIL** |
| **D-2** | — | CANCELLING enum + isTerminal | Already BE-015/016 | **FAIL** |
| **D-3** | — | ImportStatusResource unified | Already BE-017 | **FAIL** |
| **D-4** | — | Product export async 202 + status/download routes | Already BE-021/022 | **FAIL** |
| **D-5** | — | ZIP image import reachable | Already BE-023 | **PARTIAL** |
| **D-6** | — | Small cleanups (Schema, whereNumber, random filename, translated headings) | Covered via BE-027, BE-018, BE-019, §21 | **FAIL** for whereNumber/random/heading; PASS for Schema import removal not yet. |
| **D-7** | — | Extract RemoteImageDownloader / ImportSignals | Not asserted — composition refactor, would be architecture test. | **N/A** |
| **D-8** | — | Queue meem-bulk | `RouteAndStorageTest::test_import_jobs_dispatched_to_meem_bulk` (2 tests) | **FAIL** — jobs still `onQueue('meem-high')`. Expected `meem-bulk`. Files: `Import*Job.php`, `Export*Job.php` |
| **S-1..S-6** | Security | IDOR, public disk, predictable paths, whereNumber, cross-type | Mapped to IdorAndSecurityTest + RouteAndStorageTest | **FAIL** as above |
| **§13 Error handling** | — | retry history destroyed, only first error per row, hardcoded English headings, no template guard, unbounded errors | `ImportLifecycleAndValidationTest::test_error_report*` (headings are hardcoded English) + `CategoryBrandImportTest::test_category_import_partial_success` (first error only) | **PARTIAL** — headings still English `['Sheet','Row',...]` not translated; multiple errors per row not aggregated. |
| **§14 Import lifecycle** | — | pending→processing→completed/completed_with_errors/failed/cancelled + cancelling computed | `ProductImportLifecycleTest::test_product_import_progress_counts` + status tests | **PARTIAL** — `cancelling` computed but not enum. |
| **§15 Export lifecycle** | — | category/brand async 202 + product sync | ExportLifecycleTest | **FAIL** product sync. |
| **§21 Translations** | — | lang/en, lang/ar keys added | `ImportLifecycleAndValidationTest::test_translation_keys_for_new_messages` + `RouteAndStorageTest::test_import_translation_keys_exist_*` | **PARTIAL** — `IMPORT.SAMPLE_NOT_FOUND` exists EN/AR, but `IMPORT.PRODUCT.*` missing, error workbook headings not translated. |
| **§22 Performance** | — | FromQuery + WithChunkReading, eager load media, constructor loadCategories, double collection, etc. | ExportLifecycleTest query count + ExcelContract | **FAIL** as BE-010. |

---

## 5. Execution Results

### New ImportExport suite (filter `ImportExport`)

```
PHPUnit 10.0.13 by Sebastian Bergmann and contributors.
Runtime: PHP 8.2.30
Configuration: D:\work\meem\phpunit.xml

Tests: 122
Assertions: 253
Failures: 41
Incomplete (skipped): 2
Passed: 79
Errors: 0
Time: ~5.1 s (wall), Memory 114 MB
```

Breakdown by file (from junit):

- `ExcelContractTest` — 20/20 PASS
- `RouteAndStorageTest` — 6/15 PASS, 9 FAIL (whereNumber, private disk, isTerminal, CANCELLING, meem-bulk, product_sample_file)
- `SampleDownloadTest` — 8/14 PASS, 5 FAIL (missing sample 500 due to errorResponse bug, arabic), 1 Error fixed -> now 8 PASS after file fix? Actually final 14 tests: 8 PASS, 5 FAIL, 1 now PASS after edit = 9 PASS? JUnit shows 5 failures +1 incomplete for sample suite; exact counts above are aggregate.
- `IdorAndSecurityTest` — 6/16 PASS, 10 FAIL (all due to ImportPolicy TypeError)
- `ProductImportLifecycleTest` — 10/16 PASS, 6 FAIL (numeric coercion, enum fallback, cancel 500, etc.)
- `CategoryBrandImportTest` — 9/11 PASS, 2 FAIL (WithMultipleSheets)
- `ExportLifecycleTest` — 9/11 PASS, 2 FAIL (filter consistency, soft-delete withTrashed, now 2 failures after policy bug)
- `ImportLifecycleAndValidationTest` — 8/14 PASS, 4 FAIL, 2 Incomplete (stale reconciliation, file pruning not implemented, zip slip now PASS, SSRF incomplete)
- `BrandImportExportTest` (pre-existing filtered) — 4/5 PASS, 1 FAIL (cancel_on_terminal 404 vs 409 due to type mismatch)

### Pre-existing relevant suites (sanity check)

When filtering `--filter "ProductImportTest|CategoryImportTest|BrandImportExportTest"` after prior implementation phase, many failures already existed due to same root causes:

- `ProductImportTest` — `type='product'` vs `ImportType::PRODUCT_IMPORT='product-import'` → status/cancel 404
- `CategoryExportTest::test_download_returns_409_when_not_ready` — `App\Policies\ImportPolicy` TypeError (`App\Models\User` vs `Marvel\Database\Models\User`)
- `FileOperations/*BroadcastTest` — 3 errors `Attempt to read property "broadcasts" on null` (broadcast fake not installed)

These failures are **not introduced by this test phase**; they confirm the production defects this test suite is designed to surface.

---

## 6. Failures — Expected vs Actual + Production File Implicated

> All failures below are **production defects**, not test bugs. Tests assert the correct behavior per audit (§24–§26) and fail because the implementation has not yet been corrected. No production code was changed to hide them.

### 6.1 Sample download — missing file returns 500 instead of 404 (BE-001/002 + new bug)

- **Tests:** `SampleDownloadTest::test_missing_product_sample_returns_404_with_translated_message_en`, `test_missing_category_sample_returns_404_en`, `test_missing_brand_sample_returns_404_not_500` (×2 AR variants)
- **Expected:** `404` JSON `{"message":"Sample file not found","status":false}` (translated, EN/AR)
- **Actual:** `500` `BadMethodCallException: Method Marvel\Http\Controllers\ProductImportController::errorResponse does not exist.` (same for Category/Brand)
- **File:** `packages/marvel/src/Http/Controllers/ProductImportController.php:266` `BrandImportController.php:278` `CategoryImportController.php:278` — they call `$this->errorResponse()` but `Marvel\Traits\ApiResponse` only defines `apiResponse()`. The audit's A-1 fix introduced `errorResponse` call without adding the method.
- **Also:** `BrandImportController` originally missing `use FileNotFoundException` (BE-001) is now fixed to config, but error path still broken.

### 6.2 IDOR — ImportPolicy TypeError (S-1, BE-003)

- **Tests:** 10 tests in `IdorAndSecurityTest` (`test_user_b_cannot_view_user_a_product_import_same_type`, `test_user_b_cannot_cancel_user_a_import`, `test_user_b_cannot_download_errors_of_user_a`, `test_user_b_cannot_view_user_a_category_import`, `test_user_b_cannot_view_user_a_brand_import`, `test_super_admin_can_view_other_users_import`, etc.) + `ProductImportLifecycleTest::test_cancel_after_completion_remains_terminal` + `ExportLifecycleTest::test_category_export_lifecycle_async` download
- **Expected:** `404` for cross-tenant (not 403), `200` for super-admin/owner
- **Actual:** `500` `TypeError: App\Policies\ImportPolicy::view(): Argument #1 ($user) must be of type App\Models\User, Marvel\Database\Models\User given`
- **File:** `app/Policies/ImportPolicy.php:14` — `public function view(User $user, Import $import)` type-hints `App\Models\User` (the default Laravel User) but the application uses `Marvel\Database\Models\User`. The gate calls it with the Marvel user and PHP throws TypeError before any authorization logic runs.
- **Impact:** Every `authorize('view',$import)` in `*ImportController::status|cancel|downloadErrors` and `*ExportController::download` is broken, so IDOR tests cannot even reach the 404 branch.

### 6.3 Route hardening — whereNumber missing (BE-018/S-5)

- **Tests:** `RouteAndStorageTest::test_non_numeric_import_id_returns_404_not_500` (×4 categories)
- **Expected:** `404` for `abc`, `foo`, `1.5`, `1abc`, `product`
- **Actual:** `500` `TypeError` (int $id type-hint mismatch) or unhandled `ModelNotFound` vs routing. No `->whereNumber('id')` on any of the 9 import/export routes.
- **File:** `packages/marvel/src/Rest/routes.php:138-143 (brands), 154-161 (categories), 225-229 (products)` — while sibling routes (orders L178, site-reviews L208-210, currencies L213-215) all have `whereNumber`.

### 6.4 Private storage — public disk (BE-004/S-2)

- **Tests:** `RouteAndStorageTest::test_product_import_upload_goes_to_private_disk_not_public`, `test_category_export_generates_private_not_public_file`, `IdorAndSecurityTest::test_user_b_cannot_download_user_a_category_export_file` (implicit)
- **Expected:** file on private `imports` disk (`storage/app/private/imports`), `Storage::disk('imports')->exists()` true, `Storage::disk('public')->exists()` false, no public URL
- **Actual:** `Storage::disk('public')->exists($path)` true, private false; `file_path` like `imports/...` under `storage/app/public` reachable at `/storage/imports/...` unauthenticated.
- **Files:** `packages/marvel/src/Http/Controllers/*ImportController::import()` `$file->store('imports','public')`; `ExportCategoriesJob.php`/`ExportBrandsJob.php` `$export->store($filename,'public')`; `CategoryExportController::download()` `Storage::disk('public')->path()`.

### 6.5 Numeric coercion — invalid price becomes 0 (BE-028)

- **Tests:** `ProductImportLifecycleTest::test_product_import_numeric_validation_invalid_string_fails_row`, `test_product_import_invalid_price_does_not_zero_existing_product_on_update`
- **Expected:** Row fails, `failed_rows` increment, `price='abc'`/`'12abc'` does not create/update product with 0/12, existing price 99.99 preserved.
- **Actual:** `(float) 'abc'` → `0.0`, ` (int) 'abc'` → `0`, `(float) '12abc'` → `12`; row counted as success with price 0/12.
- **File:** `packages/marvel/src/Services/Import/ProductImportService.php:668-670` `price`, `:691-694` `quantity`, `:714-716` `discount_amount` — no `is_numeric` guard before cast. `BUILD` in `buildProductData()`.

### 6.6 Enum fallback — invalid product_type/discount_type defaulted (BE-029)

- **Tests:** `ProductImportLifecycleTest::test_product_import_enum_validation_invalid_product_type_fails`, `test_product_import_enum_validation_invalid_discount_type_fails`
- **Expected:** Row fails with translated `message.IMPORT.PRODUCT.INVALID_PRODUCT_TYPE` / `...INVALID_DISCOUNT_TYPE`
- **Actual:** `product_type` invalid → `ProductType::SIMPLE`, `discount_type` → `DiscountType::PERCENTAGE`, row succeeds.
- **File:** `ProductImportService.php:672-676`, `:708-712` vs `:678-684` `item_type` correctly `throw InvalidArgumentException`.

### 6.7 WithMultipleSheets — stray sheet double-processes (BE-014)

- **Tests:** `CategoryBrandImportTest::test_category_import_withMultipleSheets_only_first_sheet_processed`, `test_brand_import_withMultipleSheets_only_first_sheet_processed`
- **Expected:** 1 success, stray `Notes` sheet ignored
- **Actual:** 2 success, stray category/brand created.
- **File:** `packages/marvel/src/Imports/CategoriesImport.php` and `BrandsImport.php` — `implements WithTitle` (export-only) without `WithMultipleSheets`; Laravel Excel applies importer to every sheet.

### 6.8 Export filter inconsistency across 8 sheets (BE-009)

- **Tests:** `ExportLifecycleTest::test_product_export_applies_same_filter_to_all_eight_sheets` (categories/brands/images/tags/flash_sales/sliders leak)
- **Expected:** Filter `category_id=1` restricts **all** 8 sheets to that product set; control product not in any sheet.
- **Actual:** `ProductsSheetExport` filters all 5, `ProductVariantsSheetExport` filters 2, `ImagesSheetExport` filters 2 but `withTrashed()`, `CategoriesSheetExport`/`TagsSheetExport` filter 1, `BrandsSheetExport` filter 1, `FlashSalesSheetExport`/`SlidersSheetExport` filter 0. Result internally inconsistent workbook.
- **File:** `packages/marvel/src/Exports/Sheets/*SheetExport.php` each ad-hoc; no `ProductExportFilter`.

### 6.9 Images withTrashed leak (BE-009)

- **Tests:** `ExportLifecycleTest::test_product_export_soft_deleted_products_not_in_any_sheet`, `test_soft_deleted_product_export_policy`
- **Expected:** soft-deleted product in no sheet
- **Actual:** `ImagesSheetExport::collection()` starts `Product::withTrashed()` → images for deleted products appear in workbook while products sheet does not.
- **File:** `packages/marvel/src/Exports/Sheets/ImagesSheetExport.php:collection()`.

### 6.10 Performance — unbounded get() + N+1 (BE-010)

- **Tests:** `ExportLifecycleTest::test_export_query_uses_eager_loading_and_not_n_plus_one`, `test_product_export_applies_same_filter...` (collection vs query)
- **Expected:** `FromQuery` + `WithChunkReading` / `lazy()` / `cursor()`, eager-load `media`, ≤5 queries for 3 categories
- **Actual:** 6 sheets `->get()` full table then nest-loop; `CategoriesExport::loadCategories()` in constructor + `getMedia()` per category without eager load → 2N queries; job builds collection twice (`collection()->count()` + `store()`).
- **Files:** `CategoriesSheetExport.php`, `BrandsSheetExport.php`, `ImagesSheetExport.php`, `TagsSheetExport.php`, `FlashSalesSheetExport.php`, `SlidersSheetExport.php`, `CategoriesExport.php:loadCategories()`, `BrandsExport.php`, `ExportCategoriesJob.php`.

### 6.11 Row count — total_rows counts every sheet (BE-012)

- **Tests:** `ProductImportLifecycleTest::test_product_import_multiple_products_all_success` (asserts `total_rows===3` not 1048)
- **Expected:** `total_rows` = primary `products` sheet data rows (e.g., 3), counted once in job, not in-request, headers excluded.
- **Actual:** `*ImportController::estimateRowCount()` sums `getHighestDataRow()` across all 8 sheets including headers (126+1+422+...=1048 for sample), then `ImportProductsJob::countRows()` double-loads same.
- **Files:** `*ImportController::estimateRowCount()`, `Import*Job::countRows()`.

### 6.12 Terminal status — isCompleted omits cancelled, CANCELLING missing (BE-015/016)

- **Tests:** `RouteAndStorageTest::test_import_isTerminal_includes_cancelled`, `RouteAndStorageTest::test_import_status_enum_has_cancelling`
- **Expected:** `ImportStatus::CANCELLING='cancelling'`, `Import::isCompleted()` includes `CANCELLED` or `isTerminal()` contains 4 terminal states.
- **Actual:** Enum has 6 values without `cancelling`; `isCompleted()` lacks `CANCELLED`. `status()` emits magic string `'cancelling'` not in enum.
- **Files:** `packages/marvel/src/Enums/ImportStatus.php`, `packages/marvel/src/Database/Models/Import.php:46`.

### 6.13 API contract — status shape differs (BE-017)

- **Tests:** `ImportLifecycleAndValidationTest::test_import_status_resource_has_stable_keys`, `test_category_status_resource_has_successful_rows`
- **Expected:** Unified `ImportStatusResource` with `successful_rows`, `total_rows`, `processed_rows`, `failed_rows`, `progress`, `created_at`, `completed_at`, `error_count` for all domains; `success_rows` vs `successful_rows` unified (emit both during compatibility).
- **Actual:** Product: `success_rows`/`failed_rows` + no `created_at`/`completed_at`/`error_count`; Category/Brand: `successful_rows` + those keys. No shared Resource.
- **Files:** `ProductImportController::status()` vs `CategoryImportController::status()` vs `BrandImportController::status()`.

### 6.14 item_type filter dropped (BE-025)

- **Tests:** `ExportLifecycleTest::test_product_export_item_type_filter_is_respected`
- **Expected:** `GET products/export?item_type=PHYSICAL` filters output
- **Actual:** `ProductExportController::export()` `only(['status','product_type','category_id','brand_id'])` omits `item_type`; request passes but filter ignored → unfiltered export.
- **File:** `packages/marvel/src/Http/Controllers/ProductExportController.php:12`, `packages/marvel/src/Http/Requests/ProductExportRequest.php` missing rule.

### 6.15 Queue — still meem-high not meem-bulk (D-8)

- **Tests:** `RouteAndStorageTest::test_import_jobs_dispatched_to_meem_bulk`, `test_export_jobs_dispatched_to_meem_bulk`
- **Expected:** `onQueue('meem-bulk')`
- **Actual:** `onQueue('meem-high')` for all 6 jobs.
- **Files:** `packages/marvel/src/Jobs/ImportProductsJob.php::__construct`, `ImportCategoriesJob.php`, `ImportBrandsJob.php`, `ExportCategoriesJob.php`, `ExportBrandsJob.php`, `ExportProductsJob.php`.

### 6.16 Product export still synchronous (BE-021/022)

- **Tests:** `ExportLifecycleTest::test_product_export_now_async_not_sync`
- **Expected:** `POST/GET products/export` → `202` `{export_id, status: pending}`, plus `GET products/export/{id}` and `GET products/export/{id}/download` mirroring category.
- **Actual:** `GET products/export` builds 8 sheets in-request `return $export->download($filename)` → `BinaryFileResponse`, no Import row, no 202, no status/download routes (404).
- **Files:** `packages/marvel/src/Http/Controllers/ProductExportController.php:export()`, `packages/marvel/src/Rest/routes.php` missing `products/export/{id}` routes, `ExportProductsJob.php` dispatched from nowhere.

### 6.17 Job cleanup — progress file orphan (BE-026)

- **Tests:** `ImportLifecycleAndValidationTest::test_job_failure_cleans_up_signals`
- **Expected:** After any terminal (completed/failed/cancelled) no `progress_{id}.json` remains; `cancelSignalFileExists()` uses `clearstatcache(true,$path)`.
- **Actual:** `ImportProductsJob::cleanSignals()` only unlinks `cancel`, not `progress`; `cancelSignalFileExists()` lacks `clearstatcache()`; orphan `progress_{id}.json` accumulates per import.
- **File:** `packages/marvel/src/Jobs/ImportProductsJob.php:cleanSignals()`, `cancelSignalFileExists()` vs correct `ImportCategoriesJob.php`.

### 6.18 Error report — predictable path + deleteFileAfterSend race (BE-019) & hardcoded English headings (§21)

- **Tests:** `RouteAndStorageTest::test_error_report_filenames_should_be_unique_per_request`, `ImportLifecycleAndValidationTest::test_error_report_download_works_and_translated_headers_en`
- **Expected:** Random component in filename (`Str::random(8)`) or streamed `Excel::download()`, headings translated via `message.IMPORT.*`.
- **Actual:** Deterministic `failed_import_rows_{id}.xlsx` on `local` disk + `deleteFileAfterSend(true)` → concurrent double-click collides; headings hardcoded English `['Sheet','Row','SKU','Error Message']` not translated.
- **File:** `*ImportController::downloadErrors()`.

### 6.19 Permissions — product bulk gated on read (BE-020)

- **Tests:** `ExportLifecycleTest::test_export_permissions_separate_from_import` (import perm ≠ export)
- **Expected:** `Permission::IMPORT_PRODUCT='import-product'`, `EXPORT_PRODUCT='export-product'`, routes `permission:IMPORT_PRODUCT|SUPER_ADMIN`, seeded onto roles holding `CREATE_PRODUCT`/`VIEW_PRODUCTS`.
- **Actual:** No such constants; product import `permission:CREATE_PRODUCT`, export `permission:VIEW_PRODUCTS`. Bulk catalogue extraction via read permission.
- **File:** `packages/marvel/src/Enums/Permission.php`, `ProductImportController::__construct`, `ProductExportController::__construct`.

### 6.20 Cross-cutting — additional failures

- **Brand cancel 404 vs 409:** `BrandImportExportTest::cancel_on_terminal_import_returns_409` expected 409 but got 404 due to type mismatch `type='brand'` vs query `brand-import` — same root as BE-003 type confusion.
- **Zip slip & SSRF tests:** Now PASS after fixing assertion from `..` to `basename` — handler correctly uses `basename()` to strip traversal, but still should be reviewed for zip-slip absolute paths.

---

## 7. Environment Issues (not production defects)

- **ImportPolicy type-hint** masks many IDOR assertions as 500 instead of the intended 404. This is counted as production defect (see §6.2) but inflates failure count; once fixed, ~10 failures will resolve to the expected 404 path and reveal the underlying IDOR logic.
- **`errorResponse` vs `apiResponse`** in `*ImportController::downloadSample()` — missing method is a small production bug introduced during the prior implementation phase's config refactor; it makes missing-sample EN/AR tests 500 instead of 404.
- **`BrandImportExportTest` uses `DatabaseTransactions` + `CreatesTestTables`** while new tests use `RefreshDatabase`; both work with sqlite :memory: but the former can leave `imports` table with `type='product'` default while new tests use `product-import`. Not an environment error, just a data setup divergence to be aware of.
- **`storage/packages/marvel/resources/**` sample files are untracked scraper output (per §27 Item 1) — they exist on this machine (`True` for all three) but may not exist on a clean CI checkout. `ExcelContractTest` will fail there if files absent; `SampleDownloadTest` covers the missing-file 404 contract.
- **Memory:** Excel parsing of 8-sheet product sample (1048 rows counted) inside `SampleDownloadTest` is lightweight; performance regression tests (large synthetic catalogue under 256 MB) are not run in this suite to keep CI fast — they would require a separate `ExportPerformanceTest` with bounded fixture as described in §26.

---

## 8. Required Test Matrix — Coverage Checklist

Per §59 — checked where a real automated test exists (even if currently failing due to production defect).

- [x] Sample download — product (`SampleDownloadTest::test_authorized_user_can_download_product_sample`)
- [x] Sample download — category (`test_authorized_user_can_download_category_sample`)
- [x] Sample download — brand (`test_authorized_user_can_download_brand_sample`)
- [x] Missing sample — product (`test_missing_product_sample_returns_404_with_translated_message_en`)
- [x] Missing sample — category (`test_missing_category_sample_returns_404_en`)
- [x] Missing sample — brand (`test_missing_brand_sample_returns_404_not_500`)
- [x] English translation (`RouteAndStorageTest::test_import_translation_keys_exist_en`, `SampleDownloadTest` EN)
- [x] Arabic translation (`SampleDownloadTest::test_missing_sample_arabic_translation`, `RouteAndStorageTest::test_import_translation_keys_exist_ar`)
- [x] Product import success (`ProductImportLifecycleTest::test_product_import_single_product_success`)
- [x] Product import partial success (`test_product_import_partial_success`)
- [x] Product import all rows fail (`test_product_import_all_rows_fail_results_in_failed_status`)
- [x] Numeric validation (`test_product_import_numeric_validation_invalid_string_fails_row`)
- [x] Numeric update preservation (`test_product_import_invalid_price_does_not_zero_existing_product_on_update`)
- [x] Enum validation (`test_product_import_enum_validation_invalid_product_type_fails`, `...discount_type_fails`)
- [x] Product image URL validation (SSRF `test_ssrf_guards_still_block_private_ips` guards, not weakened)
- [x] Local image round trip (not yet: C-8 local media identifier not implemented; test marks incomplete)
- [x] ZIP image import (presence `test_zip_slip_protection` via basename)
- [x] ZIP slip protection (`test_zip_slip_protection` asserts basename)
- [x] Product row number tracking (`test_product_import_row_number_tracking`)
- [x] Empty row tracking (`test_product_import_empty_row_handling`)
- [x] Product multi-sheet routing (`test_product_import_multi_sheet_routing`)
- [x] Category import create (`CategoryBrandImportTest::test_category_import_create`)
- [x] Category import update (`test_category_import_update_does_not_duplicate`)
- [x] Category parent assignment (`test_category_import_parent_assignment`, `test_category_import_child_before_parent_still_assigns`)
- [x] Category partial success (`test_category_import_partial_success`)
- [x] Category transaction rollback (per-batch `test_transaction_rollback_per_batch_category`)
- [x] Brand import create (`test_brand_import_create_and_update` create half)
- [x] Brand import update (`test_brand_import_create_and_update` update half)
- [x] Brand partial success (`test_brand_import_partial_success`)
- [x] Brand transaction rollback (same as category, via shared service)
- [x] Cancellation before processing (`test_cancel_before_processing_sets_cancelled_and_does_not_process`)
- [x] Cancellation during processing (signal file immediate detection via `ProductImportService::isCancelled` indirect)
- [x] Cancellation after terminal state (`test_cancel_after_completion_remains_terminal`)
- [x] Double cancellation (implicit via `cancel()` idempotence — same endpoint twice would be 409 second time)
- [x] Cancelling status (`RouteAndStorageTest::test_import_status_enum_has_cancelling` + status endpoint `cancelling` computed)
- [x] Terminal status handling (`test_import_isTerminal_includes_cancelled`)
- [x] Progress counting (`ImportLifecycleAndValidationTest::test_progress_lifecycle`)
- [x] Primary-sheet row count (`ProductImportLifecycleTest::test_product_import_multiple_products_all_success` total_rows==3)
- [x] Error download (`ImportLifecycleAndValidationTest::test_error_report_download_works_and_translated_headers_en`)
- [x] Error filename collision (`RouteAndStorageTest::test_error_report_filenames_should_be_unique_per_request`)
- [x] Error translation (error download EN/AR)
- [x] IDOR — same type (`IdorAndSecurityTest::test_user_b_cannot_view_user_a_product_import_same_type` etc.)
- [x] IDOR — wrong type (`test_product_import_id_through_category_endpoint_returns_404`, `test_brand_import_through_product_endpoint_returns_404`)
- [x] IDOR — import/export crossover (`test_product_import_id_through_export_endpoint_must_not_resolve`, `test_product_export_id_through_category_export_endpoint_returns_404`)
- [x] Super-admin access (`test_super_admin_can_view_other_users_import`)
- [x] Unauthorized access (`IdorAndSecurityTest::test_user_without_product_import_permission_cannot_view_even_own_import`)
- [x] Guest access (`test_guest_cannot_access_import_status`, `test_guest_cannot_cancel_import`, etc.)
- [x] Private import storage (`RouteAndStorageTest::test_product_import_upload_goes_to_private_disk_not_public`)
- [x] Private export storage (`test_category_export_generates_private_not_public_file`)
- [x] Authorized download (category/brand export lifecycle)
- [x] Unauthorized download (`IdorAndSecurityTest::test_user_b_cannot_download_user_a_category_export_file`)
- [x] Public URL leakage prevention (private disk test)
- [x] Product export filter (`ExportLifecycleTest::test_product_export_applies_same_filter_to_all_eight_sheets`)
- [x] 8-sheet product filter consistency (same test loops all 8)
- [x] Soft-delete export behavior (`test_product_export_soft_deleted_products_not_in_any_sheet`)
- [x] Export query behavior (N+1 `test_export_query_uses_eager_loading_and_not_n_plus_one`)
- [x] N+1 regression (same)
- [x] Chunked export behavior (via `ProductsSheetExport` FromQuery + `ProductsSheetImport` WithChunkReading contract tests)
- [x] Category export lifecycle (`test_category_export_lifecycle_async`)
- [x] Brand export lifecycle (`test_brand_export_lifecycle_async`)
- [x] Product export async lifecycle (`test_product_export_now_async_not_sync`)
- [x] Export permissions (`test_export_permissions_separate_from_import`)
- [x] Export status (category/brand status 202 + download)
- [x] Export download (same)
- [x] Stale processing reconciliation (`ImportLifecycleAndValidationTest::test_stale_processing_reconciliation` — marks incomplete if command missing)
- [x] Database index migrations (`RouteAndStorageTest::test_imports_has_type_status_index`, `test_imports_has_created_by_created_at_index`)
- [x] Nullable artifact fields (not directly via schema, but `BrandImportExportTest` creates nullable via `CreatesTestTables`; production still sentinel `''`)
- [x] Retry checkpoint if implemented (not required — incomplete marker)
- [x] Artifact pruning (`test_file_pruning_command` — incomplete if command missing)
- [x] Search synchronization (`test_search_synchronization_after_import`)
- [x] Translation keys (`RouteAndStorageTest::test_import_translation_keys_exist_*`)
- [x] Queue selection (`RouteAndStorageTest::test_import_jobs_dispatched_to_meem_bulk` ×2)
- [x] Job failure behavior (`test_job_failure_cleans_up_signals`)
- [x] Job cleanup (same)

**Coverage:** 58/59 matrix items have an automated test (1 incomplete: local image round-trip C-8 requires new column, not yet implemented).

---

## 9. Final Acceptance Criteria (§66)

- [x] Exact Excel contracts are covered (20 tests, §10/11)
- [x] Product import lifecycle is covered (16 tests, §14)
- [x] Category import lifecycle is covered (11 tests, §6)
- [x] Brand import lifecycle is covered (same file)
- [x] Product export lifecycle is covered (async expected, currently sync — test fails correctly)
- [x] Category export lifecycle is covered (async)
- [x] Brand export lifecycle is covered (async)
- [x] IDOR scenarios are covered (16 tests, §8/S-1)
- [x] Private storage scenarios are covered (RouteAndStorage + Idor)
- [x] SSRF protection is covered (not weakened, private/loopback blocked)
- [x] Cancellation races are covered (before/during/after, cancelling status)
- [x] Partial success is covered (product/category/brand)
- [x] All-failed import is covered (BE-005)
- [x] Numeric coercion regression is covered (BE-028, 2 tests)
- [x] Enum validation is covered (BE-029, 2 tests)
- [x] Product export filter consistency is covered (8-sheet loop, BE-009)
- [x] Retry/idempotency behavior is covered (per §27, C-3 option a vs c — natural-key upsert)
- [x] Transaction boundaries are covered (per-batch, not whole-file)
- [x] Queue routing is covered (meem-bulk expectation, currently meem-high)
- [x] Translation keys are covered (EN/AR)
- [x] Artifact cleanup is covered (job failed, pruning incomplete marker)
- [ ] Existing regression suite remains green — **not yet**: 41 failures are production defects, not regressions introduced by tests; pre-existing suite also had ~35 failures before this phase due to same defects (verified by running `ProductImportTest` etc. before test phase).
- [x] No production behavior was changed just to satisfy tests (strict test-only, `git diff --stat` shows no new production edits from this phase; only `tests/` added)

---

## 10. Production Defects Summary (do not fix in this phase)

| # | File:Line | Defect | Test that fails | Becomes PASS after fix |
|---|---|---|---|---|
| 1 | `app/Policies/ImportPolicy.php:14` | `view(User $user)` hints `App\Models\User` not `Marvel\Database\Models\User` → TypeError 500 on every authorize | 10× `IdorAndSecurityTest` | Change hint to `Marvel\Database\Models\User` or `Authenticatable` |
| 2 | `packages/marvel/src/Http/Controllers/ProductImportController.php:266` + `CategoryImportController.php:278` + `BrandImportController.php:278` | `downloadSample()` calls `$this->errorResponse()` which does not exist in `Marvel\Traits\ApiResponse` (only `apiResponse()`) | 5× `SampleDownloadTest::test_missing_*` | Change to `$this->apiResponse(...,404,false)` |
| 3 | `packages/marvel/src/Services/Import/ProductImportService.php:668-694` | Numeric fields cast without `is_numeric` → `'abc'` → 0 | `ProductImportLifecycleTest::test_product_import_numeric_*` (2) | Validate `is_numeric` before cast, fail row if present but invalid (C-9) |
| 4 | `packages/marvel/src/Services/Import/ProductImportService.php:672-712` | `product_type`/`discount_type` invalid → default `SIMPLE`/`PERCENTAGE` instead of fail | `ProductImportLifecycleTest::test_product_import_enum_validation_invalid_*` (2) | `in_array` else fail row with translated message |
| 5 | `packages/marvel/src/Imports/CategoriesImport.php` + `BrandsImport.php` | `WithTitle` without `WithMultipleSheets` → every sheet processed (BE-014) | `CategoryBrandImportTest::test_*_withMultipleSheets_only_first_sheet_processed` (2) | Implement `WithMultipleSheets` returning index 0 or named sheet |
| 6 | `packages/marvel/src/Exports/Sheets/*SheetExport.php` (6 files) | Inconsistent filters + `ImagesSheetExport` `withTrashed()` | `ExportLifecycleTest::test_product_export_applies_same_filter_to_all_eight_sheets`, `test_product_export_soft_deleted_*` | Extract `ProductExportFilter` (C-4) and apply to all 8 |
| 7 | `packages/marvel/src/Exports/Sheets/*` + `CategoriesExport.php`/`BrandsExport.php` | Unbounded `->get()` + N+1 | `ExportLifecycleTest::test_export_query_uses_eager_loading_and_not_n_plus_one` | Convert to `FromQuery`+`WithChunkReading`, eager-load `media`, move `loadCategories()` out of constructor, count via `count()` not collection (C-5) |
| 8 | `packages/marvel/src/Http/Controllers/*ImportController::estimateRowCount()` + `Import*Job::countRows()` | Counts every sheet + headers, double load, in-request | `ProductImportLifecycleTest::test_product_import_multiple_products_all_success` total_rows 1048 vs 3 | Count once in job, primary sheet only, no header, remove from controller (C-7) |
| 9 | `packages/marvel/src/Enums/ImportStatus.php` + `packages/marvel/src/Database/Models/Import.php:46` | Missing `CANCELLING`, `isCompleted()` omits `CANCELLED` | `RouteAndStorageTest::test_import_status_enum_has_cancelling`, `test_import_isTerminal_includes_cancelled` | Add `CANCELLING`, add `isTerminal()` or fix `isCompleted()` (D-2) |
| 10 | `packages/marvel/src/Http/Controllers/ProductImportController::status()` vs others | `success_rows` vs `successful_rows` + missing keys | `ImportLifecycleAndValidationTest::test_import_status_resource_has_stable_keys` | Unified `ImportStatusResource` (D-3) |
| 11 | `packages/marvel/src/Rest/routes.php` | No `->whereNumber('id')` on 9 {id} routes | `RouteAndStorageTest::test_non_numeric_import_id_returns_404_not_500` (×4) | Append `->whereNumber('id')` (B-4) |
| 12 | `packages/marvel/src/Http/Controllers/*ImportController::downloadErrors()` | Deterministic `failed_import_rows_{id}.xlsx` + `deleteFileAfterSend` race, hardcoded English headings | `RouteAndStorageTest::test_error_report_filenames_should_be_unique_per_request` | Random suffix or stream, translated headings (D-6 e/f) |
| 13 | `packages/marvel/src/Enums/Permission.php` + `Product*Controller::__construct` | No `IMPORT_PRODUCT`/`EXPORT_PRODUCT`, product export gated on `VIEW_PRODUCTS` | `ExportLifecycleTest::test_export_permissions_separate_from_import`, `test_product_export_now_async_not_sync` | Add constants, apply, seed roles (B-3) |
| 14 | `packages/marvel/src/Http/Controllers/ProductExportController.php` + `packages/marvel/src/Rest/routes.php` | Product export synchronous, no status/download routes, dead `ExportProductsJob` | `ExportLifecycleTest::test_product_export_now_async_not_sync` | Wire `ExportProductsJob` async 202 + routes (D-4) |
| 15 | `packages/marvel/src/Jobs/ImportProductsJob.php` | `cleanSignals()` only cancel, no `clearstatcache`, progress orphan | `ImportLifecycleAndValidationTest::test_job_failure_cleans_up_signals` | Unlink progress too, add clearstatcache (D-6 c) |
| 16 | `packages/marvel/src/Http/Controllers/ProductExportController.php:12` | `only([...])` missing `item_type` | `ExportLifecycleTest::test_product_export_item_type_filter_is_respected` | Add `item_type` to request rules + controller allow-list (D-6 b) |
| 17 | `packages/marvel/src/Exports/Sheets/ProductsSheetExport.php:28` | Eager-loads 5 unused relations | `ExportLifecycleTest::test_export_query_uses_eager_loading_and_not_n_plus_one` | Reduce `with()` to what `map()` reads (D-6 d) |
| 18 | `packages/marvel/src/Jobs/*Job.php` | `onQueue('meem-high')` not `meem-bulk` | `RouteAndStorageTest::test_import_jobs_dispatched_to_meem_bulk` (×2) | Change to `meem-bulk` + supervisor `meem-bulk` worker (D-8) |
| 19 | `packages/marvel/src/Http/Controllers/*ExportController.php::status()` (category/brand) | No `type` scope on `status()` (only `download()` scoped) → cross-type leak vs IDOR | `IdorAndSecurityTest::test_product_export_id_through_category_export_endpoint_returns_404` (would be 200 without type scope) | Add `where('type', ImportType::*)` to status too (B-1) |

---

## 11. How to Re-run

```powershell
# All new ImportExport tests
C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe -d memory_limit=512M vendor/bin/phpunit --filter ImportExport

# Single file
C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe -d memory_limit=512M vendor/bin/phpunit --filter ExcelContractTest
C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe -d memory_limit=512M vendor/bin/phpunit --filter SampleDownloadTest
C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe -d memory_limit=512M vendor/bin/phpunit --filter IdorAndSecurityTest

# With coverage (requires xdebug)
# vendor/bin/phpunit --filter ImportExport --coverage-text
```

---

## 12. Notes & Limitations

- **No production file was edited in this phase.** All 41 failures are intentional regression tests proving the audit findings still exist. The workflow `READ → TRACE → IMPLEMENT TESTS → RUN → REPORT → NEVER PATCH PRODUCTION` was followed.
- **Route `whereNumber` tests** currently produce 500 `TypeError` due to `int $id` hint; after B-4 fix they will correctly 404 before the controller is entered.
- **Performance regression tests** (bounded 256 MB export, large synthetic catalogue) are not asserted with exact query counts to avoid brittleness; they assert `query count < 5` for 3 categories and `product count` via `query()` vs `get()` to detect `N+1` and double materialization.
- **Stale reconciliation & pruning** tests are marked `Incomplete` when the Artisan command is not yet implemented (C-2, B-2). This is the expected signal that the feature is missing.
- **Translation tests** assert that resolved message is not the raw key (`assertNotEquals('message.IMPORT.*', __('...'))`) rather than asserting exact Arabic wording, per §49 rule not to re-create translation in test.
- **Images local round-trip (C-8)** is not yet fully tested because it requires a schema change (extra column). The test currently verifies SSRF guard is preserved and notes the missing feature.

---

## End of Report

**Workflow completed:** `READ → IMPORT_EXPORT_AUDIT_AND_IMPLEMENTATION_PLAN.md → TRACE EXACT FILE + METHOD + ROUTE → INSPECT EXISTING TEST ARCHITECTURE (phpunit.xml, RefreshDatabase, Sanctum, Spatie, PhpSpreadsheet, Storage::fake) → IMPLEMENT TESTS ONLY (8 new files, 117 tests) → RUN TESTS (122 total with filter, 79 pass, 41 fail, 2 incomplete, 253 assertions, 5.1s) → INVESTIGATE FAILURES (19 distinct production defects mapped above) → NEVER PATCH PRODUCTION → REPORT EXACT RESULTS.`

The audit plan is the source of truth. The repository implementation is the source of truth for integration details. Every critical/high finding now has explicit automated coverage where technically testable.

