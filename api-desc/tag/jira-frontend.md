# Tag Module — Frontend Jira Tasks

---

## Task 1: Admin Tag Listing Page

**Priority:** High
**Component:** Frontend — Admin Tags Page
**Story Points:** 5

**Description:** Build the admin tag management page with a sortable, filterable table.

**API Endpoint:**
- `GET /api/v1/tags?page=&limit=&language=`

**Acceptance Criteria:**
- [ ] Table with columns: ID, Name, Slug, Image/Icon, Actions
- [ ] Pagination
- [ ] Language filter dropdown
- [ ] Search field filters by name
- [ ] Each row shows edit/delete action buttons
- [ ] Loading skeleton while fetching
- [ ] Empty state: "No tags found" with "Create Tag" CTA

---

## Task 2: Admin Tag Create/Edit Form

**Priority:** High
**Component:** Frontend — Admin Tag Form
**Story Points:** 3

**Description:** Build the create/edit form for tags with translatable name, image upload, and icon upload.

**API Endpoints:**
- `POST /api/v1/tags`
- `PUT /api/v1/tags/{id}`
- `GET /api/v1/tags/{id}` (load existing)

**Acceptance Criteria:**
- [ ] Create mode: empty form, Edit mode: pre-filled
- [ ] Translatable `name` field with language tabs (en, ar)
- [ ] Image upload with preview
- [ ] Icon upload with preview
- [ ] Form submits as `multipart/form-data`
- [ ] Validation errors displayed per field
- [ ] Loading skeleton on edit fetch

---

## Task 3: Admin Tag Delete

**Priority:** Medium
**Component:** Frontend — Delete Modal
**Story Points:** 2

**Description:** Implement delete with confirmation modal.

**API Endpoint:**
- `DELETE /api/v1/tags/{id}`

**Acceptance Criteria:**
- [ ] Clicking delete opens confirmation modal
- [ ] Modal shows tag name
- [ ] Confirm button triggers delete
- [ ] On success: remove row with success toast
- [ ] On 404 error: row already deleted, remove from table
- [ ] On network error: toast with error message

---

## Task 4: Admin Tag — Loading, Empty & Error States

**Priority:** High
**Component:** Frontend — State Handling
**Story Points:** 2

**Description:** Handle all non-happy-path states across the tag admin pages.

**Acceptance Criteria:**
- [ ] **Listing loading:** Skeleton table rows
- [ ] **Listing empty:** "No tags yet" with "Create your first tag" button
- [ ] **Listing error:** Error message with "Retry" button
- [ ] **Form loading (edit):** Skeleton while fetching tag data
- [ ] **Form error:** Toast with error message
- [ ] **Form validation:** Inline field errors from API 422 response
- [ ] **Delete error (not found):** Toast "Tag already deleted"
- [ ] **Network error:** Toast "Network error, please try again" for all API calls

---

## Task 5: Admin Tag — Multilingual Translatable `name` Field

**Priority:** Medium
**Component:** Frontend — i18n
**Story Points:** 2

**Description:** Handle the translatable `name` field (sent/received as language-keyed object).

**Request/Response format:**
```json
{
  "name": { "en": "Organic", "ar": "عضوي" }
}
```

**Acceptance Criteria:**
- [ ] Language tabs for each supported locale
- [ ] Each tab shows separate input for `name`
- [ ] On save, serialized to `{ "en": "...", "ar": "..." }` format
- [ ] On load, each tab shows correct translation
- [ ] Default language tab pre-selected
- [ ] Validation errors shown per-language
