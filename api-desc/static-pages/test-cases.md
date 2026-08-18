# Test Cases — Static Pages Module

Suite: `tests/Feature/StaticPages/` (9 classes, 105 tests, all green).
Setup: `RefreshDatabase`, sqlite `:memory:`, `CACHE_DRIVER=array`, spatie permissions created
per test, `Sanctum::actingAs` for auth.

## StaticPageJsonRoundTripTest (20 cases)

Covers DB + API round-trip integrity for translatable JSON.

| # | Case |
|---|------|
| 1 | Page title round-trips en+ar through DB |
| 2 | Section title round-trips en+ar |
| 3 | Section content map round-trips en+ar |
| 4 | Content with nested arrays (blocks) round-trips |
| 5 | Content with scalar values (int/bool/float) round-trips |
| 6 | Single-locale content round-trips |
| 7 | Null content → empty map (spatie null translation filtered) |
| 8 | Section created via API → DB row matches |
| 9 | Section created via API → response matches DB |
| 10 | Page updated via API → DB row matches |
| 11 | Page partial locale update preserves other locale |
| 12 | Section partial content update preserves other locale |
| 13 | Section partial title update preserves other locale |
| 14 | Section order stored/returned as integer |
| 15 | Page `is_active` round-trips as boolean (incl. via API) |
| 16 | Public index returns localized EN title |
| 17 | Public index returns localized AR title (`lang: ar`) |
| 18 | Public API returns full content map |
| 19 | Admin show returns sections in order |
| 20 | Page update cannot change slug |

## StaticPageCrudTest (10 cases)

| # | Case |
|---|------|
| 1 | Admin lists pages |
| 2 | Admin shows single page with sections |
| 3 | Admin updates page title |
| 4 | Admin deactivates page |
| 5 | Admin creates section |
| 6 | Admin updates section |
| 7 | Admin deletes section |
| 8 | Admin reorders sections |
| 9 | Reorder persists new order in DB |
| 10 | Full lifecycle (create → update → delete) |

## StaticPageAuthorizationTest (15 cases)

| # | Case |
|---|------|
| 1–7 | Guest gets 401 on all 7 admin endpoints |
| 8 | Authenticated user without any permission → 403 |
| 9 | `view-static-pages` → 200 index/show, 403 on all mutations |
| 10 | `update-static-pages` → 200 update only |
| 11 | `create-static-sections` → 200 create only |
| 12 | `update-static-sections` → 200 update + reorder only |
| 13 | `delete-static-sections` → 200 delete only |
| 14 | Full permission set → all admin operations 200 |
| 15 | Public endpoints accessible without auth |

## StaticPageValidationTest (15 cases)

| # | Case |
|---|------|
| 1 | Create: title required |
| 2 | Create: title.en required |
| 3 | Create: content required |
| 4 | Create: top-level list content → 422 + message |
| 5 | Create: content.en must be array |
| 6 | Create: title locale must be string |
| 7 | Create: title.en > 255 → 422 |
| 8 | Update page: title must be array |
| 9 | Update page: is_active must be 0/1 |
| 10 | Update section: list content → 422, no mutation |
| 11 | Update section: title must be string |
| 12 | Reorder: sections required |
| 13 | Reorder: nonexistent id → 422 |
| 14 | Reorder: duplicate ids → 422 |
| 15 | Reorder: non-integer id → 422 |
| 16 | Failed validation never persists any row |

## StaticPageFixedInvariantTest (11 cases)

| # | Case |
|---|------|
| 1 | POST /static-pages → 405 |
| 2 | DELETE /static-pages/{slug} → 405 |
| 3 | slug in update payload is ignored |
| 4 | Seeder is idempotent (run 3x → still 3 pages) |
| 5 | Seeder creates exactly the 3 fixed slugs |
| 6 | Seeder never overwrites an edited title |
| 7 | Seeder never overwrites a deactivated page |
| 8 | Seeder never creates sections |
| 9 | Seeder never deletes admin-created sections |
| 10 | Public unknown slug → 404 |
| 11 | Public index returns only active pages (inactive hidden) |

## StaticPageCacheTest (10 cases)

| # | Case |
|---|------|
| 1 | Public index warms `static_pages` tag |
| 2 | Public show warms `static_pages` tag |
| 3 | Repeated index calls stay cached |
| 4 | Cache stores models, not rendered resources |
| 5 | Page update invalidates cache |
| 6 | Section create invalidates cache |
| 7 | Section update invalidates cache |
| 8 | Section delete invalidates cache |
| 9 | Section reorder invalidates cache |
| 10 | Mutated data is never served from stale cache |

## StaticPageNPlusOneTest (4 cases)

| # | Case |
|---|------|
| 1 | Public index: 1 page query + 1 eager section query (no N+1) |
| 2 | Public show: single eager section query |
| 3 | Admin index: single eager section query |
| 4 | Repeated public request → 0 DB queries (lazy cache) |

## StaticPageDeleteBehaviorTest (8 cases)

| # | Case |
|---|------|
| 1 | Delete section removes the row |
| 2 | Delete on one page leaves other pages' sections intact |
| 3 | Remaining sections keep their order after delete |
| 4 | New section after delete gets `max + 1` |
| 5 | Deleting a page cascades its sections (DB level) |
| 6 | Deleting a foreign section via page route → 404 |
| 7 | Updating a foreign section via page route → 404 |
| 8 | Reorder containing a foreign id → 404 |

## StaticPageSeederTest (10 cases)

| # | Case |
|---|------|
| 1 | Creates exactly 3 pages |
| 2 | Expected slugs |
| 3 | English titles |
| 4 | Arabic titles |
| 5 | All pages active |
| 6 | Zero sections |
| 7 | Idempotent |
| 8 | Preserves edited titles |
| 9 | Preserves deactivated pages |
| 10 | Seeded pages publicly accessible (index + each slug) |
