# 🚛 Truck Lifecycle Tracking System — Backend Specification (Laravel API)

---

## 🧭 1. Overview

This document defines the **complete backend specification** for a Truck Lifecycle Tracking System.

The system tracks truck movements between:

* **Company**
* **Port**

Using **QR code scanning**, operators log each step of a truck’s journey, enabling:

* Real-time tracking
* Duration analysis
* Historical reporting

---

## 🎯 2. Core Business Rules

1. A truck can have **ONLY ONE active trip at a time**
2. A trip must follow a strict lifecycle:

   ```
   STARTED → ARRIVED_PORT → LEFT_PORT → COMPLETED
   ```
3. Each state transition is triggered by a **QR scan**
4. Scans must respect:

   * Correct **order**
   * Correct **operator location**
5. All scans must be **logged (audit trail)**

---

## 🧑‍🤝‍🧑 3. User Roles

### 3.1 ADMIN

* Full system access
* Manage trucks
* Generate QR codes
* View reports and analytics

### 3.2 OPERATOR

Two types:

* `COMPANY_OPERATOR`
* `PORT_OPERATOR`

Permissions:

* Scan QR codes
* View limited truck info

---

## 🔐 4. Authentication System

### Recommended:

* **Laravel Sanctum (token-based authentication)**

### Features:

* Mobile-friendly
* Token per device/session
* Role-based access control (RBAC)

---

## 🧱 5. Database Schema

### 5.1 `users`

```sql
id
name
email
password
role (ADMIN | COMPANY_OPERATOR | PORT_OPERATOR)
location (COMPANY | PORT) NULLABLE
created_at
updated_at
```

---

### 5.2 `trucks`

```sql
id
registration_number
qr_code (UNIQUE)
is_active (BOOLEAN)
created_at
updated_at
```

---

### 5.3 `trips`

```sql
id
truck_id (FK)
status (STARTED | ARRIVED_PORT | LEFT_PORT | COMPLETED)

started_at
arrived_port_at
left_port_at
completed_at

created_at
updated_at
```

---

### 5.4 `scan_logs`

```sql
id
truck_id (FK)
trip_id (FK)
user_id (FK)

location (COMPANY | PORT)
action (START | ARRIVE | LEAVE | RETURN)

scanned_at

created_at
```

---

## 🔁 6. Core Business Logic — Scan Engine

### Central Service:

`ScanService`

---

### Input:

```json
{
  "qr_code": "TRUCK-001"
}
```

---

### Process Flow:

#### Step 1: Identify Truck

* Find truck by `qr_code`
* If not found → ERROR

---

#### Step 2: Get Active Trip

```sql
SELECT * FROM trips 
WHERE truck_id = ? 
AND status != COMPLETED
LIMIT 1
```

---

### CASE A: No Active Trip

#### Conditions:

* Operator must be `COMPANY_OPERATOR`

#### Actions:

* Create new trip:

  * `status = STARTED`
  * `started_at = now()`

* Log scan:

  * action = START

---

### CASE B: status = STARTED

#### Conditions:

* Operator must be `PORT_OPERATOR`

#### Actions:

* Update trip:

  * `arrived_port_at = now()`
  * `status = ARRIVED_PORT`

* Log scan:

  * action = ARRIVE

---

### CASE C: status = ARRIVED_PORT

#### Conditions:

* Operator must be `PORT_OPERATOR`

#### Actions:

* Update trip:

  * `left_port_at = now()`
  * `status = LEFT_PORT`

* Log scan:

  * action = LEAVE

---

### CASE D: status = LEFT_PORT

#### Conditions:

* Operator must be `COMPANY_OPERATOR`

#### Actions:

* Update trip:

  * `completed_at = now()`
  * `status = COMPLETED`

* Log scan:

  * action = RETURN

---

### CASE E: INVALID

Return:

```json
{
  "error": "Invalid scan sequence or unauthorized location"
}
```

---

## ⏱️ 7. Time Calculations (Computed Fields)

DO NOT store durations.

### Derived fields:

```php
company_to_port = arrived_port_at - started_at
port_duration = left_port_at - arrived_port_at
port_to_company = completed_at - left_port_at
total_duration = completed_at - started_at
```

---

## 📡 8. API Endpoints

### 8.1 Authentication

```
POST   /api/login
POST   /api/logout
GET    /api/me
```

---

### 8.2 Trucks (Admin)

```
GET    /api/trucks
POST   /api/trucks
GET    /api/trucks/{id}
PUT    /api/trucks/{id}
DELETE /api/trucks/{id}
```

---

### 8.3 Trips

```
GET    /api/trips
GET    /api/trips/{id}
```

Filters:

* by status
* by date
* by truck

---

### 8.4 Scan Endpoint (Operator)

```
POST /api/scan
```

Body:

```json
{
  "qr_code": "TRUCK-001"
}
```

Response:

```json
{
  "message": "Scan successful",
  "trip_status": "ARRIVED_PORT",
  "timestamps": {...}
}
```

---

### 8.5 Reports (Admin)

```
GET /api/reports/summary
GET /api/reports/truck/{id}
```

---

## 🧠 9. Backend Architecture (Laravel)

### Folder Structure:

```
app/
 ├── Models/
 │     ├── User.php
 │     ├── Truck.php
 │     ├── Trip.php
 │     ├── ScanLog.php
 │
 ├── Services/
 │     └── ScanService.php   ← CORE LOGIC
 │
 ├── Http/
 │     ├── Controllers/
 │     │     ├── AuthController.php
 │     │     ├── TruckController.php
 │     │     ├── TripController.php
 │     │     └── ScanController.php
 │
 ├── Policies/
 ├── Requests/
 ├── Resources/
```

---

## 🔧 10. Key Services

### 10.1 ScanService

Responsibilities:

* Validate scan
* Manage state transitions
* Enforce business rules
* Log scan
* Return structured response

---

### 10.2 TripService

* Fetch active trips
* Calculate durations

---

### 10.3 ReportService

* Aggregations
* Metrics
* Filters

---

## 🔐 11. Authorization Rules

* Admin:

  * Full access

* Operator:

  * Can only access:

    * `/scan`
    * limited truck info

* Enforce via:

  * Laravel Policies
  * Middleware

---

## ⚠️ 12. Edge Cases

* Duplicate scan → reject
* Wrong operator location → reject
* Truck already in active trip → enforce lifecycle
* Missing timestamps → prevent invalid transitions



## 🚀 13. Deliverables for AI Agent

The AI agent must generate:

1. Laravel project
2. Database migrations
3. Models with relationships
4. Controllers
5. Services (ScanService is critical)
6. API routes
7. Authentication (Sanctum)
8. Validation logic
9. Error handling
10. Seeders (admin + sample trucks)

---

## ✅ 14. Success Criteria

* Full lifecycle respected
* No invalid transitions possible
* Accurate timestamps
* Clean API responses
* Scalable architecture

---

## 🧩 END OF BACKEND SPECIFICATION
