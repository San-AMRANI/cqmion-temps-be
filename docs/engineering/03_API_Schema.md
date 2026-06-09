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

---

## Field Operator Endpoints (Roles: COMPANY_OPERATOR, PORT_OPERATOR)

### `POST /api/scan`
- **Description**: The core operational endpoint. Submits a QR code scan. The backend intelligently figures out the required action (START, ARRIVE, LEAVE, RETURN) based on the truck's state.
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

### `GET /api/operator/last-scans`
- **Query Params**: `limit` (default 10)
- **Description**: Returns recent scan logs executed by the current operator.

---

## Admin Endpoints (Role: ADMIN)

### Dashboard & Analytics
- **`GET /api/dashboard`**: Returns global statistics (total trucks, active trips, trips today, etc.).
- **`GET /api/reports/summary`**: Returns aggregated trip data and average durations. Accepts `start_date` and `end_date` query filters.
- **`GET /api/reports/truck/{id}`**: Returns analytical report for a specific truck.
- **`GET /api/reports/durations`** / **`delays`**: Returns metric-specific analytics.
- **`GET /api/reports/export`**: Downloads an Excel file of the trip report.

### Truck Management
- **`GET /api/trucks`**: Paginated list of trucks. (Query filters: `is_active`, `limit`).
- **`POST /api/trucks`**: Create a truck. 
  - **Body**: `{"registration_number": "123-A-4", "driver_name": "Jane", "is_active": true}`
- **`GET /api/trucks/{id}`**: Get truck details.
- **`PUT /api/trucks/{id}`**: Update a truck.
- **`DELETE /api/trucks/{id}`**: Delete a truck.
- **`POST /api/trucks/{id}/generate-qr`**: Generates a new `SOMASTEEL-TRUCK-` QR code string.
- **`PATCH /api/trucks/{id}/activate`** / **`deactivate`**: Toggle active status.
- **`POST /api/trucks/bulk-activate`** / **`bulk-deactivate`**: Toggle active status for multiple trucks. Body: `{"truck_ids": [1, 2, 3]}`.
- **`GET /api/trucks/{id}/trips`**: Get all trips for a specific truck.

### Trip Management
- **`GET /api/trips`**: Paginated list of trips. (Query filters: `status`, `truck_id`, `from`, `to`, `limit`).
- **`GET /api/trips/active`**: List of currently ongoing trips.
- **`GET /api/trips/history`**: Paginated list of completed trips.
- **`GET /api/trips/{id}`**: Trip details, including computed durations and truck info.
- **`GET /api/trips/{id}/logs`**: Get timeline of scans for a trip.
- **`GET /api/trips/{id}/timeline`**: Timeline of all scan events formatted for UI presentation.
- **`PATCH /api/trips/{id}/cancel`**: Abort an active trip. 
  - **Body**: `{"notes": "Truck broke down"}`
- **`PATCH /api/trips/{id}/notes`**: Update trip notes.
  - **Body**: `{"notes": "Delay due to weather"}`
- **`DELETE /api/trips/{id}`**: Hard delete a trip.

### User Management
- **`GET /api/users`**: Paginated list of users.
- **`POST /api/users`**: Create a user. 
  - **Body**: `{"name": "...", "email": "...", "password": "...", "role": "COMPANY_OPERATOR", "location": "COMPANY"}`
- **`GET /api/users/{id}`**: Get user details.
- **`PUT /api/users/{id}`**: Update user details.
- **`PATCH /api/users/{id}/reset-password`**: Admin resets password. Body: `{"password": "new_password"}`.
- **`GET /api/users/{id}/activity`**: Get user stats (total scans, latest scan).

### Scan Logs (Global)
- **`GET /api/scan-logs`**: Comprehensive paginated list of all scans.
  - **Filters**: `user_id`, `truck_id`, `trip_id`, `role`, `location`, `action`, `registration_number`, `search`, `from`, `to`.

### Configuration
- **`GET /api/scan-flow`**: Retrieve the active sequence of steps.
- **`PUT /api/scan-flow`**: Update the logical flow of a trip.
