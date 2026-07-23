# Jira - Order Feature (Frontend)

## Epic: Frontend Order UI

### Story Points Estimate: 13

---

## User Stories

### FE-US-001: My Orders Page (Customer)
**As** a customer
**I want** to view my order history in a clean list
**So that** I can track my purchases and check status

**Acceptance Criteria:**
- Fetches `GET /api/v1/general/orders?status={filter}&page={n}&limit={n}` on mount
- Status filter now works correctly (backend bug fixed 2026-07-23)
- Displays table/list with: order number, date, status badge, total, payment method
- Status badges with color coding (pending=yellow, processing=blue, completed=green, cancelled=red)
- Click row → order detail (FE-US-002)
- Pagination with `page` and `limit` params
- Search by order number
- Filter by status tabs (All, Pending, Completed, Cancelled) — pass `status` param to API
- Loading skeleton
- Empty state ("No orders yet — Start shopping!")
- Error state with retry

---

### FE-US-002: Order Detail Page (Customer)
**As** a customer
**I want** to view the full details of a single order
**So that** I can see items, pricing, and delivery info

**Acceptance Criteria:**
- Fetches order detail from orders list data or dedicated endpoint
- Shows: order number, date, status, items list with images
- Price breakdown: subtotal, shipping, discount, total
- Delivery information: address, governorate, fulfillment type
- Payment information: method, status
- Status timeline/progress indicator
- Cancel button (if status allows)

---

### FE-US-003: Checkout Page
**As** a customer
**I want** a multi-step checkout process
**So that** I can review and place my order

**Acceptance Criteria:**
- Step 1: Contact info (name, phone, email)
- Step 2: Delivery address + governorate select
- Step 3: Fulfillment method (delivery/pickup)
- Step 4: Payment method (COD/Online/Cashier) + optional coupon/promotion
- Step 5: Order summary review
- Submit → POST `/api/v1/general/checkout`
- Loading state on submit
- Validation errors inline
- Success → redirect to order confirmation
- Failure → error message with retry

---


## Frontend Jest Tests

### FE-TS-001: MyOrdersPage - GET /api/v1/general/orders

**Test Suite:** `MyOrdersPage.spec.js`

**Description:** The endpoint `GET /api/v1/general/orders` returns only the authenticated user's orders. Tests must verify that the frontend correctly handles this scoping behavior.

| # | Test | Mock Setup | Assertion |
|---|------|-----------|-----------|
| 1 | `displays only authenticated user's orders` | Mock `GET /api/v1/general/orders` returns 3 orders for user A | Only user A's orders rendered |
| 2 | `does not show other users' orders` | Mock response contains only user A's orders (backend-scoped) | No unexpected user data |
| 3 | `redirects to login if 401` | Mock returns 401 | Redirect or show login prompt |
| 4 | `shows error toast on 403` | Mock returns 403 (missing permission) | Error message displayed |
| 5 | `renders correct order data` | Mock returns `{ data: [{ order_number, status, total_price, created_at }] }` | All fields in table |
| 6 | `handles empty order history` | Mock returns `{ data: [], meta: { total: 0 } }` | Empty state message |
| 7 | `paginates through results` | Mock returns 2 pages of orders | Load More or page nav works |
| 8 | `filters by status tab` | Mock filtered response for "completed" | Only completed orders |
| 9 | `searches by order number` | Mock returns matching order | Single result |
| 10 | `shows loading skeleton on fetch` | Delayed mock response | Skeleton visible during loading |
| 11 | `shows error state with retry on network failure` | Mock network error | Error message + retry button |
| 12 | `order_number format is ORD-{padded id}` | Mock returns id=1 → `ORD-00000001` | Correct padding |

**Example Test:**
```javascript
import { render, screen, waitFor } from '@testing-library/vue'
import MyOrdersPage from '@/pages/MyOrdersPage.vue'
import { rest } from 'msw'
import { setupServer } from 'msw/node'

const server = setupServer(
  rest.get('/api/v1/general/orders', (req, res, ctx) => {
    return res(ctx.json({
      data: [
        { id: 1, order_number: 'ORD-00000001', status: 'completed', total_price: 150.00, created_at: '2026-07-20T10:00:00Z', items_count: 3 }
      ],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 }
    }))
  })
)

beforeAll(() => server.listen())
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

test('displays only authenticated user orders', async () => {
  render(MyOrdersPage)
  await waitFor(() => {
    expect(screen.getByText('ORD-00000001')).toBeInTheDocument()
    expect(screen.getByText('$150.00')).toBeInTheDocument()
  })
})

test('shows empty state when no orders', async () => {
  server.use(
    rest.get('/api/v1/general/orders', (req, res, ctx) => {
      return res(ctx.json({ data: [], meta: { total: 0 } }))
    })
  )
  render(MyOrdersPage)
  await waitFor(() => {
    expect(screen.getByText(/no orders/i)).toBeInTheDocument()
  })
})

test('redirects on 401', async () => {
  server.use(
    rest.get('/api/v1/general/orders', (req, res, ctx) => {
      return res(ctx.status(401))
    })
  )
  const push = vi.fn()
  render(MyOrdersPage, { global: { mocks: { $router: { push } } } })
  await waitFor(() => {
    expect(push).toHaveBeenCalledWith('/login')
  })
})
```

### FE-TS-002: API Service Layer - orderApi

**Test Suite:** `orderApi.spec.js`

| # | Test | Description |
|---|------|-------------|
| 1 | `myOrders calls GET /api/v1/general/orders with params` | Passes page, status, search |
| 2 | `myOrders sends auth token in header` | Authorization header present |
| 3 | `myOrders throws on non-2xx response` | Error handling |

---

## Frontend Tasks

| Task ID | Description | Estimate (h) | Component |
|---------|-------------|-------------|-----------|
| FE-T-001 | Create MyOrdersPage | 6 | `MyOrdersPage.vue` |
| FE-T-002 | Create OrderDetailPage | 5 | `OrderDetailPage.vue` |
| FE-T-003 | Create OrderStatusBadge | 2 | `OrderStatusBadge.vue` |
| FE-T-004 | Create CheckoutPage (multi-step) | 10 | `CheckoutPage.vue` |
| FE-T-005 | Create AdminOrderListPage | 8 | `AdminOrderListPage.vue` |
| FE-T-006 | Create AdminOrderDetailPage | 6 | `AdminOrderDetailPage.vue` |
| FE-T-007 | Create OrderStatusTimeline | 3 | `OrderStatusTimeline.vue` |
| FE-T-008 | Create PriceBreakdown component | 2 | `PriceBreakdown.vue` |
| FE-T-009 | Create API service layer (orderApi) | 3 | `services/orderApi.js` |
| FE-T-010 | Create order store (Pinia/Vuex) | 3 | `stores/orderStore.js` |

## Frontend Bug Tickets

| Ticket | Description | Priority | Severity |
|--------|-------------|----------|----------|
| FE-BUG-001 | Order list not updating after status change | Medium | Medium |
| FE-BUG-002 | Checkout form validation not showing on all fields | High | High |
| FE-BUG-003 | Price breakdown rounding mismatch | Medium | Medium |
| FE-BUG-004 | Status timeline shows invalid transitions | High | Medium |

## Backend Bugs Affecting Frontend (Fixed)

| Bug | Description | Fix Date | Impact |
|-----|-------------|----------|--------|
| Status filter ignored on `GET /api/v1/general/orders` | `?status=` parameter was completely bypassed — all orders returned regardless of filter | 2026-07-23 | Frontend status tab filtering now works correctly |

## API Routes for Frontend Integration

| Method | Endpoint | Auth | Usage |
|--------|----------|------|-------|
| GET | `/api/v1/general/orders?status=&limit=&page=` | Sanctum | My orders (with optional filters) |

