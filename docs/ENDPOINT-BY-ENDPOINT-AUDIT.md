# ULTRA-DETAILED ENDPOINT-BY-ENDPOINT API CONTRACT AUDIT

Window: **63581db..HEAD** · Audit date: 2026-08-25 · Source-only (git diffs + current files read line-by-line).
Envelope used by most JSON responses (`Marvel\Traits\ApiResponse::apiResponse`, verified):
```json
{ "status": <int>, "message": "<translated>", "success": true|false, "data": {…} }   // 'data' omitted when empty
```
Legend: **[C]** customer (`routes/api.php`) · **[A]** admin/Marvel (`packages/marvel/src/Rest/Routes.php`) · **[W]** web.
Prefix resolution: `[C]` = `/api/v1/general/...`; `[A]` = `/api/v1/...`.
Anything unverifiable is marked **NOT PROVEN FROM SOURCE**.
Per-endpoint sections follow the 40-point checklist; shared payloads are fully expanded once and referenced by section id to avoid duplication without omitting any endpoint.

---

# PART C — CUSTOMER / STOREFRONT (routes/api.php)

## C1. POST /api/v1/general/products/{id}/reviews
**Route:** routes/api.php:139 · group `v1/general` → authenticated sub-group · no name.
**Middleware chain:** api · auth:sanctum · throttle:authenticated · (controller-level review permission gate added in window → 403 path).
**Controller:** App\Http\Controllers\Api\General\ProductController@addProductReview.
**Path params:** `id` = product id (integer implied).
**Body (validation NOT PROVEN FROM SOURCE for this exact method):**
```json
{ "rating": 5, "comment": "text", "title": "optional" }
```
**Flow:** Controller → review service/model write → `ReviewApproved/Rejected` events exist in package (dispatch conditions NOT PROVEN FROM SOURCE here).
**Response:** review resource envelope · **Errors:** 401 unauth · 403 missing permission (**NEW in window**) · 422.
**Δ vs baseline:** status-code behavior change (breaking for permission-less callers).
**Frontend:** request/create-review permission handling; surface 403 message.

## C2. PUT /api/v1/general/products/reviews/{id}
Identical metadata to C1 (routes/api.php:140, `updateProductReview`). Path `id` = review id. Δ: same 403 gate added. Body: rating/comment/title subset.

---

## C3. GET /api/v1/general/site-reviews
**routes/api.php:104 · public · throttle:public-api · SiteReviewController@index.**
**Builder:** `App\Http\Resources\SiteReview\SiteReviewResource` (verified fields):
```json
{ "id":1, "rating":5, "title":"…", "comment":"…",
  "customer": {"id":2,"name":"…"},   // only when user loaded
  "created_at":"…" }
```
Only moderation-approved rows surface (service filter). NEW in window. Pagination wrapper per standard collection (meta links) — **exact meta shape NOT PROVEN FROM SOURCE**.

## C4. POST /api/v1/general/site-reviews
**routes/api.php:146 · sanctum · SiteReviewController@store → CreateSiteReviewRequest (verified rules):**
```json
{ "rating": 1..5 (required int), "title": "nullable ≤191", "comment": "required ≤2000" }
```
Flow: `SiteReviewService::createReview(user, validated)` → model `site_reviews`. Response `201` + `SiteReviewResource`. Errors: 401/422.

---

## C5. GET /api/v1/general/currencies
**routes/api.php:105 · public · CurrencyController@index.**
**Builder:** `CurrencyResource` (verified fields): `id, code, name(trans), symbol(trans), country_name(trans), numeric_code, decimal_places(int), icon, is_active(bool), sort_order(int), is_base(bool), is_catalog(bool), created_at, updated_at`.
NEW in window. Active currencies only (**filter proof: is_active usage — exact query NOT PROVEN FROM SOURCE**).

## C6. POST /api/v1/general/currencies/select
**routes/api.php:107 · sanctum · SelectCurrencyRequest:** `currency_code` required (valid currency rule set present; **full rule array NOT PROVEN FROM SOURCE** beyond required+lookup).
**Flow:** upper-case code → `UserCurrencyPreferenceService::setUserPreference` (sanctum) AND guest cookie setter → `CurrencyService::forgetEffectiveCode()` → lookup Currency.
**Response:** `200` success envelope + currency payload (C5 shape). Sets future price conversions app-wide.

---

## C7/C8. POST & DELETE /api/v1/general/device-tokens
```text
routes/api.php:143/144 · sanctum · DeviceTokenController
```
**store body (verified rules):**
```json
{ "token": "required ≤512", "client": "client_a|client_b", "platform": "android|ios (opt)" }
```
Global-unique token: re-register reassigns owner; updates `last_used_at`. Table `device_tokens`.
**store → 200** `{uuid}` · **delete** body `{token}` deletes caller-owned rows → `200` ack. Errors 401/422.

---

## C9. GET /api/v1/general/digital/downloads
```text
routes/api.php:128 · sanctum · DigitalDownloadController@index
Models: DigitalEntitlement(+orderItem), DigitalLicenseKey, DigitalAsset
```
Ownership: `where('user_id', $user->id)` (verified). N+1-safe license prefetch grouped by entitlement.
**Response data[] per entitlement (all keys verified from controller 44–101):**
```json
{
  "uuid":"…","status":"delivered","download_limit":5,"download_count":2,
  "delivered_at":"…","revoked_at":null,"expires_at":null,
  "product":{"id":7,"name":"…"},
  "assets":[ {
    "uuid":"…","type":"FILE","original_name":"…","mime":"application/pdf","size":1024,
    "delivery_type":"download|redirect|reveal",
    "download_url":"<temporarySignedRoute general.digital.download, signedUrl()>",
    "external_url":"<only for URL type AND accessAllowed>",
    "reveal": {"path":"/api/v1/general/digital/license/{e}/{a}"}   // LICENSE/ACCESS only
  } ]
}
```
Conditional fields exactly per asset `type` match statement (FILE→download_url; URL→external_url when accessible; LICENSE/ACCESS→reveal path, secret never included).

## C10. GET /api/v1/general/digital/license/{entitlement}/{asset}
```text
routes/api.php:131 · whereUuid×2 · sanctum · name general.digital.license
Flow: resolver->revealCredential(user,…)
```
Responses (status/payload pairs verified):
`200 {type:"license"|"access", credential:"<decrypted>", revealed_at}` · `403 DIGITAL_LICENSE_ALREADY_REVEALED` · `403/404 DIGITAL_ENTITLEMENT_NOT_ACCESSIBLE` · `404 DIGITAL_LICENSE_NOT_ALLOCATED/NOT_FOUND`. Single-shot (sets revealed_at).

## C11. GET /api/v1/general/digital/url/{entitlement}/{asset}
Same auth; audited redirect to stored external URL; no download-credit consumption; app never fetches target. `302` | `403/404` envelopes. Redirect logging inside resolver (**log sink NOT PROVEN FROM SOURCE**).

## C12. GET /api/v1/general/digital/download/{entitlement}/{asset}  *(root-level)*
```text
routes/api.php:168 · middleware signed + throttle:30,1 · name general.digital.download
Controller @download → resolver->deliver(mode download)
```
No Sanctum (signature = grant). Gates verified in resolver: signature → entitlement exists → ownership/status/expiry → limit. Streams FILE/AUDIO/VIDEO attachment; error envelopes 403/404 as above.

---

## C13. GET /api/v1/general/invoices/my-invoices
```text
routes/api.php:149 · sanctum · InvoiceController@myInvoices
Query: limit (default 15, max 100 — verified clamp)
```
Ownership: `where('user_id', user->id)`; eager-loads order.
**Row builder CustomerInvoiceListResource (verified):** uuid, invoice_number, status, subtotal, shipping_price, total_discount, total, currency, payment_method, payment_gateway, generated_at, pdf_generated_at, verification_url(`/api/v1/general/invoices/verify/{uuid}`), view_url/download_url(`temporarySignedRoute('general.invoices.view'|'general.invoices.download', 10 minutes)`).
Paginated collection envelope (meta/links standard Laravel) — **exact wrapper class for this action NOT PROVEN FROM SOURCE**.

## C14. GET /api/v1/general/invoices/verify/{uuid}
```text
routes/api.php:151 · throttle:5,1 · PUBLIC · InvoiceController@verify → InvoiceService@verifyInvoice
```
Responses verified:
`200` verification payload (increments verify_count, sets last_verified_at/first verified_at; timeline recorded) · `409 {"authentic":false,"tampered":true,message:"Invoice verification failed"}` · `404 NOT_FOUND`.
Final success `data` internals: **NOT PROVEN FROM SOURCE** beyond envelope (contains verification URL built as `/api/v1/general/invoices/verify/{uuid}`).

## C15/C16. SIGNED view & download
```text
routes/api.php:159/:161 · prefix v1/general/invoices · middleware 'signed' ONLY
names general.invoices.view | general.invoices.download (10-minute signatures)
Controllers: viewByUuidSigned / downloadByUuidSigned → inline PDF render / stream
```
Bad/expired signature ⇒ framework 403 HTML. No Sanctum header needed.

---

## C17. POST /api/v1/general/checkout  *(validation delta)*
```text
routes/api.php:115 · sanctum · throttle:authenticated · OrderCreateRequest (marvel Requests)
Verified rules (window-relevant):
  name required ≤255 · user_phone required ≤255 · user_email required email ≤255
  address requiredIf(requiresShipping && fulfillment_type !== PICKUP)   ← NOW CONDITIONAL (was effectively always required)
  governorate_id requiredIf(delivery) · pickup_location_id requiredIf(pickup)
  notes nullable · selected_promotion_id nullable exists:promotions · selected_gift_product_id nullable exists:products
  type nullable in:mobile,web · fulfillment_type enum(FulfillmentType) · payment_method nullable in:online,cod,pay_at_cashier · gateway nullable ≤50
```
**Δ vs baseline:** address/governorate requirements became conditional ⇒ digital/pickup checkouts no longer require an address. **Additive-compatible** (existing senders unaffected).
**Flow:** OrderCreateRequest → OrderCreationService (FX snapshot writes at :79–293, item_type lines) → Order + OrderProducts + payment branch → events (OrderCreated etc.) → notifications queued.
**Response:** order resource (customer builder) incl. new additive fields (audit A6). Errors: 401/422/409 stock-or-state (message envelope).

## C18/C19. ANY checkout/callback · checkout/error-callback
```text
routes/api.php:109–110 · public · throttle:public-api · names api.checkout.callback|errorCallback
Gateway redirect landing handlers. Request = gateway POST/GET back-params (gateway-specific — NOT PROVEN FROM SOURCE per gateway).
```

## C20/C21. Pickup locations list/show — additive `is_default(bool)` on every row (default-branch feature). Otherwise UNCHANGED contracts.

---

# PART M — ADMIN / MARVEL (packages/marvel/src/Rest/Routes.php)

Group middleware for §M1–M46: `auth:sanctum` + `throttle:admin` (outer group, verified line 116 region) + per-controller permission middleware as listed. All import/export controllers additionally enforce permissions in their constructors.

## PRODUCT IMPORT FAMILY

### M1. POST /api/v1/products/import
Routes:227 · name admin.products.import · ProductImportController@import · `permission:create-product|super_admin`.
Body multipart: `file` — required|mimes:xlsx,xls,ods|max:20480KB (ProductImportRequest, translated messages).
Flow: store file `imports/` (public disk) → PhpSpreadsheet row estimate → `Import::create(type=product,status=pending)` → progress signal init → `ImportProductsJob::dispatch` (meem-high, tries3/t1500/backoff60-120-240).
Response `202`: `{status:202,message:IMPORT_STARTED_SUCCESSFULLY,success:true,data:{import_id,status:"pending"}}`.
Errors: 401/403/422 (field `file.*`) · 500 worker-less edge.

### M2. GET products/import/sample — binary template (`packages/marvel/resources/products/product-import-sample.xlsx`); 404 FileNotFoundException if missing. Routes:225.

### M3. GET products/import/{id} — Routes:228 · `status()`
Reads Import(id,status,total_rows,processed_rows,success_rows,failed_rows,errors) + signal files `progress_{id}.json`,`cancel_{id}.json`.
Effective status `cancelling` when cancel signal pending (verified L130).
Progress rules verified: terminal completed* ⇒100; failed/cancelled ⇒ signal progress ?? 0; processing ⇒ signal ?? 99; else 0.
**Data keys (exact):** `id,status,total_rows,processed_rows,success_rows,failed_rows,progress(float),errors[]` where errors[] rows = `{sheet,row,sku,error_message}`.
Headers: `Cache-Control:no-cache,no-store,must-revalidate · Pragma:no-cache · Expires:0` (**RESPONSE_HEADER_CHANGED** by design). Errors: 404 findOrFail.

### M4. POST products/import/{id}/cancel — Routes:229
Terminal ⇒ `409 IMPORT_CANNOT_CANCEL`. Else cancel signal + DB update → `200 {import_id,status:"cancelled"}` + FileOperationEvent(product.import.progress/cancelled). Job-side mid-run cancellation rolls back created products then emits cancelled terminal.

### M5. GET products/import/{id}/download-errors — Routes:230
Empty errors ⇒ `404 IMPORT_NO_ERRORS`. Else builds FromCollection XLSX (`Sheet,Row,SKU,Error Message`) stored local + streamed `deleteFileAfterSend`.

### M6. GET products/export — Routes:226 · **SYNCHRONOUS binary** (`ProductsExport::download`), filters via ProductExportRequest (`status,product_type,category_id,brand_id` — **rule details NOT PROVEN FROM SOURCE**). Δ window: none (G3 deferred). Listed for completeness.

## CATEGORY IMPORT FAMILY

### M7–M11 (POST categories/import :154 · sample :155 · status :156 · cancel :157 · errors :158)
CategoryImportController + CategoryImportRequest (file required/xlsx,xls,ods/20480 + translated msgs) + ImportCategoriesJob(meem-high) + CategoryImportService(**realtime broadcast category.import.progress**, milestones 10/60/80/99/100, dual-channel legacy contract preserved).
Start `202 {import_id,status:"pending"}` (CATEGORY_IMPORT_STARTED family).
**Status data keys (exact, differs from product):** `id,status,total_rows,processed_rows,**successful_rows**,failed_rows,errors,created_at,completed_at(null until terminal)`; progress merged from signal (99 cap while running).
Cancel: signal + immediate DB cancelled + broadcast terminal (additive status key) → `200 {import_id,status:"cancelled"}`.
Errors-download: brand/category style filename `failed_import_rows_{id}.xlsx` pattern (category variant) → binary.

## CATEGORY EXPORT FAMILY

### M12. GET categories/export — Routes:159 · EXPORT_CATEGORY|SUPER_ADMIN
Creates `Import(type=category-export)` → ExportCategoriesJob(meem-high,t2,600) → `202 {export_id,status:"pending"}` (CATEGORY_EXPORT_STARTED).
Job: collection count → `categories-export-{timestamp}.xlsx` stored public disk → Import updated completed(file_path,file_name,rows) → broadcast `category.export.completed` (after DB commit-order verified).

### M13. GET categories/export/{id} — status payload:
```json
{ "id":58,"status":"completed","total_rows":120,"processed_rows":120,
  "successful_rows":120,"failed_rows":0,"errors":[],
  "created_at":"…","completed_at":"…" }
```

### M14. GET categories/export/{id}/download — binary stream from public disk; `409 EXPORT_NOT_READY` unless completed && file exists. Filename falls back to basename(file_path).

## BRAND IMPORT FAMILY

### M15–M19 (POST brands/import :136 · sample :137 · status :138 · cancel :139 · errors :140)
BrandImportController + BrandImportRequest (same file rules) + ImportBrandsJob(meem-high). Status payload mirrors Category keys (`successful_rows` style, progress signal merge) — verified lines 163–182. Cancel/errors semantics identical to M4/M5. Realtime: `brand.import.progress` now actually dispatched (was false-log; fixed this window).

## BRAND EXPORT FAMILY

### M20–M22 (GET brands/export :141 · /{id} :142 · /download :143) — EXPORT_BRAND. Identical shapes to M12–M14 with `brand-export` type and `brands-export-{ts}.xlsx`.

## BULK DELETE FAMILY

### M23. POST categories/bulk-delete — Routes:162 · DELETE_CATEGORY
Body (BulkDeleteCategoriesRequest verified): `{ "ids":[…] }` ids required|array|min:1|distinct|integers|min:1|exists:categories.
Flow: unique-filter → Import(type=category-bulk-delete) + `ids_{id}.json` signal → BulkDeleteCategoriesJob(meem-high,t3,900,chunk100,cancel-aware) → `202 {bulk_delete_id,status:"pending"}` (key verified L199–201).

### M24. GET categories/bulk-delete/{id} — Routes:163 · bulkDeleteStatus
Data keys (verified): `id,status(effective cancelling),total_rows,processed_rows,successful_rows,failed_rows,errors[],error_count,created_at,completed_at?`.

### M25. POST categories/bulk-delete/{id}/cancel — Routes:164
Writes cancel signal ONLY (DB stays non-terminal until job observes it) → `200 {bulk_delete_id,status:"cancelling"}`. Job later marks cancelled + broadcasts `category.bulk-delete.cancelled` once.

## DIGITAL ASSETS (ADMIN)

Constructor middleware (DigitalAssetController): `auth:sanctum` + product-management permission + super-admin union (**exact slug union NOT RE-SHOWN here; license-keys adds manage-digital-licenses**).

### M26. GET products/{product}/digital-assets — Routes:234 (whereNumber product) → `DigitalAssetResource[]` (uuid,type,disk,path?,original_name,display_name,mime,size,extension,status,sort_order,checksum?,timestamps — **full field list NOT PROVEN FROM SOURCE** beyond resource existence).
### M27. POST products/{product}/digital-assets — Routes:235 · DigitalAssetCreateRequest (rules verified verbatim earlier: registry-driven mimes/maxKb, type-in-creatable, external_url requiredIf URL ≤2048, secret requiredIf ACCESS ≤2000). Storage private disk; content byte-inspection in service.
### M28–M30. GET/PUT/DELETE digital-assets/{uuid} — Routes:236–238 (UpdateRequest metadata rules).
### M31. POST digital-assets/{uuid}/replace — Routes:239 · ReplaceDigitalAssetRequest: file required(registry)+display_name opt; after-hook rejects non-FILE assets (422 DIGITAL_ASSET_NOT_REPLACEABLE).
### M32. POST digital-assets/{uuid}/license-keys — Routes:241 · StoreLicenseKeysRequest `{keys:[…≥1 ≤500(config)]}` → encrypted pool insert (encrypted_key cast), status available.

## ENTITLEMENTS (ADMIN W6)

### M33. GET digital-entitlements — Routes:244 · paginated `DigitalEntitlementResource` (fields enumerated in prior audit; `unlimited` derived from limit===0; user/product blocks whenLoaded).
### M34. GET digital-entitlements/{uuid} — single.
### M35. PATCH digital-entitlements/{uuid}/limit — Routes:246 · DigitalEntitlementLimitRequest `{limit: nullable int 0..4294967295}` → sets download_limit (null semantics **NOT PROVEN FROM SOURCE**).
### M36/M37. POST …/revoke · /restore — set revoked_at/status transitions (revocation cascades customer access instantly; restore clears). Resource returned.

## DASHBOARD (10 MODIFIED)

Common: Routes:357–373 · all GET · sanctum·throttle:analytics·permission:view-analytics · DashboardController@* → DashboardService (Cache 300s tag DASHBOARD) · Query params: only recent-orders/top-products/low-stock accept `limit` (default10,max50 — verified clamps).

| § | Endpoint | Verified response delta |
|---|---|---|
| D1 | order-stats | processing real counts; +delivered; dynamic unknown statuses; legacy zeros kept |
| D2 | products | inventory_value/out_of_stock PHYSICAL-only; +digital{products,units_sold,entitlements{active,revoked,expired},downloads{total,last_30_days},licenses{status:count}} |
| D3 | low-stock | physical-only rows |
| D4 | revenue | base-converted sums; +revenue_by_currency |
| D5 | finance | base-converted (incl. shipping×rate); +gross_by_currency |
| D6 | sales | daily/comparison/payment/fulfillment sums base-converted |
| D7 | orders | timelines base-converted |
| D8 | categories | line revenue × rate |
| D9 | coupons | coupon revenue base-converted |
| D10 | overview | totals base-converted (values only) |

*(recent-orders/top-products/category-stats/cart/reconciliation: NO observable change.)*
Errors: `409 {success:false,message:DASHBOARD_DATABASE_ERROR|SOMETHING_WENT_WRONG}` via controller catch; 403 view-analytics; 401; 429 analytics limiter.

## ORDERS (MARVEL)

### O1. PATCH /api/v1/orders/{id}/status — Routes:178 · name orders.update-status · whereNumber · OrderStatusUpdateRequest (`status` required|string|Rule::in canonical list incl. **processing**) · Marvel OrderController@updateStatus → OrderService transition → events/notifications.
**Δ:** numeric enforcement; processing accepted; response now FULL detail shape (`mergeWhen` extended) incl. **available_statuses** (OrderService targets map). Breaking-none/additive.
### O2. GET /api/v1/orders/{id} — same detail block (pre-existing show route) now includes available_statuses too (shared builder change).

## REFUNDS

### R1. /api/v1/refunds apiResource (index/store/show/update/destroy) — Routes:405–408 · sanctum·throttle:refunds.
Ownership verified: store validates `$user->id !== order.customer_id && !SUPER_ADMIN` → reject (RefundRepository:68); show branches SUPER_ADMIN/staff-permission vs `where('customer_id',user->id)` (RefundController:205–208).
**Δ:** anonymous previously reached data (IDOR) → now 401; cross-user show corrected. Status-code breaking by design.

## INVOICES (MARVEL ADMIN)

### I1. GET /api/v1/invoices/ — paginated AdminInvoiceResource (ADDED view_url `/api/v1/invoices/{id}`; download_url path changed to `/api/v1/invoices/{uuid}/download`).
### I2/I3. GET invoices/{uuid}/download · {uuid}/view — binary PDF, throttle:30,1 (URL format unchanged here; customer-facing equivalents moved to signed — see A-series).
### I4. GET invoices/{id} · GET invoices/uuid/{uuid} — InvoiceResource RESTORED (34-field canonical contract — full list in RESPONSE-CONTRACT-AUDIT A5; baseline was commented-out/dead).
### I5–I8. POST {id}/regenerate|correct|cancel|debit-note — lifecycle actions returning action-specific envelopes/resource (**bodies NOT PROVEN FROM SOURCE**).

---

# PART W — WEB ROUTE

### X1. GET /test-pusher — routes/web.php — **REMOVED** (−41 lines). Previously returned JSON with pusher_key/pusher_cluster and triggered anonymous admin-channel broadcast. Any frontend reference must be deleted.

---

# CROSS-CUTTING VERIFIED FACTS

- ApiResponse envelope: `{status,message,success,data?}` — message auto-translated via `translateNotice` (message.* fallback chain).
- Signed URLs: invoices 10 min; digital downloads issued per-request via `URL::temporarySignedRoute('general.digital.download', …)` (TTL value inside signedUrl() helper — **exact minutes NOT PROVEN FROM SOURCE**).
- Throttlers referenced: login, sensitive, otp(disabled group), admin, cart, analytics, refunds, public-api, authenticated, plus inline 30,1 / 5,1.
- Queue policy behind async endpoints: meem-high(5/1200) producers; realtime events ShouldBroadcastNow on private-users.{id}; failures isolated.
- All Δ claims above are backed by `git diff 63581db..HEAD` at file level and current-source line reads; items marked **NOT PROVEN FROM SOURCE** are explicitly out-of-evidence.
