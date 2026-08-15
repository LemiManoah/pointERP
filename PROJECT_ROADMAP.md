# Point Investment Construction ERP - Project Roadmap

## 1. Purpose

This is the master delivery roadmap for the Point Investment Construction ERP programme. It connects the approved direction in the Software Requirements Specification (SRS) to the detailed phase implementation documents in this repository.

The programme contains two applications sharing one database:

- `PointERP`: the client-facing construction operations system.
- `pointManager`: the Point support application for onboarding tenants, branches and initial tenant administrators.

The ERP application owns the shared database migrations. The manager application must consume that schema and must not maintain a competing migration history.

## 2. Documentation Authority

Use the documents in this order:

1. `Point_Investment_Construction_ERP_SRS_v0.1.docx`: business requirements and MVP acceptance authority.
2. `PROJECT_ROADMAP.md`: overall delivery sequence and current programme status.
3. `phase1.md`, `phase2.md`, and later whole-phase documents: architecture and scope for each major phase.
4. `phase2A.md`, `phase2B.md`, `phase2C.md`, and later slice documents: detailed implementation plans.
5. Tests and migrations: executable proof of the implemented behaviour.

If implementation and documentation disagree, investigate the code and tests, then update the relevant document. A screen alone is not proof that a requirement is complete.

## 3. Product Goal

Replace fragmented paper, spreadsheet and messaging-based construction operations with one controlled system for:

- Companies, branches, users, permissions and audit.
- Customers, contracts, projects, sites, BOQ activities and documents.
- Daily site execution and management exceptions.
- People, equipment, fuel, materials, procurement and stores.
- Budgets, expenses, certificates, invoices and collections.
- Safety, environmental and social controls.
- Notifications, evidence, approvals, dashboards and exports.

The pilot must run one real Uganda project end to end. Reliable workflows, evidence, security and exception reporting take priority over a large number of shallow screens.

## 4. Current Delivery Status

### Phase 1 - Platform Foundation

Status: Implemented, subject to the full local quality suite and final UAT.

Delivered:

- Tenant and branch context, including single-branch behaviour.
- Countries, currencies and exchange rates.
- Staff, staff positions, users, roles and permissions.
- Tenant/branch support management through `pointManager`.
- Policy foundation, audit trail UI, global confirmation dialogs and notifications/toasts.
- Seeded users demonstrating tenant, branch and role separation.

Detailed document: `phase1.md`.

### Phase 2 - Project Delivery and Daily Control

Status: Phase 2A through Phase 2D are implemented. Phase 2 is pending the full local quality suite and final UAT.

#### Phase 2A - Project and Site Foundation

- Customers and contracts.
- Projects, sites and BOQ/project activities.
- Project/site assignments and access controls.
- Base structured Daily Site Report records.

Detailed document: `phase2A.md`.

#### Phase 2B - Documents and Evidence

- Document types, controlled documents and versions.
- Record links, evidence visibility, downloads and superseded versions.
- Project, site and DSR evidence integration.

Detailed document: `phase2B.md`.

#### Phase 2C - Daily Site Reporting

- Draft, submit, return, approve and archive workflow.
- Approved-report locking and controlled corrections.
- BOQ quantities, chainage/side, labour, plant/fuel, materials, costs and delays.
- Evidence warning/override and workflow audit trail.
- Expected and missing DSR records.
- Project/site DSR summaries and cumulative approved quantities.

Detailed document: `phase2C.md`.

#### Phase 2D - Exceptions, Notifications and Operational Reporting

Status: Implemented on 15 August 2026; local verification and UAT pending.

Delivered to close Phase 2:

- Tenant/project/site working calendars and configurable non-reporting days.
- Scheduled expected-DSR generation and missing-report processing.
- In-app notification centre and database notification records.
- Optional email delivery with retry/failure status.
- First missing-report reminder and configurable consecutive-miss escalation.
- Document-expiry and pending-approval notifications.
- Role-aware exception dashboard with freshness timestamps and drill-down links.
- Date/project/site/status filters and CSV/Excel exports.
- Notification, escalation, direct-URL and tenant/branch isolation tests.

Phase 2 is complete only after Phase 2D passes its acceptance tests and the full local quality suite.

## 5. Recommended Post-Phase-2 Sequence

### Phase 3A - Equipment, Fleet and Fuel Control

Recommended first Phase 3 slice.

- Equipment categories and asset register.
- Ownership, make/model, serial/registration, capacity and documents.
- Meter types, verified opening readings and correction rules.
- Assignment, transfer, handover/return, custodian and last-known location.
- Status lifecycle, utilisation and idle time.
- Fuel issues with supplier/store, quantity, cost, meter and evidence.
- Maintenance schedules, work orders, downtime, parts and next service.
- Link DSR equipment/fuel lines to equipment records while retaining historical snapshots.

SRS coverage: `EQP-001` to `EQP-009` and `EQP-011`. Live GPS remains later.

Detailed implementation document: `phase3A.md`.

### Phase 3B - Materials, Procurement and Inventory

- Suppliers, items, categories and tenant-managed units of measure.
- Warehouses, depots and site stores.
- Requisitions, approvals, quotation comparison and purchase orders.
- Receipts, inspections, issues, returns, transfers, adjustments and counts.
- On-hand, reserved, available and in-transit balances.
- Reorder levels, low-stock exceptions and negative-stock controls.
- Link DSR material lines to inventory items and approved stock movements while retaining snapshots.

SRS coverage: `INV-001` to `INV-007`.

### Phase 3C - Workforce, Attendance and Leave

- Employee and contractor master records.
- Project/site assignments, trades, employers and contract dates.
- Leave requests, balances, approval/rejection and calendar.
- Attendance/presence with supervisor confirmation.
- Attendance-to-DSR prompts and workforce-count consistency exceptions.
- Restricted HR fields and auditable corrections.

SRS coverage: `HR-001` to `HR-006`. Payroll remains later.

### Phase 4 - Commercial and Financial Control

- Cost codes, project budgets and revisions.
- Commitments, expenses and approval thresholds.
- IPC/valuation quantities sourced from approved DSR work lines.
- Customer and supplier invoices, retention, receipts and collections.
- Project budget/commitment/actual/invoiced/collected reporting.
- Controlled reversals and adjustments for posted financial records.

SRS coverage: `FIN-001` to `FIN-009`, plus commercial requirements in `DOC-006` and `PRJ-002`.

### Phase 5 - HSE, Environment and Social Control

- Incidents, near misses, hazards, inspections and toolbox talks.
- Corrective actions, owners, deadlines, verification and escalation.
- Environmental plans, permits, scheduled reports and observations.
- Waste, dust, noise, spills, erosion and evidence.
- Community activities, commitments and grievances with confidentiality.
- Link DSR narratives to controlled HSE/environment/social records.

SRS coverage: `HSE-001` to `HSE-005`, `ENV-001` to `ENV-007`, and `SOC-001` to `SOC-005`.

### Phase 6 - Portfolio Reporting and Production Readiness

- Cross-module executive and control-role dashboards.
- Scheduled digests and standard PDF/Excel exports.
- Record comments, mentions and tasks where not delivered earlier.
- Data import/reconciliation tools and migration sign-off.
- Low-bandwidth and mobile field-workflow optimisation.
- Backup/restore verification, monitoring, support process and UAT.
- Pilot rollout, training, adoption measurement and production acceptance.

Later integrations such as live GPS, biometric attendance, payroll, statutory accounting, bank/mobile-money, environmental sensors, full chat and AI remain outside MVP unless approved through change control.

## 6. Architecture Rules Across All Phases

- Every operational record belongs to a tenant and an authorised scope.
- Single-branch tenants still use a real branch; branch fields are defaulted rather than omitted from the data model.
- Policies enforce permission, tenant, branch, project/site and record-state rules.
- A hidden UI button is never the security control.
- Approved or posted records are corrected additively, not silently edited or deleted.
- Active and inactive/archived records use separate views where appropriate.
- Master modules own identity; transaction modules select master records and retain historical snapshots.
- Audit history is separate from ordinary comments/activity.
- Dashboards distinguish zero activity from missing or stale data and drill down to source records.
- Rates, financial values and sensitive HR data remain permission-controlled.
- New migrations are generated individually with Artisan and kept narrowly scoped.

## 7. Immediate Decision

Run the Phase 2D migrations, seeders, focused tests and UAT. Once accepted, begin Phase 3 architecture with equipment, fleet and fuel control as the first implementation slice.

Reason: PointERP can already capture daily operational data, but it does not yet provide the complete notification, escalation and exception-management loop required by `PRJ-005` to `PRJ-008`, `COM-003` to `COM-005`, and `RPT-001` to `RPT-006`. Closing that loop makes the existing work usable for management and gives Phase 3 modules a reusable notification and dashboard foundation.

`phase3A.md` now defines the detailed equipment/fleet/fuel implementation plan. A broader `phase3.md` may be added before Phase 3B to consolidate equipment, inventory and workforce boundaries, but Phase 3A can proceed from its approved contract.

## 8. Phase Completion Standard

A phase is complete only when:

- The end-to-end workflow works with representative seeded users and data.
- Policies reject unauthorised direct requests.
- Tenant, branch and project/site isolation are tested.
- Status transitions, approvals, corrections and audit events are tested.
- Mobile-sized forms and important desktop views are usable.
- Empty, loading, validation, inactive and failure states are handled.
- The focused feature tests and the repository's strict quality suite pass locally.
- The phase document is updated with implemented behaviour, remaining decisions and handoff notes.
