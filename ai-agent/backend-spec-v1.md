# 🚨 Backend Gaps & Missing Specifications — Truck Lifecycle System

---

## 🎯 Purpose

This document defines **all missing APIs, services, rules, and admin use cases** that must be implemented to make the backend:

* Production-ready
* Fully usable by frontend apps (Flutter + React)
* Safe against real-world issues (concurrency, duplication, invalid flows)

---

# ❗ 1. Critical Missing Technical Guarantees

## 1.1 Single Active Trip Enforcement (DB-Level)

### Problem

Currently enforced only in logic → unsafe.

### Required Fix

Add constraint:

**Option A (Preferred if supported):**

```sql
UNIQUE INDEX unique_active_trip (truck_id)
WHERE status != 'COMPLETED'
```

**Option B (Fallback):**
Add column:

```sql
is_active BOOLEAN
```

Constraint:

```sql
UNIQUE(truck_id, is_active)
```

---

## 1.2 Database Transactions (MANDATORY)

### Problem

Scan operation updates multiple tables → risk of inconsistency.

### Required

Wrap ALL scan operations:

```php
DB::transaction(function () {
    // trip update
    // scan log insert
});
```

---

## 1.3 Concurrency Control

### Problem

Multiple scans at same time → race conditions

### Required

Use row locking:

```php
Trip::where(...)->lockForUpdate()->first();
```

---

## 1.4 Idempotency Protection

### Problem

Duplicate scans (double tap / network retry)

### Required Solutions:

* Reject same action within X seconds
  OR
* Track last action per trip
  OR
* Add unique constraint:

```sql
UNIQUE(trip_id, action, scanned_at)
```

---

# 🧠 2. Missing / Incomplete Services

## 2.1 TripService (INCOMPLETE)

### Must Handle:

* Create trip
* Get active trip
* Update status
* Close trip
* Fetch trip history

### Required Methods:

```php
createTrip(int $truckId): Trip

getActiveTrip(int $truckId): ?Trip

updateStatus(Trip $trip, string $status): Trip

completeTrip(Trip $trip): Trip

getTrips(array $filters): Collection
```

---

## 2.2 TruckService (MISSING)

### Responsibilities:

* Truck CRUD
* QR generation
* Activation/deactivation

### Methods:

```php
createTruck(array $data): Truck

updateTruck(Truck $truck, array $data): Truck

deleteTruck(Truck $truck): void

findByQrCode(string $qr): ?Truck

generateQrCode(Truck $truck): string
```

---

## 2.3 ScanLogService (MISSING)

### Responsibilities:

* Log all scans
* Provide history

### Methods:

```php
logScan(Trip $trip, User $user, string $action, string $location): void

getLogsByTrip(int $tripId): Collection
```

---

## 2.4 ValidationService (MISSING)

### Responsibilities:

* Validate scan transitions
* Enforce business rules

### Methods:

```php
validateScan(User $user, ?Trip $trip, string $nextAction): void
```

---

## 2.5 ReportService (INCOMPLETE)

### Must Include:

#### Metrics:

* Average company → port time
* Average port duration
* Average port → company
* Total trips
* Active trips
* Delayed trips

### Methods:

```php
getSummary(): array

getTruckReport(int $truckId): array

getFilteredTrips(array $filters): Collection
```

---

## 2.6 EventService / Events (MISSING)

### Events to Emit:

* TripStarted
* ArrivedAtPort
* LeftPort
* TripCompleted

### Purpose:

* Real-time updates
* Notifications
* Future integrations

---

# 🔌 3. Missing / Incomplete APIs

---

## 3.1 Admin — Trucks Management

### Missing:

```http
POST   /api/trucks/{id}/generate-qr
PATCH  /api/trucks/{id}/activate
PATCH  /api/trucks/{id}/deactivate
```

---

## 3.2 Admin — Operators Management (CRITICAL MISSING)

### Required:

```http
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
```

### Features:

* Create operators
* Assign roles
* Assign location (COMPANY / PORT)

---

## 3.3 Admin — Trip Monitoring (INCOMPLETE)

### Missing:

```http
GET /api/trips/active
GET /api/trips/history
GET /api/trips/{id}/logs
```

---

## 3.4 Reports (INCOMPLETE)

### Missing:

```http
GET /api/reports/durations
GET /api/reports/delays
GET /api/reports/export
```

---

## 3.5 Scan API Improvements

### Missing fields:

```json
{
  "qr_code": "TRUCK-001",
  "device_time": "ISO8601",
  "device_id": "optional"
}
```

---

# 🧑‍💼 4. Missing Admin Use Cases

---

## 4.1 Truck Management

* Create truck
* Edit truck
* Delete truck
* Activate / deactivate truck
* Generate QR code
* View truck history

---

## 4.2 Operator Management

* Create operator
* Assign role
* Assign location
* Disable operator
* Reset password

---

## 4.3 Trip Monitoring

* View active trips
* View completed trips
* View trip timeline
* View scan logs per trip

---

## 4.4 Analytics Dashboard

* Average durations
* Bottleneck detection (port delays)
* Daily/weekly/monthly stats
* Top slowest trucks

---

## 4.5 Alerts (Missing Feature)

* Truck stuck in port too long
* Trip not completed
* Invalid scan attempts

---

# 📱 5. Missing Operator Features (Backend Support)

---

## 5.1 Scan Feedback

Response must include:

```json
{
  "status": "SUCCESS",
  "current_step": "ARRIVED_PORT",
  "next_expected_step": "LEFT_PORT",
  "trip_summary": {...}
}
```

---

## 5.2 Last Scan History

```http
GET /api/operator/last-scans
```

---

# 🧩 6. Missing Response Standardization

### Required Format:

```json
{
  "success": true,
  "data": {...},
  "message": "Optional",
  "errors": null
}
```

---

# 🔒 7. Security Improvements

* Rate limiting on `/scan`
* Token expiration handling
* Role-based policy enforcement (not just middleware)
* Input validation for all endpoints

---

# 📊 8. Filtering & Pagination (MISSING)

### Required on ALL list endpoints:

```http
GET /api/trips?page=1&limit=20&status=COMPLETED&from=2026-01-01&to=2026-01-31
```

---

# 🚀 9. Production Readiness Checklist

* [ ] DB constraints enforced
* [ ] Transactions implemented
* [ ] Concurrency handled
* [ ] Services properly separated
* [ ] API standardized
* [ ] Admin features complete
* [ ] Reports fully defined
* [ ] Logging + monitoring ready

---

# 🧠 FINAL NOTE

Current system = **Good prototype**

After applying this file = **Production-grade backend**

---

## ✅ END OF MISSING SPEC