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
- Create/edit form with store details + working hours
- Map integration for coordinates (optional)
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

## API Routes

| Method | Endpoint | Auth | Permission |
|--------|----------|------|------------|
| GET | `/api/v1/pickup-locations` | Required | view-pickup-locations |
| POST | `/api/v1/pickup-locations` | Required | create-pickup-location |
| GET/PUT/DELETE | `/api/v1/pickup-locations/{id}` | Required | view/update/delete-pickup-location |
| GET | `/api/v1/general/pickup-locations` | None | None |
| GET | `/api/v1/general/pickup-locations/{id}` | None | None |
