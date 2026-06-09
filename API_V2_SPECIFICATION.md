# API V2 Specification

## 1. Scope
This document defines the backend API contract consumed by the frontend.

Base URL: `/api`

Authentication: Laravel Sanctum bearer token on every protected route.

Authorization:
- `ADMIN` can access administrative routes.
- `COMPANY_OPERATOR` and `PORT_OPERATOR` can access scan routes and shared truck lookup routes.
- `ADMIN`, `COMPANY_OPERATOR`, and `PORT_OPERATOR` can access `/api/trucks/{truck}/basic`.

## 2. Response Envelopes

### 2.1 Standard envelope
Most controller methods use the base controller response shape:

```json
{
  "success": true,
  "data": {},
  "message": null,
  "errors": null
}
```

Validation or domain failures use:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {}
}
```

### 2.2 Legacy admin envelope
Some admin/reporting endpoints return a direct JSON body with a `status` field:

```json
{
  "status": "SUCCESS",
  "data": {}
}
```

These endpoints are noted below.

### 2.3 Binary responses
`GET /api/reports/export` returns an `.xlsx` file download, not JSON.

## 3. Shared Schemas

### 3.1 User roles and locations
`role`:
- `ADMIN`
- `COMPANY_OPERATOR`
- `PORT_OPERATOR`

`location`:
- `COMPANY`
- `PORT`

### 3.2 Trip statuses
- `STARTED`
- `ARRIVED_PORT`
- `LEFT_PORT`
- `COMPLETED`
- `CANCELLED`

### 3.3 Scan actions
Stored scan actions:
- `START`
- `ARRIVE`
- `LEAVE`
- `RETURN`

Displayed scan labels:
- `STARTED`
- `ARRIVED_PORT`
- `LEFT_PORT`
- `COMPLETED`

### 3.4 Common query parameters
- `limit`: integer, bounded by the controller to `1..100`.
- `search`: free-text search string where supported.
- `is_active`: boolean filter for trucks.
- `role`: user role filter.
- `location`: user location filter.
- `status`: trip status filter.
- `truck_id`: truck filter.
- `from` / `to`: date filters in `Y-m-d` format.
- `timezone`: IANA timezone identifier.
- `day_start`: local day boundary in `HH:mm` format.

## 4. Resource Schemas

### 4.1 TruckResource
```json
{
  "id": 1,
  "registration_number": "TRK-001",
  "driver_name": "Driver Name",
  "qr_code": "SOMASTEEL-TRUCK-TRK-001",
  "is_active": true,
  "created_at": "2026-06-09T10:00:00.000000Z",
  "updated_at": "2026-06-09T10:00:00.000000Z",
  "active_trip": null,
  "maintenance_records": []
}
```

Notes:
- `active_trip` is only populated when the relation is loaded.
- `maintenance_records` is a collection of maintenance resources when loaded.

### 4.2 UserResource
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "role": "ADMIN",
  "location": "COMPANY",
  "created_at": "2026-06-09T10:00:00.000000Z",
  "updated_at": "2026-06-09T10:00:00.000000Z"
}
```

### 4.3 TripResource
```json
{
  "id": 10,
  "status": "STARTED",
  "next_expected_step": "ARRIVE",
  "current_location": "ON_ROUTE_TO_PORT",
  "last_scan_at": "2026-06-09T10:05:00.000000Z",
  "is_active": true,
  "created_at": "2026-06-09T10:00:00.000000Z",
  "started_at": "2026-06-09T10:00:00.000000Z",
  "arrived_port_at": null,
  "left_port_at": null,
  "completed_at": null,
  "durations": {
    "company_to_port": null,
    "port_duration": null,
    "port_to_company": null,
    "total_duration": null
  },
  "truck": {
    "id": 1,
    "registration_number": "TRK-001",
    "driver_name": "Driver Name"
  }
}
```

### 4.4 OperatorTripResource
```json
{
  "id": 10,
  "status": "STARTED",
  "next_expected_step": "ARRIVE",
  "current_location": "ON_ROUTE_TO_PORT",
  "last_scan_at": "2026-06-09T10:05:00.000000Z",
  "truck": {
    "id": 1,
    "registration_number": "TRK-001",
    "driver_name": "Driver Name"
  }
}
```

### 4.5 MaintenanceResource
```json
{
  "id": 7,
  "truck_id": 1,
  "trip_id": 10,
  "type": "ENGINE_SERVICE",
  "description": "Oil change",
  "cost": "150.00",
  "date": "2026-06-09",
  "created_at": "2026-06-09T10:00:00.000000Z",
  "updated_at": "2026-06-09T10:00:00.000000Z",
  "truck": {
    "id": 1,
    "registration_number": "TRK-001",
    "driver_name": "Driver Name"
  }
}
```

### 4.6 ScanLogResource
```json
{
  "id": 99,
  "action": "STARTED",
  "scanned_at": "2026-06-09T10:00:00.000000Z",
  "truck": {
    "id": 1,
    "registration_number": "TRK-001",
    "driver_name": "Driver Name"
  }
}
```

## 5. Authentication and Session

### 5.1 `POST /api/login`
Public.

Request:
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Response: standard envelope with the authenticated user and Sanctum token payload returned by `AuthController`.

### 5.2 `POST /api/logout`
Protected by `auth:sanctum`.

### 5.3 `GET /api/me`
Protected by `auth:sanctum`.

### 5.4 `GET /api/setup`
Internal bootstrap route. Runs migrations and seeders.

## 6. Admin APIs

### 6.1 Dashboard
`GET /api/dashboard`

Auth: `ADMIN`

Envelope: legacy admin envelope

Response `data` schema:
```json
{
  "total_trucks": 0,
  "active_trucks": 0,
  "total_trips": 0,
  "active_trips": 0,
  "total_users": 0,
  "trips_today": 0
}
```

### 6.2 Trucks

#### `GET /api/trucks`
Auth: `ADMIN`

Query:
- `limit`
- `is_active`

Response: standard envelope with paginated truck records.

#### `POST /api/trucks`
Auth: `ADMIN`

Request:
```json
{
  "registration_number": "TRK-001",
  "driver_name": "Driver Name",
  "qr_code": "SOMASTEEL-TRUCK-TRK-001",
  "is_active": true
}
```

#### `GET /api/trucks/{truck}`
Auth: `ADMIN`

#### `PUT /api/trucks/{truck}`
Auth: `ADMIN`

#### `DELETE /api/trucks/{truck}`
Auth: `ADMIN`

#### `GET /api/trucks/search`
Auth: `ADMIN`

Query:
- `search`: matches `registration_number` or `driver_name`
- `is_active`

Response: `TruckResource` collection.

#### `GET /api/trucks/stats`
Auth: `ADMIN`

Response `data`:
```json
{
  "total": 0,
  "active": 0,
  "inactive": 0
}
```

#### `POST /api/trucks/bulk-activate`
Auth: `ADMIN`

Request:
```json
{
  "truck_ids": [1, 2, 3]
}
```

#### `POST /api/trucks/bulk-deactivate`
Auth: `ADMIN`

Request:
```json
{
  "truck_ids": [1, 2, 3]
}
```

#### `POST /api/trucks/{truck}/generate-qr`
Auth: `ADMIN`

Response `data`:
```json
{
  "id": 1,
  "qr_code": "SOMASTEEL-TRUCK-TRK-001"
}
```

#### `PATCH /api/trucks/{truck}/activate`
Auth: `ADMIN`

#### `PATCH /api/trucks/{truck}/deactivate`
Auth: `ADMIN`

#### `GET /api/trucks/{truck}/basic`
Auth: `ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`

Response `data`:
```json
{
  "id": 1,
  "registration_number": "TRK-001",
  "driver_name": "Driver Name",
  "qr_code": "SOMASTEEL-TRUCK-TRK-001",
  "is_active": true,
  "active_trip_status": "STARTED"
}
```

#### `GET /api/trucks/{truck}/trips`
Auth: `ADMIN`

Response: `TripResource` collection.

### 6.3 Users

#### `GET /api/users`
Auth: `ADMIN`

Query:
- `limit`
- `role`
- `location`

Response: standard envelope with paginated users.

#### `POST /api/users`
Auth: `ADMIN`

Request:
```json
{
  "name": "Operator",
  "email": "operator@example.com",
  "password": "password123",
  "role": "COMPANY_OPERATOR",
  "location": "COMPANY"
}
```

#### `GET /api/users/{user}`
Auth: `ADMIN`

#### `PUT /api/users/{user}`
Auth: `ADMIN`

#### `DELETE /api/users/{user}`
Auth: `ADMIN`

#### `GET /api/users/search`
Auth: `ADMIN`

Query:
- `role`
- `search` (name or email)

Response: `UserResource` collection.

#### `GET /api/users/stats`
Auth: `ADMIN`

Response `data`:
```json
{
  "total": 0,
  "by_role": {},
  "by_location": {}
}
```

#### `GET /api/users/{user}/activity`
Auth: `ADMIN`

Response `data`:
```json
{
  "scans": 0,
  "latest_scan": null
}
```

#### `PATCH /api/users/{user}/reset-password`
Auth: `ADMIN`

Request:
```json
{
  "password": "new-password"
}
```

### 6.4 Trips

#### `GET /api/trips`
Auth: `ADMIN`

Query:
- `limit`
- `status`
- `truck_id`
- `from`
- `to`

Response: paginated `TripResource` collection.

#### `GET /api/trips/{trip}`
Auth: `ADMIN`

#### `GET /api/trips/active`
Auth: `ADMIN`

Response: `OperatorTripResource` collection for active trips.

#### `GET /api/trips/history`
Auth: `ADMIN`

Response: paginated `TripResource` collection filtered to `COMPLETED` trips.

#### `GET /api/trips/search`
Auth: `ADMIN`

Query: same filters supported by the trip list endpoint.

Response: `TripResource` collection.

#### `GET /api/trips/stats`
Auth: `ADMIN`

Response `data`:
```json
{
  "total": 0,
  "active": 0,
  "completed": 0,
  "cancelled": 0
}
```

#### `GET /api/trips/{trip}/logs`
Auth: `ADMIN`

Response: `ScanLogResource` collection for the trip.

#### `GET /api/trips/{trip}/timeline`
Auth: `ADMIN`

Response `data`:
```json
[
  {
    "action": "STARTED",
    "location": "COMPANY",
    "scanned_at": "2026-06-09T10:00:00.000000Z",
    "user_name": "Admin User"
  }
]
```

#### `PATCH /api/trips/{trip}/cancel`
Auth: `ADMIN`

Request:
```json
{
  "notes": "Cancelled by admin"
}
```

Behavior:
- Sets `status` to `CANCELLED`.
- Clears the trip from the active pool by setting `is_active` to `null`.
- Sets `cancelled_at` and optional `notes`.

#### `PATCH /api/trips/{trip}/notes`
Auth: `ADMIN`

Request:
```json
{
  "notes": "Updated admin note"
}
```

#### `DELETE /api/trips/{trip}`
Auth: `ADMIN`

#### `GET /api/trips/calendar`
Auth: `ADMIN`

Query:
- `from` required, `Y-m-d`
- `to` required, `Y-m-d`
- `timezone` required, valid IANA timezone
- `day_start` optional, `HH:mm`, defaults to `07:00`
- `status`
- `truck_id`
- `registration_number`
- `driver_name`

Response `data`:
```json
{
  "data": [],
  "meta": {
    "from": "2026-06-01",
    "to": "2026-06-30",
    "day_start": "07:00",
    "timezone": "Africa/Casablanca"
  }
}
```

#### `GET /api/trips/by-day`
Auth: `ADMIN`

Query:
- `day` required, `Y-m-d`
- `timezone` required, valid IANA timezone
- `day_start` optional, `HH:mm`
- `all` optional boolean
- `limit` optional integer
- `status`
- `truck_id`
- `registration_number`
- `driver_name`

Response:
- If `all=false`, returns a paginated `TripResource` collection plus summary/window metadata.
- If `all=true`, returns the full collection plus summary/window metadata.

### 6.5 Maintenance Records

Route prefix: `/api/maintenance`

Auth: `ADMIN`

#### `GET /api/maintenance`
Returns all maintenance records with `truck` and `trip` relations loaded.

#### `POST /api/maintenance`
Request:
```json
{
  "truck_id": 1,
  "trip_id": 10,
  "type": "ENGINE_SERVICE",
  "description": "Oil change",
  "cost": 150,
  "date": "2026-06-09"
}
```

#### `GET /api/maintenance/{maintenance}`
Returns a single maintenance record.

#### `PUT /api/maintenance/{maintenance}`
Updates `type`, `description`, `cost`, and `date`.

#### `DELETE /api/maintenance/{maintenance}`
Deletes the record.

### 6.6 Scan logs
#### `GET /api/scan-logs`
Auth: `ADMIN`

Returns the full audit log listing.

### 6.7 Scan flow
#### `GET /api/scan-flow`
Auth: `ADMIN`

Returns the active scan flow configuration.

#### `PUT /api/scan-flow`
Auth: `ADMIN`

Updates the scan flow configuration.

### 6.8 Reports

#### `GET /api/reports/summary`
Auth: `ADMIN`

Query:
- `start_date`
- `end_date`

Envelope: legacy admin envelope

Response `data`:
```json
{
  "total_trips": 0,
  "active_trips": 0,
  "completed_trips": 0,
  "cancelled_trips": 0,
  "average_total_duration": 0,
  "trips": []
}
```

#### `GET /api/reports/truck/{truck}`
Auth: `ADMIN`

Query:
- `start_date`
- `end_date`

Envelope: legacy admin envelope

Response `data`:
```json
{
  "truck": {
    "id": 1,
    "registration_number": "TRK-001",
    "driver_name": "Driver Name",
    "qr_code": "SOMASTEEL-TRUCK-TRK-001",
    "is_active": true
  },
  "total_trips": 0,
  "completed_trips": 0,
  "cancelled_trips": 0,
  "trips": []
}
```

#### `GET /api/reports/durations`
Auth: `ADMIN`

Returns the same structure as summary metrics focused on duration analytics.

#### `GET /api/reports/delays`
Auth: `ADMIN`

Returns delay threshold, delayed count, and delayed trip list.

#### `GET /api/reports/export`
Auth: `ADMIN`

Query: same filters as summary/report endpoints.

Response: downloadable `.xlsx` file with French column headers.

## 7. Operator APIs

### 7.1 Scan truck QR
#### `POST /api/scan`
Auth: `COMPANY_OPERATOR` or `PORT_OPERATOR`
Throttle: `30 requests / minute`

Request:
```json
{
  "qr_code": "SOMASTEEL-TRUCK-TRK-001",
  "device_time": "2026-06-09T10:00:00Z",
  "device_id": "scanner-01"
}
```

Validation:
- `qr_code` is required.
- `device_time` is optional.
- `device_id` is optional.

Success response `data` from `ScanService::process`:
```json
{
  "status": "SUCCESS",
  "message": "Scan successful",
  "current_step": "STARTED",
  "next_expected_step": "ARRIVE",
  "is_locked": true,
  "trip_summary": {
    "trip_id": 10,
    "truck_id": 1,
    "status": "STARTED",
    "truck": {
      "id": 1,
      "registration_number": "TRK-001",
      "driver_name": "Driver Name"
    },
    "action": "START",
    "timestamps": {
      "started_at": "2026-06-09T10:00:00.000000Z",
      "arrived_port_at": null,
      "left_port_at": null,
      "completed_at": null
    }
  }
}
```

Error response:
```json
{
  "success": false,
  "data": null,
  "message": "Truck not found for provided QR code.",
  "errors": {
    "scan": "Truck not found for provided QR code."
  }
}
```

### 7.2 Operator last scans
#### `GET /api/operator/last-scans`
Auth: `COMPANY_OPERATOR` or `PORT_OPERATOR`

Query:
- `limit`

Response: `ScanLogResource` collection for the authenticated user.

## 8. Error Handling

### 8.1 Validation errors
Validation failures return HTTP 422 with the standard error envelope.

### 8.2 Domain errors
`ScanService` throws `ScanException` for invalid scan states, inactive trucks, or QR lookup failures.

### 8.3 Not found / authorization
Laravel default 404 and 403 responses apply where model binding or policy middleware fails.

## 9. Frontend Integration Notes

- Use the `success` flag on standard responses before reading `data`.
- Treat `status: "SUCCESS"` endpoints as legacy admin JSON responses, not the base controller envelope.
- For trip scans, rely on `next_expected_step` and `current_step` to drive the operator UI state.
- A cancelled trip releases the truck for a new active trip because the active trip lookup uses `is_active = true`.
- For paginated endpoints, consume Laravel pagination metadata returned inside `data`.