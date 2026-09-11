# Client Handover Test Plan

## Purpose

Prove that PointERP can run one quarry operation end to end with correct permissions, traceable approvals, reliable stock and DSR posting, protected evidence, and an operationally supportable deployment.

The client-facing seed path is `DatabaseSeeder -> RolePermissionSeeder -> QuarryDemoSeeder -> QuarryWorkItemTemplateSeeder`. `PointInvestmentSeeder` and `WorkItemTemplateSeeder` remain broader regression fixtures and are not registered by default.

## Demo Accounts

All seeded passwords are `password`.

| Account | Intended test role |
|---|---|
| `lemi@gmail.com` | ERP administrator |
| `latif@gmail.com` | Project manager |
| `luate@gmail.com` | Site engineer |
| `rober@gmail.com` | Manager/approver |
| `william@gmail.com` | Administrator/auditor |
| `support@pointmanager.test` | Point support; manager application only |

## Gate 1: Environment and Seed Integrity

1. Create a fresh, isolated staging database using production-like MySQL and PHP versions.
2. Run migrations and the default seeders.
3. Confirm there is one tenant branch, one quarry project, two quarry operating sites, UGX/USD, one default store, quarry work-activity templates and no `@point.test` users.
4. Confirm the project, contract, sites, documents, work activities and DSR narratives all use quarry terminology.
5. Confirm queues, scheduler, private file storage and mail configuration are working.

Evidence: screenshots of tenant setup and project overview, plus a signed seed checklist.

## Gate 2: Role and Scope Matrix

Use separate browser profiles for each account.

1. Administrator configures reference data, users and permissions but cannot enter the support application unless `is_support` is true.
2. Project manager can manage the assigned project, sites, estimate and reporting workflow.
3. Site engineer sees only assigned project/site records and can prepare permitted DSRs.
4. Manager performs only the approvals granted by permissions.
5. Auditor/administrator can inspect records and audit history without bypassing record-state rules.
6. Test hidden actions through direct URLs; each unauthorized request must return `403`.
7. Create a second temporary tenant and prove tenant records cannot be reached by IDs or URLs from the quarry tenant.

Evidence: completed permission matrix containing allowed UI action, denied direct request and expected result.

## Gate 3: Project Planning

1. Open the quarry project and inspect its contract, customer, sites and assigned users.
2. Download the Work Activity Template CSV.
3. Import valid quarry activities with material, labour, equipment and subcontractor resource norms.
4. Import malformed, conflicting, unknown-unit and duplicate files; confirm clear errors and no partial writes.
5. Build a draft estimate from templates, review rates with an authorized account and approve the baseline.
6. Confirm approved estimate lines become project Work Activities and start with zero approved progress.

Note: this feature imports reusable Work Activity Templates. Project BOQ/estimate XLSX import with preview and source-file retention remains a separate future feature.

## Gate 4: Inventory and Procurement

1. Create/inspect inventory items, units, conversions, batches, stores and price lists.
2. Receive stock through a PO and through permission-controlled Add New Stock.
3. Create, submit, approve and issue a site material requisition.
4. Confirm insufficient stock cannot be approved/issued into a negative balance.
5. Confirm batch-tracked items require an available batch.
6. Transfer and reconcile stock through their approval workflows.
7. Confirm on-hand balance equals the immutable movement ledger and repeated requests do not double-post.

Evidence: before/after stock balances, receipt, requisition, issue and movement references.

## Gate 5: Equipment and Site Control

1. Assign equipment to Main Quarry Pit and complete handover.
2. Record meter and fuel transactions with evidence.
3. Transfer or return equipment and verify current location.
4. Create a maintenance schedule/work order and complete it.
5. Confirm assigned or in-maintenance equipment cannot be retired incorrectly.
6. Confirm only authorized users see Edit Site and Manage Site Access actions.

## Gate 6: Daily Site Reporting

1. Site engineer opens the expected DSR for the assigned site and saves a draft.
2. Record measured Work Activity quantities, labour, stock-linked materials, equipment/meter/fuel, delays, evidence and an other cost that creates a linked draft expense.
3. Remove and re-add line items before submission.
4. Submit the DSR; project manager returns it with a reason.
5. Correct, resubmit and approve it.
6. Confirm approval locks the report, updates progress once, does not deduct inventory twice, posts equipment usage once and retains workflow/audit history.
7. Test a missing report, late report and approved correction.

Evidence: DSR PDF/export, workflow trail, linked expense, stock reconciliation and project Plan vs Actual view.

## Gate 7: Documents, Expenses and POS

1. Upload a private document, create a new version and link it to the project, site and DSR.
2. Verify unauthorized users cannot download protected files or open restricted Google Drive links through the ERP.
3. Submit, approve, pay and report a non-stock expense.
4. Complete a cash POS sale and a partial-payment customer sale; verify stock and customer balance behavior.
5. Process controlled corrections/returns without editing immutable completed records.

## Gate 8: Usability and Failure Testing

Test desktop and phone widths, slow network behavior, empty states, long dropdown labels, comma-formatted numbers, date pickers, confirmation dialogs, toasts and large modal scrolling. Deliberately test duplicate clicks, stale pages, invalid files, expired sessions, unavailable queues and failed mail delivery.

## Gate 9: Production Operations

1. Perform and time a database and private-file backup.
2. Restore both into a clean environment and verify linked documents.
3. Configure HTTPS, secure cookies, rate limits, queue workers, scheduler, log rotation and error monitoring.
4. Document tenant onboarding, password reset, user deactivation, incident escalation and support ownership.
5. Agree pilot scope and clearly mark full accounting, payroll/workforce, HSE and formal inventory valuation as later phases unless separately accepted.

## Release Commands

```powershell
php artisan migrate:fresh --seed
composer lint
vendor/bin/phpstan analyse
php vendor/bin/pest --compact
bunx tsc --noEmit
```

Do not hand the system to the client until all gates have named owners, recorded evidence and signed UAT results.
