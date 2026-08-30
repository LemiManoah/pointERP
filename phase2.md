# Construction ERP - Phase 2 Implementation Plan

## Instructions for Codex

Implement Phase 2 of the Point Investment construction ERP inside the existing Laravel Inertia/React application.

This phase builds on Phase 1. Do not weaken the tenant, branch, permission, audit, toast, confirmation modal, sidebar, UUID, strict typing, Pest, PHPStan, Pint, Rector, OxLint or Oxfmt conventions already established.

The ERP and manager app share a database. PointERP owns shared migrations. The manager app remains for the support team; operational tenant work belongs in PointERP.

Do not implement every ERP module in this phase. The SRS says the MVP pilot must run one real project end to end and should favour reliable workflows, evidence and exception reporting over shallow screens. Phase 2 should therefore create the operational backbone for projects, sites, documents and daily site reporting.

---

## 1. Phase objective

Build the first real construction operations slice:

1. Customers and basic contracts.
2. Projects and sites.
3. Project/site user access.
4. Project milestones, activities and BOQ/progress basis.
5. Controlled document upload, classification and versioning.
6. Daily Site Reports (DSRs).
7. DSR review, approval, return and missing-report handling.
8. Project/site exception dashboards.
9. Notifications for assignments, missing reports, returns and approvals.
10. Audit trail coverage for controlled changes.

At the end of Phase 2, Point Investment should be able to configure one Uganda pilot project, create its sites, assign users, upload key project documents, submit daily site reports, review/approve them, see missing reports and drill from dashboard exceptions back to the source records.

---

## 2. SRS source requirements

Phase 2 is based primarily on these SRS requirements:

```text
ADM-001 to ADM-004
DOC-001 to DOC-007
PRJ-001 to PRJ-009
COM-001 to COM-005
RPT-001 to RPT-006
SEC-003 to SEC-006
NFR-001, NFR-002, NFR-006, NFR-009, NFR-010, NFR-011
```

It also prepares for later HR, equipment, inventory, finance, HSE, environmental and social modules by giving those modules a project/site/document/reporting spine to attach to.

---

## 3. Legacy spreadsheet evidence

The reviewed Excel files show how Point Investment or its contractor-side teams have been handling site reporting, costing and payment support outside the ERP.

Sources reviewed:

```text
Rev Daily costing 7th  DECEMBER 2024.xlsx
BKH - IPC No. 3 Draft 2024.xlsx
```

### 3.1 Deductions from the daily costing workbook

The daily costing workbook uses one sheet per reporting date plus a monthly summary. Each daily sheet combines:

- Project identity and report date.
- Work progress summary with narrative works done today.
- Key materials used, especially diesel and petrol.
- Key salient, operational and contractual issues.
- Output/revenue by BOQ item.
- Today's quantity of work done.
- BOQ quantity, unit and site BOQ rate.
- Today's revenue, previous cumulative revenue and cumulative revenue to date.
- Labour and wages.
- Plant/equipment daily cost, including individual equipment identifiers.
- Fuel distribution and material costs.
- Subcontractors, petty cash, allowances, overheads and other direct daily costs.
- Mobilisation and demobilisation costs.
- Daily profit/loss and cumulative profit/loss.
- Prepared/checked/received sign-off roles: costing engineer, project manager, technical director and managing director.

Important design implication: the company's "daily report" is partly operational and partly commercial. Phase 2 should not reduce DSRs to a text diary. It must capture structured daily work quantities and resource/cost summaries, even if final inventory, fleet, HR payroll and finance ledgers come later.

### 3.2 Deductions from the IPC workbook

The IPC workbook contains payment certificate and measurement logic:

- Contract metadata: employer, supervisor, contractor, contract number, period, commencement/completion dates and revised contract sum.
- Certificate summary: previous certificate, this certificate, total to date, retention, VAT, previous payments, provisional sums, claims and net amount due.
- Grand summary grouped by bill sections.
- Main bill lines with original quantities, rates, measured previous quantity, this-period quantity, total-to-date quantity and payment values.
- Measurement sheets by BOQ item.
- Measurement details include date, chainage from/to, side, description of works, hours, number of labourers/machines, total hours, litres, bar marks, dimensions, RFI/RFA references and remarks/sketches.
- Manual approval blocks for contractor, measurement engineer, project engineer and other client-side approvers.
- Existing formulas contain broken references in some certificate cells, which is a strong signal that ERP calculations should be normalized, auditable and protected from accidental spreadsheet formula breakage.

Important design implication: Phase 2 DSR quantities should be compatible with later IPC/payment workflows. A daily work quantity should be able to reference BOQ item, chainage, side, location, date, evidence and approval state. Later IPCs should aggregate approved quantities rather than manually copy spreadsheet totals.

### 3.3 Changes to keep in Phase 2

Phase 2 should include these fields earlier than originally planned:

- Chainage from and chainage to on DSR work/quantity lines.
- Side/location marker, such as LHS, RHS or full width.
- BOQ item reference on DSR work/quantity lines.
- Unit, quantity, rate snapshot and calculated value for daily work output.
- Separate previous cumulative and cumulative-to-date calculations in reporting views.
- DSR cost summary groups: labour, plant, materials, subcontractors/overheads and mobilisation/demobilisation.
- Fuel quantities split by fuel type.
- Equipment/resource identifier on plant lines where known.
- Prepared, reviewed, checked and approved timestamps/users mapped to real ERP users.
- Explicit note that some DSR/cost figures may be provisional until finance/procurement/fleet modules validate them.

### 3.4 Record for later phases

The IPC workbook should directly inform a later commercial/finance phase:

- Interim Payment Certificates.
- BOQ measurement books.
- Valuations and certified quantities.
- Retention, VAT, previous payments, claims, provisional sums and net amount due.
- Client-side approval workflows.
- Exportable certificate packs.

The daily costing workbook should also inform later resource modules:

- Equipment/fleet phase: plant identifiers, day rates, utilisation hours, fuel usage and mobilisation.
- Inventory/procurement phase: material quantities, rates, issues and supplier/subcontractor references.
- Finance phase: daily cost capture, petty cash, allowances, overheads, profit/loss and cumulative project cost.
- HR phase: labour categories, staff/casual counts, hours and wage costing.

### 3.5 Lessons from BauMaster and civil-engineering references

The BauMaster feature overview validates the product direction: construction software works best when it is organised around field capture, project control, searchable management records and collaboration. BauMaster treats photos, tasks, reports, documents, drawings, plan markers, search, costs, schedules, sharing and offline use as connected parts of the same construction memory.

Civil-engineering site diary and daily report references also point to the same core fields:

- Project identification, report date, author and sign-off.
- Weather and site conditions.
- Labour by trade/subcontractor, headcount and hours.
- Plant/equipment, working/idle status and hours.
- Materials delivered or used, delivery references and rejected quantities.
- Work completed by location, activity and quantity.
- Delays, instructions, visitors, safety observations and photos.
- Programme/activity references where possible.
- Contemporaneous same-day entry, because retrospective records lose evidential value.

RICS interim valuation guidance confirms that later payment workflows must support valuation process, payment mechanisms, retention, contract forms and standard payment documentation. This reinforces the IPC direction: approved DSR quantities should eventually feed measurement, valuation and payment certificates.

References used for design validation:

```text
https://bau-master.com/gb/features-overview/
https://www.rics.org/profession-standards/rics-standards-and-guidance/sector-standards/construction-standards/black-book/interim-valuations-and-payment
https://www.gatherinsights.com/en/site-diary/template
https://www.maptrack.com/templates/daily-site-report-template
https://www.projesttcc.com/construction-daily-report-example
```

### 3.6 Senior architect recommendations

Phase 2 should be treated as a construction operations product, not an administration module.

Recommended architecture principles:

1. Build around the daily field record.
   Projects, sites, BOQ items, documents, photos, tasks, drawings, quantities, resources and approvals should converge in the DSR. The DSR is the operational event stream for later equipment, inventory, HR and finance.

2. Separate operational facts from commercial certification.
   A DSR may capture provisional quantities and costs. Payment certificates, final valuations and posted finance entries are later controlled records that consume approved source data.

3. Store structured data before dashboards.
   Dashboards should be thin projections over trustworthy records. Do not hardcode dashboard numbers that cannot drill back to DSR, BOQ, document, site or approval source records.

4. Design for mobile and low bandwidth from the first DSR form.
   Field users should be able to save drafts quickly, upload evidence later, and avoid repeated typing through templates and previous-day copy.

5. Make search and filters part of the data model.
   Project, site, date, chainage, side, BOQ item, activity, status, responsible user, document type and evidence tags should be queryable, not buried in descriptions.

6. Use clear state machines.
   DSRs, documents, drawings, milestones and approvals must have explicit statuses and permitted transitions. Approved records should be corrected through controlled follow-up records, not silent edits.

7. Prepare for drawings without building a full drawing engine now.
   Store drawing/document versions cleanly in Phase 2B. Add plan markers later after project/site/DSR/document foundations are stable.

8. Prepare for offline without building full offline sync now.
   Use compact mobile forms, draft saves, upload retries, idempotent create/update actions and server-generated references so a later offline package has a stable base.

9. Treat external collaborators as future users, not current scope.
   BauMaster's contractor collaboration is useful, but PointERP should first prove internal Point Investment workflows. External planner/contractor access can be a later extension after document permissions mature.

10. Do not let Phase 2 become the whole ERP.
    Phase 2 should produce a reliable project/site/DSR/document spine. Equipment, inventory, HR, finance and HSE can attach to it in later focused slices.

---

## 4. Domain terminology

Use these terms consistently:

| Term | Meaning |
|---|---|
| Customer | The external client or contracting party for whom work is performed. |
| Contract | A commercial agreement linked to a customer and one or more projects. |
| Project | A controlled body of construction work under a tenant and branch. |
| Site | A physical work location under a project. |
| Activity | A planned work item, BOQ item or milestone-supporting task. |
| Daily Site Report / DSR | Structured report for a site/date covering work, labour, plant, materials, delays, visitors, safety, environment, social notes and evidence. |
| Expected DSR | A report obligation generated from a site's reporting calendar. |
| Missing DSR | An expected report not submitted by its configured deadline. |
| Returned DSR | A submitted/reviewed report sent back with a required reason. |
| Chainage | Linear road/project location marker, usually captured as from/to positions. |
| Side | Road/work side or location marker such as LHS, RHS or full width. |
| BOQ item | Contract bill item used for measured work, progress and later payment certification. |
| IPC | Interim Payment Certificate, a later commercial document aggregating approved measured work and deductions. |
| Project access | User permission to see or operate on a project. |
| Site access | User permission to see or operate on a site. |

Keep Phase 1 naming:

- Use `tenant`, not company, for the SaaS data boundary.
- Use `branch`, not country/business unit, for the operational office.
- Use `project` and `site` for delivery scope below the branch.

---

## 5. Scope

### 5.1 In scope

- Customer CRUD.
- Contract CRUD with core fields and linked documents.
- Project CRUD.
- Site CRUD.
- Project/site assignments for users.
- Project statuses and site statuses.
- Project milestones.
- Project activities or BOQ/progress items.
- Basic progress entry based on approved quantities/milestones.
- Chainage/side-aware DSR work quantity lines.
- Daily output and cost summaries for pilot reporting.
- Document types, document records and document versions.
- File upload storage with metadata and permission checks.
- DSR creation, draft saving, submission, review, approval, return and missing status.
- Expected DSR calendar generation from active sites.
- Configurable reporting deadline and non-working days.
- Missing DSR scheduled command.
- In-app notifications for DSR exceptions and review actions.
- Email notification hooks where mail is configured, with delivery status prepared for later.
- Project/site dashboards for report compliance, approvals and missing evidence.
- Policies, requests, actions, query scopes, factories, seeders and tests.
- Audit events for project/site/document/DSR changes and approvals.

### 5.2 Explicitly out of scope

- Full HR leave and attendance workflows.
- Full equipment register, fuel, maintenance and transfer workflows.
- Full inventory/procurement ledgers.
- Full finance transactions, statutory accounting or tax filing.
- GPS, biometric devices, sensors, WhatsApp ingestion and real-time chat.
- Advanced planning, forecasting, AI progress prediction and custom report builder.
- Electronic signatures and advanced OCR.
- Client/community portal.
- Full IPC/payment certificate generation.
- Full BOQ measurement book approval and client certification.

DSRs may capture labour/equipment/material summaries in this phase, but those lines must not pretend to be the final HR, fleet or stock ledgers.

---

## 6. Architectural rules

### 6.1 Tenancy and branch scope

Every tenant-owned Phase 2 model must use the established tenant isolation pattern.

Project and site queries must check:

- Correct tenant.
- Correct branch.
- User permission.
- Project/site assignment where required.
- Record state.

Do not rely on hidden buttons. A user with no permission must receive 403 from the server.

### 6.2 Project and site access

Branch access is necessary but not always sufficient.

Recommended default:

- Directors with the correct permission can view all projects/sites inside the tenant and authorised branches.
- Project managers can manage assigned projects.
- Site managers/engineers can work on assigned sites.
- Auditors can read assigned or permission-scoped project/site evidence.

Create explicit project/site access tables rather than overloading branch membership.

### 6.3 Status integrity

Controlled records must use explicit statuses and transitions.

DSR statuses:

```text
draft
submitted
reviewed
approved
returned
missing
archived
```

Project statuses:

```text
planned
active
on_hold
completed
closed
archived
```

Site statuses:

```text
planned
active
suspended
completed
closed
archived
```

Approved records should not be silently edited. Corrections must be additive, audited and permission-controlled.

### 6.4 Attachments and documents

Do not scatter file uploads across unrelated tables.

Use a central document/version model that can link to authorised records. DSR photos and evidence should either create document records or attach through a consistent polymorphic link that still enforces document permissions.

### 6.5 Audit trail

Audit these Phase 2 events:

- Customer and contract changes.
- Project and site changes.
- Project/site user assignment changes.
- Document upload, version replacement, approval, expiry and archive.
- DSR submission, review, approval, return and missing status.
- Progress quantity approval or correction.
- Reporting deadline/configuration changes.

Store actor, tenant, branch, event, record type, record ID, old values, new values, reason, IP/user agent and timestamp using the Phase 1 audit foundation.

---

## 7. Proposed database design

Adjust names only where needed to preserve repository conventions.

### 7.1 `customers`

```text
id                 uuid primary key
tenant_id          uuid foreign key
branch_id          uuid nullable foreign key
name               string
code               string
email              string nullable
phone              string nullable
address            text nullable
status             string default active
created_by         uuid nullable
updated_by         uuid nullable
created_at
updated_at
deleted_at
```

### 7.2 `contracts`

```text
id                  uuid primary key
tenant_id           uuid foreign key
branch_id           uuid foreign key
customer_id         uuid foreign key
reference           string
title               string
scope_summary       text nullable
contract_value      decimal(20, 4) nullable
currency_code       char(3)
starts_on           date nullable
ends_on             date nullable
retention_percent   decimal(8, 4) nullable
payment_terms       text nullable
status              string default draft
created_by          uuid nullable
updated_by          uuid nullable
created_at
updated_at
deleted_at
```

### 7.3 `projects`

```text
id                   uuid primary key
tenant_id            uuid foreign key
branch_id            uuid foreign key
customer_id          uuid nullable foreign key
contract_id          uuid nullable foreign key
reference            string
name                 string
description          text nullable
manager_id           uuid nullable foreign key users
base_currency_code   char(3)
budget_amount        decimal(20, 4) nullable
starts_on            date nullable
ends_on              date nullable
reporting_deadline   time nullable
status               string default planned
created_by           uuid nullable
updated_by           uuid nullable
created_at
updated_at
deleted_at
```

Rules:

- Project reference is unique per tenant.
- Project branch must belong to the current tenant.
- Project currency must be enabled for the tenant and branch.
- Project manager must belong to the same tenant and have access to the project branch.

### 7.4 `sites`

```text
id                  uuid primary key
tenant_id           uuid foreign key
branch_id           uuid foreign key
project_id          uuid foreign key
reference           string
name                string
location_name       string nullable
latitude            decimal(10, 7) nullable
longitude           decimal(10, 7) nullable
manager_id          uuid nullable foreign key users
reporting_deadline  time nullable
status              string default planned
created_by          uuid nullable
updated_by          uuid nullable
created_at
updated_at
deleted_at
```

Rules:

- Site branch and project branch must agree unless an explicit cross-branch project rule is approved later.
- A site can override the project reporting deadline.

### 7.5 `project_user`

```text
project_id       uuid foreign key
user_id          uuid foreign key
role             string nullable
can_manage       boolean default false
created_at
updated_at
```

### 7.6 `site_user`

```text
site_id          uuid foreign key
user_id          uuid foreign key
role             string nullable
can_submit_dsr   boolean default false
can_review_dsr   boolean default false
created_at
updated_at
```

Rules:

- User, project/site and branch must belong to the same tenant.
- Site assignment requires access to the related project or a permission-managed override.

### 7.7 `project_milestones`

```text
id              uuid primary key
tenant_id       uuid foreign key
branch_id       uuid foreign key
project_id      uuid foreign key
name            string
description     text nullable
planned_date    date nullable
completed_on    date nullable
status          string default pending
sort_order      unsigned integer default 0
created_by      uuid nullable
updated_by      uuid nullable
created_at
updated_at
deleted_at
```

### 7.8 `project_activities`

```text
id                  uuid primary key
tenant_id           uuid foreign key
branch_id           uuid foreign key
project_id          uuid foreign key
site_id             uuid nullable foreign key
milestone_id        uuid nullable foreign key
code                string nullable
name                string
boq_item_number     string nullable
unit                string nullable
planned_quantity    decimal(20, 4) nullable
approved_quantity   decimal(20, 4) default 0
rate_amount         decimal(20, 4) nullable
currency_code       char(3) nullable
status              string default active
sort_order          unsigned integer default 0
created_by          uuid nullable
updated_by          uuid nullable
created_at
updated_at
deleted_at
```

### 7.9 `document_types`

```text
id                    uuid primary key
tenant_id             uuid nullable foreign key
name                  string
code                  string
requires_expiry_date  boolean default false
is_confidential       boolean default false
is_active             boolean default true
created_at
updated_at
```

Allow tenant-specific document types later, but seed global/system defaults first.

### 7.10 `documents`

```text
id                uuid primary key
tenant_id         uuid foreign key
branch_id         uuid nullable foreign key
document_type_id  uuid foreign key
owner_id          uuid nullable foreign key users
title             string
reference         string nullable
description       text nullable
document_date     date nullable
expires_on        date nullable
confidentiality   string default normal
status            string default active
created_by        uuid nullable
updated_by        uuid nullable
created_at
updated_at
deleted_at
```

### 7.11 `document_versions`

```text
id             uuid primary key
tenant_id      uuid foreign key
document_id    uuid foreign key
version_number unsigned integer
disk           string
path           string
original_name  string
mime_type      string
size_bytes     unsigned big integer
checksum       string nullable
notes          text nullable
uploaded_by    uuid foreign key users
uploaded_at    timestamp
created_at
updated_at
```

### 7.12 `document_links`

```text
id                 uuid primary key
tenant_id          uuid foreign key
document_id        uuid foreign key
linkable_type      string
linkable_id        uuid
created_by         uuid nullable
created_at
updated_at
```

### 7.13 `daily_site_reports`

```text
id                    uuid primary key
tenant_id             uuid foreign key
branch_id             uuid foreign key
project_id            uuid foreign key
site_id               uuid foreign key
report_date           date
reference             string
weather               string nullable
work_summary          text nullable
delay_summary         text nullable
visitor_summary       text nullable
hse_notes             text nullable
environment_notes     text nullable
social_notes          text nullable
completion_percent    decimal(8, 4) nullable
output_value          decimal(20, 4) nullable
input_cost            decimal(20, 4) nullable
profit_loss           decimal(20, 4) nullable
status                string default draft
expected_at           timestamp nullable
submitted_by          uuid nullable foreign key users
submitted_at          timestamp nullable
reviewed_by           uuid nullable foreign key users
reviewed_at           timestamp nullable
approved_by           uuid nullable foreign key users
approved_at           timestamp nullable
returned_by           uuid nullable foreign key users
returned_at           timestamp nullable
return_reason         text nullable
created_by            uuid nullable
updated_by            uuid nullable
created_at
updated_at
deleted_at
```

Rules:

- Unique DSR per tenant/site/report_date for active records.
- Only draft and returned reports are editable by submitters.
- Approved reports require correction records, not silent edits.
- Return requires a reason.

### 7.14 DSR line tables

Create focused line tables rather than JSON blobs for pilot-critical reporting:

```text
daily_site_report_labour_lines
daily_site_report_equipment_lines
daily_site_report_material_lines
daily_site_report_work_lines
daily_site_report_delay_lines
```

Each line must include:

```text
id
tenant_id
branch_id
daily_site_report_id
description/name
boq_item_number nullable where applicable
chainage_from nullable where applicable
chainage_to nullable where applicable
side nullable where applicable
quantity/count/hours where applicable
unit where applicable
rate_amount nullable where applicable
amount nullable where applicable
currency_code nullable where applicable
notes nullable
sort_order
created_at
updated_at
```

These are reporting summaries in Phase 2. Later HR, equipment and inventory modules may replace free-text fields with strict foreign keys and ledger integration.

Other site costs are not stored in a separate DSR line table. They create normal expense drafts linked by `expenses.daily_site_report_id`, then follow the expense approval and payment workflow.

### 7.15 `expected_daily_site_reports`

```text
id                 uuid primary key
tenant_id          uuid foreign key
branch_id          uuid foreign key
project_id         uuid foreign key
site_id            uuid foreign key
report_date        date
deadline_at        timestamp
status             string default expected
daily_site_report_id uuid nullable foreign key
notified_at        timestamp nullable
escalated_at       timestamp nullable
created_at
updated_at
```

Statuses:

```text
expected
submitted
missing
closed
waived
```

---

## 8. Minimum permissions

Seed permissions idempotently so the seeder can be run again in production to add new permissions.

```text
customers.view
customers.manage
contracts.view
contracts.manage
projects.view
projects.create
projects.update
projects.archive
projects.view-all
sites.view
sites.create
sites.update
sites.archive
sites.view-all
project-users.manage
site-users.manage
documents.view
documents.upload
documents.update
documents.archive
documents.view-confidential
daily-site-reports.view
daily-site-reports.create
daily-site-reports.update
daily-site-reports.submit
daily-site-reports.review
daily-site-reports.approve
daily-site-reports.return
daily-site-reports.manage-missing
dashboards.projects.view
```

Suggested role updates:

- Director: all Phase 2 view permissions, approval permissions, dashboards and cross-project/cross-site access.
- Administrator: all Phase 2 management permissions except business approvals if segregation is required.
- Project Manager: assigned project management, DSR review/approval, project dashboard.
- Site Manager / Engineer: assigned site DSR create/update/submit and document upload.
- HSE / Environment Officer: assigned project/site DSR view plus HSE/environment note review.
- Auditor / Viewer: read-only evidence, DSR and audit visibility.

---

## 9. Backend components

Use actions, thin controllers and form requests.

Suggested action names:

```text
CreateCustomer
UpdateCustomer
ArchiveCustomer
CreateContract
UpdateContract
ArchiveContract
CreateProject
UpdateProject
ArchiveProject
CreateSite
UpdateSite
ArchiveSite
AssignProjectUsers
AssignSiteUsers
CreateProjectMilestone
UpdateProjectMilestone
CreateProjectActivity
UpdateProjectActivity
UploadDocumentVersion
LinkDocumentToRecord
CreateDailySiteReport
UpdateDailySiteReportDraft
SubmitDailySiteReport
ReviewDailySiteReport
ApproveDailySiteReport
ReturnDailySiteReport
GenerateExpectedDailySiteReports
MarkMissingDailySiteReports
EscalateMissingDailySiteReports
```

Policies:

```text
CustomerPolicy
ContractPolicy
ProjectPolicy
SitePolicy
ProjectMilestonePolicy
ProjectActivityPolicy
DocumentPolicy
DailySiteReportPolicy
ExpectedDailySiteReportPolicy
```

Each policy must check tenant first, then branch, permission, assignment and record state.

---

## 10. Inertia and React pages

Follow the professional page patterns already in the app:

- Page heading and description at the top.
- Search/filter controls under the heading on the left.
- Add/new action on the extreme right.
- Active/inactive or open/archived lists in separate tabs.
- Modal create/edit forms where the workflow is compact.
- Full pages or drawers for large, evidence-heavy workflows like DSR editing.
- Global confirmation modal for destructive/status-changing actions.
- Sonner toasts for success/failure/info/warning.
- Comboboxes for long searchable dropdowns.

Required pages:

```text
/customers
/contracts
/projects
/projects/{project}
/projects/{project}/sites
/sites/{site}
/documents
/daily-site-reports
/daily-site-reports/{dailySiteReport}
/project-dashboard
```

Important UI notes:

- Do not put DSR editing inside a cramped modal.
- DSR forms must work well on Android-sized screens.
- Upload controls must show progress/retry states.
- Report direction/status must be obvious: Draft, Submitted, Reviewed, Approved, Returned, Missing.
- Dashboards must show missing data distinctly from zero activity.

---

## 11. Required workflows

### 11.1 Project setup

1. Create customer.
2. Create contract or upload existing contract document.
3. Create project under a tenant branch.
4. Create one or more sites.
5. Assign project manager and site users.
6. Add milestones/activities/BOQ items.
7. Set reporting deadline and working/non-working calendar.
8. Upload baseline documents.

### 11.2 Daily site reporting

1. Expected DSR is generated for each active site/reporting day.
2. Site manager opens today's expected report.
3. User saves draft with work, chainage/side, BOQ quantities, labour, equipment, materials, fuel, delays, HSE, environment, social notes and evidence.
4. User submits the DSR.
5. Reviewer reviews and either approves or returns with reason.
6. Returned report becomes editable again.
7. Approved report locks ordinary edits.
8. Dashboard updates compliance and outstanding approvals.

### 11.3 Missing report escalation

1. Scheduled command checks expected reports after deadline.
2. Unsubmitted expected reports are marked Missing.
3. Owner receives in-app notification.
4. After two consecutive missing reporting days, the project manager and configured executive/HSE recipients are notified.
5. Dashboard shows missing reports by project/site and owner.

### 11.4 Document control

1. User uploads a document with type, date, project/site tags, confidentiality and optional expiry.
2. Document is linked to project/site/contract/DSR or another authorised record.
3. A new upload creates a new version, not a silent file replacement.
4. Superseded versions remain visible to authorised users.
5. Expiring documents appear on exception dashboards and notifications.

---

## 12. Factories and seeders

Create realistic factories for every new model.

Extend seeders so UI testing can show real separation:

- One Point Investment tenant.
- Uganda Branch with at least one active pilot project.
- South Sudan Branch with a separate project for branch isolation tests.
- A customer and contract for each branch.
- Two sites under the Uganda pilot project.
- One active site under the South Sudan project.
- Site manager assigned only to one Uganda site.
- Project manager assigned to Uganda project.
- Director with cross-branch/cross-project visibility.
- Auditor/viewer with read-only access.
- Several DSR examples:
  - draft
  - submitted
  - approved
  - returned
  - missing expected report
- DSR work lines with BOQ items, chainage from/to and side.
- DSR cost summaries showing labour, plant, materials, fuel and overhead examples.
- Seeded documents:
  - contract
  - drawing
  - permit with expiry
  - DSR photo/evidence

Do not make all seeded users super admins. The UI should prove branch, project, site and role separation.

---

## 13. Pest test plan

### 13.1 Tenant and branch isolation

- Tenant A cannot view Tenant B projects, sites, contracts, documents or DSRs.
- Branch-restricted users cannot access another branch's projects/sites.
- `projects.view-all` and `sites.view-all` never cross tenant boundaries.
- Cross-tenant UUID guesses return 403 or non-disclosing 404 according to policy convention.

### 13.2 Project and site access

- Assigned project manager can manage assigned project.
- Site manager can submit DSR only for assigned site.
- User with branch access but no project/site assignment cannot mutate project/site records unless permission allows it.
- Removing assignment immediately blocks access.

### 13.3 Document security

- Unauthorised users cannot download files.
- Confidential documents require `documents.view-confidential`.
- Version uploads preserve history.
- Expiry reminders are generated for expiring documents.

### 13.4 DSR workflow

- Draft can be edited by authorised submitter.
- Submitted report cannot be edited by ordinary submitter.
- Return requires reason.
- Approved report locks ordinary edits.
- Missing report command marks overdue expected reports.
- Two consecutive missed days escalates once to the configured recipients.
- DSR line items preserve tenant and branch IDs.
- DSR work quantities calculate daily output from quantity and rate snapshots.
- DSR cumulative reporting distinguishes previous, today and total-to-date values.
- Chainage from/to and side are stored and visible on DSR work lines.

### 13.5 Audit and notifications

- Project/site assignment changes are audited.
- DSR submit/approve/return/missing events are audited.
- Document upload/version/archive events are audited.
- Notifications are created for missing reports, returned reports and pending approvals.

### 13.6 UI/Inertia

- Pages return expected components and props.
- Filters use URL query parameters.
- Active/inactive or open/archived tabs do not mix inactive records into active lists.
- Permission-gated actions are absent from props and rejected server-side.

---

## 14. Implementation order

1. Confirm Phase 1 suite status and fix any regressions.
2. Add customers, contracts, projects and sites models/migrations/factories.
3. Add policies, requests, actions and CRUD pages for customers/projects/sites.
4. Add project/site user assignment.
5. Seed the Uganda pilot project and separated branch/user examples.
6. Add document types, documents, versions and links.
7. Add upload/download policies and document pages.
8. Add DSR models, line tables and draft CRUD.
9. Add DSR submit/review/approve/return actions and policies.
10. Add expected DSR generation and missing-report scheduled command.
11. Add notifications for DSR workflow and document expiry.
12. Add project/site dashboards and exports.
13. Add audit coverage across Phase 2 workflows.
14. Add full feature/unit/Inertia tests.
15. Run `composer test` and frontend lint/format checks.
16. Update documentation and handoff notes.

---

## 15. Recommended build slices

### Phase 2A - Project and Site Foundation

Build customers, contracts, projects, sites, assignments, seed data and basic project dashboard shells.

This is the safest first slice because every later module depends on project/site scope.

### Phase 2B - Documents and Evidence

Build document types, uploads, versions, links, permissions and expiry reminders.

This gives the business immediate value by replacing scattered files and creates the attachment foundation for DSRs, contracts, HSE, procurement and finance.

### Phase 2C - Daily Site Reports

Build DSR drafts, submission, review, approval, return, line items and evidence links.

This is the main pilot workflow and should receive careful mobile UI testing.

Include chainage/side, BOQ quantity lines, fuel/material/plant/labour summaries and daily output/cost/profit reporting in this slice.

### Phase 2D - Missing Reports and Dashboards

Build expected-report calendars, missing report job, escalation notifications, dashboard widgets and drill-down reports.

This turns data entry into management control.

---

## 16. Definition of done

Phase 2 is complete only when:

- One real pilot project can be configured end to end.
- Users can be assigned to project/site scope.
- Documents can be uploaded, versioned, linked, searched and permission-checked.
- DSRs can be drafted, submitted, reviewed, approved, returned and marked missing.
- DSRs capture daily work quantities against BOQ/activity items with chainage and side where applicable.
- DSRs capture pilot-level output/cost summaries without pretending to replace later finance ledgers.
- Missing DSRs are generated from expected reporting days and deadlines.
- Notifications and dashboards show missing reports and pending approvals.
- Approved/controlled records are not silently editable.
- Phase 2 actions appear in the audit trail.
- Tenant, branch, project and site isolation are proven by tests.
- Seeded users demonstrate real access separation in the UI.
- Strict backend and frontend checks pass.

---

## 17. Open decisions before implementation

These should be confirmed early but should not block database scaffolding where sensible defaults are safe:

- The first Uganda pilot project and sites.
- Daily DSR deadline and non-working days.
- Whether Saturdays are working days.
- Who can approve DSRs.
- Whether the same person can review and approve a DSR.
- Required DSR attachments by project/site.
- Initial document confidentiality classes and file size limits.
- Contract fields required for pilot acceptance.
- Whether progress is quantity-based, milestone-based or both for the pilot.
- Whether daily costing is required inside the DSR approval flow or reviewed separately by a costing engineer.
- Whether BOQ rates are visible to site users or restricted to project/commercial roles.
- Whether DSR profit/loss should be visible to project managers only, directors only or finance/commercial users.
- Email service/domain for notifications.

---

## 18. Suggested next step

Start with Phase 2A: customers, contracts, projects, sites and project/site assignments.

That gives the system a real operational hierarchy and lets the seeders demonstrate:

- Director sees all pilot work.
- Project manager sees assigned project work.
- Site manager sees only assigned site work.
- Auditor sees read-only evidence.
- Branch-restricted users cannot cross into another branch.

After Phase 2A passes tests, move to documents, then DSRs.
