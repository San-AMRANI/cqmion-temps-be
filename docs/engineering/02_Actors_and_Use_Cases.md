# Actors and Use Cases

## System Actors

1. **ADMIN**
   - **Role**: System administrator who oversees the entire operation.
   - **Location**: Anywhere (usually back-office web application).
   - **Capabilities**: 
     - Manage Users (Create, update, deactivate field operators).
     - Manage Trucks (Register new trucks, generate QR codes, activate/deactivate trucks).
     - Monitor Trips (View live active trips, historical trips, cancel trips, append notes).
     - View all global Scan Logs.
     - Generate and export analytical reports (Excel exports, duration and delay metrics).
     - Configure the dynamic Scan Flow steps.

2. **COMPANY_OPERATOR**
   - **Role**: Field operator stationed at the company headquarters or depot.
   - **Location**: `COMPANY`
   - **Capabilities**:
     - Scan truck QR codes to **START** a trip (dispatching the truck to the port).
     - Scan truck QR codes to **RETURN** a trip (recording the truck's return to the company, thus completing the trip).
     - View their own recent scan history.

3. **PORT_OPERATOR**
   - **Role**: Field operator stationed at the port checkpoint.
   - **Location**: `PORT`
   - **Capabilities**:
     - Scan truck QR codes to record **ARRIVE** (truck arrived at the port).
     - Scan truck QR codes to record **LEAVE** (truck departed the port).
     - View their own recent scan history.

---

## Core Use Cases

### 1. The Trip Lifecycle (The "Scan Flow")
This is the central engine of the application, acting as a state machine that tracks a truck's journey from the company to the port and back.

- **Pre-condition**: An active truck exists with a generated QR code.
- **Step 1 (Start)**: A `COMPANY_OPERATOR` scans the truck's QR code. The system verifies the truck is not currently on a trip, creates a new `Trip` record with status `STARTED`, and logs a `START` scan action.
- **Step 2 (Arrival)**: The truck arrives at the port. A `PORT_OPERATOR` scans the QR code. The system finds the active trip, updates its status to `ARRIVED_PORT`, and logs an `ARRIVE` scan action.
- **Step 3 (Departure)**: The truck finishes loading/unloading at the port and is ready to leave. The `PORT_OPERATOR` scans the QR code again. The system updates the trip status to `LEFT_PORT` and logs a `LEAVE` scan action.
- **Step 4 (Completion)**: The truck arrives back at the company. The `COMPANY_OPERATOR` scans the QR code. The system updates the trip status to `COMPLETED`, marks the trip as inactive (allowing the truck to start a new trip later), and logs a `RETURN` scan action.

### 2. Exception Handling: Trip Cancellation
- If a truck breaks down or a trip is aborted mid-journey, an `ADMIN` can intervene via the dashboard. They can cancel the active trip (status becomes `CANCELLED`), and append explanatory notes. This resets the truck's state, freeing it to be assigned a new trip when repaired or ready.

### 3. QR Code Generation and Management
- An `ADMIN` registers a truck in the system with its license plate (`registration_number`).
- The system generates a unique, deterministic QR Code string (e.g., `SOMASTEEL-TRUCK-XYZ`).
- This QR code is physically attached or printed for the truck so operators can scan it using their mobile devices.

### 4. Reporting and Analytics
- `ADMIN` users can generate reports based on historical trip data.
- The system automatically calculates durations for different legs of the journey: `company_to_port`, `port_duration`, `port_to_company`, and `total_duration`.
- The system logic flags delayed trips (e.g., trips taking longer than 240 minutes overall or stuck in a state) for managerial review.
