# Test Coverage — Slider Module

## Test File

| File | Type | Tests | Focus |
|------|------|-------|-------|
| `tests/Feature/SliderApiTest.php` | Feature | ~47 | CRUD, validation, auth, permissions, soft delete, status toggle, reorder, product associations, translations, response structure |

**Approximate Total: 47 tests**

---

## SliderApiTest.php Test Breakdown

### List Sliders
- `test_unauthenticated_user_cannot_list_sliders` → 401
- `test_authenticated_user_can_list_sliders` → 200 with paginated data
- `test_list_sliders_returns_empty_when_no_sliders` → empty data
- `test_list_sliders_has_expected_pagination_structure` → pagination meta
- `test_list_sliders_filters_by_active_status` → only active sliders

### Show Slider
- `test_unauthenticated_user_cannot_show_slider` → 401
- `test_authenticated_user_can_show_slider` → 200
- `test_show_returns_404_for_nonexistent_slider` → 404

### Create Slider
- `test_authenticated_user_with_permission_can_create_slider` → 201
- `test_unauthenticated_user_cannot_create_slider` → 401
- `test_user_without_create_permission_cannot_create_slider` → 403
- `test_create_slider_validates_title_required` → 422
- `test_create_slider_validates_image_required` → 422
- `test_create_slider_with_products` → 201 with pivot entries

### Update Slider
- `test_authenticated_user_with_permission_can_update_slider` → 200
- `test_unauthenticated_user_cannot_update_slider` → 401
- `test_user_without_update_permission_cannot_update_slider` → 403
- `test_update_returns_404_for_nonexistent_slider` → 404

### Delete Slider (Soft Delete)
- `test_authenticated_user_with_permission_can_delete_slider` → 200
- `test_unauthenticated_user_cannot_delete_slider` → 401
- `test_user_without_delete_permission_cannot_delete_slider` → 403
- `test_deleted_slider_not_in_index` → hidden
- `test_show_returns_404_for_soft_deleted_slider` → 404

### Change Status
- `test_authenticated_user_with_permission_can_change_status` → status toggled
- `test_unauthenticated_user_cannot_change_status` → 401
- `test_user_without_update_permission_cannot_change_status` → 403
- `test_change_status_validates_id_required` → 422
- `test_change_status_for_nonexistent_slider` → 422

### Reorder
- `test_authenticated_user_with_permission_can_reorder_sliders` → 200
- `test_unauthenticated_user_cannot_reorder_sliders` → 401
- `test_user_without_update_permission_cannot_reorder_sliders` → 403
- `test_reorder_validates_sliders_required` → 422
- `test_reorder_with_invalid_slider_ids` → 422

### Translations
- `test_index_returns_translated_title_in_current_locale` → translated string
- `test_show_returns_full_translation_object` → raw JSON

### Response Structure
- Various assertions on response envelope, image field, status field, order field

---

## Coverage Summary

| Category | Coverage | Notes |
|----------|----------|-------|
| Admin CRUD | ✅ Full | List, create, show, update, delete |
| Public API | ✅ Full | List, by slug |
| Validation | ✅ Full | Title, images, products, reorder, status |
| Authentication | ✅ Full | All routes tested with/without token |
| Authorization | ✅ Full | All permission levels tested |
| Soft Delete | ✅ Full | Soft delete, hidden from index |
| Status Toggle | ✅ Full | Toggle, validation |
| Reorder | ✅ Full | Reorder, validation |
| Translation | ✅ Full | Index vs show format |
| Response Structure | ✅ Full | Envelope, fields |
| Missing Migration | ❌ Open | Production migration file missing |
| Route Dedup | ❌ Open | apiResource registered 3 times |
