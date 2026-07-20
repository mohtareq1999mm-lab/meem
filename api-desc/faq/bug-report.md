# Bug Report — FAQ Module

---

## BUG-FAQ-001: English FAQ Translation Keys Missing

**Severity:** Low

**Component:** Translations

**Description:** The English `resources/lang/en/message.php` file does not contain FAQ translation keys. The Arabic translations exist in `resources/lang/ar/message.php`, but the English keys fall back to raw constant strings (e.g., `"MESSAGE.FAQ_CREATED_SUCCESSFULLY"` displayed as-is instead of "FAQ created successfully").

**Code Location:** `resources/lang/en/message.php`

**Status:** ❌ Open

---

## BUG-FAQ-002: GraphQL Schema References Non-Existent Columns

**Severity:** Medium

**Component:** GraphQL Schema

**Description:** The GraphQL schema at `packages/marvel/src/GraphQL/Schema/models/faqs.graphql` defines fields (`shop_id`, `slug`, `faq_type`, `issued_by`, `language`, `translated_languages`) that do not exist in the current `faqs` migration. Queries or mutations referencing these fields will fail.

**Code Location:** `packages/marvel/src/GraphQL/Schema/models/faqs.graphql`

**Status:** ❌ Open

---

## BUG-FAQ-003: User and Shop Relations Defined But Columns Missing

**Severity:** Low

**Component:** Model

**Description:** The `Faqs` model defines `user()` and `shop()` BelongsTo relations, but the current `faqs` migration does not have `user_id` or `shop_id` columns. These relations will return null or throw errors when accessed.

**Code Location:** `packages/marvel/src/Database/Models/Faqs.php` — lines for `user()` and `shop()` relations

**Status:** ❌ Open

---

## BUG-FAQ-004: Public FAQ Endpoint Missing Search/Pagination

**Severity:** Low

**Component:** Public API

**Description:** The public `/api/v1/general/faqs` endpoint returns ALL active FAQs with no search, filter, or pagination support. For platforms with many FAQs (50+), this can result in large response payloads.

**Code Location:** `app/Services/General/faqService.php` — `getfaqs()`

**Status:** ❌ Open — Enhancement request

---

## BUG-FAQ-005: Index Response Title Format Differs From Other Routes

**Severity:** Low

**Component:** Resource

**Description:** On `faqs.index`, the `FaqResource` returns a translated string for the current locale. On all other routes (show, store, update), it returns the raw JSON object with all locales. This inconsistency may confuse frontend developers expecting a consistent response format.

**Code Location:** `packages/marvel/src/Http/Resources/FaqResource.php`

**Status:** ✅ By design — Index returns single locale for listing display; show returns raw JSON for editing.
