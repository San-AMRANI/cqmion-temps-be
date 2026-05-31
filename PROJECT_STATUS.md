# Truck Lifecycle Tracking System - Current Backend Status

## 1) Project health

- Framework: Laravel 12 API backend
- Auth: Sanctum token auth
- Database: MySQL (configured via `.env`)
- Route inventory: 29 API routes registered
- Status: Core business flow is implemented and operational

## 2) Implemented features

### Authentication and access control

- Token login/logout and current-user endpoint
- Role-based middleware guards by route group
- Roles used:
  - ADMIN
  - COMPANY_OPERATOR
  - PORT_OPERATOR

### Truck lifecycle tracking

- Lifecycle states:
  - STARTED
  - ARRIVED_PORT
  - LEFT_PORT
  - COMPLETED
- Strict progression enforcement through service logic
- One active trip per truck enforced with `is_active` flow and DB uniqueness fallback
- Scan logs recorded for every action

### Admin capabilities

- Full truck management CRUD
- Truck activation/deactivation
- QR regeneration per truck
- Full user management CRUD (operators + admin users)
- Trip monitoring (all, active, history, logs)
- Reporting (summary, durations, delays, export, per-truck)

### Operator capabilities

- QR scan endpoint with rate limiting
- Last scans endpoint for operator history

### API quality and security

- Standard response envelope:
  - success
  - data
  - message
  - errors
- Input validation on all major write operations
- Rate limiting on `/api/scan` (`30` requests/minute)
- Token expiration support through `SANCTUM_EXPIRATION`

## 3) Core business logic (how it works)

### Scan flow (`ScanService`)

1. Locate truck by `qr_code` with row lock.
2. Reject when truck is not found or truck is inactive.
3. Fetch current active trip for truck with row lock.
4. Resolve next expected action from current status.
5. Validate role, location, and idempotency.
6. Execute transition in a single DB transaction.
7. Persist scan log.
8. Dispatch lifecycle event.
9. Return scan feedback including next expected step.

### Technical guarantees in place

- Transactional scan write path (`DB::transaction`)
- Concurrency control (`lockForUpdate`)
- Idempotency guard:
  - duplicate action rejection window in validation layer
  - unique key on (`trip_id`, `action`, `scanned_at`) in `scan_logs`
- Active trip guard:
  - `trips.is_active`
  - unique (`truck_id`, `is_active`) fallback strategy

## 4) Services implemented

- `ScanService`
  - orchestrates full scan lifecycle transitions
- `TripService`
  - create/get active/update status/complete/get trips/compute durations
- `TruckService`
  - create/update/delete/find by QR/generate QR/activate/deactivate
- `ScanLogService`
  - log scan/get logs by trip/get last scans by user
- `ValidationService`
  - validates transition, role/location, duplicate retry window
- `ReportService`
  - summary, truck report, duration metrics, delay metrics, filtered data

## 5) Events implemented

- `TripStarted`
- `ArrivedAtPort`
- `LeftPort`
- `TripCompleted`

## 6) Current API inventory

### Public auth

- POST `/api/login`

### Authenticated

- POST `/api/logout`
- GET `/api/me`

### Admin only

- Trucks
  - GET `/api/trucks`
  - POST `/api/trucks`
  - GET `/api/trucks/{truck}`
  - PUT/PATCH `/api/trucks/{truck}`
  - DELETE `/api/trucks/{truck}`
  - POST `/api/trucks/{truck}/generate-qr`
  - PATCH `/api/trucks/{truck}/activate`
  - PATCH `/api/trucks/{truck}/deactivate`
- Users
  - GET `/api/users`
  - POST `/api/users`
  - GET `/api/users/{user}`
  - PUT/PATCH `/api/users/{user}`
  - DELETE `/api/users/{user}`
- Trips
  - GET `/api/trips`
  - GET `/api/trips/{trip}`
  - GET `/api/trips/active`
  - GET `/api/trips/history`
  - GET `/api/trips/{trip}/logs`
- Reports
  - GET `/api/reports/summary`
  - GET `/api/reports/truck/{truck}`
  - GET `/api/reports/durations`
  - GET `/api/reports/delays`
  - GET `/api/reports/export`

### Operator only

- POST `/api/scan`
- GET `/api/operator/last-scans`

### Shared authenticated endpoint (admin + operators)

- GET `/api/trucks/{truck}/basic`

## 7) Database shape

- `users`
  - role + location enabled
- `trucks`
  - registration_number, qr_code, is_active
- `trips`
  - status timestamps + `is_active`
  - unique guard for active trip fallback
- `scan_logs`
  - action/location/scanned_at/device_id
  - unique idempotency key
- `personal_access_tokens` (Sanctum)

## 8) Seeded defaults

- Admin: `admin@truck.local`
- Company operator: `company.operator@truck.local`
- Port operator: `port.operator@truck.local`
- Default password for seeded users: `password`

## 9) How to run

From backend folder:

1. Start MySQL (XAMPP).
2. Run:
   - `php artisan config:clear`
   - `php artisan migrate --seed`
   - `php artisan serve`

## 10) Recommended next improvements

- Add feature tests for full scan lifecycle and role/location violations
- Add OpenAPI/Swagger docs and Postman collection
- Add policies for model-level authorization in addition to middleware
- Add monitoring/alerts for long port duration and invalid scan attempts
