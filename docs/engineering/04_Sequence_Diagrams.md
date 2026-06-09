# Sequence Diagrams

## 1. The Core Scan Feature Flow

This sequence diagram illustrates what happens when an operator scans a QR code via the `/api/scan` endpoint. The system acts as a state machine; the operator merely submits the QR code, and the backend resolves the correct contextual action based on the truck's current state and the operator's location/role.

```mermaid
sequenceDiagram
    actor Operator as Field Operator (Company/Port)
    participant App as Mobile/Web Client
    participant Ctrl as ScanController
    participant Svc as ScanService
    participant DB as Database (Trips & ScanLogs)

    Operator->>App: Scans QR Code on Truck
    App->>Ctrl: POST /api/scan {qr_code, device_id}
    
    Ctrl->>Svc: process(qr_code, authenticated_user)
    
    Svc->>DB: Query Truck by qr_code
    DB-->>Svc: Return Truck (or 404 Not Found)
    
    Svc->>Svc: Check if Truck is_active (or 422 Inactive)
    
    Svc->>DB: Query Active Trip for Truck (lock for update)
    DB-->>Svc: Return Active Trip (or null if none)
    
    Svc->>Svc: Resolve Next Step & Action based on ScanFlow
    Note over Svc: e.g. If no active trip -> Action: START<br/>If trip STARTED -> Action: ARRIVE
    
    Svc->>Svc: Validate Operator Role & Location
    Note over Svc: Ensures COMPANY_OPERATOR can only START/RETURN<br/>and PORT_OPERATOR can only ARRIVE/LEAVE
    
    alt If Action is START
        Svc->>DB: Create new Trip (status: STARTED)
    else If Action is ARRIVE, LEAVE, or RETURN
        Svc->>DB: Update existing Trip status
    end
    
    Svc->>DB: Create ScanLog record (truck_id, trip_id, user_id, action)
    
    Svc->>Svc: Dispatch Event (e.g., TripStarted, ArrivedAtPort)
    
    Svc-->>Ctrl: Return Trip Summary & Next Expected Step
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
    
    Ctrl->>Svc: cancelTrip(trip, notes)
    
    Svc->>DB: Update Trip (status: CANCELLED, is_active: null, notes: notes)
    
    Svc-->>Ctrl: Return updated Trip object
    Ctrl-->>App: 200 OK (JSON Response)
    App-->>Admin: Show Trip as Cancelled
```
