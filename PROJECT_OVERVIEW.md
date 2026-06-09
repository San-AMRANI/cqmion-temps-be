# Camion Temps (Somascan) - Project Overview

## 1. Project Description
**Camion Temps (Somascan)** is a backend tracking and monitoring system built for Somasteel to manage their truck fleet and trips between the company facility and the port. It relies on QR code scanning to track truck movements across different locations, ensuring a strict chronological lifecycle for every trip.

The project is built using **PHP 8** and **Laravel 12**.

## 2. Core Entities & Database Models

### 2.1 User
Represents the individuals interacting with the system, both operators scanning trucks and administrators monitoring the data.
- **Roles**: `ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`
- **Locations**: `COMPANY`, `PORT`
- **Key Fields**: `name`, `email`, `password`, `role`, `location`

### 2.2 Truck
Represents the vehicles managed by the company.
- **Key Fields**: `registration_number` (Unique), `driver_name`, `qr_code`, `is_active`
- **Relationship**: A truck can have many trips, but only **one active trip** at a time.

### 2.3 Trip
Represents the journey of a truck from the company to the port and back. A trip follows a strict lifecycle.
- **Statuses**:
  1. `STARTED`: Truck left the company.
  2. `ARRIVED_PORT`: Truck arrived at the port.
  3. `LEFT_PORT`: Truck finished at the port and is heading back.
  4. `COMPLETED`: Truck returned to the company.
- **Key Fields**: Timestamps for each stage (`started_at`, `arrived_port_at`, `left_port_at`, `completed_at`).

### 2.4 ScanLog
An immutable audit log for every scan performed by an operator on a truck's QR code.
- **Actions**: `START`, `ARRIVE`, `LEAVE`, `RETURN`
- **Key Fields**: `truck_id`, `trip_id`, `user_id`, `location`, `action`, `device_id`, `scanned_at`.

### 2.5 ScanFlow
A configuration or helper model that defines the active steps in the scanning process.

## 3. Architecture & APIs

The API is structured to serve two main audiences: **Administrators** and **Operators**. Authentication is handled via **Laravel Sanctum**.

### 3.1 Admin APIs
Administrators have access to a full suite of APIs for dashboarding and management (documented in `ADMIN_APIS.md`).
- **Truck Management**: CRUD operations for trucks, including activating/deactivating and generating QR codes.
- **User Management**: CRUD operations to create and manage system operators and other admins.
- **Trip Monitoring**:
  - Full trip lists (`/api/trips`)
  - Live active operations (`/api/trips/active`)
  - Historical data (`/api/trips/history`)
  - Calendar summary views (`/api/trips/calendar`, `/api/trips/by-day`)
- **Reporting & Analytics**:
  - Summary metrics, truck-specific reports, delay bottlenecks, and duration analytics.
  - Data export capabilities.
- **Audit Logs**: Full access to all operator scans (`/api/scan-logs`).

### 3.2 Operator Workflow (Scan Logic)
Operators use mobile devices or scanners to scan a truck's QR code when it passes a checkpoint.
- **Company Operator**: Scans trucks leaving (`START`) and returning (`RETURN`).
- **Port Operator**: Scans trucks arriving at the port (`ARRIVE`) and leaving the port (`LEAVE`).

The backend validates:
- Operator's location and role matches the scan action.
- The truck's active trip state (e.g., a truck cannot be scanned as `ARRIVE` if it hasn't been `STARTED`).

## 4. Key Workflows & Features

- **Strict State Machine**: Trips enforce a linear progression. You cannot skip steps (e.g., cannot go from `STARTED` straight to `COMPLETED`).
- **Concurrency**: Multiple trucks can be active concurrently, but a single truck can only be on one active trip at any moment.
- **Security**: The system uses role-based access control. An operator located at the `PORT` cannot execute a `COMPANY` scan.
- **Traceability**: Every transition generates a `ScanLog` tying the action to the specific operator, timestamp, and device.

## 5. Technology Stack
- **Framework**: Laravel 12 / PHP 8.x
- **Authentication**: Laravel Sanctum (Token-based)
- **Database**: Relational DB (Migrations & Eloquent ORM used)
- **Tooling**: Pest / PHPUnit for testing, Vite for frontend asset bundling (if any).

## 6. Getting Started
1. Run `composer install`
2. Create `.env` file (`cp .env.example .env`) and configure the database.
3. Generate application key: `php artisan key:generate`
4. Run migrations and seed the database: `php artisan migrate --seed`
   - *Note: The seeded default admin account is `admin@truck.local` with password `password`.*
5. Run the server: `php artisan serve`

## 7. Database Architecture

The relational database is carefully structured to maintain data integrity, enforcing the chronological rule that one truck can only be on a single active trip at any time.

### Key Tables & Constraints
1. **`users` table**:
   - Stores all application users.
   - Enums used for `role` (`ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`) and `location` (`COMPANY`, `PORT`).
2. **`trucks` table**:
   - `registration_number`: String (Unique constraint).
   - `qr_code`: String (Unique). 
   - `is_active`: Boolean. Allows for soft-disabling a truck.
3. **`trips` table**:
   - `truck_id`: Foreign key referencing `trucks(id)`.
   - `status`: Enum (`STARTED`, `ARRIVED_PORT`, `LEFT_PORT`, `COMPLETED`).
   - `is_active`: Boolean (Nullable). Defaults to `true` but becomes `null` or `false` once `COMPLETED`.
   - **Critical Constraint**: `UNIQUE(truck_id, is_active)`. This composite unique index guarantees at the database level that a single truck cannot have more than one active trip simultaneously.
   - Phase Timestamps: `started_at`, `arrived_port_at`, `left_port_at`, `completed_at` are recorded sequentially.
4. **`scan_logs` table**:
   - Foreign keys to `trucks`, `trips`, and `users`.
   - Represents the history of scans. 
   - Never modified or deleted once created (Audit Log).

## 8. Codebase Flow & Architecture

The application adheres to a clear separation of concerns, heavily leaning on Laravel's standard conventions.

### 8.1 Routing & Middleware
- **`routes/api.php`**: The entry point for all API endpoints.
- **Middleware**: 
  - `auth:sanctum` ensures requests are authenticated.
  - Role-based custom middleware ensures route protection. E.g., `Route::middleware('role:ADMIN')` protects admin features, while `role:COMPANY_OPERATOR,PORT_OPERATOR` protects the scanner logic.

### 8.2 Controllers
Controllers are kept "thin." Their primary responsibilities are:
- Validating the incoming HTTP request.
- Forwarding the validated data to a Service class.
- Wrapping the result in a standard API response (usually using `ApiResource` classes).
- *Example*: `ScanController` receives a `qr_code`, validates it, and delegates to `ScanService`. It uses a global `successResponse()` or `errorResponse()` method inherited from a base `Controller`.

### 8.3 Services (Business Logic)
This is where the complex rules live. 
- **`ScanService`**: The core of the app. It takes a QR code, identifies the truck, determines what the next logical state of the trip should be (e.g., if a truck is `ARRIVED_PORT`, the next scan must be `LEAVE`), checks if the scanning operator's role and location match the required state, inserts a `ScanLog`, and updates the `Trip`.
- **`TripService` / `TripCalendarService`**: Provide complex aggregations and queries for the dashboard, extracting logic out of the controllers.
- **`ScanLogService`**: Manages querying and retrieving audit logs.

### 8.4 Exception Handling
- The backend relies on custom exceptions (e.g., `ScanException`).
- If an operator tries an illegal scan (e.g., scanning a truck leaving the port when it hasn't even started its trip), the `ScanService` throws a `ScanException`. The Controller catches it and transforms it into a clean `4xx` HTTP response with a descriptive error message for the frontend.

### 8.5 API Resources
- Inside `app/Http/Resources`, classes like `TripResource`, `OperatorTripResource`, and `ScanLogResource` format the Eloquent models into consistent JSON payloads, calculating any virtual fields needed by the frontend (like calculating durations from timestamps).
