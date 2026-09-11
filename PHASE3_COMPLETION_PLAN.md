# PointERP Phase 3 Completion and Pilot Hardening Plan

## 1. Purpose

This document defines the remaining implementation required to close the current Phase 3 work and prepare PointERP for a controlled quarry pilot.

It covers three workstreams:

1. Close the current implementation and regression work.
2. Complete Phase 3E Point of Sale.
3. Close the remaining operational gaps in inventory, procurement, estimation and Daily Site Reporting.

This is not a new functional phase. It consolidates unfinished and unverified work already assigned to Phases 3B, 3C and 3E.

## 2. Documentation Authority

Use the following sources in this order:

1. The approved SRS for business requirements.
2. `PROJECT_ROADMAP.md` for programme sequencing.
3. `phase3B.md`, `phase3C.md` and `phase3E.md` for domain rules.
4. This document for Phase 3 completion order and release gates.
5. Migrations and automated tests as executable evidence of implemented behaviour.

If this plan conflicts with an approved domain rule, update the conflict explicitly instead of silently changing behaviour.

## 3. Current Status

The functional foundations for equipment, inventory, procurement, estimation, expenses and POS exist. They are not considered complete for production because local regression, user-interface acceptance and pilot evidence are still pending.

Current important boundaries:

- Inventory balance is derived from immutable stock movements.
- Requisition approval reserves stock; store issue deducts stock.
- DSR material entry reports consumption while the report is a draft or awaiting approval.
- DSR approval posts each site-store material line once as an inventory issue; externally supplied material is recorded with a reason and never changes stock.
- Approved estimates form the project performance baseline.
- POS checkout deducts stock once and later customer payments do not move stock.
- Operational expenses and POS are sub-ledgers, not statutory accounting.

## 4. Cross-Cutting Rules

All work in this plan must follow these rules:

- Every record is tenant scoped.
- Operational records use a real branch, including single-branch tenants.
- Project and site records enforce assignment and permission policies.
- Server-side policies are authoritative; hidden buttons are not security.
- Approval authority is permission based. A creator may approve their own record only when they hold the relevant approval permission.
- Approved and posted records are corrected with additive events, returns or reversals. They are not silently edited.
- Quantity visibility and financial visibility remain separate.
- Stock must never become negative.
- Batch-tracked items require valid batch evidence at each movement boundary.
- Source keys make stock, payment and return posting idempotent.
- Private evidence is downloaded through an authorized controller, not a public storage URL.
- Audit activity remains separate from workflow history and user comments.
- New database enums are PHP enum classes. Database columns store enum values as strings.
- The ERP application owns shared migrations. The manager application must not create competing migrations.

# Workstream 1: Current Implementation Closure

## 5. Objective

Create a stable, reproducible technical baseline before adding more features. This workstream closes current requisition, stock handover, seeder, migration, policy and quality-suite work.

## 6. Chunk 1.1: Freeze and Reproduce the Baseline

### Tasks

- Record the expected PHP, Laravel, Bun, Node, MySQL and SQLite versions.
- Confirm the default seeder path is:
  - `RolePermissionSeeder`;
  - `QuarryDemoSeeder`;
  - `QuarryWorkItemTemplateSeeder`.
- Keep broad regression seeders available but do not register them in the default client demo path.
- Run `migrate:fresh --seed` against an isolated MySQL database.
- Confirm the fresh schema contains every table required by Phase 1 through Phase 3E.
- Confirm migrations use explicit short index and foreign-key names where MySQL could exceed identifier limits.
- Verify the default seed creates one tenant, one branch, one quarry project, its operating sites, one store and the approved demo users.
- Capture the first failing test from each quality command before making additional behavioural changes.

### Deliverables

- Reproducible fresh database.
- Stable default demo seed.
- List of failures grouped as schema, static analysis, backend tests, frontend types, lint or browser/UI.

### Acceptance

A new developer can create the same database and demo state with one documented command sequence.

## 7. Chunk 1.2: Requisition Availability and Reservation Integrity

### Existing Behaviour

- Draft requisitions request stock from one source store.
- Submission does not deduct stock.
- Approval verifies current availability and creates reservations.
- Partial issue deducts stock and reduces the open reservation.
- Returns create additive stock movements.

### Remaining Tasks

- Make shortage tests create their own deterministic stock condition instead of depending on mutable demo balances.
- Recheck availability under a database lock during approval.
- Reject approval when the requested approved quantity exceeds current available stock.
- Recheck physical on-hand and batch availability during issue.
- Prevent concurrent approvals from reserving the same available quantity twice.
- Preserve reservation history after partial and complete issue.
- Release only open reservation quantities when a requisition is cancelled.
- Confirm repeated source keys return the existing result instead of posting another movement.
- Present actionable validation messages containing available, requested and approved quantities.

### Required Tests

- approval below available quantity;
- approval equal to available quantity;
- approval above available quantity;
- two approvals competing for the same stock;
- partial issue and remaining reservation;
- full issue and fulfilled reservation;
- cancellation releases only outstanding quantity;
- batch issue cannot exceed batch balance;
- duplicate issue source key is idempotent;
- unauthorized and out-of-scope issue requests return `403`.

### Acceptance

Approved reservations plus unreserved available stock never exceed physical on-hand stock, and no approved or issued workflow can create a negative balance.

## 8. Chunk 1.3: Store Issue and Handover Evidence

### Existing Implementation Pending Validation

The store issue workflow now captures:

- quantity and unit;
- batch where required;
- issue reason;
- receiver name snapshot;
- handover date and time;
- optional handover note;
- optional private handover document.

The receiver is a name snapshot rather than a required user account because a foreman, driver, subcontractor representative or casual worker may physically collect stock without having an ERP login.

### Remaining Tasks

- Validate receiver name, date, time and evidence file size/type server-side.
- Store evidence on the private disk.
- Authorize downloads through the requisition view policy.
- Verify the movement belongs to a line on the requested requisition before download.
- Show recorded-by and received-by as separate fields in issue history.
- Show handover time, note and evidence link in a consistent table.
- Audit stock posting, evidence attachment and evidence download separately.
- Add graceful `404` handling when an evidence file is missing from storage.
- Decide during pilot whether receiver signature capture is necessary. Do not implement signatures before that decision.

### Required Tests

- receiver and handover timestamp are required;
- optional evidence accepts approved formats and rejects oversized or unsupported files;
- evidence is absent from public storage;
- authorized requisition viewer can download evidence;
- user outside tenant, branch or project scope cannot download evidence;
- movement from another requisition returns `404`;
- issue audit includes actor, receiver, store, item, quantity and timestamp;
- evidence attachment and download create audit events.

### Acceptance

Every store issue identifies who recorded it and who physically received it, and any attached evidence remains private and auditable.

## 9. Chunk 1.4: Policy and Data-Isolation Audit

### Tasks

Review direct-route authorization for:

- tenant and branch reference data;
- users, roles and permissions;
- projects, sites and assignments;
- documents and downloads;
- DSR submission, review, approval and correction;
- equipment assignment, transfer, fuel and maintenance;
- inventory item, store, receipt, requisition, issue, transfer and reconciliation;
- estimates and cost visibility;
- expenses, payments and evidence;
- POS sales, payments and future returns.

For each significant endpoint, prove:

- unauthenticated access is rejected;
- missing permission returns `403`;
- wrong tenant returns `403` or scoped `404`;
- wrong branch or project/site assignment is rejected;
- invalid record state is rejected;
- restricted cost fields are not serialized to unauthorized users.

### Acceptance

The role matrix is enforced through policies and query scopes, not only through frontend controls.

## 10. Chunk 1.5: Quality and UI Regression Closure

### Backend Quality

- Resolve all PHPStan findings without suppressing legitimate errors.
- Reach the configured type-coverage threshold.
- Pass architecture tests without alternating between Rector and architecture-rule failures.
- Keep model casts and query scopes consistent with the repository architecture rules.
- Remove tests that rely on unstable collection ordering or mutable seeder totals.
- Confirm all file-backed tests use isolated fake storage.

### Frontend Quality

- Pass TypeScript compilation.
- Resolve hook dependency warnings rather than disabling rules.
- Verify comboboxes retain stable widths for long labels.
- Verify large dialogs fit phone and desktop viewports.
- Verify number inputs display thousands separators without changing submitted numeric values.
- Verify date fields use the shared date picker, with separate time inputs where time is meaningful.
- Verify confirmation dialogs and Sonner notifications are consistent.
- Verify tables scroll within their containers and do not widen the page.

### Acceptance

The full local quality suite passes from a clean checkout and all critical workflows pass the handover UI checklist.

# Workstream 2: Complete Phase 3E Point of Sale

## 11. Objective

Complete controlled sales returns and management reporting without turning POS into a full accounting, tax or cash-drawer system.

## 12. Existing POS Foundation

Already implemented, pending validation:

- sellable inventory catalogue;
- store and branch selection rules;
- cart and server-side price resolution;
- retail/wholesale price lists;
- unit conversion;
- FEFO batch allocation;
- atomic stock deduction;
- walk-in and known-customer sales;
- automatic partial-payment credit balance;
- later customer payments;
- immutable printable receipt;
- sales history.

Existing return foundations:

- `pos_returns`;
- `pos_return_lines`;
- `PosReturnStatus` with pending, approved and rejected;
- `PosReturnDisposition` with restock and damaged.

## 13. Chunk 2.1: Return Request

### User Flow

1. Open a completed sale receipt.
2. Select **Record return**.
3. See only sale lines with a remaining returnable quantity.
4. Enter quantity for one or more lines.
5. Select disposition:
   - Restockable;
   - Damaged / not restockable.
6. Enter a mandatory reason.
7. Optionally attach return evidence.
8. Submit the return for approval.

### Domain Rules

- A return cannot be created for a draft, cancelled or already-invalid sale.
- Returnable quantity equals sold quantity minus quantities in approved returns.
- Pending returns may be considered when warning about competing return requests, but approval must recheck approved quantities under a lock.
- Return price comes from the immutable sale-line snapshot, not the current item price.
- Discounts are apportioned consistently from the original sale line.
- Batch allocation should default to the batch originally sold.
- Users cannot return an item that was not present on the sale.
- The request does not change stock or customer balance.

### Components

- `StorePosReturnRequest`;
- `CreatePosReturn` action;
- return policy methods for create and view;
- return controller and routes;
- return form on the sale details page;
- return status and history on the receipt.

## 14. Chunk 2.2: Return Approval and Stock Posting

### Approval Flow

- `pending -> approved` or `pending -> rejected`;
- rejection requires a reason;
- self-approval is permitted only with `pos.returns.approve`;
- approval locks the sale, return, sale lines and relevant stock rows;
- repeated approval does not duplicate stock or refunds.

### Restockable Returns

- Post an inventory `return` movement into the original store.
- Restore the original batch where batch identity is known.
- Preserve original unit, conversion and price snapshots.
- Use one stable source key per return line.

### Damaged Returns

- Do not add damaged quantity to sellable on-hand stock in the first release.
- Preserve the physical return quantity and damaged disposition in the return record.
- Require a reason.
- Defer quarantine-store and repair workflows until required by the pilot.

### Sale Status

Add or derive clear sale return state:

- no return;
- partially returned;
- fully returned.

Do not overwrite the completed sale or delete its original stock movements.

## 15. Chunk 2.3: Credit Balance and Refund Handling

### Rules

For a known-customer sale with an unpaid balance:

1. Apply approved return value against the outstanding balance first.
2. Reduce `balance_due` by no more than that balance.
3. Only the return value above the outstanding balance is refundable to the customer.
4. Never refund more cash than the customer has actually paid after earlier refunds.

For a fully paid sale:

- the approved return value is eligible for refund;
- record refund method, amount, reference, actor and timestamp on the return;
- non-cash refunds require a reference;
- do not represent refunds as negative POS payments.

The first release may support one refund record per approved return using fields on `pos_returns`. A separate refund-allocation module belongs to Phase 4 if the client requires split refunds or accounting integration.

### Required Fields

Extend the return header where necessary with:

- customer balance reduction;
- actual refund amount;
- refund method;
- refund reference;
- refunded by;
- refunded at;
- rejection reason and rejected by/at.

## 16. Chunk 2.4: POS Permissions and Audit

Recommended permissions:

- `pos.returns.create`;
- `pos.returns.approve`;
- `pos.returns.reject`;
- `pos.returns.refund`;
- `pos.returns.view`;
- `pos.reports.view`;
- `pos.reports.export`.

Audit events:

- return requested;
- return approved;
- return rejected;
- stock restored;
- customer balance reduced;
- refund recorded;
- return evidence attached/downloaded.

Cost and margin information must remain hidden without the relevant cost permission.

## 17. Chunk 2.5: POS Reporting and Polish

### Reports

- sales by date, branch, store and cashier;
- sales by item and category;
- payment-method totals;
- paid, partially paid and unpaid sales;
- outstanding customer balances;
- collections received;
- returns by item and disposition;
- refunds and customer balance reductions;
- sellable-item low-stock list.

### Interface

- Keep the product list dense and scannable.
- Use the right-side cart drawer on narrow screens.
- Keep status as a searchable dropdown where tabs do not represent different tasks.
- Show return history on the receipt details page.
- Provide CSV and PDF exports using the same server-side filters.
- Ensure printed output is called a sales receipt, not a tax invoice.

### Required Tests

- partial and full return;
- over-return prevention;
- two pending returns competing for the same quantity;
- restock returns create one matching stock movement;
- damaged returns do not increase sellable stock;
- original batch restored correctly;
- rejected return changes no stock or balance;
- unpaid balance reduced before cash refund;
- refund cannot exceed net customer payment;
- duplicate approval is idempotent;
- unauthorized direct routes return `403`;
- tenant, branch and cashier visibility boundaries;
- cost fields omitted without permission;
- report totals reconcile to sales, payments and returns.

### Acceptance

Managers can reconcile each completed sale to payments, customer balance, stock issues, approved returns and refunds without altering the original receipt.

# Workstream 3: Operational Gap Closure

## 18. Objective

Complete the missing evidence, import and reconciliation paths needed for one quarry project to operate end to end.

## 19. Chunk 3.1: Purchase Order and Receipt Documents

### Purchase Order Output

- Generate a printable numbered PO only after approval.
- Include supplier, destination store, currency, line quantities, units, approved prices, terms and approval details.
- Prevent editing approved commercial snapshots.
- If an approved PO changes, create a controlled revision or cancel and replace it.
- Link the generated PO to document control where version history is required.

### Goods Receipt Evidence

- Allow the receiver to upload or link:
  - supplier delivery note;
  - supplier invoice;
  - inspection image;
  - rejection or damage evidence.
- Keep accepted and rejected quantities separate.
- Only accepted quantities post stock.
- Make the receipt printable and link back to its PO.
- Protect downloads using receipt and branch authorization.
- Audit evidence attachment, replacement and download.

### Required Tests

- PO output unavailable before approval;
- approved PO values remain immutable;
- receipt requires an approved PO;
- accepted quantity posts stock once;
- rejected quantity never enters on-hand;
- over-receipt is blocked;
- evidence is private and scope protected;
- partial receipts leave the PO open;
- final receipt or authorized close resolves the PO.

## 20. Chunk 3.2: Project BOQ / Estimate Import

### User Flow

1. Open a project estimate.
2. Select **Import BOQ / Estimate**.
3. Upload XLSX or CSV.
4. Select the worksheet where applicable.
5. Preview rows without writing to the database.
6. Map columns to:
   - BOQ reference;
   - Work Activity name;
   - description/specification;
   - unit;
   - planned quantity;
   - selling rate;
   - estimated unit cost;
   - optional category or section.
7. Resolve unknown units explicitly.
8. Review accepted rows, warnings and rejected rows.
9. Confirm import into a new draft estimate version.
10. Review and approve through the normal estimate workflow.

### Rules

- Never write imported rows directly into approved Work Activities.
- Never guess unit conversions.
- Normalize blank and merged spreadsheet cells during preview only.
- Detect duplicate BOQ references within the import and against the draft.
- Reject malformed numbers, negative quantities and unsupported currencies.
- Allow rows without BOQ references when names are unambiguous.
- Preserve external formatting only as source evidence, not as business logic.
- Retain the original spreadsheet as a private project document.
- Record uploader, filename, worksheet, checksum, row counts and confirmation time.
- Import must be atomic: either all confirmed accepted rows are saved or none are.

### Components

- import preview request and controller;
- workbook parser service;
- typed preview DTOs;
- column-mapping interface;
- unit-resolution step;
- confirm-import action;
- source document link;
- import audit record;
- downloadable rejection report.

### Required Tests

- valid XLSX and CSV import;
- worksheet selection;
- unknown unit requires explicit resolution;
- duplicate references;
- blank headers and malformed numbers;
- mixed currencies rejected or explicitly handled;
- preview creates no records;
- failed confirmation rolls back all rows;
- confirmed import creates a draft estimate;
- approval creates Work Activities with zero initial progress;
- source spreadsheet is private and linked;
- tenant/project isolation and direct-route `403`.

### Acceptance

A real client BOQ can become a reviewable draft estimate without manual re-entry, while approval remains the only path that creates executable Work Activities.

## 21. Chunk 3.3: DSR Workflow and Evidence Hardening

### Reporter Experience

- Show only projects and sites the reporter can access.
- Open the expected DSR for the selected site/date where one exists.
- Clearly distinguish draft, submitted, returned, approved and archived states.
- Organize the report into Summary, Progress, Resources and Costs & delays tabs without changing the underlying workflow.
- Mark required fields with a red asterisk.
- Allow editable line rows to be added and removed before submission.
- Preserve server validation errors beside the relevant section and row.
- Use searchable master-data dropdowns for Work Activities, inventory items, units, equipment and subcontractor companies.
- Keep free-text snapshots for historical readability.

### Submission Completeness

Before submission, validate configurable requirements for:

- work summary or measured Work Activity quantity;
- report date and site;
- weather where required;
- labour and subcontractor attendance where required;
- equipment readings for selected equipment;
- material quantities and valid units;
- delay descriptions and hours;
- evidence for configured high-risk activities;
- reasons for overrides.

Do not require every section on every project. Use project/reporting configuration rather than hard-coded universal rules.

### Review and Approval

- Submitter and approver authority remain permission based.
- Reviewer can return with a mandatory reason.
- Approval locks the report.
- Corrections are additive and permission guarded.
- Work progress, equipment usage and other approved postings post once.
- Duplicate submission or approval requests are idempotent.
- Workflow history uses a clean table and audit history remains separate.

### Output

Create a client-readable DSR PDF containing:

- project, site, report date and reference;
- weather and summary;
- measured Work Activity quantities and locations;
- labour and subcontractors;
- equipment hours, meters and fuel;
- materials reported;
- delays and instructions;
- linked evidence index;
- workflow approvals and timestamps.

The PDF is operational evidence. It must not be labelled as an Engineer-certified valuation.

## 22. Chunk 3.4: DSR Material Usage and Site-Store Posting

### Simplified Operating Model

- Creating a site creates one active default site store in the same transaction.
- A DSR material line declares either `Site store` or `Supplied outside inventory` while the report is editable.
- Site-store material uses an active inventory item, allowed unit, the report site's store and a valid batch when the item is batch tracked.
- Saving a draft never changes stock.
- Approving the DSR posts one idempotent issue from the site store for each site-store material line.
- External material requires a reason and never changes inventory.
- The report shows `Material usage status`: Pending approval, Stock deducted, Outside inventory or Needs attention.
- There is no DSR allocation/reconciliation table and no post-approval matching workflow.

### Access Rules

- Project/site assignment controls which site and its store a user can see.
- Inventory permissions still control whether the user may view, receive, transfer, issue or adjust stock.
- Site assignment alone does not grant stock-posting authority.
- Storekeepers and inventory managers may work across accessible site stores within their branch according to permission.
- A user approving a DSR containing site-store materials must have both `daily-site-reports.approve` and `inventory.dsr-material-usage.post`.
- Branch-wide and cross-branch access remains permission based.

### Returns and Corrections

- Material transferred from a main store to a site store remains on hand until an approved DSR records usage or an explicit site-store movement is posted.
- Unused material returns through the normal inventory transfer/return workflow; it does not rewrite an approved DSR.
- An incorrect approved DSR is fixed through the existing additive correction workflow.
- Corrections that change material usage require an explicit inventory reversal/adjustment design before they may alter stock; never silently create or reverse movements.

### Reporting and Audit

- Export material usage directly from DSR lines and their linked stock movement.
- Show report, project, site, material, source, store, batch, quantity, unit, usage status and posting time.
- Audit the DSR approval and every resulting stock movement with stable source key `dsr-material-usage:{line-id}`.
- Keep physical stock-count reconciliation as a separate inventory control; it is not DSR reconciliation.

### Acceptance

1. A new site has exactly one default site store.
2. A draft or submitted DSR does not change stock.
3. Approving a site-store material line deducts the converted stock quantity once.
4. Repeating approval cannot create a duplicate movement.
5. Insufficient stock or an invalid/missing batch prevents approval and leaves the report unapproved.
6. External material requires a reason and creates no stock movement.
7. Users without the stock-post permission receive `403` when approval would deduct stock.
8. Tenant, branch, project, site and store boundaries are enforced server side.
## 23. Chunk 3.5: Pilot Reporting and Exception Closure

### Required Operational Views

- missing and late DSRs;
- DSRs awaiting review or approval;
- DSR material usage requiring attention;
- site-store usage that could not be posted because of stock, batch or setup errors;
- low and out-of-stock items;
- outstanding requisitions;
- overdue and partially received POs;
- rejected receipt quantities;
- equipment location and overdue maintenance;
- fuel exceptions;
- pending expenses and unpaid balances;
- POS customer balances and returns awaiting approval;
- expiring controlled documents.

### Rules

- Dashboard totals must use the same scoped query services as detail pages and exports.
- Every exception count must drill down to its source records.
- Distinguish zero records from missing or stale data.
- Show data freshness timestamps.
- Cost figures must be omitted without cost permission.
- Scheduled alerts must use stable keys and reminder windows to avoid notification spam.

## 24. Chunk 3.6: Operational UI Consistency

Review the critical pilot screens at desktop and phone widths:

- project and site details;
- estimate editor and BOQ import;
- DSR create/edit/show;
- inventory balances;
- requisition create/show/issue;
- purchase order create/show;
- goods receipt;
- equipment assignment/fuel/maintenance;
- expense create/show;
- POS catalogue/cart/receipt/return;
- documents and protected downloads;
- audit trail and notifications.

Apply these interface rules:

- searchable comboboxes for database-backed selections;
- stable control widths with truncation and tooltips for long labels;
- shared date picker for dates;
- red asterisk on required labels;
- one page-level content card where framing is useful, without nested cards;
- consistent data tables for history and repeated records;
- active/inactive tabs where lifecycle requires them;
- global confirmation for destructive or approval actions;
- green success, red failure, blue information and yellow warning toasts;
- no horizontal page overflow on mobile;
- loading, empty, validation and failure states.

## 25. Implementation Order

Implement and validate in this order:

1. Workstream 1.1 fresh schema and seed baseline.
2. Workstream 1.2 requisition availability and reservation integrity.
3. Workstream 1.3 store handover evidence.
4. Workstream 1.4 policy and isolation audit.
5. Workstream 1.5 full regression closure.
6. Workstream 2.1 POS return request.
7. Workstream 2.2 return approval and stock posting.
8. Workstream 2.3 credit balance and refunds.
9. Workstream 2.4 POS permissions and audit.
10. Workstream 2.5 POS reports and polish.
11. Workstream 3.1 PO and receipt documents.
12. Workstream 3.2 BOQ / Estimate import.
13. Workstream 3.3 DSR workflow hardening.
14. Workstream 3.4 DSR material usage and site-store posting completion.
15. Workstream 3.5 reporting and exception closure.
16. Workstream 3.6 operational UI consistency.
17. Execute the full client handover plan and obtain signed UAT.

Do not begin Phase 4 calculations until the client accepts the operational sources that Phase 4 will consume.

## 26. Release Test Matrix

At minimum, maintain focused suites for:

- foundation tenant/branch/role isolation;
- project and site assignment;
- protected document download;
- DSR workflow and corrections;
- DSR expected/missing processing;
- equipment assignment, transfer, meters, fuel and maintenance;
- stock ledger and negative-stock prevention;
- requisitions, reservations, issue handover and returns;
- purchase orders and receipts;
- transfers and physical reconciliation;
- DSR material usage, site-store posting and external-supply handling;
- estimates, baselines and BOQ import;
- expenses and payments;
- POS checkout, credit, collection, return and refund;
- audit and notification delivery.

Run against SQLite for fast isolation where appropriate and MySQL for migration, locking, decimal, index and query-compatibility behaviour.

## 27. Completion Commands

Run from `PointERP`:

```powershell
php artisan migrate:fresh --seed
composer lint
vendor/bin/phpstan analyse
php vendor/bin/pest --compact
bunx tsc --noEmit
```

Run any repository browser suite separately after the application and browser environment are configured.

## 28. Definition of Phase 3 Completion

Items 1, 2 and 3 are complete only when:

- the fresh database and default quarry seed succeed;
- focused and full quality suites pass;
- critical policies reject unauthorized direct requests;
- tenant, branch, project and site isolation is proven;
- stock, payment and progress posting is idempotent;
- private evidence cannot be downloaded outside scope;
- POS returns reconcile with sales, payments and stock;
- a client BOQ can be imported into a draft estimate and approved safely;
- a complete DSR can be prepared, returned, approved, corrected and exported;
- DSR material use can be explained by valid stock or external evidence;
- dashboards and exports reconcile to their source records;
- phone and desktop workflows pass usability review;
- backup, restore and production operations are verified through `CLIENT_HANDOVER_TEST_PLAN.md`;
- named client representatives sign the pilot UAT results.

## 29. Explicitly Deferred

The following are not part of this completion plan:

- statutory double-entry accounting;
- formal inventory valuation and cost of goods sold;
- tax invoices, VAT/EFRIS and fiscal-device integration;
- full accounts receivable and payable;
- bank and mobile-money feeds;
- cashier shifts and till reconciliation;
- formal IPC generation and certification;
- critical-path scheduling;
- full QA/QC, HSE, environment and social modules;
- payroll and biometric attendance;
- live equipment telematics;
- offline synchronization;
- advanced warehouse quarantine, serial custody and lot genealogy.

These remain Phase 4, Phase 5, Phase 6 or separately approved changes.