# Driver Name Field Change Specification

## 1. Overview

A new truck field was added: `driver_name`.

This field is now treated with the same business priority as `registration_number`.

Backend impact:
- persisted in database (`trucks.driver_name`)
- validated as required on truck creation
- validated as optional field on truck update when provided
- returned in key admin and operator payloads

## 2. API Contract Changes

## 2.1 Create Truck
Endpoint:
- `POST /api/trucks`

New required body field:
- `driver_name` (string, max 255)

Request example:
```json
{
  "registration_number": "REG-300",
  "driver_name": "Yassine El Idrissi",
  "qr_code": "SOMASTEEL-TRUCK-REG-300",
  "is_active": true
}
```

Validation behavior:
- Missing `driver_name` => `422`
- Duplicate `driver_name` is allowed

## 2.2 Update Truck
Endpoint:
- `PUT/PATCH /api/trucks/{truck}`

Updated body support:
- `driver_name` can be updated
- value can repeat across trucks

## 2.3 Truck Response Shape Updates
The following payloads now include `driver_name` in truck data:
- `GET /api/trucks`
- `GET /api/trucks/{truck}`
- `GET /api/trucks/{truck}/basic`
- `GET /api/trips`
- `GET /api/trips/{trip}`
- `GET /api/trips/active`
- `GET /api/trips/history`
- `GET /api/trips/{trip}/logs`
- `POST /api/scan` response (`trip_summary.truck.driver_name`)
- `GET /api/operator/last-scans`
- `GET /api/reports/truck/{truck}`
- report/export outputs that include trip truck identity

## 3. Admin Frontend Changes

## 3.1 Required UI/Input Updates
- Add a required input field in create truck form: `driver_name`.
- Add editable field in truck update form: `driver_name`.
- Show `driver_name` in truck list table near `registration_number`.
- Show `driver_name` in truck details and basic card views.

## 3.2 Validation UX
When backend returns `422`:
- bind `errors.driver_name` to the driver name input
- display required/format error messages clearly

## 3.3 Suggested Table Order
Recommended columns for truck list:
1. registration_number
2. driver_name
3. qr_code
4. is_active
5. actions

## 4. Operator Frontend Changes

## 4.1 Display Updates
- In scan success UI, show `trip_summary.truck.driver_name` with registration number.
- In last scans list, show driver name in each truck card/row.
- In trip monitoring views (if operator app renders trip details), show `driver_name` next to truck registration.

## 4.2 No New Operator Input Needed
Operator app does not send `driver_name` in scan requests.
It is read/display data only for operator workflows.

## 5. Backward Compatibility Notes

- Existing truck records may have `driver_name = null` if they were created before this change.
- New records must provide `driver_name`.
- Frontends should handle null display safely during transition (for example: `-` or `Unknown`).

## 6. Testing Checklist

Admin app:
- Create truck without `driver_name` => confirm `422`
- Create truck with `driver_name` => success
- Update truck `driver_name` => success
- Update truck to duplicate `driver_name` => success

Operator app:
- Scan flow response shows `driver_name`
- Last scans endpoint renders `driver_name`
- Truck basic endpoint renders `driver_name`

## 7. Delivered Backend Files

Core code updates were applied in:
- `database/migrations/2026_03_27_000001_add_driver_name_to_trucks_table.php`
- `database/migrations/2026_03_29_000002_drop_driver_name_unique_on_trucks_table.php`
- `app/Models/Truck.php`
- `app/Http/Controllers/TruckController.php`
- `database/seeders/AdminAndTruckSeeder.php`
- `database/seeders/TruckInitialDataSeeder.php`
- `app/Http/Resources/TripResource.php`
- `app/Http/Resources/OperatorTripResource.php`
- `app/Http/Resources/ScanLogResource.php`
- `app/Services/ScanService.php`
- `app/Services/ScanLogService.php`
- `app/Services/TripService.php`
- `app/Services/ReportService.php`
