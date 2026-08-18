# Business Flow — Static Pages Module

## 1. Page Lifecycle (seeded, never created/deleted via API)

```
StaticPageSeeder::run()
   └─ for each slug (about-us, terms-and-conditions, privacy-policy):
        StaticPage::firstOrCreate(['slug' => $slug], ['title' => en/ar, 'is_active' => true])
```

- Re-running is a no-op for existing rows — titles, `is_active` and sections are preserved.
- There is **no** create or delete page endpoint, so the page set is stable for frontend links
  and SEO.

## 2. Public Read Flow (cached)

```
GET /api/v1/general/static-pages        GET /api/v1/general/static-pages/{slug}
   │                                         │
   ├─ StaticPage::where('is_active', true)   └─ StaticPage::where slug + is_active(true)
   │        ->with('staticSections')->get()        ->with('staticSections')->firstOrFail()
   │
   └─ remember(tag 'static_pages', md5(fullUrl), closure)
        • Cache HIT  → 0 DB queries (lazy closure never runs)
        • Cache MISS → run query, cache models (NOT rendered resources)
   │
   └─ StaticPageResource::collection/make → localized title per `lang` header
```

- The cached value is the model set, so `lang: ar` requests still resolve the Arabic `title` and
  the full `content` map at render time even on a cache hit.

## 3. Admin Mutation Flow (cache-invalidating)

Every mutation flushes the `static_pages` cache tag (controller + observers), so the next public
request refetches:

| Action | Endpoint | Service method | Cache flush |
|--------|----------|----------------|-------------|
| Update page | PUT `/{slug}` | `updatePage` (title/is_active only) | controller + observer |
| Create section | POST `/{slug}/sections` | `createSection` (next order auto) | controller + observer |
| Update section | PUT `/{slug}/sections/{id}` | `updateSection` (ownership check) | controller + observer |
| Delete section | DELETE `/{slug}/sections/{id}` | `deleteSection` (ownership check) | controller + observer |
| Reorder | POST `/{slug}/sections/reorder` | `reorderSections` (page-scoped) | controller only (setNewOrder is a raw update, no model events) |

## 4. Section Ownership Guard

Sections are hard-scoped to a page to prevent cross-page tampering and existence leaks:

```
updateSection / deleteSection
   └─ assertSectionBelongsToPage(page, section)
        └─ (int) $section->static_page_id !== (int) $page->id
             └─ throw ModelNotFoundException  →  404

reorderSections
   └─ every id must belong to the page (count check)  → else 404
   └─ StaticSection::setNewOrder(ids, 1, 'id', fn($q) => $q->where('static_page_id', $page->id))
        └─ second safety layer: update query scoped by page
```

## 5. Section Ordering

- New section → `order = max(order within page) + 1` (Spatie `sort_when_creating`).
- Reorder → supplied id order becomes the new order (1-based).
- Delete → remaining sections keep their `order` (gaps allowed); next create gets `max + 1`.

## 6. Validation Guard (free-form content)

`content` is intentionally free-form, so only the **outer shape** is validated:
- must be an object keyed by locale (`{ en: {...}, ar: {...} }`)
- a top-level JSON list is rejected via a `withValidator` after-hook
  (`MESSAGE.STATIC_SECTION_CONTENT_INVALID`, 422) before it can hit Spatie's single-locale branch.
