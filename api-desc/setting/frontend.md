# Settings Module — Frontend Integration Guide (Admin)

---

### 1. GET /api/v1/settings — Fetch Settings

**Purpose:** Retrieve current settings for the admin settings form.

**Authentication:** Public (index is unauthenticated)

**Response 200:** Full settings object (see api.md)

---

### 2. PUT /api/v1/settings — Update Settings

**Purpose:** Save admin settings form.

**Authentication:** Sanctum token with `update-settings` permission

**Request:** JSON body with all settings fields

**Response 200:** Updated settings object

---

### 3. GET /api/v1/fast-shipping/settings — Fetch Fast Shipping Config

**Purpose:** Retrieve fast shipping configuration for admin form.

**Authentication:** Sanctum token with `view-fast-shipping` permission

**Response 200:**
```json
{
    "enabled": true,
    "duration_minutes": 120,
    "fee": 0,
    "start_hour": "08:00",
    "end_hour": "22:00"
}
```

---

### 4. PUT /api/v1/fast-shipping/settings — Update Fast Shipping Config

**Purpose:** Save fast shipping configuration.

**Authentication:** Sanctum token with `update-fast-shipping` permission

**Request:**
```json
{
    "enabled": true,
    "duration_minutes": 120,
    "fee": 0,
    "start_hour": "08:00",
    "end_hour": "22:00"
}
```

---

## State Handling

| State | Behavior |
|-------|----------|
| **Loading** | Form skeleton / spinner |
| **Success** | Populate form, toast on save |
| **Error** | Show error alert, form remains editable |
| **Validation** | Inline field validation errors from 422 |
| **Saving** | Button loading state, form disabled |
