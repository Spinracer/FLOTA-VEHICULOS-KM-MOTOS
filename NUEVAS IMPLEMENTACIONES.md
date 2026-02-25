# Fleet Management System (Laravel + MySQL) — Implementation Task Plan

> Goal: A production-grade fleet management system (TuFlota-like) with strict business rules, detailed maintenance & fuel control, vehicle assignment locks, attachments (photos/docs), inventory of vehicle components/tools, and powerful exportable reports (CSV/XLSX/PDF).
>
> Stack: **PHP 8.2+**, **Laravel 10/11**, **MySQL 8**. APIs-first design with web UI consuming the API.

---

## 0) Tech Decisions (do this first)

### 0.1 Required packages
- ✅ **Auth & API**
  - Laravel Sanctum (token auth for SPA/mobile/integrations)
- ✅ **Exports**
  - CSV/XLSX: `maatwebsite/excel`
- ✅ **PDF**
  - `barryvdh/laravel-dompdf` (simple) **or** `spatie/browsershot` (best quality HTML→PDF via headless Chrome)
- ✅ **Media/Files**
  - `spatie/laravel-medialibrary` (recommended for photos/docs + conversions + storage drivers)
- ✅ **Activity/Audit log**
  - `spatie/laravel-activitylog` (audit critical changes)
- ✅ **Permissions**
  - `spatie/laravel-permission` (RBAC)

### 0.2 Non-negotiable architecture rules
- Use **Service layer** for business rules (no heavy logic in controllers).
- Use **Policies/Guards** to block forbidden actions (assignments/fuel/maintenance).
- All edits/anulations create **audit entries** (no silent deletes for critical records).
- All “exports” are generated from **filtered queries** (vehicle/date/type, etc.) and stored for traceability.

---

## 1) Task: Project Baseline & Conventions

### Subtasks
- [ ] Define environment requirements: PHP version, MySQL version, storage (local/S3).
- [ ] Set code standards (PSR-12), run Pint, Larastan/PHPStan (optional).
- [ ] Create folders:
  - `app/Domain/*` (services, policies, DTOs)
  - `app/Http/Controllers/Api/*`
  - `app/Http/Resources/*`
  - `app/Exports/*`, `app/Reports/*`
- [ ] Configure API versioning prefix: `/api/v1/...`
- [ ] Configure response format (success/error envelope) and validation errors format.
- [ ] Add global exception handler mapping (Validation/Authorization/NotFound).

Acceptance
- API endpoints respond consistently; validation + auth errors are standardized.

---

## 2) Task: Security, Roles, Permissions, Audit

### 2.1 Authentication (Sanctum)
Subtasks
- [ ] Implement login/logout, token issuance, token revocation.
- [ ] Add middleware for API auth + rate limiting on auth endpoints.
- [ ] Add password reset flow (optional if internal-only).

### 2.2 RBAC (spatie/permission)
Subtasks
- [ ] Create roles: `Admin`, `FleetManager`, `Workshop`, `Driver`, `Accounting`, `Viewer`
- [ ] Define permissions by module:
  - Vehicles: view/create/edit/block/export
  - Assignments: create/close/override
  - Maintenance: open/approve/close/void/export
  - Fuel: register/approve/void/export
  - Reports: view/export
  - Catalogs: manage
- [ ] Protect routes with permission middleware.

### 2.3 Audit Log (spatie/activitylog)
Subtasks
- [ ] Log create/update/void actions for: assignments, maintenance orders, maintenance items, fuel logs, vehicle status changes, overrides.
- [ ] Store: user, action, entity, before/after, IP, timestamp.
- [ ] Expose audit read endpoints for Admin/FleetManager.

Acceptance
- Any critical change can be traced to a user and time.

---

## 3) Task: Core Catalogs & System Settings

### 3.1 Catalogs
Subtasks
- [ ] CRUD: expense categories (parts, lubricants, labor, tires, etc.)
- [ ] CRUD: units (L, gal, pza, service, etc.)
- [ ] CRUD: maintenance types (preventive/corrective/inspection/emergency)
- [ ] CRUD: vehicle states (active/blocked/sold/out-of-service)
- [ ] CRUD: workshop services

### 3.2 Settings
Subtasks
- [ ] Global thresholds:
  - abnormal fuel consumption threshold (% below average)
  - max allowed fuel liters per event (optional)
  - maintenance approval threshold (amount)
- [ ] Per-vehicle defaults:
  - preventive maintenance interval (km/days) per maintenance kind

Acceptance
- Forms must use catalog IDs (no free-text categories).

---

## 4) Task: Vehicles Module (Vehicle 360° Profile)

### 4.1 Vehicle CRUD
Subtasks
- [ ] Vehicles table: plate/code, brand, model, year, fuel type, notes, current status.
- [ ] Implement soft-delete for non-critical, but **block delete** if vehicle has history.
- [ ] Vehicle profile endpoint returns:
  - summary
  - current assignment (if any)
  - active maintenance order (if any)
  - last odometer reading
  - last fuel log
  - counts/totals (monthly cost, etc.)

### 4.2 Media: Photos & Documents
Subtasks
- [ ] Attach multiple photos (damage, angles).
- [ ] Attach documents (registration, insurance, permits).
- [ ] Validate file types + size + virus scan optional.
- [ ] Return signed URLs or controlled download endpoints.

### 4.3 Odometer Logs (foundation)
Subtasks
- [ ] Create odometer_logs:
  - vehicle_id, reading_km, source (assignment/fuel/maintenance/manual), recorded_at, user_id
- [ ] Rule: no decreasing odometer unless `override` permission + justification saved + audit.
- [ ] Auto-create odometer logs when closing maintenance, registering fuel, closing assignment (when km provided).

Acceptance
- Every relevant workflow updates odometer consistently; inconsistencies are blocked or require override.

---

## 5) Task: Vehicle Components / Tools Inventory (per vehicle)

### 5.1 Components master + vehicle mapping
Subtasks
- [ ] Create `components` catalog (e.g., jack/gata, spare tire, tool kit, fire extinguisher, first aid kit, fuel card, fleet card, etc.)
- [ ] Create `vehicle_components`:
  - vehicle_id, component_id, quantity, serial/identifier (optional), condition, notes
- [ ] Allow component “types”:
  - `tool`
  - `safety`
  - `documents`
  - `cards` (fuel card, fleet card)
- [ ] Add “card” details (masked number, provider, expiry, status active/blocked)

### 5.2 Component checklist on assignment
Subtasks
- [ ] When creating/closing assignment, capture component checklist:
  - missing/damaged items
  - photos
  - notes
- [ ] Persist snapshots: `assignment_component_snapshots` to prove what was delivered/returned.

Acceptance
- You can see exactly what tools/cards were inside each vehicle per assignment event.

---

## 6) Task: Personnel/Drivers Module

### Subtasks
- [ ] CRUD for drivers/employees (name, phone, area/route, active flag).
- [ ] Driver documents (license photo, expiry date).
- [ ] Driver history endpoint:
  - assignments history
  - fuel logs (optional)
  - incident reports (optional)
- [ ] Rule: inactive driver cannot be assigned.

Acceptance
- Driver profile is auditable and includes history.

---

## 7) Task: Assignments Module (with lock rules)

### 7.1 Assignment lifecycle
Subtasks
- [ ] Create assignment (vehicle_id, driver_id, start_at, start_km optional, notes, photos).
- [ ] Close assignment (end_at, end_km required if business requires, notes, photos).
- [ ] Keep full history; do not overwrite.

### 7.2 HARD lock rules (must enforce in service + DB constraints)
Subtasks
- [ ] Prevent new assignment if:
  - vehicle has an **active assignment** (not closed), OR
  - vehicle has an **active maintenance order** (not closed), OR
  - vehicle is **blocked/out-of-service**
- [ ] Return error message containing:
  - reason
  - link/id of blocking record (assignment/maintenance)
- [ ] Admin override flow:
  - permission `assign.override`
  - mandatory justification stored + audit
  - still logs system event "override used"

### 7.3 Optional PDF
Subtasks
- [ ] Generate “Delivery/Return” PDF:
  - vehicle data, driver data, date/time
  - component checklist snapshot
  - photos references
  - signature lines (Driver / Fleet Manager)

Acceptance
- Vehicle cannot be reassigned unless rules allow or override is used with justification.

---

## 8) Task: Workshops (Authorized) + Portal Access

### Subtasks
- [ ] CRUD workshops (contact, services, authorized flag).
- [ ] Workshop user accounts (role=Workshop).
- [ ] Permission boundaries:
  - workshop can create maintenance draft
  - workshop can upload diagnostics/quotes/photos
  - workshop cannot approve payments unless allowed

Acceptance
- Only authorized workshops can submit maintenance work for vehicles.

---

## 9) Task: Maintenance Module (OT + history like TuFlota)

### 9.1 Maintenance Order (OT)
Subtasks
- [ ] Create OT: vehicle, type, workshop/internal, priority, issue/description, opened_at, entry_km required.
- [ ] Status machine:
  - Draft → PendingApproval → Approved → InProgress → Completed → Voided
- [ ] Attachments: diagnostics, quote(s), invoice, photos.

### 9.2 Maintenance Items (line items)
Subtasks
- [ ] Items table: category(expense), description, qty, unit, unit_price, subtotal.
- [ ] Totals calculation + tax optional.
- [ ] Lock editing items after OT is Completed (only Admin can void/correct via credit note style).

### 9.3 Preventive Scheduling
Subtasks
- [ ] Per-vehicle schedules:
  - interval_km and/or interval_days
  - next_due_km, next_due_date
- [ ] Alerts list:
  - upcoming (within X km/days)
  - overdue
- [ ] One-click “Create OT from alert”.

### 9.4 Completion rules
Subtasks
- [ ] On complete:
  - exit_km required (>= entry_km unless override)
  - work summary required
  - at least 1 attachment required if total > threshold (configurable)
  - auto-create odometer log
  - update vehicle operational status:
    - if active assignment exists → status becomes Assigned
    - else → Available

### 9.5 Maintenance history view/export
Subtasks
- [ ] Vehicle maintenance history endpoint:
  - filter by vehicle, date range, workshop, type, status, cost range
  - sorting and pagination
- [ ] Export history:
  - CSV + XLSX + PDF

Acceptance
- OT acts as single source of truth for maintenance and is exportable + auditable.

---

## 10) Task: Fuel Module (detailed) + PDF authorization with signatures

### 10.1 Fuel log entry
Subtasks
- [ ] Create fuel log fields:
  - vehicle_id, driver_id (or responsible), station/provider, fuel_type
  - odometer_km required
  - liters, unit_price, total
  - payment_method (cash/card/voucher), receipt_number
  - recorded_at
  - notes
- [ ] Attachments: receipt photo, odometer photo.

### 10.2 Fuel lock rules
Subtasks
- [ ] By default: **block fuel registration when vehicle is in active maintenance**.
- [ ] Add exception flow:
  - reason required (e.g., “transfer to workshop”)
  - optional approval required based on settings
  - audit required

### 10.3 Consumption & anomaly detection
Subtasks
- [ ] Compute km traveled since last fuel record with valid odometer.
- [ ] Compute km/L and store on fuel log.
- [ ] Maintain rolling average per vehicle and alert when below threshold.
- [ ] Flag suspicious patterns:
  - multiple fills too close in time
  - liters exceed tank capacity (optional config)
  - odometer jump or regression

### 10.4 Fuel authorization PDF (printable) with signature lines
Subtasks
- [ ] Generate PDF per fuel log and also in batch (weekly/monthly):
  - header with company + folio + date
  - vehicle + driver data
  - table of fuel entries
  - totals
  - signature lines:
    - Driver/Requester (Firma / Nombre)
    - Fleet Manager (Firma / Nombre)
    - Accounting (Firma / Nombre)
- [ ] Include optional QR to open record page.
- [ ] Store generated PDF as attachment for traceability.

Acceptance
- Fuel control is strict, measurable, and printable with signatures to speed paperwork.

---

## 11) Task: Reporting & Exports (CSV/XLSX/PDF + Filters)

### 11.1 Export engine (reusable)
Subtasks
- [ ] Create a “ReportQueryBuilder” pattern:
  - accepts filters (vehicle_id, date_from, date_to, driver_id, workshop_id, category_id, status, etc.)
  - returns paginated dataset and export dataset
- [ ] Create `ReportExportService`:
  - formats: CSV, XLSX, PDF (and optional JSON for integrations)
  - large exports run as queued jobs
  - stores exported file in storage + logs who exported and filters used

### 11.2 Reports (minimum set)
Subtasks
- [ ] Maintenance cost report:
  - totals by vehicle / month / workshop / category
- [ ] Fuel report:
  - totals, average km/L, cost per km (optional)
- [ ] Vehicle utilization report:
  - assignments time, downtime in maintenance
- [ ] Top expensive vehicles report:
  - combined maintenance + fuel in period
- [ ] Workshop performance report:
  - count of OTs, average time to close, total cost

### 11.3 Filters (must exist in UI + API)
Subtasks
- [ ] Filter by:
  - vehicle (single/multiple)
  - date range (required for most exports)
  - driver
  - workshop
  - category (expense type)
  - status
- [ ] Sorting and grouping options:
  - by vehicle, by month, by category

Acceptance
- Every report supports CSV/XLSX/PDF and is filterable by vehicle + date at minimum.

---

## 12) Task: API Design (REST) + Documentation

### Subtasks
- [ ] Create REST endpoints per module:
  - `/vehicles`, `/vehicles/{id}/profile`, `/vehicles/{id}/history`
  - `/assignments` (create/close/list)
  - `/maintenance-orders` (CRUD + status transitions)
  - `/fuel-logs` (CRUD + exports)
  - `/reports/*` (query + export endpoints)
  - `/catalogs/*`
- [ ] Use FormRequests for validation; Resources for responses.
- [ ] Add API documentation:
  - `knuckleswtf/scribe` (recommended) OR `l5-swagger`

Acceptance
- All functionality is available via API with documented endpoints.

---

## 13) Task: Data Integrity, Concurrency, and Performance

### Subtasks
- [ ] Add DB indexes:
  - vehicle_id + recorded_at for fuel/maintenance/odometer
  - status fields
- [ ] Use DB transactions for:
  - assignment create/close
  - OT status transitions + item updates
  - fuel create + odometer log create
- [ ] Prevent duplicates:
  - Ensure only 1 active assignment per vehicle
  - Ensure only 1 active maintenance order per vehicle
- [ ] Add tests:
  - lock rules (assignment/fuel/maintenance)
  - odometer validation + override
  - consumption calculations
  - report filtering correctness

Acceptance
- No race conditions create double active records; workflows remain consistent.

---

## 14) Task: Optional “Big System” Upgrades (recommended for scale)
- [ ] Queue system (Redis + Horizon) for exports and PDF batches.
- [ ] Notifications (email/WhatsApp integration later):
  - preventive due, approvals pending, anomalies
- [ ] Multi-branch support (if needed).
- [ ] Incident/accident module (photos, insurance claim).
- [ ] Admin “Override Registry” report (who overrode what and why).

---

## Definition of Done (global)
- Each module:
  - has API endpoints + validation + policies
  - logs audit for critical changes
  - supports attachments where applicable
  - enforces lock rules
- Reports:
  - filterable by vehicle and date
  - exportable as CSV/XLSX/PDF
  - exports stored and auditable
- PDFs:
  - include folio + date + signature lines
  - can be printed cleanly and consistently

---

## Suggested Implementation Order (to avoid context switching)
1) Security + RBAC + Audit  
2) Catalogs + Vehicles + Odometer  
3) Vehicle Components/Tools  
4) Assignments (lock rules)  
5) Workshops  
6) Maintenance (OT + history)  
7) Fuel (km/L + PDFs)  
8) Reports + exports engine  
9) Hardening + tests + performance
