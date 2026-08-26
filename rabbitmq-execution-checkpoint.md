# RabbitMQ Migration Execution Checkpoint

- **Timestamp:** 2026-08-26 (UTC+3 session)
- **Git branch:** `main`
- **HEAD:** `b6a830d` — chore(test): drop debug probe file
- **Recent history:**
  - `37ce4a8` feat(inventory): order-owned reservation lifecycle with 24h unpaid-order reaper
  - `c49ea84` fix(phase-0): event transaction-boundary correctness + async hardening
  - `b6a830d` chore(test): drop debug probe file

---

## Git Status Summary (verified at checkpoint time)

Tracked modifications (uncommitted):

| File | Note |
|---|---|
| `app/Providers/EventServiceProvider.php` | Adds missing `use App\Listeners\SendUserOrderDeliveredNotification;` import — completes Phase 0 item #18 (phantom-mapping fix). Authored by the concurrent actor's Phase-0 work; not yet committed. |
| `docs/production-history.md` | Pre-existing local edit predating this checkpoint session. Preserve. |

Untracked (non-source artifacts / pre-existing docs):

- `.phpunit.cache/*` junit exports and problem-lists from verification runs (`junit-unit/sub/fa.xml`, `branch-problems.txt`, `final-junit/`)
- `docs/ENDPOINT-BY-ENDPOINT-AUDIT.md`, `docs/FRONTEND-API-INTEGRATION-CONTRACT.md` (pre-existing)

**No source code is dirty. Nothing may be committed/restored/cleaned by this checkpoint task.**

## Stash Summary (verified)

```
git stash list  ->  (empty)
```

The previously reported `stash@{0}: On main: full-baseline` **no longer exists**. It was consumed during the earlier reconciliation cycle (stash pop applied its content into the working tree; a later conflict-recovery pop restored the remainder). Per handoff rule "current source/Git state wins": there is nothing to restore. **Do not attempt any stash operation.**

---

## Current Phase / Step / Execution Status

```
STATUS:            PAUSED — DOCUMENTATION CHECKPOINT ONLY
CURRENT PHASE:     PHASE -1 (reconciliation) — factually SATISFIED at Git level; formal exit sign-off still required
CURRENT STEP:      Confirm Phase -1 exit criteria (re-run regression batteries on HEAD), then gate Phase 0 closure
PHASE 0:           SUBSTANTIALLY COMPLETE (committed in c49ea84) — one uncommitted wiring patch pending
PHASE 1:           NOT STARTED
PHASES 2–8:        NOT STARTED
Outbox:            NOT IMPLEMENTED
Inbox:             NOT IMPLEMENTED
RabbitMQ topology: NOT IMPLEMENTED
Consumers:         NOT IMPLEMENTED
Supervisor changes:NOT IMPLEMENTED (existing meem-high/meem-medium definitions untouched)
Migrations (RabbitMQ-related): NONE EXECUTED (only the inventory migration from 37ce4a8 exists)
config/queue.php rabbitmq connection: ABSENT
config/rabbitmq.php: ABSENT
```

---

## What Was Completed (verified in CURRENT source)

### PHASE -1 — Inventory refactor: LANDED (commit `37ce4a8`)

Present in working tree **and** tracked by Git:

- `app/Services/Inventory/OrderReservationService.php`
- `app/Exceptions/CartEmptyException.php`, `app/Exceptions/InsufficientStockException.php`
- `database/migrations/2026_08_26_100001_add_inventory_reservation_state_to_orders.php`
- `orders.inventory_state` enum (`none|active|released|committed`) + `inventory_reserved_at` + `reservation_expires_at` (+ reaper index)
- `app/Console/Commands/MigrateInventoryReservations.php` (`inventory:migrate-reservations`, dry-run/--fix, idempotent)
- `CancelUnpaidOrders` rewritten (24h stored-expiry eligibility, gateway pre-check, releases only its own reservation, never touches carts)
- `CartInventoryService` reduced to cart-local duties (`clearCheckedOutSlice`, activity window); cart-expiry commands retired; `carts:expire` unscheduled
- Promotion gifts resolve to order-line descriptors; materialize + reserve atomically at checkout
- Legacy GraphQL `createOrder` / `createOrderPayment` mutations disabled (REST checkout sole authority)
- `tests/Feature/Inventory/{OrderReservationLifecycleTest,GiftAndReconciliationTest}.php`

State machine (idempotent, row-locked conditional claims):

```text
none -> active      reserveForOrder()   (checkout tx)
active -> committed commit()            (payment success)
active -> released  release()           (24h expiry / unpaid cancel)
Digital lines excluded everywhere (rule D1).
```

### PHASE 0 — Correctness fixes: SUBSTANTIALLY LANDED (commit `c49ea84`)

Verified present at HEAD:

| Item | Status | Evidence |
|---|---|---|
| OrderCreated inside-transaction dispatch | FIXED | Critical domain events (`OrderCreated`, `PaymentSucceeded`, `PaymentFailed`, `OrderCancelled`, `OrderStatusChanged`, `RefundApproved`) implement `ShouldDispatchAfterCommit` (event-level deferral; listener-level `$afterCommit` alone is ignored for queued listeners on Laravel 10.30) |
| Payment-success notification listeners after-commit | COVERED by the same event-level mechanism |
| Webhook `dispatchSync` → queued | FIXED | `SendFrontendWebhookJob implements ShouldQueue`, `onQueue(config('frontend.queue','meem-high'))` |
| Import retry/failure-state inversion | FIXED | product/category/brand imports mark `failed` only on final attempt; intermediate attempts stay retryable |
| FCM global fan-out/scoping | FIXED | pushes scoped to intended notifiable's device tokens; missing target skips |
| SMS silent swallowing | FIXED | structured error logs in `SmsTrait` (no bodies/recipients logged) |
| QueueName centralization | DONE | `App\Enums\QueueName { HIGH=meem-high, MEDIUM=meem-medium, DEFAULT=default }` |
| Failed-job alerting/pruning | DONE | scheduler `queue:prune-failed --hours=720` daily 03:15 + `HandleFailedQueueJob` alerts each final failure |
| `OrderDelivered` phantom mapping | FIXED in code, **wiring uncommitted** | real `App\Events\OrderDelivered` import added in tests + **uncommitted `EventServiceProvider.php` listener-import patch** |

### Verification executed THIS session against HEAD (post-commit, read-only test runs)

Green:

- `tests/Feature/Inventory/OrderReservationLifecycleTest` 24/24 (159 assertions)
- `tests/Feature/Inventory/GiftAndReconciliationTest` 8/8 (50)
- `PaymentProductionHardenTest` 35/35 (105)
- `CheckoutPendingOrderRedesignTest` 15/15 (49)
- `PaymentCheckoutTest` 29/29 (95)
- `CartApiTest` 80/80 (334)
- `PromotionFlowTest` 15/15
- `PromotionProductionHardenTest` 41/41 except 1 known pre-existing failure
- `ProductionReadinessAuditTest` 32/32, `EventSystemTest` errors resolved (residual failures pre-existing)

Full-suite comparison (chunked runs, name-level diff):

| Tree | Tests | Errors | Failures | Problem tests |
|---|---|---|---|---|
| clean `0b35472` baseline | 3658 | 121 | 198 | **319** |
| HEAD + inventory work | 3679–3697 | 17 | 150 | **155–177** |

**Zero branch-only regressions**: every failing test at HEAD exists verbatim in the clean-main baseline set. Net −164 problem tests, +21 tests added.

Known pre-existing debt (NOT introduced by this work, NOT fixed here): FastShippingControllerTest, FastShippingHardenTest (2 infra failures), WishlistApiTest, NotifyLogsTest, Attributes*/AssignedCoupon*/UserStaffMisc/UserAuthAdmin clusters, PaymentSystemTest, ActivityLogApiTest, DimensionFilterTest, PricingCacheInvalidationTest, EventSystemTest residual asserts, `eligible_promotions_endpoint_returns_eligible_only`.

### Historical Test Evidence — PRE-RECOVERY (label: executed against the pre-stash inventory-refactor working tree; superseded by the post-HEAD re-runs above; must be re-run after any future reconciliation)

These groups were verified green during the interrupted session BEFORE the external stash operation. They are preserved as historical evidence of the changeset's quality at that point in time and were since superseded by the fresh HEAD re-runs recorded above:

- `tests/Feature/Inventory/*` — 32/32 (209 assertions)
- `OrderCreationFlowTest` — 17/17 · `OrderStatusLifecycleTest` — 15/15 · `PendingOrderLifecycleTest` — 6/6 · `OrdersProductionHardenTest` — 17/17 (later expanded to 38 tests by the author)
- `PaymentCallbackStressTest` — 9/9 · `PaymentCheckoutTest` — 29/29
- `CheckoutConcurrencyStressTest` — 8/8 · `CheckoutPendingOrderRedesignTest` — 15/15
- `CartApiTest` — 80/80 · `PromotionFlowTest` — 15/15 · `CouponsProductionHardenTest` — 44/44

Previously observed baseline issues during that session (do NOT assume fixed): FastShippingControllerTest / FastShippingHardenTest route-drift failures (`/general/fast-shipping/products|orders` never registered), PromotionProductionHardenTest `eligible_promotions_endpoint_returns_eligible_only`, and the OrderDelivered phantom wiring TypeError (since fixed in code; see Phase 0 table).

Full-suite runs recorded this migration effort:

| Run | Tree | Tests | Errors | Failures | Notes |
|---|---|---|---|---|---|
| Author baseline (junit-branch.xml, from consumed stash) | pre-stash branch | 3679 | 17 | 150 | author's own full run |
| Phase-0 gate run (junit-phase0.xml in agent temp dir) | HEAD `b6a830d` + uncommitted import patch | 3703 | 17 | 157 | 2 skipped, 1 risky; **case-level diff vs author baseline NOT yet analyzed** |

---

## Known Discrepancies (handoff brief vs CURRENT repository)

| Brief assumption | Current reality |
|---|---|
| Inventory refactor absent from working tree | **PRESENT and COMMITTED** (`37ce4a8`) |
| `stash@{0} full-baseline` exists | **Stash list empty** (consumed during earlier reconciliation, before this checkpoint request) |
| PHASE -1 not reconciled | Reconciliation factually complete; formal re-test sign-off pending (targeted suites above are green) |
| Phase 0 not started | **Substantially implemented & committed** (`c49ea84`); remaining: commit the `EventServiceProvider.php` import patch, full-suite green confirmation |
| composer RabbitMQ state unknown | `composer.json`: `"vladimir-yuldashev/laravel-queue-rabbitmq": "^15.0"`; lock: `v15.0.1` + `php-amqplib v3.7.4` (installed, NOT integrated) |

---

## Architecture Decisions (NON-NEGOTIABLE constraints)

1. RabbitMQ is NOT the authority for payment state.
2. RabbitMQ is NOT the authority for inventory state.
3. Payment verification/state mutation remains synchronous and transactional.
4. Inventory reservation/commit/release remains synchronous and transactional.
5. Business logic stays in `app/Services`.
6. Marvel remains CRUD/admin-only.
7. Do not move business logic into Marvel.
8. `ProductPricingService` remains the single pricing authority.
9. Do not redesign unrelated architecture.
10. Critical events use transactional outbox.
11. Delivery semantics are at-least-once.
12. Consumers must be idempotent.
13. Inbox key is `(consumer_name, event_id)`.
14. ACK happens only after successful DB commit.
15. Never use `NACK(requeue=true)` for domain failures.
16. Retry through the DLX/TTL ladder.
17. Failed messages eventually go to DLQ.
18. Existing Laravel jobs are reused rather than rewritten.
19. No Horizon.
20. No Kafka.
21. No duplicate RabbitMQ-specific business services.

## Approved critical Outbox events

`order.paid`, `payment.failed`, `order.cancelled`, `order.status.changed`, `refund.approved`
Optional later: `order.created`, `digital.delivered`. Do NOT expand without architectural approval.

## Target Topology (PLANNED / NOT YET EXECUTED)

Exchanges: `meem.domain.events` (topic), `meem.work.tasks` (direct), `meem.dlx` (topic)
Domain queues: `q.domain.invoice`, `q.domain.digital`, `q.domain.lifecycle`, `q.domain.notifications`
Retry: `q.retry.15s`, `q.retry.2m`, `q.retry.15m` — Shadow: `q.shadow` — DLQs: `q.dl.*`
Existing work queues preserved: `meem-high`, `meem-medium`, `default`
Critical retry path: NACK(false) → DLX → TTL ladder → origin → delivery cap → DLQ + alert

## Package State (from CURRENT files)

- `vladimir-yuldashev/laravel-queue-rabbitmq`: constraint `^15.0`, locked **v15.0.1**
- `php-amqplib/php-amqplib`: locked **v3.7.4**
- Installed in vendor; **zero Laravel integration** (no connection config, no topology, no supervisor changes).

---

## Phase-by-phase TODO Checklist

Legend: `[ ]` not started · `[~]` blocked/waiting · `[x]` verified complete · `[!]` requires verification

### PHASE -1 — Reconcile inventory refactor
- [x] Verify current Git state (HEAD `b6a830d`, refactor committed `37ce4a8`)
- [x] Identify ownership of former `stash@{0}` (consumed during reconciliation; stash list now empty)
- [x] Determine whether `full-baseline` is intentionally preserved (moot — content landed in `37ce4a8`)
- [~] Explicitly decide whether/when to restore the stash → MOOT: nothing to restore
- [x] Reconcile inventory refactor (landed via external commit `37ce4a8`)
- [x] Re-run Inventory tests (Lifecycle 24/24, GiftAndReconciliation 8/8 on HEAD)
- [x] Re-run affected order/payment/checkout tests (PPH 35/35, CheckoutPendingOrderRedesign 15/15, PaymentCheckout 29/29, CartApi 80/80)
- [!] Confirm composer state after reconciliation (`^15.0` / v15.0.1 present; verify `composer install` validity on deploy target)
- [ ] Formal Phase -1 exit sign-off (record green matrix below in production-history)
- [ ] Only then allow Phase 0 closure

### PHASE 0 — Pre-infrastructure correctness
- [x] OrderCreated after-commit fix (`ShouldDispatchAfterCommit` on critical events, `c49ea84`)
- [x] PaymentSucceeded notification after-commit (same mechanism)
- [x] Webhook dispatch fix (`SendFrontendWebhookJob` queued)
- [x] Import retry/failure-state inversion fixed
- [x] FCM scoping fix
- [x] SMS logging fix
- [x] QueueName centralization (`App\Enums\QueueName`)
- [x] Failed-job alert/pruning (`HandleFailedQueueJob` + `queue:prune-failed --hours=720`)
- [!] OrderDelivered mapping regression/fix — code+test landed; **uncommitted `EventServiceProvider.php` import patch must be committed**
- [ ] Full regression suite re-run recorded as final Phase 0 evidence

### PHASE 1 — RabbitMQ infrastructure only
- [ ] RabbitMQ connection config (`config/queue.php` connection `rabbitmq`)
- [ ] Broker config (`config/rabbitmq.php` or equivalent)
- [ ] Topology installer command
- [ ] Outbox publisher command (shell; DB recording lands Phase 2)
- [ ] Broker health command
- [ ] Dev Docker Compose RabbitMQ service
- [ ] Supervisor programs (new consumers/workers)
- [ ] Verify pcntl enabled
- [ ] laravel-queue-rabbitmq v15.0.1 internals spike
- [ ] Driver/predeclared-queue declaration compatibility spike
- [ ] Infrastructure smoke test (NO business migration)

### PHASE 2 — Outbox + Inbox shadow
- [ ] `outbox_events` migration · [ ] `processed_messages` migration
- [ ] Outbox model/contracts/recorder · [ ] Inbox guard
- [ ] Producer recording at 5 critical transaction sites
- [ ] Shadow publish to `q.shadow` · [ ] Parity verification

### PHASE 3 — Pilot
- [ ] Webhook job → `onConnection('rabbitmq')` · [ ] Monitor · [ ] Rollback test

### PHASE 4 — A-path cutover
- [ ] Move normal jobs/listeners to rabbitmq · [ ] Dual-stack drain · [ ] Duplicate-safety validation · [ ] Rollback verification

### PHASE 5 — First B-path events
- [ ] `order.paid` consumer · invoice consumer · digital consumer
- [ ] Inbox dedup · registration gate · disable legacy listeners after broker-driven verification · 48h production watch

### PHASE 6 — Remaining B-path
- [ ] `payment.failed`, `order.cancelled`, `order.status.changed`, `refund.approved`
- [ ] lifecycle consumer · notifications consumer · optional `order.created` · DLQ/retry verification

### PHASE 7 — Async conversions
- [ ] Products export → 202 · bulk-delete → 202 · frontend coordination · e2e tests

### PHASE 8 — Decommission
- [ ] Retire database workers · keep jobs table for rollback window · prune

---

## Blockers

1. **Uncommitted Phase-0 wiring patch** (`app/Providers/EventServiceProvider.php`) — blocks formal Phase 0 closure until committed/approved by owner.
2. **Pre-existing suite debt** (list above) — must not be conflated with migration regressions; baseline captured.

## Risks

- Concurrent writers have been active on this repo (three commits landed mid-session). Any further external mutation invalidates this checkpoint — re-inspect before acting.
- RabbitMQ package installed but unintegrated: local dev runs unaffected; do not assume `rabbitmq` connection works without Phase 1.
- `ORDER_TIMEOUT_HOURS` default changed 72→24 (intentional, part of inventory landing); ensure production env is deliberate about it.
- SQLite test env lacks true cross-connection parallelism; concurrency guarantees were validated via serialized real-code paths + conditional-claim latches.

## Rollback Notes

- Inventory feature: single revert of `37ce4a8` restores prior behavior; data rollback via documented reverse-reconciliation (release order reservations, re-add cart reservations from surviving carts).
- Phase 0 fixes: isolated within `c49ea84`; revertable independently.
- RabbitMQ phases: A-path keeps database queue drainable; B-path listeners gated by EventServiceProvider registration flag; jobs table retained through Phase 8 rollback window.

---

## NEXT SESSION / NEXT AGENT INSTRUCTIONS

1. Do NOT start Phase 1..8. Phase 0 closure and Phase -1 sign-off come first.
2. Re-inspect `git log`/`git status` — a concurrent writer has been active; this checkpoint may be stale the moment you read it.
3. Do NOT run any stash commands — the stash list is empty; there is nothing to restore.
4. Commit (with owner approval) the pending `app/Providers/EventServiceProvider.php` import patch to finish #18.
5. Re-run the green battery listed under "Verification executed THIS session" plus the pre-existing-debt suites; record results in `docs/production-history.md`.
6. Only after those are green AND the debt list is unchanged may PHASE 0 be marked closed and PHASE 1 begin.
7. Execute phases strictly sequentially; stop immediately on unexpected external writes; never overwrite another agent's work.

---

## EXECUTION STOP MARKER

STATUS: PAUSED — DOCUMENTATION CHECKPOINT ONLY

No implementation was performed after this checkpoint request.

The next action MUST NOT be implementation.

The next action is to resolve PHASE -1 ownership/reconciliation.
(Clarification per CURRENT Git state: the reconciliation itself landed as commit `37ce4a8` and the stash no longer exists; what remains for formal Phase -1 closure is owner sign-off of the green matrix above, plus committing the pending one-file Phase-0 wiring patch — both documentation/approval steps, not implementation.)

RabbitMQ implementation phases 0–8 remain gated.

END OF CHECKPOINT.
