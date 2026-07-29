# Test Coverage — Tag Module

---

## Test Files

No test files currently exist for the Tag module.

---

## Recommended Test Suite

### TagCrudTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_list_tags` | Feature | GET /tags returns paginated list |
| 2 | `test_create_tag` | Feature | POST /tags with valid data |
| 3 | `test_show_tag_by_id` | Feature | GET /tags/{id} returns tag |
| 4 | `test_show_tag_by_slug` | Feature | GET /tags/{slug} returns tag |
| 5 | `test_update_tag` | Feature | PUT /tags/{id} updates fields |
| 6 | `test_delete_tag` | Feature | DELETE /tags/{id} deletes |
| 7 | `test_show_non_existent_tag` | Edge Case | GET /tags/99999 → 404 |
| 8 | `test_update_non_existent_tag` | Edge Case | PUT /tags/99999 → 404 |
| 9 | `test_delete_non_existent_tag` | Edge Case | DELETE /tags/99999 → 404 |

### TagValidationTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_create_tag_requires_name` | Validation | Missing name → 422 |
| 2 | `test_create_tag_duplicate_name` | Validation | Existing name → 422 |
| 3 | `test_create_tag_non_array_name` | Validation | String instead of array → 422 |
| 4 | `test_update_tag_duplicate_name` | Validation | Name taken by another → 422 |
| 5 | `test_update_tag_partial` | Validation | Partial update succeeds |

### TagAuthorizationTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_view_only_cannot_create` | Auth | view-tags only → 403 on POST |
| 2 | `test_view_only_cannot_update` | Auth | view-tags only → 403 on PUT |
| 3 | `test_view_only_cannot_delete` | Auth | view-tags only → 403 on DELETE |
| 4 | `test_no_permission_all_endpoints_return_403` | Auth | No permissions → 403 on all |
| 5 | `test_guest_cannot_access_any_endpoint` | Auth | No token → 401 on all |

### TagTranslationTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_create_tag_with_bilingual_name` | Feature | POST with en + ar |
| 2 | `test_resource_returns_translated_name` | Feature | GET returns correct locale |
| 3 | `test_resource_does_not_return_raw_json` | Feature | Name is string, not object |

### TagResourceTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_paginated_response_structure` | Feature | Has data + pagination meta |
| 2 | `test_tag_resource_fields` | Feature | id, name, slug, image, icon present |
| 3 | `test_tag_resource_field_types` | Feature | Correct data types |

### TagMediaTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_upload_image_on_create` | Feature | POST with image file |
| 2 | `test_upload_icon_on_create` | Feature | POST with icon file |
| 3 | `test_update_replaces_image` | Feature | PUT with new image |
| 4 | `test_delete_does_not_clean_media` | Known Issue | Media preserved after delete |

### TagRegressionTest.php

| # | Test Name | Type | Description |
|---|-----------|------|-------------|
| 1 | `test_store_returns_tag_resource` | Regression | store() returns data, not null |
| 2 | `test_show_works_with_numeric_id` | Regression | Numeric lookup works |
| 3 | `test_show_works_with_string_slug` | Regression | Slug lookup works |
| 4 | `test_unique_translation_on_tags_table` | Regression | Checks tags table, not categories |
| 5 | `test_create_rule_has_no_ignore` | Regression | CREATE unique rule without ->ignore() |

---

## Missing Coverage

- [ ] No tests exist at all
- [ ] Slug auto-generation edge cases
- [ ] Concurrent tag operations
- [ ] Pagination boundary tests
- [ ] Image upload validation (file type, size)
- [ ] Large dataset performance
