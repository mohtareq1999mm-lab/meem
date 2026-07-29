# Tag Module — Changelog

## [1.0.0] — 2026-07-29

### Added
- Comprehensive API investigation documentation (`api-desc/tag/`)
- Tags API: full CRUD with permissions, translatable names, image/icon uploads

### Fixed
- `TagUpdateRequest`: UniqueTranslationRule was checking `categories` table instead of `tags` table
- `TagCreateRequest`: Removed nonsensical `->ignore()` from CREATE unique rule
- `TagController::store()`: Added missing `return new TagResource($tag)` — method returned `null`
- `TagController::show()`: Added missing `$language` variable definition — was undefined
- `TagController`: Fixed wrong exception constants in catch blocks (all used `COULD_NOT_CREATE_THE_RESOURCE`)
- `resources/lang/en/message.php`: Added 7 missing English translation keys
- `packages/marvel/config/constants.php`: Added 4 tag-specific constants

### Known
- No soft deletes — tags are hard-deleted
- No observer — no activity logging for tag CRUD
- No public endpoints — no `/general/tags` API exists
- No test files exist for the Tag module
- `type` relationship is eager-loaded but never exposed in TagResource
- Media files are not cleaned up when a tag is deleted
- `destroy()` returns raw boolean instead of JSON response
