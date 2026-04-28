## Admin APIs - Detailed Reference

## 1. Scope

This document covers all APIs relevant to ADMIN users in the current backend.

It includes:
- authentication endpoints used by admins
- all ADMIN-only endpoints
- one shared endpoint that admins can also consume
- business logic behind each endpoint
- request/response expectations
- practical integration guidance

## 2. Global Rules

## 2.1 Base path
- All endpoints are under `/api`.

## 2.2 Authentication
- Use bearer token:
  - `Authorization: Bearer <token>`
- Token is obtained through `POST /api/login`.

## 2.3 Authorization
- ADMIN-only endpoints are protected by role middleware.
- Non-admin users receive `403 Forbidden` on admin routes.

## 2.4 Response envelope
Most endpoints return:

```json
{
  "success": true,
  "data": {},
  "message": "Optional",
  "errors": null
}
```

Error example:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "field": ["..."]
  }
}
```

## 3. Authentication APIs (Admin uses these)

## 3.1 Login
### Endpoint
- `POST /api/login`

### Purpose
- Authenticate an admin and issue a Sanctum token.

### Request body
```json
{
  "email": "admin@truck.local",
  "password": "password",
  "device_name": "admin-dashboard"
}
```

### Validation
- `email`: required, valid email
- `password`: required, string
- `device_name`: optional, max 255

### Logic
- Checks credentials with Auth attempt.
- Creates token using Sanctum.
- Token expiry is driven by `SANCTUM_EXPIRATION`.

### Success response
```json
{
  "success": true,
  "data": {
    "token": "...",
    "user": {
      "id": 1,
      "name": "System Admin",
      "email": "admin@truck.local",
      "role": "ADMIN",
      "location": null
    },
    "expires_at": "2026-..."
  },
  "message": "Login successful",
  "errors": null
}
```

## 3.2 Logout
### Endpoint
- `POST /api/logout`

### Purpose
- Revoke current token.

### Logic
- Deletes current access token only.

## 3.3 Current user
### Endpoint
- `GET /api/me`

### Purpose
- Return currently authenticated admin profile.

## 4. Truck Management APIs (ADMIN)

## 4.1 List trucks
### Endpoint
- `GET /api/trucks`

### Query params
- `limit` (optional, 1 to 100, default 15)
- `page` (optional)
- `is_active` (optional, boolean)

### Logic
- Paginates trucks ordered by latest id.
- Optional active-state filter.

### Use in UI
- Main trucks table with pagination and active filter.

## 4.2 Create truck
### Endpoint
- `POST /api/trucks`

### Request body
```json
{
  "registration_number": "REG-100",
  "qr_code": "TRUCK-100",
  "is_active": true
}
```

### Validation
- `registration_number`: required, unique
- `qr_code`: optional, unique
- `is_active`: optional, boolean

### Logic
- Delegates to TruckService.
- If `qr_code` is missing, backend auto-generates one.

## 4.3 Get one truck
### Endpoint
- `GET /api/trucks/{truck}`

### Logic
- Route-model binding by id.

## 4.4 Update truck
### Endpoint
- `PUT /api/trucks/{truck}` or `PATCH /api/trucks/{truck}`

### Body
- any of:
  - `registration_number`
  - `qr_code`
  - `is_active`

### Validation
- uniqueness checks exclude current truck id.

## 4.5 Delete truck
### Endpoint
- `DELETE /api/trucks/{truck}`

### Logic
- Hard deletes truck record.

## 4.6 Generate QR code
### Endpoint
- `POST /api/trucks/{truck}/generate-qr`

### Purpose
- Regenerate truck QR code.

### Logic
- TruckService generates a new code and persists it.

## 4.7 Activate truck
### Endpoint
- `PATCH /api/trucks/{truck}/activate`

### Logic
- Sets `is_active=true`.

## 4.8 Deactivate truck
### Endpoint
- `PATCH /api/trucks/{truck}/deactivate`

### Logic
- Sets `is_active=false`.

## 5. User Management APIs (ADMIN)

## 5.1 List users
### Endpoint
- `GET /api/users`

### Query params
- `limit` (optional, 1 to 100)
- `page` (optional)
- `role` (optional)
- `location` (optional)

### Logic
- Paginates users ordered by latest id.
- Filters by role and/or location.

## 5.2 Create user
### Endpoint
- `POST /api/users`

### Request body
```json
{
  "name": "New Operator",
  "email": "new.operator@truck.local",
  "password": "secret123",
  "role": "PORT_OPERATOR",
  "location": "PORT"
}
```

### Validation
- `name`: required
- `email`: required, unique
- `password`: required, min 8
- `role`: required, one of `ADMIN|COMPANY_OPERATOR|PORT_OPERATOR`
- `location`: nullable, one of `COMPANY|PORT`

### Logic
- Password is hashed before saving.

## 5.3 Get one user
### Endpoint
- `GET /api/users/{user}`

## 5.4 Update user
### Endpoint
- `PUT /api/users/{user}` or `PATCH /api/users/{user}`

### Body
- any editable field from create endpoint

### Logic
- Re-hashes password only when provided.

## 5.5 Delete user
### Endpoint
- `DELETE /api/users/{user}`

### Logic
- Removes user.

## 6. Trip Monitoring APIs (ADMIN)

## 6.1 List trips
### Endpoint
- `GET /api/trips`

### Query params
- `limit` (optional, 1 to 100)
- `page` (optional)
- `status` (optional)
- `truck_id` (optional)
- `from` (optional, date)
- `to` (optional, date)

### Logic
- Returns `TripResource` collection.
- Includes computed fields:
  - `next_expected_step`
  - `current_location`
  - `last_scan_at`
  - `durations`
- Includes truck object:
  - `id`
  - `registration_number`

## 6.2 Get trip details
### Endpoint
- `GET /api/trips/{trip}`

### Logic
- Returns one enriched `TripResource`.

## 6.3 Active trips
### Endpoint
- `GET /api/trips/active`

### Query params
- `limit` (optional, 1 to 100)

### Logic
- Returns active trips (`is_active=true`) with `OperatorTripResource` shape.
- Optimized for real-time view.

## 6.4 Trip history
### Endpoint
- `GET /api/trips/history`

### Query params
- `limit`, `page`

### Logic
- Returns completed trips only (`status=COMPLETED`) using `TripResource`.

## 6.5 Trip logs
### Endpoint
- `GET /api/trips/{trip}/logs`

### Logic
- Returns `ScanLogResource` collection with:
  - mapped lifecycle action label
  - `scanned_at`
  - truck info

## 7. Reporting APIs (ADMIN)

## 7.1 Summary
### Endpoint
- `GET /api/reports/summary`

### Logic
- Aggregated KPIs:
  - `total_trucks`
  - `total_trips`
  - `active_trips`
  - `completed_trips`
  - `delayed_trips`
  - status breakdown
  - average duration metrics

## 7.2 Truck report
### Endpoint
- `GET /api/reports/truck/{truck}`

### Logic
- Per-truck payload including trip history and durations.

## 7.3 Duration metrics
### Endpoint
- `GET /api/reports/durations`

### Logic
- Returns duration-focused metrics built from trip timeline data.

## 7.4 Delay metrics
### Endpoint
- `GET /api/reports/delays`

### Logic
- Applies delay threshold logic and returns delayed trips summary.

## 7.5 Export payload
### Endpoint
- `GET /api/reports/export`

### Logic
- Returns one export-ready payload:
  - generated timestamp
  - summary
  - durations
  - delays

## 8. Shared endpoint (Admin can use)

## 8.1 Basic truck view
### Endpoint
- `GET /api/trucks/{truck}/basic`

### Access
- ADMIN, COMPANY_OPERATOR, PORT_OPERATOR

### Purpose
- Fast lightweight truck card data for status checks.

## 9. Business logic behind scan flow (important for admins)

Even though `/api/scan` is operator-only, admins should know enforcement logic because it drives all monitoring/reporting data quality.

Core rules:
- One active trip at a time per truck.
- Sequence is strict:
  - `STARTED -> ARRIVED_PORT -> LEFT_PORT -> COMPLETED`
- Truck must exist and be active.
- Operator role/location must match expected transition.
- DB safety:
  - transaction wrapping
  - row locking (`lockForUpdate`)
- Anti-abuse:
  - duplicate same action blocked in 10-second window
  - strict timing guard blocks too-fast `ARRIVED_PORT -> LEFT_PORT`
- Lifecycle events dispatched:
  - `TripStarted`
  - `ArrivedAtPort`
  - `LeftPort`
  - `TripCompleted`

## 10. How to deal with admin APIs (integration strategy)

1. Auth bootstrapping
- login -> store token -> call `/api/me` to confirm ADMIN role.

2. Data listing patterns
- Always use `limit` and `page`.
- Keep filters server-side for large datasets (`status`, dates, etc.).

3. Write operations
- Validate forms client-side, but rely on backend validation as source of truth.
- Handle `422` and display field errors.

4. Access control handling
- `401`: token missing/expired.
- `403`: role not allowed.
- Route unauthorized users back to login or restricted view.

5. Monitoring workflow
- For live operations: `/api/trips/active`
- For audits: `/api/trips/{id}/logs`
- For dashboards: `/api/reports/summary` + `/durations` + `/delays`

6. Truck lifecycle operations
- Deactivate truck to hard-stop field scanning.
- Regenerate QR when label is compromised.

## 11. Seeded admin test account

- Email: `admin@truck.local`
- Password: `password`

## 12. Quick endpoint checklist

Auth:
- POST `/api/login`
- POST `/api/logout`
- GET `/api/me`

Trucks:
- GET `/api/trucks`
- POST `/api/trucks`
- GET `/api/trucks/{truck}`
- PUT/PATCH `/api/trucks/{truck}`
- DELETE `/api/trucks/{truck}`
- POST `/api/trucks/{truck}/generate-qr`
- PATCH `/api/trucks/{truck}/activate`
- PATCH `/api/trucks/{truck}/deactivate`
- GET `/api/trucks/{truck}/basic`

Users:
- GET `/api/users`
- POST `/api/users`
- GET `/api/users/{user}`
- PUT/PATCH `/api/users/{user}`
- DELETE `/api/users/{user}`

Trips:
- GET `/api/trips`
- GET `/api/trips/{trip}`
- GET `/api/trips/active`
- GET `/api/trips/history`
- GET `/api/trips/{trip}/logs`

Reports:
- GET `/api/reports/summary`
- GET `/api/reports/truck/{truck}`
- GET `/api/reports/durations`
- GET `/api/reports/delays`
- GET `/api/reports/export`
# Operator APIs - Current Reference

## 1. Scope

This document lists all APIs currently usable by operator accounts:
- `COMPANY_OPERATOR`
- `PORT_OPERATOR`

It also explains the lifecycle logic behind each endpoint and how to consume these APIs safely from a mobile app.

## 2. Authentication and Access Rules

## 2.1 Login

### Endpoint
- `POST /api/login`

### Body
```json
{
  "email": "company.operator@truck.local",
  "password": "password",
  "device_name": "flutter-device"
}
```

### Success response shape
```json
{
  "success": true,
  "data": {
    "token": "...",
    "user": {
      "id": 2,
      "name": "Company Operator",
      "email": "company.operator@truck.local",
      "role": "COMPANY_OPERATOR",
      "location": "COMPANY"
    },
    "expires_at": "2026-..."
  },
  "message": "Login successful",
  "errors": null
}
```

### Notes
- Use token as `Authorization: Bearer <token>`.
- Token expiration is controlled by `SANCTUM_EXPIRATION`.

## 2.2 Current user

### Endpoint
- `GET /api/me`

### Purpose
- Retrieve current authenticated operator profile and role/location.

## 2.3 Logout

### Endpoint
- `POST /api/logout`

### Purpose
- Revoke current access token.

## 3. Operator Functional Endpoints

## 3.1 Scan truck QR

### Endpoint
- `POST /api/scan`

### Access
- `COMPANY_OPERATOR` and `PORT_OPERATOR`
- Rate limited: `30 requests / minute`

### Body
```json
{
  "qr_code": "TRUCK-001",
  "device_time": "2026-03-26T10:10:10Z",
  "device_id": "pixel-7-abc"
}
```

### Success response shape
```json
{
  "success": true,
  "data": {
    "status": "SUCCESS",
    "message": "Scan successful",
    "current_step": "ARRIVED_PORT",
    "next_expected_step": "LEFT_PORT",
    "is_locked": true,
    "trip_summary": {
      "trip_id": 12,
      "truck_id": 1,
      "status": "ARRIVED_PORT",
      "truck": {
        "id": 1,
        "registration_number": "REG-001"
      },
      "action": "ARRIVE",
      "timestamps": {
        "started_at": "...",
        "arrived_port_at": "...",
        "left_port_at": null,
        "completed_at": null
      }
    }
  },
  "message": "Scan successful",
  "errors": null
}
```

### Scan lifecycle logic
- System finds truck by QR code.
- Truck must be active (`is_active = true`).
- Active trip is fetched with DB row lock.
- Expected action is derived from current trip status:
  - no active trip => `START`
  - `STARTED` => `ARRIVE`
  - `ARRIVED_PORT` => `LEAVE`
  - `LEFT_PORT` => `RETURN`
- Role/location checks:
  - `START` and `RETURN`: only `COMPANY_OPERATOR` at `COMPANY`
  - `ARRIVE` and `LEAVE`: only `PORT_OPERATOR` at `PORT`
- Transition and scan log are written inside a DB transaction.

### Common error cases
- 404: truck not found by QR code
- 422: truck inactive
- 403: wrong role/location for expected step

### Error response shape
```json
{
  "success": false,
  "data": null,
  "message": "Invalid scan sequence or unauthorized location.",
  "errors": {
    "scan": "Invalid scan sequence or unauthorized location."
  }
}
```

## 3.2 Operator last scans

### Endpoint
- `GET /api/operator/last-scans?limit=10`

### Access
- `COMPANY_OPERATOR` and `PORT_OPERATOR`

### Response shape
```json
{
  "success": true,
  "data": [
    {
      "id": 91,
      "action": "ARRIVED_PORT",
      "scanned_at": "2026-03-26T10:11:00Z",
      "truck": {
        "id": 1,
        "registration_number": "REG-001"
      }
    }
  ],
  "message": null,
  "errors": null
}
```

### Notes
- `limit` is clamped between `1` and `100`.
- `action` is mapped to UI-friendly lifecycle labels:
  - `START` => `STARTED`
  - `ARRIVE` => `ARRIVED_PORT`
  - `LEAVE` => `LEFT_PORT`
  - `RETURN` => `COMPLETED`
- `truck` object is always returned (never null). When relation is missing, backend returns fallback values.

## 3.3 Basic truck details

### Endpoint
- `GET /api/trucks/{truck}/basic`

### Access
- `ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`

### Response shape
```json
{
  "success": true,
  "data": {
    "id": 1,
    "registration_number": "REG-001",
    "qr_code": "TRUCK-001",
    "is_active": true,
    "active_trip_status": "STARTED"
  },
  "message": null,
  "errors": null
}
```

## 4. Operator data model hints for Flutter

Use this simple local model mapping:
- `current_step` => current lifecycle state
- `next_expected_step` => next UI action guidance
- `is_locked` => backend safety lock hint to block immediate rescan in UI
- `trip_summary.truck.registration_number` => label on card
- `trip_summary.status` => direct lifecycle status for current trip state
- `trip_summary.timestamps` => timeline display
- `operator/last-scans[].action` => history badges

No additional mobile-side business mapping is required for these fields.

## 5. Important route visibility note

Even though `/api/trips/active` returns operator-friendly resource format, it is currently protected under admin role in route definitions. Operators should rely on:
- `/api/scan`
- `/api/operator/last-scans`
- `/api/trucks/{truck}/basic`

If needed, route policy can be changed later to expose `/api/trips/active` to operators.

## 6. Quick test users (seeded)

- Company operator: `company.operator@truck.local` / `password`
- Port operator: `port.operator@truck.local` / `password`

---

# 7. Admin APIs - Detailed Reference

This section centralizes all ADMIN endpoints in the same document.

## 7.1 Global rules

- All admin endpoints are under `/api`.
- Auth required: `Authorization: Bearer <token>`.
- Admin role required on protected routes.
- Standard response envelope:

```json
{
  "success": true,
  "data": {},
  "message": "Optional",
  "errors": null
}
```

## 7.2 Admin authentication APIs

### `POST /api/login`

Purpose:
- Authenticate admin and issue Sanctum token.

Body:
```json
{
  "email": "admin@truck.local",
  "password": "password",
  "device_name": "admin-dashboard"
}
```

Validation:
- `email`: required, valid email
- `password`: required
- `device_name`: optional, max 255

Logic:
- Validates credentials.
- Creates token with configured expiration (`SANCTUM_EXPIRATION`).

### `POST /api/logout`

Purpose:
- Revoke current token.

Logic:
- Deletes current access token.

### `GET /api/me`

Purpose:
- Return current authenticated admin profile.

## 7.3 Truck Management (ADMIN)

### `GET /api/trucks`

Purpose:
- List trucks with pagination/filtering.

Query:
- `limit` (1..100)
- `page`
- `is_active` (true/false)

Logic:
- Returns latest-first paginated truck list.

### `POST /api/trucks`

Purpose:
- Create truck.

Body:
```json
{
  "registration_number": "REG-100",
  "qr_code": "TRUCK-100",
  "is_active": true
}
```

Validation:
- `registration_number`: required, unique
- `qr_code`: optional, unique
- `is_active`: optional boolean

Logic:
- If `qr_code` missing, backend auto-generates it.

### `GET /api/trucks/{truck}`

Purpose:
- Get one truck by id.

### `PUT|PATCH /api/trucks/{truck}`

Purpose:
- Update truck fields.

Allowed fields:
- `registration_number`
- `qr_code`
- `is_active`

### `DELETE /api/trucks/{truck}`

Purpose:
- Delete truck.

### `POST /api/trucks/{truck}/generate-qr`

Purpose:
- Regenerate truck QR code.

### `PATCH /api/trucks/{truck}/activate`

Purpose:
- Set `is_active=true`.

### `PATCH /api/trucks/{truck}/deactivate`

Purpose:
- Set `is_active=false`.

## 7.4 User Management (ADMIN)

### `GET /api/users`

Purpose:
- List users/operators with filters.

Query:
- `limit` (1..100)
- `page`
- `role`
- `location`

### `POST /api/users`

Purpose:
- Create admin or operator account.

Body:
```json
{
  "name": "New Operator",
  "email": "new.operator@truck.local",
  "password": "secret123",
  "role": "PORT_OPERATOR",
  "location": "PORT"
}
```

Validation:
- `name`: required
- `email`: required, unique
- `password`: required, min 8
- `role`: one of `ADMIN|COMPANY_OPERATOR|PORT_OPERATOR`
- `location`: nullable, one of `COMPANY|PORT`

Logic:
- Password is hashed before save.

### `GET /api/users/{user}`

Purpose:
- Get one user by id.

### `PUT|PATCH /api/users/{user}`

Purpose:
- Update user data, role, location, or password.

Logic:
- Password is re-hashed when provided.

### `DELETE /api/users/{user}`

Purpose:
- Delete user.

## 7.5 Trip Monitoring (ADMIN)

### `GET /api/trips`

Purpose:
- List trips with filters and enriched fields.

Query:
- `limit` (1..100)
- `page`
- `status`
- `truck_id`
- `from`
- `to`

Returned enrichments:
- `next_expected_step`
- `current_location`
- `last_scan_at`
- `durations`
- nested `truck` with id and registration

### `GET /api/trips/{trip}`

Purpose:
- Get one enriched trip.

### `GET /api/trips/active`

Purpose:
- View active trips snapshot.

### `GET /api/trips/history`

Purpose:
- View completed trip history.

### `GET /api/trips/{trip}/logs`

Purpose:
- View scan timeline for a specific trip.

## 7.6 Reports (ADMIN)

### `GET /api/reports/summary`

Purpose:
- KPI summary:
  - total trucks
  - total trips
  - active/completed trips
  - delayed trips
  - status breakdown
  - average durations

### `GET /api/reports/truck/{truck}`

Purpose:
- Per-truck report with trips and durations.

### `GET /api/reports/durations`

Purpose:
- Duration-focused metrics.

### `GET /api/reports/delays`

Purpose:
- Delay analysis based on threshold logic.

### `GET /api/reports/export`

Purpose:
- Export-ready payload containing summary/durations/delays.

## 7.7 Shared endpoint (ADMIN + operators)

### `GET /api/trucks/{truck}/basic`

Purpose:
- Lightweight truck status card.

## 7.8 How to deal with admin APIs

1. Login and verify role via `/api/me`.
2. Use server-side pagination (`limit`, `page`) on list pages.
3. Use backend filters for trips/users to keep UI fast.
4. Handle expected error codes:
   - `401` unauthenticated
   - `403` forbidden (role)
   - `422` validation
   - `404` not found
   - `409` conflict (timing/scan guards)
5. For operations dashboard:
   - active state: `/api/trips/active`
   - audit trail: `/api/trips/{id}/logs`
   - analytics: `/api/reports/*`

## 7.9 Admin seeded account

- `admin@truck.local` / `password`
