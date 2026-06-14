# API Schema Specification

All API endpoints are prefixed with `/api` and expect JSON requests (`Accept: application/json`, `Content-Type: application/json`).

Responses conform to a standard wrapper:
```json
{
  "success": true|false,
  "data": { ... },
  "message": "Optional message",
  "errors": { ... } // Present if validation fails
}
```

---

## Authentication

### `POST /api/login`
- **Description**: Authenticate a user and issue a Sanctum token.
- **Auth**: None (public).
- **Request Body**:
  ```json
  {
    "email": "user@example.com",
    "password": "secretpassword",
    "device_name": "android-tablet-1" // Optional
  }
  ```
- **Response Data**:
  ```json
  {
    "token": "1|abc...",
    "user": { "id": 1, "name": "Admin", "role": "ADMIN", "location": null },
    "expires_at": null
  }
  ```

### `POST /api/logout`
- **Headers**: `Authorization: Bearer {token}`
- **Description**: Revokes the current token.
- **Response Data**: `null`

### `GET /api/me`
- **Headers**: `Authorization: Bearer {token}`
- **Description**: Retrieves current authenticated user profile.
- **Response Data**: User object (`id`, `name`, `email`, `role`, `location`, `created_at`, `updated_at`).

---

## Field Operator Endpoints (Roles: COMPANY_OPERATOR, PORT_OPERATOR)

### `POST /api/scan`
- **Description**: The core operational endpoint. Submits a QR code scan. The backend intelligently figures out the required action (START, ARRIVE, LEAVE, RETURN) based on the truck's state and the active scan flow configuration.
- **Rate Limit**: 30 requests per minute per user (`throttle:30,1`).
- **QR Code Input Formats**: Accepts raw QR code strings, URLs with `code`/`qr_code` query params, URLs with QR codes as path segments, or any string containing the `SOMASTEEL-*` pattern.
- **Request Body**:
  ```json
  {
    "qr_code": "SOMASTEEL-TRUCK-XYZ",
    "device_time": "2023-10-25T10:00:00Z", // Optional
    "device_id": "device-uuid-123" // Optional
  }
  ```
- **Response Data**:
  ```json
  {
    "status": "SUCCESS",
    "message": "Scan successful",
    "current_step": "STARTED",
    "next_expected_step": "ARRIVED_PORT",
    "is_locked": true,
    "trip_summary": {
      "trip_id": 42,
      "truck_id": 5,
      "status": "STARTED",
      "truck": { "id": 5, "registration_number": "AB-123", "driver_name": "John Doe" },
      "action": "START",
      "timestamps": { 
          "started_at": "2023-10-25T10:00:00Z",
          "arrived_port_at": null,
          "left_port_at": null,
          "completed_at": null
      }
    }
  }
  ```
- **Error Responses**:
  - `404`: Truck not found for provided QR code.
  - `422`: Truck is inactive and cannot be scanned.
  - `403`: Operator role/location mismatch (e.g., PORT_OPERATOR trying to START).
  - `409`: Trip is already completed.
  - `500`: Scan flow not configured.

### `GET /api/operator/last-scans`
- **Query Params**: `limit` (default 10, max 100).
- **Description**: Returns recent scan logs executed by the current authenticated operator.
- **Response Data**: Array of scan log objects: `{ id, action (label), scanned_at, truck: { id, registration_number, driver_name } }`.

---

## Shared Endpoints (Roles: ADMIN, COMPANY_OPERATOR, PORT_OPERATOR)

### `GET /api/trucks/{id}/basic`
- **Description**: Returns lightweight truck info with active trip status. Used by mobile apps to show truck details post-scan.
- **Response Data**:
  ```json
  {
    "id": 5,
    "registration_number": "AB-123",
    "driver_name": "John Doe",
    "qr_code": "SOMASTEEL-TRUCK-AB-123",
    "is_active": true,
    "active_trip_status": "STARTED" // or null if no active trip
  }
  ```

---

## Admin Endpoints (Role: ADMIN)

### Dashboard & Analytics
- **`GET /api/dashboard`**: Returns global statistics.
  ```json
  {
    "total_trucks": 50,
    "active_trucks": 45,
    "total_trips": 1200,
    "active_trips": 12,
    "total_users": 30,
    "trips_today": 8
  }
  ```

---

### Truck Management
- **`GET /api/trucks`**: Paginated list of trucks. 
  - **Query Params**: `is_active` (boolean), `limit` (default 15, max 100).
- **`POST /api/trucks`**: Create a truck.
  - **Body**: `{"registration_number": "123-A-4", "driver_name": "Jane", "qr_code": "optional-custom", "is_active": true}`
  - QR code is auto-generated from `registration_number` if not provided.
  - Returns `201` on success.
- **`GET /api/trucks/{id}`**: Get truck details.
- **`PUT /api/trucks/{id}`**: Update a truck.
  - Changing `registration_number` auto-regenerates the QR code.
  - All fields are optional (`sometimes` validation).
- **`DELETE /api/trucks/{id}`**: Delete a truck (cascades to trips and scan logs).
- **`POST /api/trucks/{id}/generate-qr`**: Regenerates the QR code string from the current `registration_number`.
  - Returns: `{ "id": 5, "qr_code": "SOMASTEEL-TRUCK-AB-123" }`
- **`PATCH /api/trucks/{id}/activate`**: Set truck `is_active = true`.
- **`PATCH /api/trucks/{id}/deactivate`**: Set truck `is_active = false`.
- **`POST /api/trucks/bulk-activate`**: Activate multiple trucks.
  - **Body**: `{"truck_ids": [1, 2, 3]}`
  - Validates all IDs exist in the `trucks` table.
- **`POST /api/trucks/bulk-deactivate`**: Deactivate multiple trucks.
  - **Body**: `{"truck_ids": [1, 2, 3]}`
  - Validates all IDs exist in the `trucks` table.
- **`GET /api/trucks/{id}/trips`**: Get all trips for a specific truck (uses `TripResource`).
- **`GET /api/trucks/search`**: Search trucks by text.
  - **Query Params**: `search` (matches `registration_number` or `driver_name`), `is_active`.
  - Returns: `TruckResource` collection.
- **`GET /api/trucks/stats`**: Get fleet statistics.
  - Returns: `{ "total": 50, "active": 45, "inactive": 5 }`

---

### Trip Management
- **`GET /api/trips`**: Paginated list of trips.
  - **Query Params**: `status`, `truck_id`, `from` (date), `to` (date), `limit` (default 15, max 100).
  - Eager-loads `truck` and `latestScan`.
- **`GET /api/trips/active`**: List of currently ongoing trips (`is_active = true`).
  - Uses `OperatorTripResource` (lightweight).
  - **Query Params**: `limit` (default 15, max 100).
- **`GET /api/trips/history`**: Paginated list of completed trips (`status = COMPLETED`).
  - **Query Params**: `limit` (default 15, max 100).
- **`GET /api/trips/{id}`**: Trip details, including computed durations, delay flag, current location, and truck info.
- **`GET /api/trips/{id}/logs`**: Get timeline of scans for a trip (ordered by `scanned_at`).
  - Each log includes: user info, truck info.
- **`GET /api/trips/{id}/timeline`**: Timeline of all scan events for a trip, formatted for UI.
  - Returns: `[{ "action": "START", "location": "COMPANY", "scanned_at": "...", "user_name": "..." }]`
- **`PATCH /api/trips/{id}/cancel`**: Cancel an active trip.
  - **Body**: `{"notes": "Truck broke down"}` (optional).
  - Sets status to `CANCELLED`, `is_active` to `NULL`, `cancelled_at` to now.
- **`PATCH /api/trips/{id}/notes`**: Update trip notes.
  - **Body**: `{"notes": "Delay due to weather"}` (required).
- **`DELETE /api/trips/{id}`**: Hard delete a trip record.
- **`GET /api/trips/search`**: Search trips with filters.
  - **Query Params**: `status`, `truck_id`, `from`, `to`.
  - Returns: `TripResource` collection (non-paginated).
- **`GET /api/trips/stats`**: Get trip statistics.
  - Returns: `{ "total": 1200, "active": 12, "completed": 1100, "cancelled": 88 }`
- **`GET /api/trips/calendar`**: Calendar summary for a date range.
  - **Required Query Params**: `from`, `to` (YYYY-MM-DD).
  - **Optional Query Params**: `timezone` (IANA, defaults to app timezone), `day_start` (HH:mm, defaults to `07:00`), `status`, `truck_id`, `registration_number`, `driver_name`.
  - **Response Data**:
    ```json
    {
      "data": [
        {
          "day": "2023-10-25",
          "start_at": "2023-10-25T07:00:00+01:00",
          "end_at": "2023-10-26T06:59:59+01:00",
          "total": 15,
          "active": 3,
          "completed": 12,
          "by_status": {
            "STARTED": 1,
            "ARRIVED_PORT": 1,
            "LEFT_PORT": 1,
            "COMPLETED": 12
          },
          "trips": [ ... ] // Full TripResource array for the day
        }
      ],
      "meta": {
        "from": "2023-10-25",
        "to": "2023-10-31",
        "day_start": "07:00",
        "timezone": "Africa/Casablanca"
      }
    }
    ```
- **`GET /api/trips/by-day`**: Trips for a specific day (paginated or all).
  - **Required Query Params**: `day` (YYYY-MM-DD).
  - **Optional Query Params**: `timezone`, `day_start`, `limit` (default 20, max 100), `all` (boolean — if true, returns up to 1000 trips without pagination), `status`, `truck_id`, `registration_number`, `driver_name`.
  - **Response**: Paginated `TripResource` collection with `summary` (total, active, completed), `window` (day, start_at, end_at, day_start, timezone), and pagination metadata.

---

### User Management
- **`GET /api/users`**: Paginated list of users.
  - **Query Params**: `role`, `location`, `limit` (default 15, max 100).
- **`POST /api/users`**: Create a user.
  - **Body**: `{"name": "...", "email": "...", "password": "...", "role": "COMPANY_OPERATOR", "location": "COMPANY"}`
  - `role` must be one of: `ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`.
  - `location` must be one of: `COMPANY`, `PORT` (nullable).
  - `password` minimum 8 characters, hashed before storage.
  - Returns `201` on success.
- **`GET /api/users/{id}`**: Get user details.
- **`PUT /api/users/{id}`**: Update user details.
  - All fields optional (`sometimes` validation). Password re-hashed if provided.
- **`DELETE /api/users/{id}`**: (Not Implemented) The route is registered via `apiResource`, but the `destroy` method does not exist on `UserController`. Calling this endpoint will result in a 500 server error.
- **`PATCH /api/users/{id}/reset-password`**: Admin resets a user's password.
  - **Body**: `{"password": "new_password"}` (min 8 chars).
- **`GET /api/users/{id}/activity`**: Get user activity stats.
  - Returns: `{ "scans": 150, "latest_scan": { ... } }`
- **`GET /api/users/search`**: Search users by text.
  - **Query Params**: `search` (matches `name` or `email`), `role`.
  - Returns: `UserResource` collection.
- **`GET /api/users/stats`**: Get user statistics.
  - Returns: `{ "total": 30, "by_role": { "ADMIN": 2, "COMPANY_OPERATOR": 15, "PORT_OPERATOR": 13 }, "by_location": { "COMPANY": 15, "PORT": 13, null: 2 } }`

---

### Scan Logs (Global)
- **`GET /api/scan-logs`**: Comprehensive paginated list of all scans.
  - **Query Params**: `limit` (default 20, max 100), `page`, `user_id`, `truck_id`, `trip_id`, `role` (ADMIN, COMPANY_OPERATOR, PORT_OPERATOR), `location` (COMPANY, PORT), `action` (START, ARRIVE, LEAVE, RETURN), `registration_number` (partial match), `search` (free-text across operator name/email, truck registration/driver/QR code, device ID), `from`, `to` (date range on `scanned_at`).
  - All filter params are validated server-side.
  - **Response Data**:
    ```json
    {
      "items": [ ... ], // AdminScanLogResource array
      "pagination": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 20,
        "total": 200,
        "from": 1,
        "to": 20
      },
      "summary": {
        "total_logs": 200,
        "unique_operators": 15,
        "by_action": { "START": 50, "ARRIVE": 50, "LEAVE": 50, "RETURN": 50 },
        "by_location": { "COMPANY": 100, "PORT": 100 }
      },
      "applied_filters": {
        "user_id": null,
        "truck_id": null,
        "trip_id": null,
        "role": null,
        "location": null,
        "action": null,
        "registration_number": null,
        "search": null,
        "from": null,
        "to": null
      }
    }
    ```

---

### Reports & Exports
- **`GET /api/reports/summary`**: Returns aggregated trip data and average durations.
  - **Query Params**: `start_date`, `end_date`.
  - **Response Data**:
    ```json
    {
      "total_trips": 100,
      "active_trips": 5,
      "completed_trips": 90,
      "cancelled_trips": 5,
      "average_total_duration": 7200, // seconds
      "trips": [ ... ] // Array with per-trip duration breakdowns
    }
    ```
- **`GET /api/reports/truck/{id}`**: Returns analytical report for a specific truck.
  - **Query Params**: Inherits from `TripService::getTrips()` filters (`status`, `truck_id`, `from`, `to`).
  - **Response Data**:
    ```json
    {
      "truck": { "id": 5, "registration_number": "AB-123", ... },
      "total_trips": 50,
      "completed_trips": 45,
      "cancelled_trips": 3,
      "trips": [ ... ] // Array with per-trip duration breakdowns
    }
    ```
- **`GET /api/reports/export`**: Downloads an Excel (`.xlsx`) file of the trip report.
  - **Query Params**: Inherits from `TripService::getTrips()` filters (`status`, `truck_id`, `from`, `to`).
  - **Response**: Binary file download (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`). File is auto-deleted after download.
  - **Columns** (French headers): `ID du Voyage`, `Camion (Matricule)`, `Chauffeur`, `Statut`, `Date de Début`, `Arrivée au Port`, `Départ du Port`, `Date de Fin`, `Durée (Compagnie -> Port)`, `Durée au Port`, `Durée (Port -> Compagnie)`, `Durée Totale`.
- **`GET /api/reports/durations`**: Duration-specific analytics endpoint (route defined but delegates to `ReportController::durations` — currently not implemented as a separate method; the route exists in the routing layer).
- **`GET /api/reports/delays`**: Delay-specific analytics endpoint (route defined but delegates to `ReportController::delays` — currently not implemented as a separate method; the route exists in the routing layer).

> **Note**: `GET /api/reports/durations` and `GET /api/reports/delays` are registered in `routes/api.php` but their controller methods (`durations()`, `delays()`) are not yet implemented in `ReportController`. These routes will return a 500 error until the methods are added.

---

### Configuration
- **`GET /api/scan-flow`**: Retrieve the active scan flow step sequence.
  - Returns a default flow if none exists in the database.
  - **Response Data**:
    ```json
    {
      "id": 1,
      "steps": ["STARTED", "ARRIVED_PORT", "LEFT_PORT", "COMPLETED"],
      "is_active": true,
      "updated_at": "2023-10-25T10:00:00Z",
      "created_at": "2023-10-25T10:00:00Z"
    }
    ```
- **`PUT /api/scan-flow`**: Update the logical flow of a trip.
  - **Validation Rules**:
    - `steps` must be an array with at least 1 element.
    - Each step must be a valid status (`STARTED`, `ARRIVED_PORT`, `LEFT_PORT`, `COMPLETED`).
    - Steps must be unique (no duplicates).
    - The last step must be `COMPLETED`.
  - Creates a new flow record if none exists, or updates the existing active flow.

---

## System Utilities

### `GET /api/setup`
- **Auth**: None (public).
- **Description**: Runs database migrations and seeders. Intended for initial deployment bootstrapping only.
- **Response**: Plain text: `"Database migrated and seeded!"`

### `GET /` (Web)
- Returns the default Laravel welcome view.

### `GET /login` (Web)
- Returns a JSON 401 response: `{"message": "Unauthenticated. Use POST /api/login to obtain an API token."}`. Named route `login` used by Sanctum for unauthenticated redirects.
