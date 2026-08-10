# Jira - Currency Feature

## Epic: Multi-Currency Support

### Story Points Estimate: 13

## User Stories

### US-001: Manage Currencies
**As** an admin
**I want** to create, view, update, and delete currencies
**So that** the store can support multiple currencies

**Acceptance Criteria:**
- Create currency with 3-letter ISO code, translatable name/symbol/country_name, decimal places, icon, active flag
- Duplicate/uppercase-variant codes rejected
- Soft delete currencies with no exchange rates
- Base currency and currencies with rates cannot be deleted (409 with distinct messages)

### US-002: Manage Exchange Rates
**As** an admin
**I want** to create, view, update, and delete exchange rates
**So that** conversion values stay current

**Acceptance Criteria:**
- One rate per currency per effective date (upsert on same day)
- Rate must be a positive numeric value
- Filter rates by currency and effective date
- Deleting a rate is a hard delete

### US-003: Set Base Currency
**As** an admin
**I want** to switch the base currency
**So that** prices and conversions reflect the primary currency

**Acceptance Criteria:**
- Base currency must be active
- Base currency must have a rate on/before today
- Switching updates settings and flushes price caches

### US-004: Convert Prices
**As** the system
**I want** bcmath-precision conversions with historical rate lookup
**So that** money values are accurate

**Acceptance Criteria:**
- Same-currency conversion is identity without DB queries
- Latest rate on/before a date is used; missing rate throws
- Conversions use scale-6 arithmetic and round to 2 decimals for prices

### US-005: Snapshot Currency on Orders
**As** the system
**I want** to record the currency snapshot on every order
**So that** orders are auditable even if rates change later

**Acceptance Criteria:**
- Orders store currency_code, base_currency_code, currency_rate, currency_rate_date, converted_total_price
- Snapshot refreshes when an order is updated after a base-currency change
- OrderResource exposes currency, base_currency, exchange_rate, converted_total

### US-006: Public Currency List
**As** a storefront visitor
**I want** a fast, cached list of active currencies
**So that** the UI can render a currency selector

**Acceptance Criteria:**
- No authentication required
- Only active currencies, cached 4h under the `currencies` tag
- Cache invalidated on any currency/rate change

## Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| BUG-001 | Non-numeric currency/rate IDs returned 500 instead of 404 | High | High |
| BUG-002 | Base currency without rates could be deleted (check order) | High | High |
| BUG-003 | Date comparisons failed on SQLite (raw `<=` vs `whereDate`) | Medium | Medium |
| BUG-004 | Rate string precision differs between SQLite and MySQL | Low | Low |
