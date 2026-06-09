# Database Schema Specification

This document contains the comprehensive database schema specification and relationship diagram for the backend system.

## 1. Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    users {
        bigint id PK
        varchar name
        varchar email "Unique"
        varchar password
        enum role "ADMIN, COMPANY_OPERATOR, PORT_OPERATOR"
        enum location "COMPANY, PORT (Nullable)"
        varchar remember_token "Nullable"
        timestamp created_at "Nullable"
        timestamp updated_at "Nullable"
    }

    trucks {
        bigint id PK
        varchar registration_number "Unique"
        varchar driver_name "Nullable"
        varchar qr_code "Unique"
        boolean is_active "Default: true"
        timestamp created_at "Nullable"
        timestamp updated_at "Nullable"
    }

    trips {
        bigint id PK
        bigint truck_id FK "Constrained, Cascade On Delete"
        enum status "STARTED, ARRIVED_PORT, LEFT_PORT, COMPLETED, CANCELLED"
        boolean is_active "Nullable, Default: 1"
        timestamp started_at "Nullable"
        timestamp arrived_port_at "Nullable"
        timestamp left_port_at "Nullable"
        timestamp completed_at "Nullable"
        timestamp cancelled_at "Nullable"
        text notes "Nullable"
        timestamp created_at "Nullable, Indexed"
        timestamp updated_at "Nullable"
    }

    scan_logs {
        bigint id PK
        bigint truck_id FK "Constrained, Cascade On Delete"
        bigint trip_id FK "Constrained, Cascade On Delete"
        bigint user_id FK "Constrained, Cascade On Delete"
        enum location "COMPANY, PORT"
        enum action "START, ARRIVE, LEAVE, RETURN"
        varchar device_id "Nullable"
        timestamp scanned_at
        timestamp created_at "Default: CURRENT_TIMESTAMP"
    }

    scan_flows {
        bigint id PK
        json steps
        boolean is_active "Default: true, Indexed"
        timestamp created_at "Nullable"
        timestamp updated_at "Nullable"
    }

    users ||--o{ scan_logs : "performs"
    trucks ||--o{ trips : "undergoes"
    trucks ||--o{ scan_logs : "associated_with"
    trips ||--o{ scan_logs : "logs_scans_for"
```

## 2. Relationships & Cardinalities Explanation

| Source Table | Destination Table | Cardinality | Explanation |
| :--- | :--- | :--- | :--- |
| `users` | `scan_logs` | `1 : 0..*` (One to Zero or Many) | A user (e.g. port or company operator) can perform multiple barcode scans over time. A scan log must be executed by exactly one user. |
| `trucks` | `trips` | `1 : 0..*` (One to Zero or Many) | A truck can perform many trips over its lifetime. Each trip is assigned to exactly one truck. |
| `trucks` | `scan_logs` | `1 : 0..*` (One to Zero or Many) | A truck gets scanned at various checkpoints, accumulating scan logs. |
| `trips` | `scan_logs` | `1 : 0..*` (One to Zero or Many) | A trip has a workflow of scan events (e.g. START, ARRIVE, LEAVE). Each scan log maps back to the specific trip context. |

## 3. Database Integrity & Constraints

1. **Trip Active Uniqueness per Truck (`trips`)**:
   - Index name: `trips_truck_id_is_active_unique`
   - Columns: `(truck_id, is_active)`
   - **Rationale**: Ensures a single truck can only have **at most one active trip** at any given time (`is_active` = `1`). Because MySQL allows multiple `NULL` values in a unique index, completed or cancelled trips have `is_active` updated to `NULL` (via DB migrations), allowing new active trips to be started.

2. **Scan Log Workflow Uniqueness (`scan_logs`)**:
   - Index name: Composite unique constraint on `(trip_id, action, scanned_at)`
   - **Rationale**: Prevents duplicate scan event entries for the same trip, action, and timestamp.
