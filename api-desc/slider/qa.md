# QA Test Cases — Slider Module

## API Functionality

| ID | Test Case | Expected |
|----|-----------|----------|
| SF-01 | List sliders with pagination | Returns paginated results with meta |
| SF-02 | List sliders with active filter | Returns only active sliders |
| SF-03 | Create slider with all valid fields | Returns 201 with slider data |
| SF-04 | Get slider by ID | Returns 200 with slider data |
| SF-05 | Update slider | Returns 200 with updated data |
| SF-06 | Delete slider (soft delete) | Returns 200, slider hidden |
| SF-07 | Toggle slider status | Status flipped |
| SF-08 | Reorder sliders | Returns 200, order column updated |
| SF-09 | Public list active sliders | Returns only status=true sliders |
| SF-10 | Public get by slug | Returns 200 with products |

## Validation

| ID | Test Case | Expected |
|----|-----------|----------|
| SV-01 | Create without title | 422, title required |
| SV-02 | Create without title.en | 422, title.en required |
| SV-03 | Create without image_desktop | 422, image_desktop required |
| SV-04 | Create without image_mobile | 422, image_mobile required |
| SV-05 | Create with invalid image format | 422, image must be jpeg/png/jpg/gif |
| SV-06 | Create with duplicate title | 422, title already taken |
| SV-07 | Update with same title (self) | 200, unique ignores self |
| SV-08 | Reorder without sliders field | 422, sliders required |
| SV-09 | Reorder with invalid IDs | 422, IDs must exist |
| SV-10 | Change status without id field | 422, id required |

## Authorization

| ID | Test Case | Expected |
|----|-----------|----------|
| SA-01 | List sliders without token | 401 |
| SA-02 | Create slider without permission | 403 |
| SA-03 | Update slider without permission | 403 |
| SA-04 | Delete slider without permission | 403 |
| SA-05 | Change status without permission | 403 |
| SA-06 | Reorder without permission | 403 |
| SA-07 | Public list without auth | 200 (public) |
| SA-08 | Public by slug without auth | 200 (public) |

## Soft Delete

| ID | Test Case | Expected |
|----|-----------|----------|
| SS-01 | Delete slider → not in index | Hidden from index |
| SS-02 | Show deleted slider | 404 |
| SS-03 | Update deleted slider | 404 |

## Translation

| ID | Test Case | Expected |
|----|-----------|----------|
| ST-01 | Create slider with en + ar translations | Both stored |
| ST-02 | Index returns translated title in current locale | en when locale=en |
| ST-03 | Show returns full translation object | Raw JSON with all locales |

## Reorder / Status Toggle

| ID | Test Case | Expected |
|----|-----------|----------|
| SR-01 | Reorder 3 sliders to reverse | order column updated |
| SR-02 | Toggle active → inactive | status becomes false |
| SR-03 | Toggle inactive → active | status becomes true |

## Response Structure

| ID | Test Case | Expected |
|----|-----------|----------|
| SRS-01 | Response has correct envelope | success, message, status, data |
| SRS-02 | Slider resource has image field | desktop + mobile URLs |
| SRS-03 | Index returns translated string, not JSON | "Summer Sale" not `{"en":"..."}` |
| SRS-04 | Product association included when loaded | products array in response |

## Edge Cases

| ID | Test Case | Expected |
|----|-----------|----------|
| SE-01 | Create with empty products array | Slider created, no associations |
| SE-02 | Update removing all products | Products cleared |
| SE-03 | Toggle status on already-deleted slider | 404 |
| SE-04 | Reorder with single slider | OK |
| SE-05 | Public by slug not found | 404 |
| SE-06 | Soft-deleted slider force delete | Permanently removed, media cleaned up |
