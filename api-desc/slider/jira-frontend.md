# Slider Module — Frontend Jira Tasks

## Task 1: Admin Slider Listing Table

**Description:** Create admin table showing all sliders with columns: ID, Title, Slug, Status, Order, Image Preview, Actions.

**Requirements:**
- Server-side pagination
- Active/Inactive filter
- Drag-and-drop reorder handles
- Image thumbnail preview
- Loading skeleton state
- Empty state when no sliders found

---

## Task 2: Admin Slider Create/Edit Form

**Description:** Create form for creating and editing sliders.

**Fields:**
- `title` — multilingual (en, ar) text inputs
- `image_desktop` — file upload with preview (accepts jpeg/png/jpg/gif, max 2MB)
- `image_mobile` — file upload with preview (accepts jpeg/png/jpg/gif, max 2MB)
- `status` — toggle switch
- `products` — multi-select product search (optional)

---

## Task 3: Drag-and-Drop Reorder

**Description:** Add drag-and-drop reordering to the slider admin list.

**Flow:**
1. User drags slider items to desired order
2. On drop, collect IDs in new sequence
3. Send PUT `/api/v1/sliders/reorder` with `{ sliders: [3, 1, 2] }`
4. Show loading state during request
5. Refresh list on success

---

## Task 4: Homepage Banner Carousel

**Description:** Display active sliders as a responsive banner carousel on the homepage.

**Data source:** `GET /api/v1/general/sliders`

**Features:**
- Responsive images (desktop/mobile via `<picture>` element)
- Auto-play with configurable interval
- Manual navigation (dots, arrows)
- Pause on hover
- Slide/fade transition animation
- Loading skeleton while fetching

---

## Task 5: Slider Detail Page with Products

**Description:** Create a slider detail page showing associated products.

**Data source:** `GET /api/v1/general/sliders/{slug}`

- Display slider image as hero banner
- Show associated products in a grid
- Product pricing and add-to-cart

---

## Task 6: Status Toggle UI

**Description:** Add quick-status toggle in admin table without opening edit form.

- Toggle switch in table row
- Confirmation before toggle
- PATCH `/api/v1/sliders/change-status`
- Optimistic UI update with rollback on error

---

## Task 7: Delete Confirmation Dialog

**Description:** Add confirmation dialog before deleting a slider.

- Modal: "Are you sure you want to delete this slider?"
- Shows slider title and image preview
- Confirm/Cancel buttons
- Loading state on delete

---

## Task 8: Loading, Empty, Error States

**Description:** Implement consistent states across slider components.

- **Loading:** Skeleton loaders for carousel, table rows, and form fields
- **Empty:** "No sliders available" empty state with illustration
- **Error:** Inline error messages for API failures with retry

---

## Task 9: Multilingual Translatable Fields

**Description:** Ensure slider title fields support bilingual input.

- Tab/segment toggle for language (en/ar)
- Send as JSON object `{"en": "...", "ar": "..."}`
- Display translated title based on current locale
