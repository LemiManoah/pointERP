# Point Investment Construction ERP — User Manual & Completion Status

**Version:** 1.0 (August 2026)
**System:** PointERP — Multi-tenant Construction Operations ERP

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Getting Started](#2-getting-started)
3. [Navigation Guide](#3-navigation-guide)
4. [Foundation Module](#4-foundation-module)
5. [Operations Module](#5-operations-module)
6. [Resources Module](#6-resources-module)
7. [Dashboard & Reporting](#7-dashboard--reporting)
8. [User Roles & Permissions](#8-user-roles--permissions)
9. [Completion Status](#9-completion-status)
10. [Remaining Work](#10-remaining-work)
11. [Local Development Commands](#11-local-development-commands)

---

## 1. System Overview

PointERP is a multi-tenant, multi-branch construction ERP built for Point Investment to manage daily construction operations across Uganda, South Sudan, and DRC.

### Architecture
- **Shared Database Tenancy:** All data belongs to a tenant (company). Users operate within their tenant's boundaries.
- **Branch Model:** Each tenant has operational branches (offices/sites). Users are assigned to one or more branches.
- **Permission-Based Access:** Access is controlled through role-based permissions, not hardcoded roles.
- **Audit Trail:** Every sensitive operation is logged with user, timestamp, and before/after values.

### Two Applications
1. **PointERP** — The main construction operations system
2. **pointManager** — Support application for tenant/branch administration

---

## 2. Getting Started

### Accessing the System

1. Navigate to `http://pointerp.test` (local) or your deployment URL
2. Log in with your credentials
3. The system will resolve your tenant and branch automatically

### First-Time Setup

1. **Select your branch** from the sidebar if you have access to multiple branches
2. **Review your profile** at Settings → Profile
3. **Check notifications** — you may have pending assignments or overdue reports

### Default Login (Development)

| Email | Role | Branch Access |
|-------|------|---------------|
| lemi@gmail.com | Director | All branches |
| admin.kla@point.test | Administrator | Uganda |
| pm.kla@point.test | Project Manager | Uganda |
| site.kla@point.test | Site Manager | Uganda (Busunju Section) |
| auditor.kla@point.test | Auditor | Uganda |

---

## 3. Navigation Guide

The sidebar is organized into three groups:

### Foundation
- **Dashboard** — Executive overview with KPIs, charts, and alerts
- **Currency** — Exchange rates and multi-currency settings
- **Access Control** — Users, roles, and permissions
- **Audit Trail** — Searchable history of all system changes

### Operations
- **Operations Control** — Exception dashboard for DSRs, documents, and equipment
- **Projects & Sites** — Project hierarchy and site management
- **Companies** — Customer, supplier, and subcontractor management
- **Contracts** — Contract management and document linking
- **Daily Reports** — Daily Site Report (DSR) workflow
- **Reporting Calendars** — Non-working days and reporting deadlines
- **Documents** — Central document register and evidence
- **Document Types** — Classification system for documents

### Resources
- **Staff** — Employee and contractor records
- **Equipment** — Fleet register, assignments, fuel, and maintenance
- **Materials & Stores** — Inventory items, categories, and units
- **Stock Balances** — Current stock levels by store
- **Requisitions** — Internal material requests
- **Receive Stock** — Goods receipt from suppliers
- **Stock Movements** — Issues, returns, transfers, adjustments
- **Procurement** — Purchase orders *(coming soon)*

---

## 4. Foundation Module

### 4.1 Dashboard

The main dashboard provides an executive overview:

**KPI Cards:**
- Active Projects — number of active construction projects
- DSRs Awaiting Approval — reports needing management review
- Equipment Available — assets ready for assignment
- Documents Expiring — permits/licenses nearing expiry

**Charts:**
- DSR Workflow — donut showing draft/submitted/approved/returned/missing
- Output vs Input Cost — 6-month trend area chart
- Cost Breakdown — labour/equipment/materials/other donut
- Output by Work Category — bar chart of BOQ quantities
- Equipment Status — available/assigned/under maintenance/out of service

**Lists:**
- Documents Expiring Soon — with day-count badges
- Site DSR Compliance — per-site submission rates
- Operational Value to Date — cumulative output and costs

### 4.2 Currency & Exchange Rates

**Multi-Currency Setup:**
1. Navigate to Currency (sidebar)
2. Enable additional currencies for the tenant
3. Set exchange rates with effective dates
4. Branch-specific rates can override tenant rates

**Key Rules:**
- Base currency (e.g., USD) cannot be disabled
- Exchange rates are dated historical records
- Approved rates are immutable; new periods get new rates
- Cost visibility requires dedicated permission

### 4.3 Access Control

**User Management:**
- Create/edit users with tenant and branch assignments
- Assign users to one or more branches
- Set default branch for each user
- Control login status (active/inactive)

**Role Management:**
- Create custom roles with granular permissions
- Pre-seeded roles: Director, Administrator, Project Manager, Site Manager, Store Keeper, Auditor
- Permissions cover every module and action

### 4.4 Audit Trail

- Searchable log of all create/update/delete/approve/reject operations
- Filter by user, date range, action type, and subject
- Shows before/after values for sensitive changes
- Export to CSV for compliance records

---

## 5. Operations Module

### 5.1 Projects & Sites

**Project Hierarchy:**
```
Tenant → Branch → Customer → Contract → Project → Site → Activity/BOQ
```

**Creating a Project:**
1. Navigate to Projects & Sites
2. Click "New Project"
3. Fill in reference, name, customer, contract, manager, currency, dates
4. Assign team members in the Access tab
5. Add sites under the project

**Site Management:**
- Each site has a reference, location, manager, and reporting deadline
- Sites are assigned to users for DSR submission
- Activities/BOQ items link daily work to commercial measurement

**Key Features:**
- Active/Inactive tab separation
- Project show page with Overview, Sites, Activities, Access, Documents, DSRs tabs
- Permission-based visibility (projects.view-all vs assigned only)
- Tenant and branch isolation enforced server-side

### 5.2 Companies (Customers)

- Manage clients, suppliers, and subcontractors
- Types: Client, Supplier, Subcontractor
- Link to contracts and projects
- Active/Inactive status management

### 5.3 Contracts

- Link commercial scope to customers and projects
- Track contract value, dates, retention, and payment terms
- Status workflow: Draft → Active → Completed → Closed → Archived
- Attach contract documents from the Documents module

### 5.4 Daily Site Reports (DSR)

The DSR is the core operational record — what happened on site today.

**Workflow:**
```
Draft → Submit → Review → Approve/Return → Archive
```

**Creating a DSR:**
1. Navigate to Daily Reports
2. Click "New Report" or open an expected report
3. Fill in site, date, weather, conditions
4. Add structured lines:
   - **Work Quantities** — BOQ item, chainage, side, quantity, unit
   - **Labour** — trade, headcount, hours
   - **Equipment** — asset, status, hours, fuel
   - **Materials** — item, quantity, unit, delivery reference
   - **Other Costs** — category, description, amount
   - **Delays** — type, hours, cause, action
5. Link evidence (photos, documents)
6. Submit for review

**Approval Rules:**
- Site managers submit; project managers/directors approve
- Approved reports are locked — corrections require a formal correction request
- Evidence warning when work lines exist without attached photos/documents
- Missing reports are detected and escalated automatically

**DSR Data:**
- Output value (quantity × rate)
- Input cost (labour + equipment + materials + other)
- Profit/loss per report
- Cumulative approved quantities by BOQ item

### 5.5 Reporting Calendars

- Define non-working days (holidays, rain days, site closures)
- Expected DSRs generated automatically based on calendars
- Missing report detection uses calendar-aware deadlines

### 5.6 Documents

**Central Document Register:**
- Upload files to private storage
- Classify by type (Drawing, Permit, Contract, Evidence, etc.)
- Version history — new uploads create versions, not replacements
- Link to projects, sites, contracts, or DSRs
- Confidentiality controls (normal, restricted, confidential, commercial)
- Expiry tracking for permits and licenses

**Document Workflow:**
1. Upload document with metadata
2. Classify type, confidentiality, expiry
3. Link to relevant records
4. Authorised users download/view
5. New versions uploaded as superseded versions

**Key Features:**
- Search by title, reference, type, project, site
- Expiry filters (expiring, expired, no-expiry)
- Active/Archived tab separation
- Audit trail for all document operations

---

## 6. Resources Module

### 6.1 Staff

- Employee and contractor master records
- Staff positions with descriptions
- Link to project/site assignments
- Search and filter by position, status, branch

### 6.2 Equipment

**Fleet Register:**
- Categories (Excavator, Grader, Truck, etc.)
- Locations (Depot, Yard, Workshop, Site)
- Asset details: make, model, serial, ownership, meter type, capacity

**Equipment Tabs:**
- **Register** — full asset list with status, location, custodian
- **Assignments** — active handovers, returns, custody tracking
- **Transfers** — inter-site/inter-branch movement workflow
- **Fuel** — fuel issues, consumption, efficiency tracking
- **Maintenance** — schedules, work orders, downtime, parts
- **Dashboard** — fleet KPIs, utilisation, exceptions

**Key Features:**
- Equipment lifecycle: Available → Assigned → Idle → Under Maintenance → Out of Service → Retired
- Meter readings with monotonic enforcement and correction workflow
- Fuel tracking with supplier, cost, meter posting, and efficiency exceptions
- Maintenance scheduling by date, meter, or whichever comes first
- DSR integration — approved DSRs post usage and fuel to fleet ledgers

### 6.3 Materials & Stores

**Inventory Items:**
- Categories and units of measure
- Item master with code, name, description, stock unit
- Tracking types: None, Serial, Batch, Other
- Saleable items with price tiers (Retail, Wholesale)
- Reorder levels and preferred suppliers

**Stores:**
- Warehouses, depots, site stores
- Branch-specific with optional project/site links
- Store-item stocking settings
- Active/Inactive management

**Stock Balances:**
- On-hand, reserved, available, in-transit quantities
- Per store and per item
- Low-stock alerts and reorder triggers
- CSV export for reporting

**Requisitions:**
- Internal material requests from project/site teams
- Draft → Submit → Approve workflow
- Partial issue and return tracking
- Reservation of approved quantities

**Stock Movements:**
- Direct supplier deliveries (goods receipt)
- Internal issues to projects/sites
- Returns from projects/sites
- Inter-store transfers (dispatch → receive)
- Stock adjustments with approval
- Reversals of posted movements

**Key Features:**
- Append-only stock ledger — corrections via reversals
- Negative stock prevention (concurrency-safe)
- Unit conversions with historical snapshots
- Batch tracking with expiry dates
- Cost visibility controlled by permission

---

## 7. Dashboard & Reporting

### Operations Control Dashboard

The exception dashboard provides real-time operational visibility:

- **DSR Exceptions** — missing, late, returned, pending approval
- **Document Expiry** — permits and licenses needing renewal
- **Equipment Exceptions** — overdue maintenance, idle assets, fuel anomalies
- **Low Stock** — items below reorder levels
- **Freshness timestamps** — when data was last updated

**Filters:** Date range, project, site, status
**Export:** CSV download of exception lists

### Project/Site Dashboards

Each project and site show page includes:
- DSR compliance summary (submitted vs expected)
- Equipment assignments and status
- Recent approved reports with output/cost
- Document links and expiry warnings
- Activity/BOQ progress (approved vs planned quantities)

### Equipment Dashboard

Fleet-level visibility:
- Count by status (available, assigned, idle, maintenance, out of service)
- Utilisation and idle hours by period/project/site
- Fuel quantity/cost and efficiency exceptions
- Maintenance due/overdue and downtime
- Missing/stale meter readings

---

## 8. User Roles & Permissions

### Pre-Seeded Roles

| Role | Typical User | Key Permissions |
|------|-------------|-----------------|
| **Director** | Company owner/executive | All permissions, all-branch access, cost visibility, approvals |
| **Administrator** | Office manager | Most management permissions, no automatic cost/approval override |
| **Project Manager** | PM for assigned projects | Project/site management, DSR approve, equipment view |
| **Site Manager** | Engineer on site | DSR create/submit, site equipment, material requests |
| **Store Keeper** | Warehouse staff | Inventory manage, stock issue/receive, fuel entry |
| **Fleet Manager** | Equipment coordinator | Full fleet management, transfer/fuel/maintenance approval |
| **Auditor** | Read-only reviewer | View all, export, audit trail — no mutations |
| **Accountant** | Finance staff | Cost view, financial reports — no operational changes |

### Permission Structure

Permissions are organized by module and action:
- `{module}.{action}` — e.g., `projects.view`, `equipment.fuel.approve`
- View permissions are separate from manage permissions
- Cost visibility is controlled independently from quantity visibility
- Cross-branch access requires explicit `view-all` permission

---

## 9. Completion Status

### Phase 1 — Platform Foundation ✅ COMPLETE

| Component | Status |
|-----------|--------|
| Tenant and branch context | ✅ Implemented |
| Countries and currencies | ✅ Implemented |
| Exchange rates (dated, historical) | ✅ Implemented |
| User-to-branch assignments | ✅ Implemented |
| Current branch selection | ✅ Implemented |
| Permission-ready all-branch access | ✅ Implemented |
| Tenant isolation tests | ✅ Implemented |
| Branch access tests | ✅ Implemented |
| Currency tests | ✅ Implemented |

### Phase 2A — Project and Site Foundation ✅ COMPLETE

| Component | Status |
|-----------|--------|
| Customer CRUD | ✅ Implemented |
| Contract CRUD | ✅ Implemented |
| Project CRUD with assignments | ✅ Implemented |
| Site CRUD with assignments | ✅ Implemented |
| Project activities/BOQ | ✅ Implemented |
| Policies and permissions | ✅ Implemented |
| Seed data (Uganda + South Sudan) | ✅ Implemented |
| Feature tests | ✅ Implemented |

### Phase 2B — Documents and Evidence ✅ COMPLETE

| Component | Status |
|-----------|--------|
| Document type management | ✅ Implemented |
| Central document register | ✅ Implemented |
| File upload/download (private) | ✅ Implemented |
| Document versioning | ✅ Implemented |
| Polymorphic document links | ✅ Implemented |
| Confidentiality controls | ✅ Implemented |
| Expiry tracking | ✅ Implemented |
| Audit events | ✅ Implemented |
| Feature tests | ✅ Implemented |

### Phase 2C — Daily Site Reporting ✅ COMPLETE

| Component | Status |
|-----------|--------|
| DSR workflow (draft/submit/approve/return) | ✅ Implemented |
| Structured work/labour/equipment/material lines | ✅ Implemented |
| BOQ/activity selection with snapshots | ✅ Implemented |
| Evidence warning and override | ✅ Implemented |
| Approved report locking | ✅ Implemented |
| Controlled correction workflow | ✅ Implemented |
| Expected DSR generation | ✅ Implemented |
| Missing report detection | ✅ Implemented |
| Project/site DSR summaries | ✅ Implemented |
| Cumulative quantities | ✅ Implemented |
| Feature tests | ✅ Implemented |

### Phase 2D — Exceptions and Notifications ✅ COMPLETE

| Component | Status |
|-----------|--------|
| Working calendars | ✅ Implemented |
| Non-reporting days | ✅ Implemented |
| In-app notification centre | ✅ Implemented |
| Email delivery (optional) | ✅ Implemented |
| Missing-report escalation | ✅ Implemented |
| Document-expiry notifications | ✅ Implemented |
| Exception dashboard | ✅ Implemented |
| Date/project/site filters | ✅ Implemented |
| CSV/Excel exports | ✅ Implemented |

### Phase 3A — Equipment, Fleet, and Fuel ⚠️ 90% COMPLETE

| Component | Status |
|-----------|--------|
| Equipment categories and locations | ✅ Implemented |
| Asset register | ✅ Implemented |
| Meter readings and corrections | ✅ Implemented |
| Assignment and handover/return | ✅ Implemented |
| Transfers (dispatch/receive) | ✅ Implemented |
| Fuel transactions | ✅ Implemented |
| Maintenance schedules and work orders | ✅ Implemented |
| DSR fleet integration | ✅ Implemented |
| Fleet dashboard | ✅ Implemented |
| CSV export | ✅ Implemented |
| UI acceptance testing | ⏳ Pending |
| Focused test suite verification | ⏳ Pending |

### Phase 3B — Materials, Procurement, and Inventory ⚠️ 50% COMPLETE

| Component | Status |
|-----------|--------|
| **3B.1** Reference data and stores | ✅ Implemented |
| **3B.2** Stock ledger and balances | ✅ Implemented |
| **3B.2A** Direct receiving workspace | ✅ Implemented |
| **3B.3** Requisitions and internal issues | ✅ Implemented |
| **3B.4** Procurement (quotations, POs) | ❌ Not started |
| **3B.5** Receipts and inspection | ❌ Not started |
| **3B.6** Transfers and stock counts | ❌ Not started |
| **3B.7** DSR material integration | ❌ Not started |
| **3B.8** Reporting and hardening | ❌ Not started |

---

## 10. Remaining Work

### Immediate (Quality Gates)

1. **Fix 8 failing tests** from the last test run:
   - PhaseThreeBStockLedgerTest — Customer not found
   - AuditTrailPageTest — activity count mismatch
   - PhaseThreeAFuelReportingTest — review_required count + charset
   - PhaseThreeAMaintenanceReportingTest — charset + document_count
   - ArchTest — protected scope visibility
   - PhaseThreeARegisterTest — 403 vs 200

2. **Resolve PHPStan errors** (27 remaining):
   - Inventory controller type hints
   - Seeder @var annotations
   - Nullsafe operator false positives

3. **Fix TypeScript lint warnings**:
   - Enum type casting in inventory dialogs
   - Ternary-as-statement patterns

### Phase 3B Remaining (4-6 weeks)

| Chunk | Description | Est. Effort |
|-------|-------------|-------------|
| 3B.4 | Procurement — quotations, purchase orders, approval workflow | 2 weeks |
| 3B.5 | Receipts and inspection — PO matching, partial delivery | 1 week |
| 3B.6 | Transfers and stock counts — dispatch/receive, variance approval | 1 week |
| 3B.7 | DSR material integration — item linking, stock posting | 1 week |
| 3B.8 | Reporting, seed data, hardening | 1 week |

### Phase 3C — Workforce, Attendance, and Leave (4-6 weeks)

- Employee and contractor master records
- Project/site assignments with trades and employers
- Leave requests, balances, approval workflow
- Attendance/presence tracking
- DSR workforce-count consistency checks

### Phase 4 — Commercial and Financial Control (6-8 weeks)

- Cost codes and project budgets
- Commitments and expense tracking
- IPC/valuation from approved DSR quantities
- Customer/supplier invoices and collections
- Retention and payment tracking

### Phase 5 — HSE, Environment, and Social Control (4-6 weeks)

- Incident and near-miss reporting
- Hazards, inspections, and toolbox talks
- Environmental plans and permits
- Community activities and grievances

### Phase 6 — Portfolio Reporting and Production Readiness (4-6 weeks)

- Cross-module executive dashboards
- Scheduled reports and PDF/Excel exports
- Data import/reconciliation tools
- Mobile/low-bandwidth optimisation
- UAT, training, and production deployment

---

## 11. Local Development Commands

### Getting Started

```bash
# Navigate to the project
cd PointERP

# Install dependencies
composer install
bun install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations and seeders
php artisan migrate:fresh --seed
```

### Daily Development

```bash
# Start development servers
php artisan serve          # Laravel backend
bun run dev                # Vite frontend with HMR

# Or use Laravel Herd (recommended)
# Herd serves the app automatically at http://pointerp.test
```

### Quality Checks

```bash
# Full test suite (lint + types + phpstan + tests)
composer test

# Individual checks
vendor/bin/pint --parallel --test          # PHP code style
vendor/bin/rector --dry-run               # PHP code improvements
vendor/bin/phpstan                         # Static analysis
bun run test:types                         # TypeScript checking
bun run test:lint                          # Frontend linting

# Run specific test suites
vendor/bin/pest tests/Feature/Operations/   # Phase 2 tests
vendor/bin/pest tests/Feature/Equipment/    # Phase 3A tests
vendor/bin/pest tests/Feature/Inventory/    # Phase 3B tests
```

### Database Management

```bash
# Fresh database with seed data
php artisan migrate:fresh --seed

# Seed specific seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=PointInvestmentSeeder

# Run specific migrations
php artisan migrate
```

### Frontend Development

```bash
# Type checking
bun run test:types

# Linting
bun run test:lint

# Formatting
NODE_OPTIONS='--experimental-strip-types' bunx vp fmt resources/
```

---

## Appendix A: Seeded Demo Data

### Tenant
- **Point Investment Co. Ltd** — Multi-branch tenant with USD base currency

### Branches
- **Uganda (KLA-HQ)** — Kampala headquarters, UGX base currency
- **South Sudan (JUB-HQ)** — Juba office, USD base currency

### Projects
- **Busunju-Kiboga-Hoima Road Rehabilitation** — Uganda, UNRA contract
  - Busunju Section (site)
  - Kiboga Section (site)
- **Juba Access Road Works** — South Sudan

### Equipment
- Motor Grader (EXC-GRD-001) — Owned
- Excavator (EXC-001) — Hired
- Vibratory Roller (EXC-ROL-001) — Owned
- Water Bowser (EXC-WBS-001) — Owned
- Tipper Truck (EXC-TIP-001) — Subcontractor
- Generator (EXC-GEN-001) — Owned

### Inventory Items
- Portland Cement 42.5N (CEM-42) — Batch tracked
- Crushed Aggregate 20mm (AGG-20) — Non-tracked
- Diesel Fuel (DSL-001) — Fuel related
- Reinforcement Steel (STL-REBAR) — Non-tracked
- PPE Safety Vest (PPE-VEST) — Consumable

---

## Appendix B: Troubleshooting

### Common Issues

**Blank Screen:**
- Check browser console for JavaScript errors
- Common cause: missing component imports
- Run `bun run test:lint` to catch undefined references

**403 Forbidden:**
- Verify your user has the required permission
- Check your branch assignment
- Ensure you're logged in as the correct user

**Data Not Appearing:**
- Check branch selector — you may be filtered to a different branch
- Verify the record belongs to your tenant
- Check active/inactive tab toggle

**Test Failures:**
- Run `php artisan migrate:fresh --seed` to reset database
- Ensure no other process is using the database
- Check `.env` for correct database credentials

### Getting Help

- Check the audit trail for recent changes
- Review notifications for pending actions
- Consult the phase documents in the repository for architecture details

---

*This manual reflects the system state as of August 2026. For the latest status, check the `PROJECT_ROADMAP.md` file in the repository.*
