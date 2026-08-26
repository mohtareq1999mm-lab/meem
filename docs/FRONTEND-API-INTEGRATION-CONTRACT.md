# FINAL FRONTEND API CONTRACT — ENDPOINT-BY-ENDPOINT (ZERO-GUESS)

Window **63581db..HEAD** · Rebuilt 2026-08-25 · Documentation-only.
Source priority honored: routes → providers → controllers → FormRequests → middleware → services → resources → models/enums → HTTP-affecting events/jobs → tests → git diff. Where code does not prove something, the exact marker **NOT PROVEN FROM SOURCE** is used (with the file/method to inspect).

Standard success/error envelope for most JSON endpoints (`Marvel\Traits\ApiResponse`, verified):
```json
{ "status": <httpInt>, "message": "<translated>", "success": true|false, "data": {…} }
```
`data` key omitted when empty. Conventions: `Authorization: Bearer <token>` on Sanctum routes · `Accept: application/json` everywhere · `Content-Type: application/json` (JSON) / `multipart/form-data` (uploads). Signed routes need **no Authorization**.

---

# PART C — CUSTOMER / STOREFRONT

Base prefix proven: RouteServiceProvider `prefix('api')` + in-file `Route::prefix('v1/general')`.

---

## C1. POST /api/v1/general/products/{id}/reviews

ROUTE FILE: routes/api.php
ROUTE OWNER: CUSTOMER/STOREFRONT
FINAL URL: `/api/v1/general/products/{id}/reviews`
HTTP METHOD: POST
ROUTE NAME: —
FULL MIDDLEWARE CHAIN: group(api) → group(auth:sanctum, throttle:authenticated) → controller permission gate CREATE_REVIEW (added this window)
CONTROLLER@METHOD: App\Http\Controllers\Api\General\ProductController@addProductReview

### Path Parameters
| Parameter | Type | Required | Validation | Meaning | Example |
|---|---|---|---|---|---|
| id | int | yes | implied product id | product being reviewed | 42 |

### Query Parameters
NONE

### Headers
Authorization: Bearer {token} · Accept: application/json · Content-Type: application/json

### Request Body
```json
{ "product_id": 42, "comment": "Great", "rating": 5 }
```
Validation (ReviewCreateRequest, verified): `product_id` required exists(products) · `comment` required string · `rating` required int 1–5. (images rules commented-out in source.)

### Response
`200` `{status:200,message:"review created",success:true,data:{…ReviewResource}}`
`403` missing permission (**NEW**) · `404` product/review not found · `422` field errors `{errors:{field:[…]}}`.

Δ window: 403 introduced → breaking for permission-less callers.
FE: hide form on 403; map ReviewResource fields.

---

## C2. PUT /api/v1/general/products/reviews/{id}

ROUTE FILE: routes/api.php · OWNER: CUSTOMER/STOREFRONT
FINAL URL: PUT `/api/v1/general/products/reviews/{id}` · NAME: —
CHAIN: api → auth:sanctum → throttle:authenticated → UPDATE_REVIEW gate (window)
CONTROLLER@METHOD: General\ProductController@updateProductReview

Path: `id`=review id. Query: NONE. Body: `{ "comment": "required", "rating": 4 }` (ReviewUpdateRequest).
Response `200` REVIEW_UPDATED + ReviewResource · 403 (NEW) · 404 not-owned/not-found · 422.

---

## C3. GET /api/v1/general/site-reviews

ROUTE FILE: routes/api.php:104 · OWNER: CUSTOMER/STOREFRONT · NEW
METHOD GET · NAME —
CHAIN: api → throttle:public-api (public)
CONTROLLER@METHOD: General\SiteReviewController@index

Query: NONE.
**200** `{…, data:[{id,rating,title,comment,customer{id,name}?,created_at}]}` (SiteReviewResource; approved-only; server-cached tag SITE_REVIEWS).

---

## C4. POST /api/v1/general/site-reviews

routes/api.php:146 · [C] · sanctum · SiteReviewController@store → CreateSiteReviewRequest.
Body (verified): `{ "rating": 5, "title": "opt ≤191", "comment": "req ≤2000" }`.
**201** + SiteReviewResource · 401/422.

---

## C5. GET /api/v1/general/currencies

routes/api.php:105 · [C] public · NEW · CurrencyController@index.
**200 data[] = CurrencyResource:** `id,code,name{},symbol{},country_name{},numeric_code,decimal_places(int),icon,is_active,is_base,is_catalog,created_at,updated_at`.

---

## C6. POST /api/v1/general/currencies/select

routes/api.php:107 · [C] sanctum · SelectCurrencyRequest(`currency_code` required) · CurrencyController@select.
Body: `{"currency_code":"usd"}`.
Sets user preference AND guest cookie; clears effective-code cache.
**200** envelope + CurrencyResource. Affects all later money conversions.

---

## C7. POST /api/v1/general/device-tokens

routes/api.php:143 · [C] sanctum · DeviceTokenController@store.
Body (verified rules): token req ≤512 · client in client_a|client_b · platform opt android|ios.
**200** `{data:{uuid}}`. Token globally unique → re-register reassigns owner.

## C8. DELETE /api/v1/general/device-tokens
Same file/ownership · body `{"token":"…"}` → deletes caller-owned rows → **200** ack.

---

## C9. GET /api/v1/general/digital/downloads

routes/api.php:128 · [C] sanctum · DigitalDownloadController@index.
Ownership: entitlements scoped to caller (verified where-user_id).
**200 data[] per entitlement (all keys verified):**
```json
{
  "uuid":"…","status":"delivered","download_limit":5,"download_count":2,
  "delivered_at":"ISO","revoked_at":null,"expires_at":null,
  "product":{"id":7,"name":"…"},
  "assets":[{
    "uuid":"…","type":"FILE","original_name":"…","mime":"application/pdf","size":1024,
    "delivery_type":"download|redirect|reveal",
    "download_url":"<signed 30min|null>",
    "external_url":"<URL-type AND accessAllowed|null>",
    "reveal":{"path":"/api/v1/general/digital/license/{e}/{a}"}
  }]
}
```
Conditionals proven: FILE→download_url · URL→external_url (accessAllowed only) · LICENSE/ACCESS→reveal path (secret never included).

## C10. GET /api/v1/general/digital/license/{entitlement}/{asset}

routes/api.php:131 · whereUuid ×2 · name general.digital.license · sanctum.
Flow: DeliveryResolver@revealCredential.
**200** `{type:"license"|"access", credential:"<decrypted>", revealed_at}`
**403** second reveal (ALREADY_REVEALED) / inaccessible · **404** no-allocation/missing. Single-shot, audited.

## C11. GET /api/v1/general/digital/url/{entitlement}/{asset}
routes/api.php:136 · sanctum · name general.digital.url.
**302** redirect to stored external URL (audited; app never fetches target; no download-count use) · else 403/404 envelopes.

## C12. GET /api/v1/general/digital/download/{entitlement}/{asset}

ROUTE FILE: routes/api.php:168 (root level — outside general prefix)
OWNER: CUSTOMER/STOREFRONT · METHOD GET · NAME general.digital.download
CHAIN: signed → throttle:30,1 (**NO Sanctum**)
CONTROLLER@METHOD: General\DigitalDownloadController@download → DeliveryResolver@deliver(download)

TTL proven: `config('digital.signed_url_ttl_minutes',30)` minutes; helper returns null when accessAllowed false.
Success = binary attachment stream. Errors: bad/expired signature ⇒ framework 403 HTML; revoked/expired/exceeded ⇒ JSON 403/404 envelopes.

---

## C13. GET /api/v1/general/invoices/my-invoices

routes/api.php:149 · [C] sanctum · InvoiceController@myInvoices.
Query: `limit` int optional default **15** max **100** (verified clamp). Sort created_at desc (verified).
Wrapper: `CustomerInvoiceCollection` inside envelope data (verified).
Row (CustomerInvoiceListResource): uuid,invoice_number,status,subtotal,shipping_price,total_discount,total,currency,payment_method,payment_gateway,generated_at,pdf_generated_at,verification_url,view_url(signed 10 min),download_url(signed 10 min).

## C14. GET /api/v1/general/invoices/verify/{uuid}

routes/api.php:151 · PUBLIC · throttle:5,1 · InvoiceController@verify → InvoiceService@verifyInvoice.
Responses: **200** verification payload (increments verify_count; sets timestamps — do NOT poll; inner keys beyond envelope **NOT PROVEN FROM SOURCE**) · **409** `{authentic:false,tampered:true,message:"Invoice verification failed"}` · **404** NOT_FOUND.

## C15/C16. SIGNED view & download

ROUTE FILE: routes/api.php:159/:161 · PREFIX v1/general/invoices · MIDDLEWARE signed ONLY (NO Sanctum)
NAMES general.invoices.view | general.invoices.download (10-minute signatures)
CONTROLLERS: viewByUuidSigned / downloadByUuidSigned
C15 renders inline PDF; C16 streams attachment. Expired/tampered signature → Laravel **403 HTML** (not API envelope).

---

## C17. POST /api/v1/general/checkout

routes/api.php:115 · [C] sanctum · throttle:authenticated · OrderCreateRequest (marvel) · OrderController@checkout.
Verified rules (window-relevant):
```json
{
  "name": "req ≤255",
  "user_phone": "req ≤255",
  "user_email": "req email ≤255",
  "address": "requiredIf(requiresShipping && fulfillment_type!==PICKUP)",
  "governorate_id": "requiredIf delivery",
  "pickup_location_id": "requiredIf pickup",
  "notes": "nullable",
  "selected_promotion_id": "nullable exists promotions",
  "selected_gift_product_id": "nullable exists products",
  "type": "nullable in mobile,web",
  "fulfillment_type": "enum FulfillmentType",
  "payment_method": "nullable in online,cod,pay_at_cashier",
  "gateway": "nullable ≤50"
}
```
Line items come from the caller's ACTIVE cart (server-side). **Window change:** address/governorate became conditional → digital/pickup checkout needs no address.
Flow: OrderCreationService (FX snapshot currency_code/base/rate/date/converted_total_price/catalog + item_type lines) → payment branch → events → queued notifications.
Response: customer order payload incl. additive `converted_total,currency,base_currency,catalog_currency,exchange_rate,digital_downloads[]?(uuid,order_item_id,status,download_limit,download_count,delivered_at)`; items add converted prices + item_type.
Errors 401/422/409(stock-state envelope).

## C18/C19. ANY checkout/callback · checkout/error-callback

routes/api.php:109–110 · PUBLIC · throttle:public-api · names api.checkout.callback|errorCallback · OrderController@checkoutCallback|checkoutErrorCallback.
Verified flow: `paymentId` (query/body) else **400 MISSING_PAYMENT_ID** → transaction lookup(gateway_transaction_id|invoice_id; gateway fallback myfatoorah) → unsupported gateway **500 PAYMENT_GATEWAY_UNAVAILABLE** → verify.
Failure (proven): transaction updated(status/gateway_response/_callback_type/error_message); `_callback_type==='mobile'` ⇒ **200** envelope `{status:"failed",message,payment_id}`; ELSE **302 redirect** `{app_url_frontend}/{locale}/payment/failed?status=failed&message=…&payment_id=…`.
Success/no-order branches mirror mobile-JSON vs redirect pattern; exact success redirect args **NOT PROVEN FROM SOURCE**.

## C20/C21. GET pickup-locations · pickup-locations/{id}
Public; additive row field `is_default(bool)` (default-branch feature). Otherwise unchanged.

---

# PART A — ADMIN / MARVEL

Provider mount: `RestAPIServiceProvider::Route::prefix('api/v1')` ⇒ final URLs below. Outer group middleware `auth:sanctum` + `throttle:admin` applies to §A-M* CRUD/import families (verified at Routes:116); dashboard/refunds/invoices groups carry their own chains as printed.

## PRODUCT IMPORT

### A-M1. POST /api/v1/products/import
ROUTE FILE: packages/marvel/src/Rest/Routes.php:227 · OWNER ADMIN/MARVEL · NAME admin.products.import
CHAIN: auth:sanctum → throttle:admin → ctor auth:sanctum + permission:create-product|super_admin
CONTROLLER@METHOD: Marvel\Http\Controllers\ProductImportController@import
Headers: Authorization Bearer · Content-Type multipart/form-data.
Body: multipart `file` — required|mimes:xlsx,xls,ods|max:20480KB (ProductImportRequest).
Flow: store imports/ (public disk) → row estimate → Import(type=product,pending) → progress signal → ImportProductsJob(meem-high,t3,t1500,backoff60/120/240).
**202** `{data:{import_id,status:"pending"}}` · 401/403/422(file.*)/500.
Realtime: job emits `product.import.progress` (+terminals) on private-users.{id}; failures isolated.

### A-M2. GET /api/v1/products/import/sample — binary XLSX template; 404 if package asset absent. Routes:225.

### A-M3. GET /api/v1/products/import/{id} — Routes:228 · status().
**200 data keys EXACT:** `id,status(pending|processing|cancelling|completed|completed_with_errors|failed|cancelled),total_rows,processed_rows,**success_rows**,failed_rows,progress(0–100),errors[{sheet,row,sku,error_message}]`.
`cancelling` while cancel signal pending. **Headers:** Cache-Control:no-cache,no-store,must-revalidate · Pragma:no-cache · Expires:0. Errors 404.

### A-M4. POST /api/v1/products/import/{id}/cancel — Routes:229.
Terminal ⇒ **409 IMPORT_CANNOT_CANCEL**. Else **200** `{import_id,status:"cancelled"}` + realtime terminal (mid-run cancel rolls back created rows first).

### A-M5. GET /api/v1/products/import/{id}/download-errors — binary report (Sheet/Row/SKU/Error Message), temp auto-delete; **404 IMPORT_NO_ERRORS** when empty.

### A-M6. GET /api/v1/products/export — UNCHANGED synchronous binary XLSX (filters via ProductExportRequest — **rules NOT PROVEN FROM SOURCE**). Async conversion deferred (G3).

## CATEGORY FAMILY

### A-M7. POST /api/v1/categories/import — Routes:154 · CategoryImportRequest(identical file rules, translated msgs) · CategoryImportController → ImportCategoriesJob(meem-high) → CategoryImportService **broadcasts category.import.progress** on private-users.{id} + legacy admin.notifications (payload: progress,processed_rows,success_rows,failed_rows,type:"category",import_id). Start **202** `{import_id,status:"pending"}`.
### A-M8. GET categories/import/sample — binary template.
### A-M9. GET categories/import/{id} — status data keys EXACT: `id,status,total_rows,processed_rows,**successful_rows**,failed_rows,errors,created_at,completed_at(null until terminal)` + progress merged from signal (≤99 running). Same no-cache headers.
### A-M10. POST categories/import/{id}/cancel — signal + immediate DB cancelled + broadcast terminal(additive status) → `200 {import_id,status:"cancelled"}`; terminal ⇒ 409.
### A-M11. GET categories/import/{id}/download-errors — binary; 404 when empty.

## CATEGORY EXPORT

### A-M12. GET categories/export — Routes:159 · EXPORT_CATEGORY|super_admin → **202** `{export_id,status:"pending"}`; ExportCategoriesJob(meem-high,t2,600) writes `categories-export-{ts}.xlsx`(public disk) then completed + broadcast category.export.completed (strictly after DB update).
### A-M13. GET categories/export/{id} — status: id,status,total_rows,processed_rows,successful_rows,failed_rows,errors,created_at,completed_at?
### A-M14. GET categories/export/{id}/download — binary stream; **409 EXPORT_NOT_READY** unless completed && file exists.

## BRAND FAMILY

### A-M15–A-M19 brands/import(+sample,status,cancel,download-errors) — Routes:136–140 · BrandImportRequest identical rules · mirrors Category shapes(successful_rows style) · realtime brand.import.progress NOW REAL (false-log fixed; regression-pinned).
### A-M20–A-M22 brands/export(+status,download) — Routes:141–143 · EXPORT_BRAND · type brand-export · identical shapes to M12–14 with brand.export.completed|failed.

## BULK DELETE

### A-M23. POST categories/bulk-delete — Routes:162 · DELETE_CATEGORY · BulkDeleteCategoriesRequest ids required|array|min:1|distinct|*.int|*.min|exists:categories → **202** `{bulk_delete_id,status:"pending"}`; ids mirrored to signal file.
### A-M24. GET categories/bulk-delete/{id} — Routes:163 → id,status(effective cancelling),total_rows,processed_rows,**successful_rows**,failed_rows,errors[],**error_count**,created_at,completed_at?
### A-M25. POST categories/bulk-delete/{id}/cancel — Routes:164 → cancel signal ONLY → **200 {bulk_delete_id,status:"cancelling"}**; job later flips DB cancelled + single cancelled event.

## DIGITAL ASSETS (ctor: auth + product-mgmt union; license-keys adds manage-digital-licenses)

### A-M26. GET products/{product}/digital-assets — Routes:234 → DigitalAssetResource[] (uuid,type,disk,path,original_name,display_name,mime,size,extension,status,sort_order,checksum?,timestamps — **full list NOT PROVEN FROM SOURCE**).
### A-M27. POST products/{product}/digital-assets — Routes:235 · DigitalAssetCreateRequest (registry-driven mimes/maxKb; type∈creatable; external_url req-if-URL ≤2048; secret req-if-ACCESS ≤2000; original_name≤255; sort_order≥0). Failure override: plain `{field:[msgs]}` 422. → resource.
### A-M28/M29/M30. GET|PUT|DELETE digital-assets/{uuid} — resource / metadata(DigitalAssetUpdateRequest) / ack.
### A-M31. POST digital-assets/{uuid}/replace — ReplaceDigitalAssetRequest: file required(registry)+display_name opt; **FILE-type only** else 422 DIGITAL_ASSET_NOT_REPLACEABLE.
### A-M32. POST digital-assets/{uuid}/license-keys — StoreLicenseKeysRequest `{keys:[… ≥1 ≤config500]}` → encrypted pool(available) summary.

## ENTITLEMENTS

### A-M33. GET digital-entitlements — Routes:244 · paginated DigitalEntitlementResource: uuid,status(pending|delivered|revoked),download_limit,**unlimited**(limit===0),download_count,delivered_at,revoked_at,expires_at,order_id,order_product_id,user{id,name,email}(whenLoaded),product{id,name}(whenLoaded),created_at.
### A-M34. GET digital-entitlements/{uuid} — single.
### A-M35. PATCH digital-entitlements/{uuid}/limit — DigitalEntitlementLimitRequest limit nullable int 0..4294967295. Controller-proven nuance: ABSENT key→null→service; PRESENT(null)→cast 0. **200** `{uuid,previous_limit,download_limit,unlimited(new===UNLIMITED)}`.
### A-M36/A-M37. POST …/revoke · …/restore — revoke sets revoked_at/status(instant customer cut-off); restore clears. Resource returned.

## DASHBOARD (chain each: auth:sanctum → throttle:analytics → permission:view-analytics → DashboardController@* → DashboardService, Cache300s tag DASHBOARD)

### A-D1. GET dashboard/order-stats — buckets today/weekly/monthly/yearly `{pending,processing(real),completed,cancelled,delivered,refunded:0,failed:0,local_facility:0,out_for_delivery:0[,dynamic]}`. Δ: processing real; +delivered; dynamic unknown statuses; legacy zeros kept.
### A-D2. GET dashboard/products — inventory_value/out_of_stock PHYSICAL-only; ADDED digital{digital_products,digital_units_sold,entitlements{active,revoked,expired},downloads{total,last_30_days},licenses{status:n}}.
### A-D3. GET dashboard/low-stock — physical-only rows; limit opt default10 max50.
### A-D4. GET dashboard/revenue — base-converted totals; ADDED revenue_by_currency{}.
### A-D5. GET dashboard/finance — base-converted (incl shipping×rate); ADDED gross_by_currency{}.
### A-D6. GET dashboard/sales — daily/comparison/payment/fulfillment sums converted-base.
### A-D7. GET dashboard/orders — timeline sums converted-base.
### A-D8. GET dashboard/categories — line revenue × rate.
### A-D9. GET dashboard/coupons — coupon revenue converted-base.
### A-D10. GET dashboard/overview — total/todays revenue converted-base.

Unchanged siblings: recent-orders(limit≤50)/top-products(limit≤50)/category-stats/cart/reconciliation({total_checked,total_mismatches,pending_mismatches,resolved_mismatches,last_run}).
Errors(all): 401 · 403 view-analytics · 409 {success:false,message:DASHBOARD_DATABASE_ERROR|SOMETHING_WENT_WRONG} · 429.

## ORDERS (ADMIN)

### A-O1. PATCH /api/v1/orders/{id}/status
Routes:178 · NAME orders.update-status · whereNumber(id) · OrderStatusUpdateRequest: status required|string|in(pending,**processing**,completed,delivered,cancelled) · Marvel Order\OrderController@updateStatus → OrderService transitions → events/notifications.
Δ: numeric enforcement; processing accepted; response returns FULL detail resource (mergeWhen extended) incl. **available_statuses[]**. Additive.
Errors 401/403/404(non-numeric/missing)/422.

### A-O2. GET /api/v1/orders/{id} — same builder; includes available_statuses.

## REFUNDS — Routes:405–408 · sanctum · throttle:refunds · RefundController/Repository
Ownership proven (Repo:68 store-owner check; Ctrl:205–208 show scoping).

### A-R1a. GET refunds — anonymous previously leaked data ⇒ now **401**.
### A-R1b. POST refunds — non-owner/non-super-admin rejected (verified condition).
### A-R1c. GET refunds/{id} — cross-user corrected (was inverted condition).
### A-R1d. PUT refunds/{id} — scoped like R1c.
### A-R1e. DELETE refunds/{id} — scoped like R1c.
Verdict: intentional status-code breaks for abusive callers; payloads unchanged.

## INVOICES (ADMIN) — Routes:409–419 · sanctum group

### A-I1. GET /api/v1/invoices/ — paginated AdminInvoiceResource; ADDED view_url(/api/v1/invoices/{id}); download_url repointed to /api/v1/invoices/{uuid}/download.
### A-I2. GET invoices/{uuid}/download — binary PDF · throttle:30,1.
### A-I3. GET invoices/{uuid}/view — inline PDF · throttle:30,1.
### A-I4. GET invoices/{id} · GET invoices/uuid/{uuid} — RESTORED canonical InvoiceResource (baseline commented-out/dead). Fields(34): id,uuid,order_id,invoice_number,status,subtotal,shipping_price,coupon_discount,promotion_discount,total_discount,total,amount_paid,currency,payment_method,payment_gateway,snapshot_hash,verification_hash,pdf_generated_at,generated_at,generation_attempts,last_generation_error,is_correction,correction_reason,corrected_at,cancelled_at,cancellation_reason,verified_at,downloaded_at,printed_at,archived_at,last_verified_at,verify_count,created_at,verification_url,view_url,download_url.
### A-I5. POST invoices/{id}/regenerate — allowed failed|ready|generated else 422 (legacy msg key ERROR_ADDING_ITEMS_TO_ORDER — cosmetic known issue); sets pdf_generating, attempts++, clears error.
### A-I6. POST invoices/{id}/correct — CorrectInvoiceRequest: reason req ≤500; overrides nullable{total,amount_paid,shipping_price numeric≥0,customer{name,email,phone},billing_address{},shipping_address{},notes} → 200 corrected invoice.
### A-I7. POST invoices/{id}/cancel — reason req ≤500 → 200 cancelled invoice.
### A-I8. POST invoices/{id}/debit-note — DebitNoteRequest amount numeric≥0.01 req; reason req ≤500; allowed generated|ready|verified|downloaded|printed else 422 INVOICE_DEBIT_NOTE_NOT_ALLOWED(status interpolated).

---

# PART W — WEB

### W-X1. GET /test-pusher — routes/web.php — REMOVED (security: leaked pusher_key/cluster; anonymous admin-channel trigger). Frontend must delete references.

---

# GLOBAL FRONTEND INTEGRATION RULES

1. Send `Accept: application/json` always; `Authorization: Bearer` on all Sanctum routes; NEVER send tokens to signed endpoints (C12/C15/C16 redeem by signature).
2. Async ops (imports/exports/bulk delete): capture returned id → subscribe Pusher `private-users.{userId}` (events *.import.progress / *.export.completed|failed / category.bulk-delete.*) → reconcile via matching status GET on event, reconnect, or mount. Terminal wins; clamp progress upward; treat duplicates idempotently.
3. Status-code specials: 202 async start · 403 permission/signed-expiry · 409 conflict (cancel-terminal, export-not-ready, tampered invoice, dashboard DB error) · 422 validation/lifecycle · 429 throttles.
4. Multi-currency: after currencies/select every money value follows effective currency (cart) or carries explicit currency fields (orders/invoices/dashboard breakdowns). Never sum across currencies client-side.
5. Signed links are opaque & short-lived (invoices 10 min; digital downloads 30 min default config) — refetch on expiry instead of reconstructing.
