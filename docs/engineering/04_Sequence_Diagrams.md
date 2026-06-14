# Sequence Diagrams

## 1. The Core Scan Feature Flow

This sequence diagram illustrates what happens when an operator scans a QR code via the `/api/scan` endpoint. The system acts as a state machine; the operator merely submits the QR code, and the backend resolves the correct contextual action based on the truck's current state and the operator's location/role.

```mermaid
sequenceDiagram
    actor Operator as Field Operator (Company/Port)
    participant App as Mobile/Web Client
    participant Ctrl as ScanController
    participant Svc as ScanService
    participant Val as ValidationService
    participant Flow as ScanFlowService
    participant Trip as TripService
    participant Log as ScanLogService
    participant DB as Database (Trucks, Trips, ScanLogs)

    Operator->>App: Scans QR Code on Truck
    App->>Ctrl: POST /api/scan {qr_code, device_id}
    Note over Ctrl: Validates: qr_code (required, string, max 2048),<br/>device_time (nullable, date), device_id (nullable, string)
    
    Ctrl->>Svc: process(qr_code, authenticated_user, device_id)
    Note over Svc: BEGIN DB TRANSACTION
    
    Svc->>Svc: extractQrCandidates(rawScanValue)
    Note over Svc: 1. Raw value (uppercased, trimmed)<br/>2. Parse URL: extract ?code= or ?qr_code=<br/>3. Parse URL: extract last path segment<br/>4. Regex: extract SOMASTEEL-* pattern<br/>5. De-duplicate candidates
    
    Svc->>DB: Query Truck by qr_code IN candidates (LOCK FOR UPDATE)
    DB-->>Svc: Return Truck (or null)
    
    alt Truck not found
        Svc-->>Ctrl: ScanException(404): "Truck not found"
    end
    
    Svc->>Svc: Check if Truck is_active
    alt Truck inactive
        Svc-->>Ctrl: ScanException(422): "Truck is inactive"
    end
    
    Svc->>DB: Query Active Trip for Truck (is_active=true, LOCK FOR UPDATE)
    DB-->>Svc: Return Active Trip (or null if none)
    
    Svc->>Flow: resolveNextStep(activeTrip)
    Note over Flow: Load active ScanFlow from DB (cached in memory).<br/>If no trip → return steps[0] (e.g. STARTED).<br/>If trip completed → ScanException(409).<br/>Find current index → return steps[index+1].
    Flow-->>Svc: nextStatus (e.g. "STARTED", "ARRIVED_PORT", etc.)
    
    Svc->>Flow: resolveAction(nextStatus)
    Note over Flow: Map status to action via STATUS_TO_ACTION:<br/>STARTED→START, ARRIVED_PORT→ARRIVE,<br/>LEFT_PORT→LEAVE, COMPLETED→RETURN
    Flow-->>Svc: nextAction (e.g. "START")
    
    Svc->>Val: validateScan(operator, activeTrip, nextAction)
    Note over Val: 1. Operator must be COMPANY_OPERATOR or PORT_OPERATOR<br/>2. START/RETURN → requires COMPANY_OPERATOR @ COMPANY<br/>3. ARRIVE/LEAVE → requires PORT_OPERATOR @ PORT
    alt Role/location mismatch
        Val-->>Svc: ScanException(403): "Invalid scan sequence"
    end
    
    alt No active trip (Action is START)
        Svc->>Trip: createTrip(truck_id, nextStatus)
        Trip->>DB: INSERT Trip (status, started_at, is_active=true)
        DB-->>Trip: New Trip
    else Active trip exists (ARRIVE, LEAVE, or RETURN)
        Svc->>Trip: updateStatus(activeTrip, nextStatus)
        Trip->>DB: UPDATE Trip (status, timestamp for status, is_active=NULL if COMPLETED)
        DB-->>Trip: Updated Trip
    end
    
    Svc->>Log: logScan(trip, operator, action, location, deviceId)
    Log->>DB: INSERT ScanLog (truck_id, trip_id, user_id, location, action, device_id, scanned_at, created_at)
    
    Svc->>Svc: dispatchEvent(trip, nextStatus)
    Note over Svc: STARTED → TripStarted event<br/>ARRIVED_PORT → ArrivedAtPort event<br/>LEFT_PORT → LeftPort event<br/>COMPLETED → TripCompleted event
    
    Note over Svc: COMMIT TRANSACTION
    
    Svc-->>Ctrl: Return {status, message, current_step, next_expected_step, is_locked, trip_summary}
    Ctrl-->>App: 200 OK (JSON Response)
    App-->>Operator: Display Success & Current Trip State
```

## 2. Trip Cancellation Flow

Sometimes, a trip cannot be completed (e.g., due to a truck breakdown or accident). This diagram shows the admin intervention process to clear the truck's state.

```mermaid
sequenceDiagram
    actor Admin
    participant App as Admin Dashboard
    participant Ctrl as TripController
    participant Svc as TripService
    participant DB as Database

    Admin->>App: Clicks "Cancel Trip" and adds notes
    App->>Ctrl: PATCH /api/trips/{id}/cancel {notes}
    Note over Ctrl: Validates: notes (nullable, string)
    
    Ctrl->>Svc: cancelTrip(trip, notes)
    
    Svc->>DB: UPDATE Trip (status: CANCELLED, is_active: NULL, cancelled_at: now(), notes: notes)
    Note over DB: Setting is_active = NULL releases the<br/>unique constraint, allowing a new active trip
    
    Svc-->>Ctrl: Return updated Trip object
    Ctrl-->>App: 200 OK (TripResource JSON)
    App-->>Admin: Show Trip as Cancelled
```

## 3. Dashboard & Reporting Flow

Admins access the dashboard and reporting system for real-time overview and historical analytics.

```mermaid
sequenceDiagram
    actor Admin
    participant App as Admin Dashboard
    participant DCtrl as DashboardController
    participant DSvc as DashboardService
    participant RCtrl as ReportController
    participant RSvc as ReportService
    participant ESvc as ExcelExportService
    participant DB as Database

    Admin->>App: Opens Dashboard
    App->>DCtrl: GET /api/dashboard
    DCtrl->>DSvc: getOverviewStats()
    DSvc->>DB: COUNT trucks, active trucks, trips, active trips, users, trips today
    DB-->>DSvc: Counts
    DSvc-->>DCtrl: {total_trucks, active_trucks, total_trips, active_trips, total_users, trips_today}
    DCtrl-->>App: 200 OK (JSON)
    
    Admin->>App: Views Report (with date filters)
    App->>RCtrl: GET /api/reports/summary?start_date=...&end_date=...
    RCtrl->>RSvc: generateGeneralReport(filters)
    RSvc->>DB: Query trips with date filters, load truck relations
    DB-->>RSvc: Trip collection
    RSvc->>RSvc: Calculate durations for completed trips, compute average
    RSvc-->>RCtrl: {total_trips, active, completed, cancelled, avg_duration, trips[]}
    RCtrl-->>App: 200 OK (JSON)
    
    Admin->>App: Clicks "Export Excel"
    App->>RCtrl: GET /api/reports/export?start_date=...&end_date=...
    RCtrl->>RSvc: exportReportToExcel(filters)
    RSvc->>DB: Query filtered trips
    RSvc->>ESvc: exportTrips(trips)
    ESvc->>ESvc: Create Spreadsheet with French headers
    ESvc->>ESvc: Populate rows with trip data & computed durations
    ESvc->>ESvc: Save .xlsx to storage/app/public/
    ESvc-->>RSvc: filePath
    RSvc-->>RCtrl: filePath
    RCtrl-->>App: Binary file download (auto-deleted after send)
```

## 4. Calendar View Flow

The admin calendar feature provides a multi-day overview with drill-down capability.

```mermaid
sequenceDiagram
    actor Admin
    participant App as Admin Dashboard
    participant Ctrl as TripController
    participant CalSvc as TripCalendarService
    participant DB as Database

    Admin->>App: Opens Calendar View (selects date range)
    App->>Ctrl: GET /api/trips/calendar?from=2023-10-01&to=2023-10-31&timezone=Africa/Casablanca&day_start=07:00
    
    Ctrl->>Ctrl: Parse & validate from, to, timezone, day_start
    Ctrl->>Ctrl: extractCalendarFilters(request)
    Note over Ctrl: Extracts: status, truck_id,<br/>registration_number, driver_name
    
    Ctrl->>CalSvc: getCalendarSummary(filters, from, to, dayStart)
    
    loop For each day in range
        CalSvc->>CalSvc: buildDayWindow(day, "07:00")
        Note over CalSvc: start_local = day @ 07:00<br/>end_local = next day @ 06:59:59<br/>Convert to UTC for DB queries
        CalSvc->>DB: SELECT status, COUNT(*) WHERE created_at BETWEEN start_utc AND end_utc GROUP BY status
        DB-->>CalSvc: Status counts
    end
    
    CalSvc-->>Ctrl: Array of day summaries [{day, start_at, end_at, total, active, completed, by_status}]
    
    loop For each day (embed trips)
        Ctrl->>CalSvc: getTripsForDay(filters, dayDate, dayStart)
        CalSvc->>DB: SELECT trips WHERE created_at BETWEEN window, with truck & latestScan
        DB-->>CalSvc: Trip collection
    end
    
    Ctrl-->>App: 200 OK {data: [{day, ..., trips: [...]}], meta: {from, to, day_start, timezone}}
    App-->>Admin: Render Calendar Grid with Trip Data
    
    Admin->>App: Clicks on a specific day
    App->>Ctrl: GET /api/trips/by-day?day=2023-10-15&timezone=Africa/Casablanca&day_start=07:00
    Ctrl->>CalSvc: getTripsByDay(filters, day, dayStart, limit)
    CalSvc->>DB: SELECT trips (paginated) + summary counts
    DB-->>CalSvc: Paginated results + summary
    CalSvc-->>Ctrl: {paginator, summary, window}
    Ctrl-->>App: 200 OK (Paginated TripResource + summary + window metadata)
```

## 5. Admin Scan Log Auditing Flow

```mermaid
sequenceDiagram
    actor Admin
    participant App as Admin Dashboard
    participant Ctrl as AdminScanLogController
    participant Svc as ScanLogService
    participant DB as Database

    Admin->>App: Opens Scan Logs with filters
    App->>Ctrl: GET /api/scan-logs?action=START&from=2023-10-01&limit=20
    
    Ctrl->>Ctrl: Validate all filter params
    Note over Ctrl: Validates: limit, page, user_id, truck_id,<br/>trip_id, role, location, action,<br/>registration_number, search, from, to
    
    Ctrl->>Svc: getAdminLogs(validated_filters, limit)
    Svc->>Svc: buildAdminLogsQuery(filters)
    Note over Svc: Builds query with conditionals:<br/>- where user_id, truck_id, trip_id, action, location<br/>- whereHas user (role filter)<br/>- whereHas truck (registration_number LIKE)<br/>- whereDate scanned_at (from, to)<br/>- Complex search (device_id, user name/email,<br/>  truck registration/driver/qr_code)
    Svc->>DB: Paginated query with eager loading (user, truck, trip)
    DB-->>Svc: Paginated results
    
    Ctrl->>Svc: getAdminLogsSummary(validated_filters)
    Svc->>DB: COUNT total, COUNT DISTINCT user_id, GROUP BY action, GROUP BY location
    DB-->>Svc: Summary stats
    
    Ctrl-->>App: 200 OK {items, pagination, summary, applied_filters}
    App-->>Admin: Render Scan Log Table with Summary Stats
```

## 6. Truck Registration and QR Code Flow

```mermaid
sequenceDiagram
    actor Admin
    participant App as Admin Dashboard
    participant Ctrl as TruckController
    participant Svc as TruckService
    participant DB as Database

    Admin->>App: Fills truck registration form
    App->>Ctrl: POST /api/trucks {registration_number: "AB-123-CD", driver_name: "Ahmed"}
    
    Ctrl->>Ctrl: Validate: registration_number (required, unique), driver_name (required)
    Ctrl->>Svc: createTruck(validated_data)
    
    Svc->>Svc: buildQrCodeFromRegistration("AB-123-CD")
    Note over Svc: 1. Uppercase: "AB-123-CD"<br/>2. Replace non-alphanumeric: "AB-123-CD"<br/>3. Prefix: "SOMASTEEL-TRUCK-AB-123-CD"
    
    Svc->>DB: INSERT Truck (registration_number, driver_name, qr_code, is_active=true)
    DB-->>Svc: New Truck
    
    Svc-->>Ctrl: Truck object
    Ctrl-->>App: 201 Created (Truck JSON)
    App-->>Admin: Show truck with QR code ready for printing
    
    Note over Admin: Later, admin wants to regenerate QR
    Admin->>App: Clicks "Regenerate QR Code"
    App->>Ctrl: POST /api/trucks/{id}/generate-qr
    Ctrl->>Svc: generateQrCode(truck)
    Svc->>Svc: buildQrCodeFromRegistration(truck.registration_number)
    Svc->>DB: UPDATE truck SET qr_code = new_qr
    Svc-->>Ctrl: new QR code string
    Ctrl-->>App: 200 OK {id, qr_code}
```

## 7. User Authentication Flow

```mermaid
sequenceDiagram
    actor User
    participant App as Client Application
    participant Ctrl as AuthController
    participant Auth as Laravel Auth
    participant DB as Database (Users + Tokens)

    User->>App: Enters email & password
    App->>Ctrl: POST /api/login {email, password, device_name?}
    
    Ctrl->>Ctrl: Validate: email (required), password (required), device_name (optional)
    Ctrl->>Auth: Auth::attempt({email, password})
    Auth->>DB: Query user by email, verify password hash
    
    alt Invalid credentials
        DB-->>Auth: No match
        Auth-->>Ctrl: false
        Ctrl-->>App: 422 Validation Error: "The provided credentials are incorrect."
    else Valid credentials
        DB-->>Auth: User found
        Auth-->>Ctrl: true
        Ctrl->>DB: createToken(device_name || "api-token")
        DB-->>Ctrl: Plain text token
        Ctrl-->>App: 200 OK {token, user, expires_at: null}
    end
    
    Note over User: Later, user logs out
    User->>App: Clicks Logout
    App->>Ctrl: POST /api/logout (Authorization: Bearer {token})
    Ctrl->>DB: Delete current access token
    Ctrl-->>App: 200 OK {data: null, message: "Logout successful"}
```
