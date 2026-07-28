# Invoice Module — Changelog

## [1.0.0] — 2026-07-28

### Added
- Invoice API investigation documentation (`api-desc/invoice/`)
- Full API documentation for all 10 endpoints

### Known Issues

1. **Hardcoded response messages** — Success/error messages are literal English strings. No translation keys exist for invoice messages (except `ERROR_CREATING_INVOICE`).
2. **ModelNotFoundException not caught** — `show()`, `showByUuid()`, `download()`, `regenerate()`, `correct()`, `cancel()`, `issueDebitNote()` call `findOrFail()`/`firstOrFail()` without catching the exception. Non-existent records return HTML exception page instead of JSON 404.
3. **Duplicate status allowlists** — `cancelInvoice()` in service, `regenerate()`, and `issueDebitNote()` in controller maintain separate status arrays that duplicate the enum's state machine. Enum changes require manual sync.
4. **Inline validation in cancel()** — `cancel()` uses `$request->validate()` instead of a dedicated Form Request.
5. **Download returns 404 instead of 403** — Unauthorized download returns 404 (privacy) instead of 403, inconsistent with other modules.
6. **No rate limiting on auth group** — Only `verify` and `download` have throttles. List, show, my-invoices, regenerate, correct, cancel, debit-note have no limit.
7. **No feature/API tests** — Only 20 unit tests exist in `InvoiceLifecycleTest.php`. No controller-level tests.
8. **No seeder** — No seed data for development.
9. **Self-referencing correction FK** — `correction_to_id` references `invoices.id` with `SET NULL` on delete — if the original invoice is ever deleted (blocked by restrict), corrections would lose their parent reference.
