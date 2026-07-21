# Settings Module — QA Test Cases (Admin API)

## Test Files

No admin settings feature tests exist yet.

---

## API Functionality Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| F1 | GET settings | Fetch settings | 200, full settings object |
| F2 | PUT settings | Update settings with valid data | 200, updated |
| F3 | PUT settings without auth | No token | 401 |
| F4 | PUT settings without permission | Token but no update-settings | 403 |
| F5 | PUT settings invalid data | Wrong field types | 422 |
| F6 | GET fast shipping settings | Fetch fast shipping config | 200, config object |
| F7 | PUT fast shipping settings | Update config | 200, success message |
| F8 | PUT fast shipping no auth | No token | 401 |
| F9 | PUT fast shipping invalid duration | duration_minutes: 9999 | 422 |

---

## Response Structure Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| S1 | GET settings structure | All 18 fields present | Correct types |
| S2 | minimumOrderAmount present | Top-level float | 0 or configured value |
| S3 | Fast shipping GET structure | 5 fields | enabled, duration_minutes, fee, start_hour, end_hour |

---

## Regression Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| R1 | PUT then GET settings | Update value, fetch public | Updated value reflected |
| R2 | minimumOrderAmount flow | Set via PUT, verify GET + checkout | Enforced correctly |
| R3 | Fast shipping cache | Update settings, immediate GET | Fresh data (cache cleared) |

---

## Performance Tests

| # | Test | Description | Expected |
|---|------|-------------|----------|
| P1 | GET settings response | Baseline | <100ms |
| P2 | GET fast shipping cached | After first request | Cache HIT |
| P3 | PUT settings transaction | Concurrent updates | lockForUpdate prevents races |
