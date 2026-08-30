# Construction ERP - Phase 2C Detailed Implementation Plan

## Implementation Status

Implemented - operational core complete.

Implemented scope includes:

- Draft, submit, return, resubmit, approve, archive and approved-report locking.
- Controlled correction requests with separate approval/rejection and audit events.
- Expected-report generation and overdue/missing detection commands.
- Workflow-separated DSR tabs and project/site/dashboard summaries.
- Structured work, labour, equipment/fuel, material, other-cost and delay lines.
- BOQ/activity selection with server-enforced tenant/project/site validation and historical rate/unit snapshots.
- Previous approved and cumulative-to-date activity quantities.
- Evidence warning/override, linked evidence, policy enforcement and focused feature tests.

Database notifications and strict fleet/inventory/HR master-data foreign keys remain deliberately deferred to the next focused modules. They are integration work, not missing DSR transaction workflow.

Current foundation already exists from Phase 2A and Phase 2B:

- Customers, contracts, projects, sites and project activities.
- Project and site user assignments.
- Daily Site Report tables and base UI.
- Structured DSR lines for work, labour, equipment, materials, other costs and delays.
- DSR draft, submit, approve and return actions.
- Linked document/evidence upload from project, site and DSR pages.
- Audit foundation and policy foundation.

Phase 2C should therefore refine and complete Daily Site Reporting rather than create it from zero.

---

## 1. Purpose

Phase 2C turns the existing DSR shell into the operational control centre for field work.

In a construction company, the DSR is not just a diary. It is the daily source record for:

- What work was done.
- Where it was done.
- Which BOQ/activity item it supports.
- Which labour, plant, fuel and materials were consumed.
- Which delays, instructions, visitors and safety/environment/social issues occurred.
- Which evidence proves the work.
- Whether the report was submitted, returned, corrected and approved on time.

Phase 2A created the project/site spine. Phase 2B created controlled documents and evidence. Phase 2C should connect those into a reliable field-reporting workflow.

---

## 2. Goal

At the end of Phase 2C, Point Investment should be able to run daily reporting for the Uganda road demo project as a real field workflow:

1. Generate or identify expected DSRs for each active reporting site/date.
2. Let site users create/save draft reports quickly.
3. Capture structured daily work quantities by BOQ item, chainage and side.
4. Capture labour, equipment, fuel, materials, subcontractor and other daily costs.
5. Attach/link evidence to the DSR.
6. Submit the report for review.
7. Return reports with reasons.
8. Approve valid reports.
9. Lock approved reports from silent editing.
10. Allow controlled corrections after approval.
11. Show missing, late, returned and pending approval reports.
12. Give project managers/directors useful exception views.

The output should be strong enough to feed later IPC/payment, equipment, inventory, HR and finance phases.

---

## 3. Why Phase 2C Is Next

The next natural phase is not equipment, inventory or finance yet.

Those later modules need approved source records:

- Equipment hours should come from approved DSR plant lines.
- Fuel usage should come from approved DSR equipment/material lines.
- Labour effort should come from approved DSR labour lines.
- Materials consumed should come from approved DSR material lines.
- IPC quantities should come from approved DSR work lines.
- Financial profitability should consume approved output and cost summaries.

If we build those modules before DSR control is reliable, the ERP will have many screens but weak operational truth. The DSR is the daily event record that the rest of the ERP can trust.

---

## 4. Civil Engineering Domain Reasoning

For a road project, daily reporting must be location-aware and measurement-aware.

A useful field report should answer:

- Which chainage was worked on?
- Was the work on LHS, RHS, centreline or full width?
- Which BOQ item does the work support?
- What quantity was completed today?
- What is the cumulative approved quantity to date?
- What drawings, sketches, photos or test results support the quantity?
- Which machines worked and for how many hours?
- How much fuel was issued or consumed?
- Which delays affected the works?
- Were there HSE, environmental or social issues?
- Who submitted, reviewed and approved the record?

The Excel files reviewed earlier showed that Point's daily costing and IPC workflows already depend on these facts. Phase 2C should normalize those facts so later commercial certificates do not rely on fragile spreadsheet copying.

---

## 5. Scope

### 5.1 In Scope

DSR workflow:

- Draft save.
- Submit.
- Return with reason.
- Resubmit after correction.
- Approve.
- Lock after approval.
- Controlled correction request/record after approval.

Expected reporting:

- Expected DSR records per active site/date.
- Missing report detection.
- Late submission flag.
- Returned report tracking.
- Reporting deadlines by site, falling back to project default.
- Basic non-working day support.

Structured reporting:

- Work quantity lines.
- Labour lines.
- Equipment and fuel lines.
- Material lines.
- Other cost lines.
- Delay lines.
- Visitor/HSE/environment/social narrative sections.
- Completion percent.
- Output, input cost and profit/loss calculations.

Evidence:

- Linked documents visible in the DSR.
- Upload evidence from the DSR page.
- Evidence-required warning before submit when work quantities exist without evidence.

Dashboards:

- DSR exception summary on operations/dashboard area.
- Missing reports.
- Late reports.
- Returned reports.
- Pending approval.
- Approved reports this period.
- Output/cost/profit summary by project/site/date range.

Security and audit:

- Policies for tenant, branch, project and site access.
- Site submitter vs reviewer/approver separation.
- No direct URL bypass.
- Audit submit, return, resubmit, approve, correction and archive.

Tests:

- DSR status transitions.
- Approved report lock.
- Correction path.
- Missing report marking.
- Branch/site isolation.
- Evidence visibility and permissions.
- Dashboard counts.

### 5.2 Out of Scope

Do not implement these in Phase 2C:

- Full IPC/payment certificate generation.
- Full inventory stock ledger.
- Full equipment ownership/maintenance module.
- Full HR attendance/payroll.
- Finance journal postings.
- Offline sync.
- Client/subcontractor external portals.
- Complex programme scheduling/Gantt logic.
- CAD/PDF drawing markup.

Phase 2C should prepare clean data for these modules, not absorb them.

---

## 6. Remaining Integration Work

The Phase 2C DSR workflow is implemented. The following integrations belong to subsequent focused modules:

1. Replace equipment name/identifier snapshots with an optional `equipment_id` selected from the fleet register while retaining the snapshots for history.
2. Replace material name/type snapshots with an optional `inventory_item_id` selected from the inventory catalogue and post approved consumption to a stock ledger.
3. Labour source and subcontractor master selection are implemented. A later HR phase may replace the remaining trade/role snapshot with staff attendance and trade masters while preserving the approved DSR history.
4. Replace the controlled unit list with tenant-managed units of measure when the inventory/commercial foundation is introduced.
5. Add database/email notifications and an in-app notification centre in Phase 2D.
6. Convert approved DSR quantities into IPC measurement/certification records without editing the original DSR.

### Source-of-truth boundary

The phrase "DSR is the source of truth" applies to daily operational transactions, not master data:

| Fact | Owner | DSR responsibility |
| --- | --- | --- |
| Equipment identity, ownership and service state | Fleet/equipment register | Select equipment and record the hours, status and fuel observed that day. |
| Material identity, unit and stock balance | Inventory catalogue and stock ledger | Select material and record delivered/used/wasted quantities for the day. |
| Unit definitions and conversions | Unit-of-measure catalogue | Select a valid unit and snapshot it on the DSR line. |
| BOQ item, rate and planned quantity | Project activity/BOQ | Select the activity and snapshot item, unit, rate and currency. |
| Daily work, resource use and site events | Approved DSR | Preserve the approved event as the auditable operational record. |
| Certified quantity and payment | IPC/commercial module | Consume approved DSR quantities through a separate measurement and certification workflow. |

This separation avoids duplicate equipment and material names while ensuring later edits to a catalogue or rate never rewrite an approved historical report.

---

## 7. Domain Model Additions

Use Artisan to create each migration separately.

### 7.1 `daily_site_report_corrections`

Purpose: approved DSRs should not be silently edited. A correction creates an auditable record.

Fields:

```text
id uuid primary
tenant_id uuid
branch_id uuid
daily_site_report_id uuid
requested_by uuid
approved_by nullable uuid
status draft/submitted/approved/rejected/cancelled
reason text
old_values json nullable
new_values json nullable
approved_at nullable timestamp
rejected_at nullable timestamp
created_at
updated_at
```

Rules:

- Same tenant and branch as the DSR.
- Only approved DSRs should use correction records.
- Correction approval should audit old/new values.
- Later finance/IPC modules should know whether corrected quantities changed certified values.

### 7.2 `daily_site_report_reviews`

Purpose: support more than one review/check step without overloading the main DSR table.

Fields:

```text
id uuid primary
tenant_id uuid
branch_id uuid
daily_site_report_id uuid
reviewed_by uuid
action reviewed/returned/approved/rejected
remarks text nullable
created_at
updated_at
```

Rules:

- Keep the current `submitted_by`, `approved_by`, etc. columns for fast display.
- Use review records as the detailed workflow trail.

### 7.3 Expected DSR Enhancement

Review existing `expected_daily_site_reports` before editing.

Likely needed fields:

```text
daily_site_report_id nullable uuid
expected_for date
deadline_at timestamp
submitted_at nullable timestamp
status expected/submitted/late/missing/excused
excuse_reason nullable text
marked_by nullable uuid
```

Rules:

- One expected DSR per tenant/site/date.
- Link to actual DSR once created/submitted.
- Missing/late records should be queryable.

---

## 8. State Machine

### 8.1 DSR Statuses

Use these statuses:

```text
draft
submitted
returned
approved
missing
archived
```

The existing `reviewed` status can be kept only if there is a real intermediate review step. If no separate review step is used in the UI, prefer removing it from the active workflow later or treating it as internal.

### 8.2 Allowed Transitions

```text
draft -> submitted
submitted -> returned
submitted -> approved
returned -> submitted
missing -> draft
approved -> correction submitted
correction submitted -> correction approved/rejected
approved -> archived only by privileged user
```

Forbidden:

- approved -> draft
- approved -> returned
- approved -> silent update
- submitted -> draft by normal site user
- returned without reason

### 8.3 Lock Rule

Once approved:

- DSR header cannot be edited directly.
- DSR lines cannot be edited directly.
- Evidence can still be linked only if user has permission and action is audited.
- Corrections must go through `daily_site_report_corrections`.

---

## 9. Permissions

Add permissions idempotently:

```text
daily-site-reports.view
daily-site-reports.create
daily-site-reports.update
daily-site-reports.submit
daily-site-reports.review
daily-site-reports.approve
daily-site-reports.return
daily-site-reports.archive
daily-site-reports.correct
daily-site-reports.view-costs
daily-site-reports.manage-expected
daily-site-reports.view-dashboard
```

Suggested roles:

- Director: all DSR permissions, including cost visibility and dashboards.
- Administrator: manage expected reports and operational setup.
- Project Manager: view/create/update/submit/review/approve/return/correct for assigned projects/sites, cost visibility.
- Site Manager/Engineer: create/update/submit returned/draft DSRs for assigned sites, no approval of own report.
- Auditor: view approved/submitted DSRs and dashboard, no edits.
- Store Keeper: no DSR permissions unless later materials module requires it.

Important rule:

- A user should not approve their own submitted DSR unless they have an explicit director-level override permission.

---

## 10. Policies

Update `DailySiteReportPolicy` to check:

- Same tenant.
- Branch access.
- Project access or site access.
- Status state.
- Site assignment flags:
  - `can_submit_dsr`
  - `can_review_dsr`
- Permission.
- Self-approval restriction.

Policy expectations:

- `viewAny`: has DSR view permission.
- `view`: same tenant, branch and project/site access.
- `create`: user can submit/create for the selected site.
- `update`: draft/returned only, plus site submit permission.
- `submit`: draft/returned/missing only, plus site submit permission.
- `return`: submitted only, plus review permission.
- `approve`: submitted only, plus review/approve permission, not own submission unless override.
- `correct`: approved only, plus correction permission.
- `archive`: privileged only.

---

## 11. Actions

Use actions for business logic.

Recommended actions:

```text
SaveDailySiteReport
SubmitDailySiteReport
ReturnDailySiteReport
ApproveDailySiteReport
CreateDailySiteReportCorrection
ApproveDailySiteReportCorrection
GenerateExpectedDailySiteReports
MarkMissingDailySiteReports
SyncExpectedDailySiteReport
CalculateDailySiteReportTotals
CalculateProjectProgressFromApprovedDsrs
```

### 11.1 Save

Responsibilities:

- Save header fields.
- Sync work/resource/cost/delay lines.
- Recalculate output/input/profit.
- Prevent direct edits to approved reports.
- Sync related expected DSR if report date/site matches.
- Audit old/new values.

### 11.2 Submit

Responsibilities:

- Validate completeness.
- Require at least a work summary or structured work lines.
- Warn/block when no linked evidence exists and work quantities are present.
- Set submitted fields.
- Link to expected DSR.
- Mark expected DSR submitted or late.
- Notify reviewers.

### 11.3 Return

Responsibilities:

- Require reason.
- Set returned fields.
- Clear approval fields.
- Record review action.
- Notify submitter/site manager.

### 11.4 Approve

Responsibilities:

- Check not own submission unless override.
- Set approved fields.
- Record review action.
- Lock report.
- Update project activity approved quantities from approved work lines.
- Notify submitter/project manager/director as needed.

### 11.5 Correction

Responsibilities:

- Create correction record against approved DSR.
- Store reason and proposed changes.
- On approval, apply changes or create adjustment lines.
- Audit correction.
- Mark if commercial quantities changed.

---

## 12. DSR Form Design

This is an operational tool, not a landing page.

Keep the page dense, clear and fast.

Recommended structure:

1. Header:
   - Site, project, report date, status.
   - Submit/return/approve/correction actions.

2. Summary metrics:
   - Output.
   - Input cost.
   - Profit/loss.
   - Completion percent.
   - Evidence count.

3. Daily narrative:
   - Weather.
   - Site conditions.
   - Work summary.
   - Delays.
   - Visitors.
   - HSE.
   - Environment.
   - Social.

4. Work quantities:
   - BOQ/activity select.
   - Chainage from/to.
   - Side.
   - Quantity.
   - Unit.
   - Rate snapshot if user can view costs.
   - Amount.
   - Evidence indicator.

5. Labour:
   - Labour source: internal staff, casual labour or subcontractor.
   - Trade/role.
   - Searchable subcontractor company when the source is subcontracted.
   - Headcount.
   - Hours per worker and derived total person-hours.
   - Rate per person-hour and derived cost if allowed.

6. Equipment and fuel:
   - Equipment name/identifier.
   - Working hours.
   - Idle hours.
   - Fuel type.
   - Fuel quantity.
   - Rate/cost if allowed.

7. Materials:
   - Material.
   - Quantity.
   - Unit.
   - Delivery reference.
   - Rejected/wasted quantity later.

8. Other costs:
   - Category.
   - Description.
   - Quantity/unit/rate.
   - While the DSR is editable, a permitted user records the cost once and the system immediately creates a linked Expense draft. The Expense follows its own submit/approve/payment workflow; there is no second manual linking step.

9. Delays:
   - Delay type.
   - Hours lost.
   - Cause.
   - Action taken.

10. Evidence:
   - Existing linked documents.
   - Upload evidence.
   - Show missing evidence warning.

---

## 13. DSR Index And Dashboards

### 13.1 DSR Index

Filters:

- Search.
- Project.
- Site.
- Date range.
- Status.
- Missing/late.
- Branch.

Tabs:

- Open.
- Pending approval.
- Returned.
- Missing/late.
- Approved.
- Archived.

Do not mix missing/inactive/archived reports into the active list.

### 13.2 Project Dashboard Widgets

On project show page, add compact operational summary:

- Expected reports this week.
- Submitted.
- Missing.
- Returned.
- Pending approval.
- Approved.
- Output value this week/month.
- Input cost this week/month.
- Profit/loss this week/month.
- Top delayed sites.

Each number must drill back to filtered DSR list or source report.

### 13.3 Site Dashboard Widgets

On site show page:

- Last report date.
- Current reporting status.
- Missing reports count.
- Pending returned reports.
- Latest approved output.
- Latest linked evidence.

---

## 14. Expected DSR Workflow

### 14.1 Generation

Add command:

```text
php artisan dsr:generate-expected
```

Options:

```text
--date=YYYY-MM-DD
--from=YYYY-MM-DD
--to=YYYY-MM-DD
--site=
--tenant=
```

Rules:

- Generate for active sites only.
- Skip non-reporting days.
- Use site reporting deadline.
- Fall back to project reporting deadline.
- Use tenant timezone where appropriate.

### 14.2 Missing Detection

Add command:

```text
php artisan dsr:mark-missing
```

Rules:

- If expected DSR deadline has passed and no submitted/approved report exists, mark missing.
- If draft exists but not submitted, status should be late/missing depending on deadline.
- Do not mark future records missing.
- Audit status changes.

### 14.3 Manual Excuse

Later in Phase 2C or 2D, allow a privileged user to excuse expected reports:

- Rain day.
- Site closed.
- Public holiday.
- Client stoppage.
- No work planned.

This should be audited.

---

## 15. Calculations

### 15.1 Daily Work Output

For each work line:

```text
amount = quantity * rate_snapshot
```

Use rate snapshots from project activities so later rate changes do not rewrite historical DSRs.

If user cannot view costs/rates:

- Store calculations server-side.
- Hide rates and amounts in UI.

### 15.2 Daily Input Cost

```text
input_cost = labour_cost + equipment_cost + material_cost + other_cost
profit_loss = output_value - input_cost
```

These are provisional operational costs until HR/equipment/inventory/finance modules validate them.

### 15.3 Cumulative Quantities

Add reporting query/service:

```text
previous_approved_quantity = sum approved DSR work lines before report date
today_quantity = current report line quantity
cumulative_to_date = previous_approved_quantity + today_quantity
```

This should be by:

- Tenant.
- Project.
- Site optional.
- Activity/BOQ item.
- Chainage/side where useful.

This prepares for IPC.

---

## 16. Evidence Rules

Minimum Phase 2C evidence behavior:

- DSR can save draft without evidence.
- DSR submit should warn or block if work lines exist without evidence.
- Project Manager/Director can override with reason if needed.
- Evidence uploads should default to `DSR_EVIDENCE`, `PHOTO`, `SKETCH` or `TEST_RESULT`.
- Linked evidence should remain available after approval.
- Deleting/unlinking evidence from approved DSR should require privileged permission and audit reason.

Suggested first implementation:

- Start with a warning banner and confirmation.
- Later tighten to blocking by document type/activity requirement.

---

## 17. Notifications

Use Laravel notifications and existing toast system.

Events that should create in-app notification records:

- User assigned to site/project.
- Expected DSR generated for site user.
- DSR submitted to reviewer/project manager.
- DSR returned to submitter.
- DSR approved.
- DSR missing after deadline.
- Correction submitted/approved/rejected.

Email can be optional depending on mail configuration.

Do not block DSR saving because mail fails.

---

## 18. Audit Trail

Audit:

- DSR created.
- DSR updated.
- Lines changed.
- DSR submitted.
- DSR returned with reason.
- DSR approved.
- DSR archived.
- Expected DSR generated.
- Expected DSR marked missing/late/excused.
- Correction requested.
- Correction approved/rejected.
- Evidence linked/unlinked after approval.

Store:

- Actor.
- Tenant.
- Branch.
- Event.
- Record type.
- Record id.
- Old values.
- New values.
- Reason.
- IP/user agent where available.
- Timestamp.

---

## 19. UI Standards

Follow the same professional approach used in the current pages:

- Shadcn dialogs for compact create/edit flows.
- Large operational pages for DSR detail.
- Search/filter controls under page heading on the left.
- Add/create actions on the right.
- Active/open/returned/missing/approved tabs, not mixed lists.
- Comboboxes for project/site/activity/user choices.
- Global confirmation modal for submit, return, approve, archive and correction actions.
- Sonner toasts for outcomes.
- No marketing-style panels.
- Dense but readable tables.
- Mobile-safe forms with horizontal scroll only where table density requires it.

---

## 20. Seed Data

Extend Point Investment seed data:

- Expected DSRs for Busunju and Kiboga-Hoima for at least one week.
- One approved complete DSR.
- One submitted pending approval DSR.
- One returned DSR with reason.
- One missing expected DSR.
- One corrected approved DSR.
- Evidence linked to the approved DSR.
- Site manager who can submit but not approve.
- Project manager who can approve but cannot approve own submitted report.
- Director who can override.
- Auditor who can view but not edit.

Demo should clearly show:

- Different users see different sites/reports.
- Rates hidden where required.
- Missing reports separate from approved/open reports.
- Approved reports locked.

---

## 21. Tests

Create a focused Phase 2C test file:

```text
tests/Feature/Operations/PhaseTwoCDailySiteReportingTest.php
```

Test cases:

1. Site manager can create draft DSR for assigned site.
2. Site manager cannot create DSR for unassigned site.
3. Draft DSR calculates output, input cost and profit/loss.
4. Draft DSR can be submitted.
5. Submitted DSR can be returned with reason.
6. Returned DSR can be corrected and resubmitted.
7. Submitted DSR can be approved by project manager.
8. Submitter cannot approve own DSR without override permission.
9. Approved DSR cannot be edited directly.
10. Approved DSR can receive a correction record.
11. Expected DSR generation creates one obligation per active site/date.
12. Missing command marks overdue expected DSRs missing.
13. DSR dashboard counts missing, returned and pending approval correctly.
14. Site user cannot direct-URL view another branch/site report.
15. Linked evidence appears on DSR show page.

---

## 22. Implementation Order

Recommended order:

1. Review existing DSR migrations/models/policies/actions.
2. Add correction/review migrations using Artisan.
3. Tighten `DailySiteReportPolicy`.
4. Split status transition logic into actions/services.
5. Prevent direct edits to approved reports.
6. Add correction workflow backend.
7. Add expected DSR generation command.
8. Add missing DSR marking command.
9. Improve DSR index tabs and filters.
10. Replace return prompt with proper modal.
11. Add DSR dashboard widgets on project/site/index pages.
12. Add evidence completeness warning.
13. Extend seed data.
14. Add Phase 2C feature tests.
15. Run Pint/Rector/PHPStan/Pest/frontend lint.

---

## 23. Acceptance Criteria

Phase 2C is complete when:

- A site user can save and submit a DSR for their assigned site.
- A project manager can return or approve submitted DSRs.
- Returned DSRs carry a reason and can be resubmitted.
- Approved DSRs cannot be silently edited.
- Corrections to approved DSRs are auditable.
- Missing DSRs are generated/detected from expected-report obligations.
- DSR index separates open, returned, missing/late, approved and archived records.
- Project/site dashboards show DSR exceptions and drill to source records.
- Work quantities include BOQ/activity, chainage, side, quantity, unit and value.
- Daily output/input/profit summaries are calculated server-side.
- Rates/costs remain hidden from users without permission.
- Evidence linked to DSRs is visible from the DSR page.
- Policies block unauthorized direct URL access.
- Tests prove tenant, branch, site and workflow isolation.

---

## 24. What Comes After Phase 2C

After Phase 2C, the strongest next phase is Phase 2D: **Operational Dashboards and Notifications** if not fully completed inside 2C, or Phase 3A: **Equipment/Fleet and Fuel Control**.

Preferred path:

1. Finish DSR workflow and missing-report exceptions in Phase 2C.
2. Add dashboards/notifications if Phase 2C scope needs to be split.
3. Move to equipment/fuel because DSR equipment lines already capture utilisation and fuel.
4. Then inventory/materials, because DSR material lines already capture usage.
5. Then commercial IPC, because approved DSR work lines already capture measurable quantities.
