# Actors and Use Cases

## System Actors

1. **ADMIN**
   - **Role**: System administrator who oversees the entire operation.
   - **Location**: Anywhere (usually back-office web application).
   - **Capabilities**: 
     - Manage Users (Create, update, search, view stats, view activity, reset passwords).
     - Manage Trucks (Register new trucks, generate QR codes, search, view stats, activate/deactivate trucks individually or in bulk, delete trucks).
     - Monitor Trips (View paginated list of all trips with filters, view live active trips, historical completed trips, view individual trip details, view trip scan log timeline, cancel trips, append/update notes, delete trips).
     - Search Trips (Full-text and filtered search across trips).
     - View Trips by Calendar (Calendar summary per day with configurable `day_start` boundary and timezone, drill-down into trips for a specific day).
     - View Trip and Truck Statistics (Aggregated counts: total, active, completed, cancelled).
     - View Dashboard (Global overview: total trucks, active trucks, total trips, active trips, total users, trips today).
     - View all global Scan Logs (Paginated, with filters by operator, truck, trip, role, location, action, registration number, date range, free-text search; includes summary statistics).
     - Generate and export analytical reports (General summary with average durations, per-truck reports, Excel `.xlsx` exports with French headers).
     - Configure the dynamic Scan Flow steps (view and update the active step sequence).
     - View basic truck info (shared endpoint accessible by all roles).

2. **COMPANY_OPERATOR**
   - **Role**: Field operator stationed at the company headquarters or depot.
   - **Location**: `COMPANY`
   - **Capabilities**:
     - Scan truck QR codes to **START** a trip (dispatching the truck to the port).
     - Scan truck QR codes to **RETURN** a trip (recording the truck's return to the company, thus completing the trip).
     - View their own recent scan history (last N scans, configurable limit).
     - View basic truck info after scanning (shared endpoint).

3. **PORT_OPERATOR**
   - **Role**: Field operator stationed at the port checkpoint.
   - **Location**: `PORT`
   - **Capabilities**:
     - Scan truck QR codes to record **ARRIVE** (truck arrived at the port).
     - Scan truck QR codes to record **LEAVE** (truck departed the port).
     - View their own recent scan history (last N scans, configurable limit).
     - View basic truck info after scanning (shared endpoint).

---

## Core Use Cases

### 1. The Trip Lifecycle (The "Scan Flow")
This is the central engine of the application, acting as a **configurable state machine** that tracks a truck's journey from the company to the port and back.

- **Pre-condition**: An active truck exists with a generated QR code.
- **Step 1 (Start)**: A `COMPANY_OPERATOR` scans the truck's QR code. The system verifies the truck is not currently on a trip, creates a new `Trip` record with status `STARTED`, and logs a `START` scan action. Event `TripStarted` is dispatched.
- **Step 2 (Arrival)**: The truck arrives at the port. A `PORT_OPERATOR` scans the QR code. The system finds the active trip (locked for update to prevent race conditions), updates its status to `ARRIVED_PORT`, and logs an `ARRIVE` scan action. Event `ArrivedAtPort` is dispatched.
- **Step 3 (Departure)**: The truck finishes loading/unloading at the port and is ready to leave. The `PORT_OPERATOR` scans the QR code again. The system updates the trip status to `LEFT_PORT` and logs a `LEAVE` scan action. Event `LeftPort` is dispatched.
- **Step 4 (Completion)**: The truck arrives back at the company. The `COMPANY_OPERATOR` scans the QR code. The system updates the trip status to `COMPLETED`, marks the trip as inactive (`is_active = NULL`, allowing the truck to start a new trip later), and logs a `RETURN` scan action. Event `TripCompleted` is dispatched.

**State Machine Configuration**: The step sequence is stored in the `scan_flows` table and can be modified by an `ADMIN` via the API. The default flow is: `STARTED → ARRIVED_PORT → LEFT_PORT → COMPLETED`. The last step must always be `COMPLETED`, steps must be unique, and only valid status values are accepted.

**QR Code Resolution**: The scan endpoint intelligently resolves truck QR codes from multiple input formats:
1. Direct QR code string (e.g., `SOMASTEEL-TRUCK-XYZ`).
2. URLs with `code` or `qr_code` query parameters.
3. URLs with the QR code embedded as the last path segment.
4. Regex extraction of the `SOMASTEEL-*` pattern from any raw input.

### 2. Exception Handling: Trip Cancellation
- If a truck breaks down or a trip is aborted mid-journey, an `ADMIN` can intervene via the dashboard. They can cancel the active trip (status becomes `CANCELLED`, `is_active` set to `NULL`, `cancelled_at` timestamp recorded), and append explanatory notes. This resets the truck's state, freeing it to be assigned a new trip when repaired or ready.

### 3. Exception Handling: Trip Deletion
- An `ADMIN` can permanently (hard) delete a trip record from the database. This is a destructive action used for data cleanup.

### 4. Trip Notes Management
- An `ADMIN` can add or update notes on any trip (active or completed) at any time. Notes are used for contextual information such as delay reasons, special instructions, or incident reports.

### 5. QR Code Generation and Management
- An `ADMIN` registers a truck in the system with its license plate (`registration_number`) and `driver_name`.
- The system automatically generates a deterministic QR Code string using the format `SOMASTEEL-TRUCK-{NORMALIZED_REGISTRATION}`. The registration number is normalized: uppercased and non-alphanumeric characters are replaced with hyphens.
- The QR code is regenerated automatically whenever the `registration_number` is updated.
- The admin can also explicitly regenerate a QR code via `POST /api/trucks/{id}/generate-qr`.
- If a custom `qr_code` is provided at creation time, it is normalized: uppercased, special characters replaced with hyphens, and the `SOMASTEEL-TRUCK-` prefix is prepended if not already present.
- This QR code is physically attached or printed for the truck so operators can scan it using their mobile devices.

### 6. Truck Lifecycle Management
- **Registration**: Admin creates a truck with `registration_number` (unique, required), `driver_name` (required), optional `qr_code`, and optional `is_active` flag (defaults to `true`).
- **Update**: Admin updates truck details. Changing `registration_number` auto-regenerates the QR code.
- **Activate/Deactivate**: Admin can toggle a truck's active status individually or in bulk. Inactive trucks cannot be scanned (the scan endpoint returns a 422 error).
- **Delete**: Admin can remove a truck (cascading deletes to related trips and scan logs via FK constraints).
- **Search**: Admin can search trucks by `registration_number` or `driver_name` with free-text matching, and filter by `is_active` status.
- **Stats**: Admin can retrieve fleet statistics: `total`, `active`, `inactive` counts.
- **Truck Trips**: Admin can list all historical trips for a specific truck.

### 7. User Lifecycle Management
- **Creation**: Admin creates users with `name`, `email` (unique), `password` (min 8 chars, hashed with bcrypt), `role` (`ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`), and optional `location` (`COMPANY`, `PORT`).
- **Update**: Admin updates user details (including role and location changes). Password can be updated in-place (re-hashed automatically).
- **Search**: Admin can search users by `name` or `email` (free-text), and filter by `role`.
- **Stats**: Admin can retrieve user statistics: total count, breakdown by role, breakdown by location.
- **Activity**: Admin can view a user's activity report: total scan count and their most recent scan log entry.
- **Password Reset**: Admin can force-reset any user's password via a dedicated endpoint (`PATCH /api/users/{id}/reset-password`).
- **Listing**: Paginated user list with optional `role` and `location` filters.

### 8. Dashboard and Analytics
- **Dashboard Overview**: Admin views a real-time summary of system health: `total_trucks`, `active_trucks`, `total_trips`, `active_trips`, `total_users`, `trips_today`.
- **Trip Statistics**: Aggregated counts: `total`, `active`, `completed`, `cancelled`.
- **Truck Statistics**: Aggregated counts: `total`, `active`, `inactive`.
- **User Statistics**: Total count, breakdown by role, breakdown by location.

### 9. Reporting and Analytics
- `ADMIN` users can generate reports based on historical trip data.
- **General Summary Report**: Accepts `start_date` and `end_date` date filters. Returns `total_trips`, `active_trips`, `completed_trips`, `cancelled_trips`, `average_total_duration` (in seconds, computed from completed trips only), and a list of all matching trips with per-trip duration breakdowns.
- **Per-Truck Report**: Returns truck details, trip counts (total, completed, cancelled), and all associated trips with duration breakdowns.
- **Duration Calculations**: The system automatically calculates four duration legs for each trip (in seconds): `company_to_port` (started → arrived), `port_duration` (arrived → left), `port_to_company` (left → completed), `total_duration` (started → completed). Null if the corresponding timestamps are not yet recorded.
- **Delay Detection**: The system flags delayed trips via `Trip::isDelayed()`: trips taking longer than 240 minutes overall (whether still active or already completed). Cancelled trips are never flagged as delayed.
- **Excel Export**: Downloads an `.xlsx` file with French-language headers: `ID du Voyage`, `Camion (Matricule)`, `Chauffeur`, `Statut`, `Date de Début`, `Arrivée au Port`, `Départ du Port`, `Date de Fin`, and four duration columns. File is named `Export_Voyages_{timestamp}.xlsx` and auto-deleted after download.

### 10. Calendar View and Day-Based Trip Browsing
- **Calendar Summary** (`GET /api/trips/calendar`): Admin views a multi-day calendar grid. Each day cell shows: `total` trips, `active` trips, `completed` trips, and a `by_status` breakdown (`STARTED`, `ARRIVED_PORT`, `LEFT_PORT`, `COMPLETED`). Days are bounded by a configurable `day_start` time (e.g., `07:00`) and are timezone-aware.
  - Required: `from`, `to` (date range in `YYYY-MM-DD`).
  - Optional: `timezone` (IANA, defaults to app timezone), `day_start` (HH:mm, defaults to `07:00`).
  - Supports filters: `status`, `truck_id`, `registration_number`, `driver_name`.
- **Day Drill-Down** (`GET /api/trips/by-day`): Paginated trip list for a specific day, including a summary and the time window used.
  - Supports an `all=true` mode that returns all trips for the day (up to 1000) without pagination.

### 11. Global Scan Log Auditing
- Admin can view a comprehensive, paginated list of all scan logs system-wide.
- **Filters**: `user_id`, `truck_id`, `trip_id`, `role`, `location`, `action`, `registration_number`, `search` (free-text across operator name/email, truck registration/driver/QR code, and device ID), `from`, `to` (date range on `scanned_at`).
- **Summary Stats**: Alongside the paginated results, the response includes: `total_logs`, `unique_operators`, `by_action` (count per action type), `by_location` (count per location).
- **Applied Filters**: The response echoes back which filters were applied for frontend state management.

### 12. Operator Scan History
- Field operators (`COMPANY_OPERATOR`, `PORT_OPERATOR`) can view their own recent scans.
- Returns the last N scan logs (configurable via `limit` param, default 10, max 100) performed by the authenticated user.
- Each entry includes: action label (human-readable status mapping), scanned timestamp, truck info.

### 13. Truck Basic Info (Shared Endpoint)
- All authenticated users (ADMIN, COMPANY_OPERATOR, PORT_OPERATOR) can retrieve basic truck information given a truck ID.
- Returns: `id`, `registration_number`, `driver_name`, `qr_code`, `is_active`, and `active_trip_status` (the current active trip's status, if any).
- Used by operator mobile apps to show truck details after a QR code is resolved.

### 14. Authentication and Session Management
- **Login**: Any user authenticates with `email` and `password` (optional `device_name` for token identification). Returns a Sanctum bearer token, user profile, and `expires_at` (currently `null` — tokens do not expire).
- **Logout**: Revokes the current access token (single-device logout).
- **Profile**: Authenticated users retrieve their own profile via `GET /api/me`.

### 15. Database Setup and Seeding
- `GET /api/setup` is a public (unauthenticated) route that runs database migrations and seeders (`migrate --force`, `db:seed --force`). Intended for initial deployment bootstrapping only.
- **Seeders**:
  - `AdminAndTruckSeeder` — Seeds initial admin user and sample trucks.
  - `TruckInitialDataSeeder` — Seeds a larger set of initial truck data.
