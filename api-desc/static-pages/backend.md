# Backend — Static Pages Module

## Layered Structure

```
StaticPageController (admin, packages/marvel)          StaticPageController (public, app/Http)
        │  (ApiResponse + HasCache, permission middleware)        │  (ApiResponse + HasCache, lazy cache)
        ▼                                                          ▼
StaticPageService (app/Services/General/StaticPageService.php)
        │
        ▼
StaticPage / StaticSection (Marvel\Database\Models)
        │
        ▼
StaticPageResource / StaticSectionResource (App\Http\Resources\StaticPage)
```

No business logic lives in controllers or resources; all ownership checks, reorder scoping and
404 mapping live in `StaticPageService`.

## Key Files

| Concern | File |
|---------|------|
| Admin controller | `packages/marvel/src/Http/Controllers/StaticPageController.php` |
| Public controller | `app/Http/Controllers/Api/General/StaticPageController.php` |
| Service | `app/Services/General/StaticPageService.php` |
| Models | `packages/marvel/src/Database/Models/StaticPage.php`, `StaticSection.php` |
| Enum | `packages/marvel/src/Enums/StaticPageIdentifier.php` |
| Requests | `packages/marvel/src/Http/Requests/{UpdateStaticPage,StoreStaticSection,UpdateStaticSection,ReorderStaticSections}Request.php` |
| Resources | `app/Http/Resources/StaticPage/{StaticPageResource,StaticSectionResource}.php` |
| Observers | `app/Observers/StaticPageObserver.php`, `StaticSectionObserver.php` |
| Seeder | `database/seeders/StaticPageSeeder.php` |
| Migrations | `database/migrations/2026_08_18_0000{01,02}_*.php` |
| Tests | `tests/Feature/StaticPages/*.php` (9 classes, 105 tests) |

## Routes

- Admin (registered in `packages/marvel/src/Rest/Routes.php`, `auth:sanctum` + `throttle:admin`
  group): 7 routes under `static-pages`, pages bound by slug
  (`{static_page:slug}`), sections by id.
- Public (registered in `routes/api.php`, `v1/general` group, no auth): index + show by slug.
- `reorder` is declared before `sections/{static_section}` to avoid route capture.

## Permissions

Five spatie permissions applied **only** via controller middleware:

`view-static-pages`, `update-static-pages`, `create-static-sections`,
`update-static-sections`, `delete-static-sections`.

They are defined in `Marvel\Enums\Permission`, seeded in `PermissionSeeder` (master + editor
lists), and labelled in `resources/lang/{en,ar}/permissions.php`.

## Translations & Messages

- `constants.php` defines six `STATIC_*` constants → `MESSAGE.*` keys, resolved by
  `ApiResponse::translateNotice` (`STATIC_PAGE_UPDATED_SUCCESSFULLY`,
  `STATIC_SECTION_CREATED/UPDATED/DELETED_SUCCESSFULLY`,
  `STATIC_SECTIONS_REORDERED_SUCCESSFULLY`, `STATIC_SECTION_CONTENT_INVALID`).
- EN + AR keys added to `resources/lang/{en,ar}/message.php`.

## Translation Mechanics (Spatie HasTranslations)

- Resources read `getTranslation('title', app()->getLocale())` for the localized title and
  `getTranslations('content')` for the full locale map (null/empty values filtered).
- Partial locale updates merge per-locale, so sending only `title.en` preserves `title.ar`.
- A top-level list for `content` would trigger Spatie's single-locale branch, so it is rejected
  in the request's `withValidator` after-hook before reaching the model.

## Caching

- Trait: `App\Traits\HasCache::remember(tag, key, closure)` — **lazy**, so cache hits avoid the
  DB entirely.
- Tag: `FrontendResource::STATIC_PAGES` = `'static_pages'`; key: `md5(request()->fullUrl())`.
- Cache stores **models** (locale still resolved per request), not rendered resources.
- Invalidated on every mutation via `flushTag` in the controller; observers also flush for
  model-level changes. Reorder relies on the controller flush (query-builder `setNewOrder` fires
  no model events).

## Edge Cases Handled

- Cross-page section update/delete/reorder → 404 (no existence leak).
- Fixed-page invariants → 405 for POST/DELETE on pages; slug cannot be changed.
- Section content free-form but must be locale-keyed object → 422 otherwise.
- Deleting a section keeps remaining orders; new sections continue at `max + 1`.
- Public 404 for unknown or inactive page slug.
