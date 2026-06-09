# Architecture and Tech Stack

## Tech Stack
- **Framework**: Laravel 11/12 (PHP 8+)
- **Database**: MySQL / MariaDB (managed via Eloquent ORM)
- **Authentication**: Laravel Sanctum (Token-based API authentication)
- **Routing**: API-centric routes (`routes/api.php`)

## Architectural Pattern: Service-Oriented MVC
The system uses a refined Model-View-Controller (MVC) architecture, specifically the **Controller-Service-Model** pattern. This is a best practice in modern Laravel API development designed to keep controllers thin, business logic isolated and reusable, and database interactions strictly managed.

### Layer Breakdown

1. **Controllers (`app/Http/Controllers`)**
   - **Responsibility**: HTTP routing, request validation, and HTTP response formatting.
   - **Behavior**: They receive the request, validate inputs (either inline or via FormRequests), pass the sanitized data to the appropriate Service class, and return a standardized JSON response using the base `Controller` methods (`successResponse` and `errorResponse`).
   - **Example**: `ScanController` validates the `qr_code` payload and delegates the complex state logic to `ScanService`.

2. **Services (`app/Services`)**
   - **Responsibility**: Core business logic, transaction management, and complex data manipulation.
   - **Behavior**: Services encapsulate the strict rules of the application. For instance, `ScanService` handles the state machine of a trip, determining if a truck is active, computing the next logical step based on the `ScanFlow`, verifying operator permissions, updating the database within a transaction, logging the scan, and dispatching events.
   - **Examples**: `TripService`, `TruckService`, `ReportService`, `DashboardService`.

3. **Models (`app/Models`)**
   - **Responsibility**: Data representation, database interaction (ORM), and defining relationships.
   - **Behavior**: Models like `User`, `Truck`, `Trip`, and `ScanLog` map directly to database tables. They encapsulate relationship definitions (e.g., a Truck `hasMany` Trips) and helper methods (e.g. `$trip->isDelayed()`).

4. **Resources (`app/Http/Resources`)**
   - **Responsibility**: Data transformation and presentation layer.
   - **Behavior**: Instead of returning raw Eloquent models (which might expose hidden fields or have a database-centric structure), Resources map the model data into the exact JSON structure expected by the frontend.
   - **Example**: `TripResource` adds computed fields like `current_location`, `next_expected_step`, and calculated leg `durations`.

5. **Middleware (`app/Http/Middleware`)**
   - **Responsibility**: Request filtering and authorization.
   - **Behavior**: The `EnsureUserRole` middleware checks if the authenticated user has the required roles (e.g., `ADMIN`, `COMPANY_OPERATOR`, `PORT_OPERATOR`) before allowing access to specific routes.

6. **Events (`app/Events`)**
   - **Responsibility**: Decoupling side-effects from core logic.
   - **Behavior**: When key actions occur (e.g., `TripStarted`, `ArrivedAtPort`), events are dispatched. This architecture allows for future asynchronous listeners (like sending push notifications or webhooks) without blocking the main request cycle.
