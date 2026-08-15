# Construction ERP - Phase 3A Detailed Implementation Plan

## 1. Status

Architecture and implementation plan prepared on 15 August 2026.

Phase 3A.1, Equipment Register and Locations, is implemented and awaiting UI and automated-test acceptance. It includes separate equipment-category, equipment-location and equipment migrations; tenant/branch-aware models and policies; audited register actions; active/inactive views; controlled-document linkage; permissions; and road-project demo data.

Phase 3A.2a, Meter Ledger and Controlled Correction, is implemented and awaiting UI and automated-test acceptance. It includes opening-reading backfill, accepted meter events, calculated usage, additive correction requests, two-person approval/rejection, asset-cache recalculation, notifications, audit, permissions, UI history and seeded examples.

Phase 3A.2b has not started. Assignments, custody, handover/return, transfers and manual location confirmations follow after the meter-control checkpoint is accepted.

Phase 3A is the first resource-control slice after Phase 2. It turns the equipment and fuel observations already captured in approved Daily Site Reports into a controlled fleet register, movement history, meter ledger, fuel ledger and maintenance workflow.

This document is the implementation contract. Schema or workflow changes should be reflected here before migrations are written.

## 2. Objective

At the end of Phase 3A, an authorised manager should be able to answer:

1. What equipment does the tenant own, lease, hire or receive from subcontractors?
2. Where is each asset now, who is responsible for it and what work is it supporting?
3. What is its latest accepted meter reading and how much has it been used?
4. How much fuel was issued and, where evidence is sufficient, what consumption rate was observed?
5. Which assets are idle, out of service, overdue for maintenance or approaching service?
6. What did an asset look like when handed over, transferred and returned?
7. Which approved DSR, fuel issue, assignment or work order supports each operational fact?
8. Who created, approved or corrected each sensitive record?

The phase must replace repeated free-text equipment identity with a fleet master while preserving the original DSR snapshots that make approved reports historically reliable.

## 3. SRS Coverage

```text
EQP-001 Asset register: category, make/model, serial/registration, ownership,
        capacity, acquisition, default depot, meter, opening reading, status and documents.
EQP-002 Meter types: odometer kilometres, engine hours, operating hours or no meter.
EQP-003 Readings at assignment, logs, fuel issue, maintenance and return;
        decreasing readings require an authorised correction.
EQP-004 Usage is current accepted reading minus prior accepted reading.
EQP-005 Statuses: Available, Assigned/In Use, Idle, Under Maintenance,
        Out of Service, Transferred and Retired.
EQP-006 Operational location derives from accepted assignment/transfer and may
        be manually confirmed with date, user, coordinates/photo and site.
EQP-007 Operator/custodian, project/site, dates, conditions and attachments.
EQP-008 Fuel quantity, source, cost, meter, issuer/receiver, evidence and exceptions.
EQP-009 Date/meter schedules, work orders, downtime, parts, cost, provider,
        evidence and next service.
EQP-011 Last known manual or assignment-based location and status without GPS.

PRJ-003 DSR equipment, hours and fuel.
DOC-001 to DOC-007 Controlled documents, links, evidence and expiry.
COM-003 to COM-005 In-app/email notifications and user preferences.
RPT-001, RPT-002, RPT-004 and RPT-005 Scope-aware dashboards, drill-down and export.
SEC-003 to SEC-006 Authorisation, audit and controlled correction.
NFR-002, NFR-006, NFR-010 and NFR-011 Low bandwidth, auditability,
        controlled data and accessible UI.
```

`EQP-010` real-time GPS is explicitly out of scope. Phase 3A must remain useful without trackers.

## 4. Domain Principles

### 4.1 Master identity and historical snapshots

`equipment` owns the current identity of an asset. A DSR equipment line will gain an optional `equipment_id`, but retain:

- equipment name;
- identifier;
- status;
- hours;
- fuel type and quantity;
- rate, amount and currency.

When a user selects an asset, the server writes current master values into these snapshot fields. Renaming or disposing an asset must never rewrite an approved DSR.

### 4.2 Ledger before editable totals

Meter readings, movements, fuel and maintenance are event records. Current meter, location, custodian and status may be cached on `equipment` for fast lists, but actions update those values only from accepted events.

No controller may directly change cached operational state.

### 4.3 Fuel issued is not automatically fuel consumed

A store or supplier issue proves that fuel was handed over. It does not always prove that the same quantity was consumed during the measured period.

Phase 3A will therefore distinguish:

- `issue` or `refuel`: fuel delivered to an asset;
- `consumption`: fuel supported by a DSR or approved reconciliation;
- `return`: unused fuel returned;
- `adjustment`: authorised correction with reason.

Consumption-rate exceptions are definitive only where the meter interval and fuel basis are comparable. Otherwise the system raises a review warning, not an accusation of theft or misuse.

### 4.4 Meter corrections are additive

An accepted reading is never silently edited. A correction references the original reading, states the corrected value and reason, and records its approver. The original remains in history as superseded.

### 4.5 Location is derived

The current location comes from the latest accepted assignment, transfer receipt, return or manual location confirmation. A text field edited on the equipment form is not sufficient evidence of movement.

### 4.6 Transactions follow record state

Draft transactions may be edited. Submitted, accepted, completed or posted transactions are locked. Errors are corrected through return/rejection, reversal or additive correction according to the workflow.

## 5. Scope

### 5.1 In scope

- Equipment categories and operational locations.
- Asset register and ownership details.
- Meter types, opening readings, logs and controlled corrections.
- Assignment, handover, return and custody.
- Inter-site and inter-branch transfer with dispatch and receipt.
- Manual last-known-location confirmation.
- Daily utilisation and idle-time history.
- Fuel issues, returns, consumption records and review exceptions.
- Date- and meter-based maintenance schedules.
- Maintenance work orders, downtime, parts snapshots, providers and cost.
- Document links for assets, handovers, transfers, fuel and maintenance.
- DSR equipment selection and approved-DSR posting.
- Notifications, dashboards, CSV export and audit events.
- Tenant, branch, project and site isolation policies.
- Demo data and focused automated tests.

### 5.2 Out of scope

- Live GPS, geofences and tracker-vendor APIs.
- Fuel pump, tank sensor or telematics integration.
- Inventory stock deduction for fuel and spare parts; Phase 3B will own stock.
- Purchase orders, accounts payable and accounting journals.
- Depreciation, tax asset registers and statutory fixed-asset accounting.
- Workshop payroll and mechanic time sheets.
- Route planning and driver trip management.
- Predictive maintenance or machine-learning fuel models.

## 6. Connection to the Existing App

### 6.1 Tenant and branch foundation

Every fleet record carries `tenant_id`. Operational records also carry the responsible `branch_id`.

- Single-branch tenants receive their only branch automatically.
- Multi-branch users operate within `BranchContext`.
- Cross-branch visibility requires `branches.view-all` or a specific fleet permission.
- A transfer changes custody/location; it never changes tenant ownership.
- Cross-tenant asset movement is forbidden.

### 6.2 Projects and sites

Assignments and transfers select existing projects and sites. Policies must prove the actor can access both source and destination.

Project and site pages will show assigned equipment, current condition, last reading, utilisation and maintenance exceptions. Equipment pages will link back to project/site.

### 6.3 Staff and users

- `staff_id` identifies an internal operator or custodian.
- `issued_by`, `received_by`, `approved_by`, `created_by` and similar fields reference authenticated `users` because they represent system actions.
- External operators remain possible through an optional name/employer snapshot, with the employer referencing an existing subcontractor where available.
- A user account is not required for every equipment operator.

### 6.4 Customers, suppliers and subcontractors

The existing `customers` table already supports `supplier` and `subcontractor` types.

- Hired/subcontractor equipment may reference its owner through `owner_customer_id`.
- Fuel and maintenance providers may reference `provider_customer_id`.
- Provider name/reference snapshots remain on posted transactions.

### 6.5 Documents

The existing polymorphic document-link system will link controlled files to:

- equipment;
- assignments and returns;
- transfers;
- meter corrections;
- fuel transactions;
- maintenance work orders.

Examples include logbooks, insurance, inspection certificates, handover photos, fuel vouchers, service reports and invoices. Existing expiry alerts apply to linked expiring documents.

### 6.6 Daily Site Reports

Draft DSR equipment lines will select from equipment currently assigned or available to that site. The form will still allow an authorised unregistered-equipment snapshot for hired plant pending fleet registration, but submission should flag it for review.

On DSR approval, one idempotent posting action will:

1. Create or update the linked daily utilisation event.
2. Create an accepted meter event when a valid closing reading is supplied.
3. Create a fuel-consumption event when fuel quantity is supplied and not already posted.
4. Link the resulting records back to the DSR equipment line.
5. Recalculate the asset cache and applicable exceptions.

Returning or editing a draft does not post fleet transactions. Corrections to an approved DSR use the existing correction workflow and create additive fleet adjustments.

### 6.7 Currency and exchange rates

- Asset acquisition, hire, fuel and maintenance records store transaction currency.
- Currency must be enabled for the tenant and branch.
- Historical transaction amount/currency remain unchanged.
- Portfolio conversion uses the approved exchange-rate service for the transaction date; it does not overwrite source values.
- Cost visibility requires a dedicated permission.

### 6.8 Notifications, audit and operations dashboard

Phase 3A reuses `OperationalNotificationSender`, preferences, queued delivery and audit trail.

Fleet exceptions may appear as a new equipment section on the operations dashboard and as a dedicated equipment dashboard. Notifications link to the source asset, transfer or work order.

## 7. Proposed Data Model

All primary keys are UUIDs. Every migration must be generated individually with Artisan. Index names must be short enough for MySQL.

### 7.1 `equipment_categories`

- `id`, `tenant_id`;
- `code`, `name`, `description`;
- default meter type;
- optional default capacity unit;
- optional fuel-efficiency basis and expected value;
- tolerance percentage;
- `is_active`, audit users, timestamps, soft delete.

Unique: tenant/code and tenant/name.

### 7.2 `equipment_locations`

Represents depots, yards, workshops, project compounds and site locations.

- `id`, `tenant_id`, `branch_id`;
- optional `project_id`, `site_id`;
- type: depot, yard, workshop, site or other;
- code, name, address;
- optional latitude/longitude;
- `is_active`, audit users, timestamps, soft delete.

A site-backed location is unique per tenant/site. This table can later be referenced by Phase 3B warehouses without making a warehouse the fleet source of truth.

### 7.3 `equipment`

- category and stable asset code;
- name, make, model, model year;
- serial number, registration number and chassis/VIN where applicable;
- ownership: owned, leased, hired or subcontractor;
- optional owner/supplier reference and ownership snapshot;
- capacity value/unit;
- acquisition date, amount and currency;
- hire rate and rate basis where applicable, permission-controlled;
- default location and responsible branch;
- meter type: odometer_km, engine_hours, operating_hours or none;
- verified starting reading and date;
- expected fuel-efficiency basis/value and tolerance override;
- tank capacity where known;
- current status, location, project, site, custodian, accepted meter and reading time;
- condition summary;
- `is_active`, audit users, timestamps, soft delete.

Stable identifiers must be unique within the tenant when present. Retired equipment is not deleted and cannot receive new operational transactions.

### 7.4 `equipment_meter_readings`

- equipment, tenant, branch, project/site and location context;
- event type: opening, assignment, daily_log, fuel, maintenance, return, transfer, manual or correction;
- reading value and reading time;
- previous accepted reading and calculated usage;
- status: pending, accepted, rejected or superseded;
- source morph/reference, such as DSR line, fuel transaction or work order;
- optional corrected-reading reference;
- reason, evidence note, recorded/approved users and timestamps.

Only accepted readings update the equipment cache. A reading below the previous accepted value is rejected unless submitted through the correction permission and approved.

### 7.5 `equipment_assignments`

- equipment, branch, project, site and location;
- internal custodian/operator or external custodian snapshot;
- assignment, expected-return and actual-return dates;
- handover and return meter readings;
- handover/return condition and notes;
- status: draft, active, returned or cancelled;
- handed-over, received, returned and accepted users;
- timestamps and audit users.

Only one active assignment is allowed per equipment. Assignment activation sets status to Assigned/In Use and location to the destination.

### 7.6 `equipment_transfers`

- equipment;
- source/destination branch, location, project and site;
- requested, approved, dispatched and received dates/users;
- dispatch and receipt readings/conditions;
- transfer reason and transport reference;
- status: draft, submitted, approved, dispatched, received, returned or cancelled;
- timestamps and audit users.

Dispatch changes the asset to Transferred/In Transit. Receipt changes its responsible branch/location and closes or replaces the previous assignment. Source and destination acceptance are distinct actions.

### 7.7 `equipment_location_confirmations`

- equipment, branch, project/site and location;
- observed date/time;
- optional latitude/longitude;
- condition/status observation;
- note, confirmer and evidence links.

An accepted confirmation updates last-known location but does not fabricate a transfer or assignment.

### 7.8 `equipment_usage_logs`

- equipment, date, branch, project/site and location;
- optional DSR equipment-line source;
- operating status;
- opening/closing accepted meter references;
- working and idle hours;
- calculated meter usage;
- notes and status: draft, posted, reversed;
- posted/reversed users and reason.

Unique source linkage prevents an approved DSR line from posting twice.

### 7.9 `equipment_fuel_transactions`

- equipment, date/time, branch, project/site and location;
- type: issue, refuel, consumption, return or adjustment;
- fuel type, quantity and unit;
- source type: supplier, store, site stock, mobile bowser or other;
- optional provider customer; source-name snapshot;
- unit cost, total cost and currency;
- accepted meter reading;
- optional tank level before/after and full-tank flag;
- issuer/receiver users or staff, voucher/reference and notes;
- optional DSR line source;
- exception status/reason;
- status: draft, submitted, approved, posted, rejected or reversed;
- approval/posting/reversal users and timestamps.

Phase 3B may add `inventory_item_id`, `warehouse_id` and `stock_movement_id`. Phase 3A must not create fake stock balances.

### 7.10 `equipment_maintenance_schedules`

- equipment;
- maintenance type/name;
- basis: date, meter or whichever comes first;
- interval days and/or meter units;
- last service date/reading;
- next due date/reading;
- warning days/units;
- responsible user/role;
- `is_active`, audit users and timestamps.

### 7.11 `equipment_maintenance_work_orders`

- equipment and schedule;
- branch, project/site and workshop location;
- reference, type, priority and description;
- status: draft, planned, approved, in_progress, completed, cancelled;
- reported, planned-start, actual-start and completion dates;
- opening/closing meter readings;
- provider and provider snapshot;
- downtime hours;
- labour, parts, other and total cost with currency;
- findings, work performed, completion notes and next-service values;
- requester, approver, supervisor and completer;
- audit users and timestamps.

Starting work changes the equipment status to Under Maintenance. Completion requires work performed, completion reading where metered, final cost/parts, and next-service calculation. Out-of-service equipment remains unavailable until an authorised release.

### 7.12 `equipment_maintenance_part_lines`

- work order;
- part code/name snapshot;
- quantity and unit;
- unit/total cost and currency;
- provider/reference and notes.

Phase 3B may add `inventory_item_id` and `stock_movement_id`; historical snapshots remain.

### 7.13 Changes to `daily_site_report_equipment_lines`

Add separate migrations for:

- nullable `equipment_id`;
- optional opening and closing meter readings;
- optional `equipment_usage_log_id`;
- optional `fuel_transaction_id`;
- posting status and posted timestamp.

Foreign keys use restrict/null-on-delete according to history requirements. An asset with operational history cannot be hard-deleted.

## 8. State Machines and Invariants

### 8.1 Equipment lifecycle

```text
Available -> Assigned/In Use -> Idle -> Assigned/In Use
Available/Assigned/Idle -> Under Maintenance -> Available/Assigned
Any operational status -> Out of Service -> Under Maintenance/Available
Available/Assigned -> Transferred -> Available/Assigned at destination
Available/Idle/Out of Service -> Retired
```

- Retired is terminal unless a specially audited administrative reversal is approved.
- Under Maintenance and Out of Service assets cannot be newly assigned.
- An asset cannot have two active assignments or two open transfers.
- Cached status changes only through a domain action.

### 8.2 Meter invariants

- No reading for a no-meter asset.
- Reading time cannot be unreasonably future-dated.
- Normal accepted reading must be greater than or equal to the previous accepted reading.
- Usage is calculated, never trusted from client input.
- Correction must reference an accepted reading and contain a reason.
- Approver cannot approve their own correction unless an explicit override permission exists.
- Posted DSR/maintenance/fuel readings are not edited directly.

### 8.3 Fuel invariants

- Quantity must be positive except a controlled reversal.
- Currency must be enabled for tenant/branch when cost is supplied.
- Meter belongs to the same asset and event time.
- DSR source can post only once.
- Return cannot exceed unreconciled issued quantity without authorised adjustment.
- Fuel above known tank capacity is blocked or requires an override reason.
- Abnormal efficiency creates an exception; it does not silently reject a valid physical issue.

### 8.4 Maintenance invariants

- Completed work orders are locked.
- Actual completion cannot precede start.
- Meter-based completion reading follows meter rules.
- Next due values are calculated from the completed service where a schedule exists.
- Cancelling in-progress work requires a reason and an explicit equipment release decision.

## 9. Actions and Service Boundaries

Controllers validate, authorise and delegate. Domain state changes belong in actions/services:

```text
CreateEquipment
UpdateEquipmentIdentity
RetireEquipment
RecordOpeningMeterReading
RecordEquipmentMeterReading
RequestMeterReadingCorrection
ApproveMeterReadingCorrection
AssignEquipment
ReturnEquipment
SubmitEquipmentTransfer
ApproveEquipmentTransfer
DispatchEquipmentTransfer
ReceiveEquipmentTransfer
ConfirmEquipmentLocation
RecordEquipmentUsage
SubmitFuelTransaction
ApproveFuelTransaction
ReverseFuelTransaction
CreateMaintenanceSchedule
StartMaintenanceWorkOrder
CompleteMaintenanceWorkOrder
CancelMaintenanceWorkOrder
PostApprovedDsrEquipmentLines
RecalculateEquipmentState
EvaluateEquipmentExceptions
```

Multi-record state transitions use database transactions and row locking. Posting actions are idempotent.

## 10. Permissions and Policies

Suggested permissions:

```text
equipment.view
equipment.view-all
equipment.create
equipment.update
equipment.retire
equipment.costs.view
equipment.categories.manage
equipment.locations.manage
equipment.assignments.manage
equipment.transfers.request
equipment.transfers.approve
equipment.transfers.dispatch
equipment.transfers.receive
equipment.readings.create
equipment.readings.correct
equipment.readings.approve-correction
equipment.fuel.create
equipment.fuel.approve
equipment.fuel.reverse
equipment.maintenance.manage
equipment.maintenance.approve
equipment.dashboard.view
equipment.export
```

Policies:

```text
EquipmentPolicy
EquipmentCategoryPolicy
EquipmentLocationPolicy
EquipmentAssignmentPolicy
EquipmentTransferPolicy
EquipmentMeterReadingPolicy
EquipmentUsageLogPolicy
EquipmentFuelTransactionPolicy
EquipmentMaintenanceSchedulePolicy
EquipmentMaintenanceWorkOrderPolicy
```

Every policy checks tenant, branch access, project/site access, permission and record state. Destination access is required for transfer receipt. Cost fields are omitted server-side when `equipment.costs.view` is absent.

Initial role mapping:

- Director: full view/approval/export and cost visibility.
- Administrator: register/categories/locations; no automatic cost or approval override.
- Project Manager: project equipment, assignments, transfers, readings, fuel review and maintenance requests.
- Site Manager: assigned-site equipment, daily readings, usage, fuel issue/receipt and condition confirmation.
- Store Keeper: fuel issue/return entries; Phase 3B later adds stock posting.
- Accountant: equipment/fuel/maintenance cost view and export, not operational state changes.
- Auditor: read/export/audit, no mutation.
- Fleet Manager: add as a seeded operational role with broad fleet management and separated approvals.

## 11. Audit Events

Audit at minimum:

- equipment create/update/retire/reactivate;
- category/location changes;
- assignment handover and return;
- transfer request/approval/dispatch/receipt/cancel;
- meter reading acceptance/rejection/correction;
- location confirmation;
- usage post/reversal;
- fuel submit/approve/post/reverse and exception override;
- maintenance schedule changes;
- work-order approval/start/complete/cancel;
- DSR-to-fleet posting and correction;
- filtered export.

Audit records include actor, tenant, branch, subject, old/new values, reason, source reference and request metadata through the existing `AuditLogger`.

## 12. Notifications and Exceptions

Notification categories:

```text
equipment_assignment
equipment_transfer
equipment_meter_exception
equipment_fuel_exception
equipment_maintenance_due
equipment_maintenance_overdue
equipment_out_of_service
equipment_document_expiry
```

Examples:

- Assignment awaiting handover/receipt.
- Transfer awaiting source approval or destination receipt.
- Decreasing or implausible meter reading.
- Maintenance due within configured date/meter warning.
- Maintenance overdue or equipment still in workshop.
- Abnormal fuel rate requiring review.
- Required insurance/inspection document expiring.

Critical safety/out-of-service notices cannot be muted. Notifications remain idempotent by source/category/threshold occurrence.

## 13. UI and Navigation

Use one sidebar item: **Equipment**.

The equipment area uses route-backed tabs on one horizontal line:

```text
Register | Assignments | Transfers | Fuel | Maintenance | Dashboard
```

### 13.1 Register

- Heading and description.
- Search and meaningful filters on the left.
- Add Equipment on the extreme right.
- Active and Inactive/Retired switcher on the extreme right of the tab line.
- Dense table: asset, category, ownership, current location, custodian, meter, status and exceptions.
- Separate modal components for create/edit/category/location.
- Equipment detail page with identity, current state, timeline, readings, assignments, fuel, maintenance and documents.

### 13.2 Assignments

- Active, Due Return and History tabs.
- Assignment/handover modal with searchable equipment, project, site and staff comboboxes.
- Return modal with meter, condition, evidence and reason.
- Prevent assignment of unavailable assets in both UI and policy/action.

### 13.3 Transfers

- Requested, Approved, In Transit, Received and Cancelled tabs.
- Distinct Request, Approve, Dispatch and Receive commands.
- Source/destination locations are visible together for scanning.
- Dispatch and receipt use confirmation modals with reading and condition evidence.

### 13.4 Fuel

- Search/date/project/site/equipment/type/source/exception/status filters.
- New Fuel Transaction modal.
- Cost columns hidden without permission.
- Exception badge drills into meter interval, baseline and evidence used.
- Posted and Reversed use separate tabs.

### 13.5 Maintenance

- Due Soon, Overdue, Planned, In Progress and Completed tabs.
- Schedule modal and work-order modal are separate React components.
- Work-order detail shows timeline, downtime, parts, costs and documents.
- Start, complete and cancel use global confirmations/reason dialogs.

### 13.6 Dashboard

- Fleet count by status and location.
- Assigned, available, idle, under-maintenance and out-of-service totals.
- Utilisation and idle hours by period/project/site/category.
- Fuel quantity/cost and reliable efficiency exceptions.
- Maintenance due/overdue and downtime.
- Missing/stale meter readings.
- Data freshness, filters, drill-down and CSV export.

No page relies on colour alone. Numbers use the existing comma-aware formatter. Large forms use viewport-bounded scrollable dialogs.

## 14. DSR Form Changes

The equipment section will use a searchable equipment combobox filtered to the selected site and report date.

Selecting equipment fills snapshot identity and currency/rate defaults. The user records:

- operational status;
- opening/closing meter where applicable;
- working and idle hours;
- fuel transaction type, fuel type and quantity;
- notes and evidence.

Validation compares:

- asset assignment/location for that date;
- meter delta against working/idle hours;
- duplicate asset lines in the same DSR;
- fuel quantity and tank/threshold rules;
- equipment availability state.

Warnings may require a reason. Hard contradictions, such as another-tenant equipment or an unapproved decreasing reading, are blocked.

## 15. Reporting Calculations

```text
meter_usage = current_accepted_reading - previous_accepted_reading
availability_hours = period_hours - approved_downtime_hours
utilisation_percent = working_hours / availability_hours * 100
idle_percent = idle_hours / availability_hours * 100
fuel_rate_hour = comparable_fuel_consumed_litres / engine_or_operating_hours
fuel_rate_100km = comparable_fuel_consumed_litres / odometer_km * 100
maintenance_overdue = today > next_due_date OR current_meter >= next_due_reading
```

Reports must identify whether fuel efficiency is calculated from issue, full-to-full refuel or approved consumption evidence. Null/missing data is not presented as zero.

## 16. Migration and Build Order

Generate every migration separately with Artisan. Proposed order:

1. Create equipment categories.
2. Create equipment locations.
3. Create equipment.
4. Create equipment meter readings.
5. Create equipment assignments.
6. Create equipment transfers.
7. Create equipment location confirmations.
8. Create equipment usage logs.
9. Create equipment fuel transactions.
10. Create equipment maintenance schedules.
11. Create equipment maintenance work orders.
12. Create equipment maintenance part lines.
13. Add fleet links to DSR equipment lines.

Before each migration is implemented, check foreign-key order, MySQL identifier length, SQLite compatibility and rollback order.

## 17. Implementation Slices

### 3A.1 Register and locations

- Categories, operational locations and equipment register.
- Equipment policies, permissions and active/inactive UI.
- Current-state summary and document links.
- Seed asset register.

Exit: authorised users can register and inspect assets with correct tenant/branch separation.

### 3A.2 Meter, assignment and transfer control

- Checkpoint 3A.2a: meter ledger and correction workflow.
- Checkpoint 3A.2b: assignment, transfer and location workflow.
- Meter ledger and correction workflow.
- Assignment/handover/return.
- Transfer request through destination receipt.
- Location confirmations and state recalculation.
- Notifications and audit.

Exit: current status, custodian, meter and location are derived and historically explainable.

### 3A.3 Fuel control

- Fuel transaction lifecycle.
- Supplier/store snapshots and cost permissions.
- Meter integration and defensible exception rules.
- Fuel list, filters, dashboard and export.

Exit: fuel is traceable to asset, source, receiver, meter, site and evidence without pretending inventory exists.

### 3A.4 Maintenance

- Date/meter schedules.
- Work-order lifecycle, downtime, parts snapshots and cost.
- Due/overdue scheduler and notifications.
- Equipment status integration.

Exit: due work is visible before failure and completed service updates the next requirement.

### 3A.5 DSR integration and management reporting

- Equipment combobox and snapshots in DSR forms.
- Approved-DSR posting action and correction path.
- Project/site equipment panels.
- Fleet dashboard, CSV export and final seed scenarios.
- Full focused policy/workflow/isolation tests.

Exit: site reporting and fleet ledgers agree without duplicate posting.

## 18. Seed Data

Extend the Uganda road demo with:

- motor grader;
- excavator;
- vibratory roller;
- water bowser;
- tipper truck;
- generator or compressor.

Include:

- owned, hired and subcontractor ownership examples;
- Busunju and Kiboga assignments;
- one inter-site transfer awaiting receipt;
- monotonic meter history and one pending correction example;
- normal and review-required fuel records;
- maintenance due soon, overdue and completed examples;
- one out-of-service asset;
- insurance/inspection/service evidence documents;
- Fleet Manager role and branch-restricted site users.

The seeder must be idempotent on MySQL and SQLite and must not overwrite user-created records with the same unrelated identifier.

## 19. Test Plan

Create focused Pest feature tests for:

### Register and access

- tenant and branch isolation;
- direct URL returns 403 without permission;
- cost fields omitted without cost permission;
- retired/inactive records are separated;
- asset with history cannot be hard-deleted.

### Meter

- opening reading accepted once;
- lower normal reading blocked;
- authorised correction preserves original;
- self-approval blocked without override;
- usage calculated from accepted readings;
- no-meter equipment rejects readings.

### Assignment and transfer

- one active assignment per asset;
- unavailable asset cannot be assigned;
- handover and return update state/location;
- transfer requires source and destination access;
- dispatch sets Transferred; receipt sets destination state;
- receiving twice is idempotent/blocked.

### Fuel

- asset/source/receiver/meter validation;
- cost currency must be enabled;
- DSR source posts once;
- abnormal comparable rate creates exception;
- unreliable interval is labelled review-only;
- reversal is additive and audited.

### Maintenance

- date and meter schedules become due correctly;
- starting work changes equipment status;
- completion records downtime/cost and next service;
- completed work order cannot be silently edited;
- due/overdue notifications are idempotent.

### DSR integration

- selected master writes snapshots;
- approved DSR posts usage/fuel once;
- returned/draft DSR does not post;
- approved correction creates additive adjustment;
- historical DSR snapshots survive asset rename/retirement.

Run focused tests before the full suite. Maintain PHPStan, type coverage, architecture tests, frontend types and lint standards.

## 20. Acceptance Criteria

Phase 3A is accepted when:

- Every active asset has a stable identity, status, location and custody history.
- Meter usage is based only on accepted readings.
- Decreasing readings cannot be silently saved.
- Assignment, transfer, return and maintenance state changes are auditable.
- Fuel transactions identify asset, source, receiver, meter and evidence.
- Fuel exceptions disclose the calculation basis and data quality.
- Date/meter maintenance due and overdue items are visible and notified.
- Approved DSR equipment lines post once to fleet records.
- DSR snapshots remain historically unchanged.
- Tenant, branch, project/site, cost and state policies pass direct-route tests.
- Dashboard filters and exports contain only authorised data.
- Seed data demonstrates role, branch, status and exception separation.
- Focused and full local quality suites pass.

## 21. Decisions Needed Before Implementation

The plan can start with the recommended defaults below, but Point Investment should confirm them during 3A.1:

1. Asset code format. Recommended: tenant-managed code, with examples such as `EXC-001` and `GRD-001`.
2. Internal custody. Recommended: select existing staff; allow external operator/employer snapshots for hired/subcontractor plant.
3. Meter correction approval. Recommended: Fleet Manager or Director, with requester/approver separation.
4. Transfer approval. Recommended: source Project/Fleet Manager approval plus destination receipt.
5. Fuel units. Recommended MVP canonical unit: litre; retain display unit for future conversion.
6. Fuel baselines. Recommended: category default with per-asset override and tolerance; do not hard-code one rate for all machines.
7. Fuel evidence. Recommended: voucher/reference required for posted issues; attachment required above a configurable quantity/cost threshold.
8. Maintenance approval. Recommended: Fleet Manager approves; Project/Site Manager may request and report breakdowns.
9. Hire rates. Recommended: store but expose only through cost permission; do not automatically change approved DSR rates.
10. Required asset documents. Recommended by category: logbook/ownership, insurance, inspection and service evidence where applicable.

## 22. Handoff to Phase 3B

Phase 3B will add materials, procurement and inventory. It will integrate by adding optional references rather than replacing Phase 3A history:

- fuel transaction -> inventory item, warehouse and stock movement;
- maintenance part line -> inventory item and issue movement;
- equipment location -> warehouse/depot where appropriate;
- provider -> procurement supplier and purchase-order records;
- costs -> later finance commitments/actuals.

Phase 3A remains the owner of equipment identity, readings, custody, utilisation, fuel operational context and maintenance. Phase 3B owns physical stock balances and procurement.
