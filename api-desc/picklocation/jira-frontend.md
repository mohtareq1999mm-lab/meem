# Jira - Pickup Location Feature (Frontend)

## Epic: Pickup Location Management UI

### Story Points Estimate: 5

---

## User Stories

### FE-US-001: Admin Pickup Locations Management
**As** an admin
**I want** to manage pickup locations (list, create, edit, delete)
**So that** customers can collect orders at physical locations

**Acceptance Criteria:**
- Data table with search, active/inactive filter
- Sortable by display order
- Create form with all required fields (store_name, address, phone) + optional fields
- Edit form pre-fills existing data; all fields optional (PATCH-like behavior)
- Edit form supports translatable `working_hours.*.day.ar` (Arabic) and `working_hours.*.day.en` (English) per time block
- Map integration for coordinates (optional)
- Form validation: email format, display_order ≥ 0, working_hours day+open+close required together
- Delete with confirmation (soft delete)

### FE-US-002: Checkout Pickup Location Selector
**As** a customer
**I want** to select a pickup location during checkout
**So that** I can collect my order at a convenient branch

**Acceptance Criteria:**
- Dropdown/list of active locations
- Shows store name, address, phone, working hours
- No authentication required
- Selected location saved with order

---

## Frontend Tasks

| ID | Description | h | Component |
|----|-------------|---|-----------|
| FE-T-001 | Create PickupLocationsList | 4 | `PickupLocationsList.vue` |
| FE-T-002 | Create PickupLocationFormModal | 4 | `PickupLocationFormModal.vue` |
| FE-T-003 | Create CheckoutLocationSelector | 3 | `PickupLocationSelector.vue` |
| FE-T-004 | Create API service | 1 | `services/pickupLocationApi.js` |
| FE-T-005 | Edit form — partial update + translatable day.ar/day.en working hours UI | 3 | `PickupLocationFormModal.vue` |

## API Routes

| Method | Endpoint | Auth | Permission | Request Body |
|--------|----------|------|------------|-------------|
| GET | `/api/v1/pickup-locations` | Required | view-pickup-locations | — |
| POST | `/api/v1/pickup-locations` | Required | create-pickup-location | `{ store_name, address, phone, email?, latitude?, longitude?, working_hours?, status?, display_order? }` |
| GET | `/api/v1/pickup-locations/{id}` | Required | view-pickup-locations | — |
| PUT | `/api/v1/pickup-locations/{id}` | Required | update-pickup-location | All optional: `{ store_name?, address?, phone?, email?, latitude?, longitude?, working_hours?, status?, display_order? }` |
| DELETE | `/api/v1/pickup-locations/{id}` | Required | delete-pickup-location | — |
| GET | `/api/v1/general/pickup-locations` | None | None | — |
| GET | `/api/v1/general/pickup-locations/{id}` | None | None | — |
