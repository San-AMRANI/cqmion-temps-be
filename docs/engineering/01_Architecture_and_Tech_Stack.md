# Architecture and Tech Stack

## Tech Stack
- **Framework**: Laravel 11/12 (PHP 8+)
- **Database**: MySQL / MariaDB (managed via Eloquent ORM)
- **Authentication**: Laravel Sanctum (Token-based API authentication)
- **Routing**: API-centric routes (`routes/api.php`)
- **Excel Exports**: PhpSpreadsheet (`PhpOffice\PhpSpreadsheet`) for `.xlsx` generation
- **Date/Time**: Carbon / CarbonImmutable for timezone-aware date manipulation

## Architectural Pattern: Service-Oriented MVC
The system uses a refined Model-View-Controller (MVC) architecture, specifically the **Controller-Service-Model** pattern. This is a best practice in modern Laravel API development designed to keep controllers thin, business logic isolated and reusable, and database interactions strictly managed.

### Layer Breakdown

1. **Controllers (`app/Http/Controllers`)**
   - **Responsibility**: HTTP routing, request validation, and HTTP response formatting.
   - **Behavior**: They receive the request, validate inputs (either inline or via FormRequests), pass the sanitized data to the appropriate Service class, and return a standardized JSON response using the base `Controller` methods (`successResponse` and `errorResponse`).
   - **Base Controller**: All controllers extend `App\Http\Controllers\Controller`, which provides two standardized response helpers:
     - `successResponse(mixed $data, ?string $message, int $status)` → `{"success": true, "data": ..., "message": ..., "errors": null}`
     - `errorResponse(string $message, mixed $errors, int $status)` → `{"success": false, "data": null, "message": ..., "errors": ...}`
   - **Controllers**:
     - `AuthController` — Login, logout, current user profile (`/me`).
     - `ScanController` — Core scan endpoint for field operators; delegates to `ScanService`.
     - `TruckController` — Full CRUD, search, stats, QR generation, activate/deactivate (single & bulk), basic info, and truck trip history.
     - `TripController` — Listing (paginated, active, history), search, stats, calendar summary, by-day view, timeline, scan logs for a trip, cancel, update notes, delete, and operator last scans.
     - `UserController` — Full CRUD, search, stats, activity report, and admin password reset.
     - `DashboardController` — Global overview statistics for the admin panel.
     - `ReportController` — General summary report, per-truck report, and Excel export download.
     - `AdminScanLogController` — Paginated, filterable global scan log viewer with summary statistics.
     - `ScanFlowController` — Retrieve and update the configurable scan flow step sequence.

2. **Services (`app/Services`)**
   - **Responsibility**: Core business logic, transaction management, and complex data manipulation.
   - **Services**:
     - `ScanService` — The orchestrator for the core scan feature. Runs inside a DB transaction: resolves the truck from QR input (supports raw codes, URLs with query params, and path segments), checks truck activity, queries for an active trip with pessimistic locking (`lockForUpdate`), delegates to `ScanFlowService` for step resolution, calls `ValidationService` for role/location validation, creates or updates the trip via `TripService`, logs the scan via `ScanLogService`, and dispatches domain events.
     - `ScanFlowService` — Manages the configurable state machine. Reads the active `ScanFlow` from the database (with in-memory caching), resolves the next logical status for a trip, maps statuses to actions (`STATUS_TO_ACTION`), provides default steps (`STARTED → ARRIVED_PORT → LEFT_PORT → COMPLETED`), and validates that the scan flow is configured.
     - `TripService` — Creates trips with the correct timestamp fields based on initial status, updates trip status with corresponding timestamp mutations, cancels trips (sets `CANCELLED` status, clears `is_active` to `NULL`, records `cancelled_at`), provides search/filter, stats aggregation (`total`, `active`, `completed`, `cancelled`), timeline generation from scan logs, and duration formatting (`company_to_port`, `port_duration`, `port_to_company`, `total_duration` in seconds).
     - `TruckService` — Creates trucks with auto-generated QR codes (deterministic: `SOMASTEEL-TRUCK-{REGISTRATION}`), updates trucks (auto-regenerates QR when registration changes), deletes trucks, generates/regenerates QR codes, activates/deactivates (single and bulk), provides stats (`total`, `active`, `inactive`), and search (by `registration_number`, `driver_name`, `is_active`).
     - `UserService` — Search (by `role`, `name`, `email`), stats aggregation (by role and location), activity reports (total scans, latest scan), and admin password reset.
     - `DashboardService` — Computes global overview: `total_trucks`, `active_trucks`, `total_trips`, `active_trips`, `total_users`, `trips_today`.
     - `ReportService` — Generates general summary reports (with date filters, trip counts, average durations), per-truck analytical reports, and delegates Excel export to `ExcelExportService`.
     - `ExcelExportService` — Generates `.xlsx` files using PhpSpreadsheet with French-language headers (`ID du Voyage`, `Camion (Matricule)`, `Chauffeur`, etc.). Computes per-trip leg durations and saves to `storage/app/public/`.
     - `ScanLogService` — Creates scan log records, retrieves logs by trip (ordered by `scanned_at`), retrieves last scans by user, builds admin log queries (filterable by `user_id`, `truck_id`, `trip_id`, `action`, `location`, `role`, `registration_number`, `from`, `to`, `search`), and computes admin log summaries (`total_logs`, `unique_operators`, `by_action`, `by_location`).
     - `TripCalendarService` — Calendar summary generation: iterates over a date range producing per-day statistics (total, active, completed, by_status) using configurable day boundaries (`day_start` in `HH:mm` format, timezone-aware). Also provides paginated trips-by-day and all-trips-for-day retrieval. Supports filters: `status`, `truck_id`, `registration_number`, `driver_name`.
     - `ValidationService` — Enforces scan permission rules: only operators (`COMPANY_OPERATOR`, `PORT_OPERATOR`) can scan; `START` and `RETURN` actions require `COMPANY_OPERATOR` at `COMPANY` location; `ARRIVE` and `LEAVE` actions require `PORT_OPERATOR` at `PORT` location.

3. **Models (`app/Models`)**
   - **Responsibility**: Data representation, database interaction (ORM), and defining relationships.
   - **Models**:
     - `User` — Extends `Authenticatable` with `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`. Defines role constants (`ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`) and location constants (`COMPANY`, `PORT`). Has `scanLogs()` relationship and `isAdmin()` helper. Hides `password` and `remember_token` from serialization. Casts `password` as `hashed`.
     - `Truck` — Fields: `registration_number` (unique), `driver_name`, `qr_code` (unique), `is_active` (boolean, default true). Relationships: `trips()` (HasMany), `scanLogs()` (HasMany), `activeTrip()` (HasOne, filtered by `is_active = true`).
     - `Trip` — Fields: `truck_id` (FK), `status` (enum), `is_active` (nullable boolean), timestamps (`started_at`, `arrived_port_at`, `left_port_at`, `completed_at`, `cancelled_at`), `notes`. Status constants: `STARTED`, `ARRIVED_PORT`, `LEFT_PORT`, `COMPLETED`, `CANCELLED`. Relationships: `truck()` (BelongsTo), `scanLogs()` (HasMany), `latestScan()` (HasOne, latest by `scanned_at`). Helper: `isDelayed()` returns `true` if a trip exceeds 240 minutes (either elapsed for active trips or total duration for completed trips).
     - `ScanLog` — Fields: `truck_id` (FK), `trip_id` (FK), `user_id` (FK), `location`, `action`, `device_id`, `scanned_at`, `created_at`. Action constants: `START`, `ARRIVE`, `LEAVE`, `RETURN`. Timestamps disabled (`$timestamps = false`). Relationships: `truck()`, `trip()`, `user()` (all BelongsTo).
     - `ScanFlow` — Fields: `steps` (JSON, cast to array), `is_active` (boolean). Stores the configurable step sequence for the trip state machine.

4. **Resources (`app/Http/Resources`)**
   - **Responsibility**: Data transformation and presentation layer.
   - **Resources**:
     - `TripResource` — Full trip representation with computed fields: `current_location` (mapped from status: `ON_ROUTE_TO_PORT`, `AT_PORT`, `RETURNING`, `AT_COMPANY`), `next_expected_step` (resolved from `ScanFlowService`), `is_delayed`, `last_scan_at`, and `durations` object with four computed leg times in seconds.
     - `OperatorTripResource` — Lightweight trip view for field operators: omits duration details and full timestamps; includes `next_expected_step`, `current_location`, `is_delayed`, and basic truck info.
     - `ScanLogResource` — Scan log entry for operator views: includes action label mapping (`START→STARTED`, `ARRIVE→ARRIVED_PORT`, `LEAVE→LEFT_PORT`, `RETURN→COMPLETED`), truck info.
     - `AdminScanLogResource` — Rich scan log entry for admin views: includes operator details (name, email, role, location), truck details (including `qr_code`), trip status, action labels.
     - `TruckResource` — Truck details with conditional `activeTrip` relationship loading.
     - `UserResource` — User details excluding sensitive fields.
     - `ScanFlowResource` — Flow steps, active status, and timestamps.

5. **Middleware (`app/Http/Middleware`)**
   - **Responsibility**: Request filtering and authorization.
   - `EnsureUserRole` — Variadic role check middleware. Accepts one or more role strings (e.g., `role:ADMIN` or `role:COMPANY_OPERATOR,PORT_OPERATOR`). Aborts with 401 if unauthenticated, 403 if the user's role is not in the allowed list. Registered as `role` alias.

6. **Events (`app/Events`)**
   - **Responsibility**: Decoupling side-effects from core logic.
   - **Events** (all dispatched by `ScanService` after successful scan processing):
     - `TripStarted` — Dispatched when a new trip is created (status `STARTED`).
     - `ArrivedAtPort` — Dispatched when trip status transitions to `ARRIVED_PORT`.
     - `LeftPort` — Dispatched when trip status transitions to `LEFT_PORT`.
     - `TripCompleted` — Dispatched when trip status transitions to `COMPLETED`.
   - Each event carries the `Trip` model instance. No listeners are currently registered, but the architecture is prepared for future async side-effects (push notifications, webhooks, audit logs).

7. **Exceptions (`app/Exceptions`)**
   - `ScanException` — Domain-specific exception for scan failures. Carries a custom `statusCode` (used for HTTP response status). Common scenarios:
     - `404` — Truck not found for QR code.
     - `422` — Truck is inactive.
     - `403` — Operator role/location mismatch.
     - `409` — Trip already completed (conflict).
     - `500` — Scan flow not configured / unsupported action.

## Cross-Cutting Concerns

### Rate Limiting
- The `POST /api/scan` endpoint has a throttle middleware (`throttle:30,1`): max 30 requests per minute per user.

### Database Locking
- The scan process uses pessimistic locking (`lockForUpdate()`) on both the truck and the active trip query to prevent race conditions from concurrent scans.

### QR Code Resolution
- The `ScanService` supports multiple QR code input formats:
  1. Direct QR code string (e.g., `SOMASTEEL-TRUCK-XYZ`).
  2. URL with `code` or `qr_code` query parameter.
  3. URL with the QR code as the last path segment.
  4. Regex extraction of `SOMASTEEL-*` pattern from any input.
  All candidates are normalized to uppercase before database lookup.

### Setup Route
- `GET /api/setup` — Public (unauthenticated) route that runs `migrate --force` and `db:seed --force`. Intended for initial deployment bootstrapping.

### Shared Endpoints
- `GET /api/trucks/{id}/basic` — Accessible by all authenticated roles (`ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`). Returns lightweight truck info with active trip status. Used by operator mobile apps to preview truck details after scanning.
