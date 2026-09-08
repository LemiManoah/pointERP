# PointERP Potential Client Presentation Script

## Meeting objective

The objective is not to demonstrate every menu. It is to prove one construction-management story:

> PointERP connects the approved plan, the site, materials, equipment, daily reporting, costs, documents and management oversight in one controlled record.

The desired outcome is agreement on a short pilot using one real project, one store and a small group of users.

## Recommended duration

- 5 minutes: introductions and discovery
- 5 minutes: current problem and PointERP's proposition
- 25 minutes: end-to-end system demonstration
- 10 minutes: controls, reporting and implementation approach
- 10 minutes: questions and pilot decision

Keep a 30-minute compressed version available by skipping detailed setup screens and showing only the end-to-end project transaction.

## Preparation before the meeting

1. Use a seeded demonstration tenant that is clearly labelled as demo data.
2. Confirm the director/admin, project manager, site reporter and storekeeper accounts can log in.
3. Confirm a project, site, approved estimate, inventory, equipment and at least one DSR exist.
4. Keep one DSR in draft and one submitted for approval.
5. Keep one material requisition approved but not fully issued.
6. Keep one document linked to the project and one linked to a DSR.
7. Keep one expense draft created from a DSR other cost.
8. Open the ERP login page before the meeting. Do not begin in the support-only manager application.
9. Avoid creating foundational records live unless the client specifically asks. Demonstrate the working operational flow first.
10. Have the two source workbooks ready as evidence that their existing Excel data can be mapped into the system.

## Opening script

“Thank you for meeting with us. Before I demonstrate the system, I want to understand how you currently control a project from award to daily site reporting. In particular, I would like to know who prepares the BOQ or estimate, who requests and issues materials, who submits the daily report, and who approves costs and progress.”

Ask:

- How many active projects and physical sites do you manage?
- Are stores project-specific, or can one store serve several sites?
- Who owns the BOQ and who is allowed to see rates?
- How are materials requested, issued and compared with site consumption today?
- How are equipment hours, fuel and breakdowns recorded?
- Who submits and approves daily site reports?
- Which reports take the most time to prepare or reconcile?
- What must remain in Excel during the pilot?

Then say:

“I will show one transaction moving through the system: plan a road activity, make it available to a site, issue material and equipment, report the day's work, approve it, and see the result at management level. That is the core of PointERP.”

## Product positioning

“PointERP is not just a digital daily-report form. A standalone form still leaves management reconciling separate spreadsheets. PointERP keeps the operational records connected: the work reported today relates to an approved project plan, issued material, assigned equipment, supporting documents and accountable users.”

Emphasize four benefits:

1. One source of operational truth across projects and branches.
2. Permission-controlled access to sensitive rates, costs and approvals.
3. Traceability from management summaries back to the source record.
4. Less duplicate entry between BOQs, stores, site reports, expenses and documents.

Do not claim that PointERP is already a full accounting, payroll or statutory tax system. Those are separate later capabilities.

## End-to-end live demonstration

### 1. Sign in and establish scope

Action:

- Sign in as the client-side director or administrator.
- Point out the company and current branch in the application shell.
- Briefly show the profile/logout area.

Say:

“Every record is isolated to the company. Within the company, branch and project access further control what a person can see. A hidden button is not the security mechanism; the server also checks the user's permission and record scope.”

Do not spend time in the manager application. Explain that it is used by the PointERP support team for onboarding tenants and initial facilities, not for the client's daily operations.

### 2. Management dashboard

Action:

- Open Dashboard.
- Show active projects/sites, DSR status, equipment status and expiring documents.

Say:

“The dashboard answers the first management questions: what is active, which reports need attention, what equipment is available or under maintenance, and which controlled documents are expiring.”

Be transparent where a chart is provisional or seeded. Say, “This demonstration uses seeded figures; during a pilot these panels will be driven by your approved operational records.”

### 3. Companies, staff and controlled access

Action:

- Briefly show Companies.
- Briefly show Staff and Users & Roles.
- Show that users receive branch/project access and permissions.

Say:

“Companies is the shared business-party register for clients, suppliers, subcontractors and other counterparties. Staff represents the employee. A user account is created for a staff member only when system access is required. Roles determine permitted actions; project and branch assignments determine where those actions may be performed.”

Use this sentence if asked about project access:

“Project assignment opens the door to that project, while permissions determine what the user may do after entering it.”

### 4. Create or open the project

Action:

- Open Projects & sites.
- Open the seeded road project.
- Show the project summary, sites, access and documents.

Say:

“A project is the main operational container. Sites represent physical reporting locations or sections. A contract may be linked where formal commercial details are needed, but the operational project remains the centre of execution.”

Avoid editing project setup during the main demo. Use an existing clean record.

### 5. Plan the project through an estimate

Action:

- Open the project's Estimates tab.
- Open an estimate revision.
- Show estimate lines: description, BOQ reference, unit, planned quantity, selling rate, estimated unit cost and expected resources.
- Show approval of a prepared draft if the workflow is stable in the demo environment.

Say:

“The estimate is the internal, revision-controlled plan. A line can carry the client's BOQ reference, but the BOQ reference is optional because not every internal activity is a payable BOQ item. Rates and cost assumptions remain visible only to authorised users.”

“Once approved, this estimate becomes the project baseline. The system then creates the operational Work Activities against which the site reports progress. Approved versions are locked so historical planning decisions are not silently rewritten.”

### 6. Explain Work Activities clearly

Recommended user-facing name: **Work Activities**.

Say:

“A BOQ Item is a contractual measurement line, such as item 31.01(b)(i). An Estimate Line is our planned quantity and cost assumption for that scope. A Work Activity is the operational record the site team executes and reports against, such as placing selected fill or constructing a culvert.”

Use this relationship:

```text
BOQ/estimate import -> estimate revision -> approved baseline -> work activities -> daily progress -> performance comparison
```

Show:

- planned quantity;
- approved progress from DSRs;
- remaining quantity;
- completion percentage;
- rate/value only for authorised users.

Do not present Work Activities as inventory products. An activity may consume inventory, labour and equipment, but it represents work delivered, not an item held in a store.

### 7. Demonstrate Excel-assisted setup

Show the existing IPC workbook without promising a one-click import before it is implemented.

Say:

“Your current workbook already contains most of the project-plan fields we need: BOQ reference, description, unit, contract quantity and rate. We propose an Import BOQ / Estimate action that validates the spreadsheet and lets a user review the mapping before creating an estimate draft.”

Recommended import flow:

1. Start from a project and select **Import BOQ / Estimate**.
2. Upload `.xlsx` or a controlled CSV template.
3. Select the source sheet, normally Main Bill.
4. Map item reference, description, unit, planned quantity and optional rate.
5. Preview valid rows, warnings and rejected rows.
6. Resolve unknown units and duplicate references.
7. Import into a new draft estimate revision.
8. Review and approve through the normal permissions workflow.

Never import directly into approved Work Activities. The reviewable estimate draft is the safety boundary.

### 8. Materials: request, approve and issue

Action:

- Open Requisitions.
- Show a request raised for the project/site and optionally linked to a Work Activity.
- Show approval and partial/full issue from a store.
- Open Stock balances or Stock movements to show the resulting ledger entry.

Say:

“The site requests material; an authorised user approves it; the storekeeper issues what is actually available. Approval alone does not reduce stock. The store issue is the event that reduces stock, and negative stock is blocked.”

“The Work Activity link is optional but valuable. It tells management why the material left the store. The DSR later records what the site says was consumed without deducting stock a second time.”

### 9. Equipment accountability

Action:

- Open Equipment.
- Show one asset's current assignment, location, meter readings, fuel and maintenance history.

Say:

“Equipment is assigned and handed over to a project or site. Meter readings, fuel transactions, transfers and maintenance are separate controlled events. This prevents an asset from appearing simultaneously available and assigned, and prevents retirement while an active assignment still exists.”

### 10. Create the Daily Site Report

Action:

- Open Daily reports.
- Open the prepared draft for the same project/site.
- Show the report date, weather/summary fields and reporting status.
- Add or show Work quantities, Labour, Equipment, Materials, Delays, Other costs and Linked evidence.

Say:

“The DSR is the daily execution record. It answers: what work was completed, who worked, which equipment operated, which materials were consumed, what delayed progress, what incidental cost occurred, and what evidence supports the report.”

Explain each section:

- Work quantities: measurable output completed today against a Work Activity.
- Labour: people or subcontractor effort used to achieve the output.
- Equipment: equipment used, hours, meter readings and operating status.
- Materials: actual site consumption, later compared with store issues.
- Delays: reason and hours lost; not a monetary amount.
- Other costs: creates a linked draft expense rather than duplicating a cost inside the DSR.
- Evidence: photos, drawings, permits, delivery notes or other controlled documents.

Say:

“Draft means the site team can still edit the report. Submitted means it is awaiting review. Approved means the operational record is locked and its progress and actuals are accepted for management reporting. A correction after approval requires a controlled correction workflow.”

### 11. Submit and approve the DSR

Action:

- Submit the draft as the site reporter or project manager.
- Switch to an approver account if practical.
- Review and approve it.
- Show Workflow trail.

Say:

“The system records who submitted, returned, approved or corrected the report. Permission controls the action. Approval posts the accepted work progress to the project's performance view; it does not automatically alter unrelated source ledgers.”

If switching accounts is risky during the meeting, use two browser profiles already logged in or show a submitted report and explain the approval action without changing live data.

### 12. Return to project Plan vs Actual

Action:

- Return to the project.
- Open Plan vs Actual.
- Show baseline quantity, approved progress, remaining quantity and actual resource/cost comparisons.

Say:

“This is the payoff. Management can move from the approved plan to approved daily progress and then back to the supporting report, material, equipment, expense and document records.”

“The key comparison is planned versus actual: planned scope and resource assumptions against approved work and actual site inputs. This is operational performance, not yet a formal accounting profit-and-loss statement.”

### 13. Documents and audit trail

Action:

- Open a document linked to the project or DSR.
- Show Linked records and Version history.
- Open Audit trail and filter to a relevant approval/change.

Say:

“Documents are stored once and linked to the records where they provide evidence. Uploading a new version does not overwrite history. Google Drive links can also be recorded, but the underlying Drive permissions still control who can open the file.”

“The audit trail is separate from ordinary business activity. It records significant changes such as approvals, role changes, branch access, stock movements, rates and corrections.”

### 14. Briefly show expenses and POS only if relevant

Expenses talk track:

“Expenses capture genuine non-stock costs such as utilities, permits, transport or site welfare. They use reusable expense types and items, approval, payment history and evidence. Stock purchases and fuel should not be entered again as expenses.”

POS talk track:

“POS sells inventory items marked for sale and uses the same stock ledger. A payment below the sale total creates an outstanding customer balance where credit is permitted.”

Do not let these secondary modules distract from the construction execution story unless the client identifies them as priorities.

## Recommended terminology changes

Use these names consistently in UI and conversation:

| Current term | Recommended term | Meaning |
|---|---|---|
| Work items | Work Activities | Executable project scope reported by site teams |
| Work items library | Work Activity Library | Reusable templates used while preparing estimates |
| BOQ reference | BOQ Item No. | Optional external contract/tender reference |
| Estimate line | Estimate Line | Planned quantity, rates, costs and resources before approval |
| Performance | Plan vs Actual | Baseline compared with approved execution |

Do not rename a single detailed activity to “Work Package.” In construction planning, a work package normally groups several activities and would be a useful hierarchy later, for example Earthworks -> Excavation, Fill and Compaction.

## Excel findings and recommendation

The previously shared files are still accessible:

- `BKH - IPC No. 3 Draft 2024.xlsx`
- `Rev Daily costing 7th DECEMBER 2024.xlsx`
- `Rev Daily costing 8th DECEMBER 2024.xlsx`
- `Rev Daily costing 9th DECEMBER 2024.xlsx`

The IPC workbook contains:

- project and contract identity;
- bill/section hierarchy;
- BOQ item references and descriptions;
- units, original quantities, rates and amounts;
- previous, current-period and cumulative measured quantities;
- payment certificate summaries and measurement sheets.

The daily-costing workbooks contain daily output, input, profit/loss analysis, work-progress summaries, operational/contractual issues and detailed labour/material/equipment costing. They should guide DSR capture and reporting, not populate the baseline estimate directly.

Recommended first importer scope:

- Import only clean BOQ/estimate detail into a draft estimate.
- Support one explicit source sheet and a visible column-mapping preview.
- Preserve source row, sheet and workbook name for traceability.
- Validate units, quantities, duplicate BOQ numbers and rates.
- Allow import without rates for users who are not authorised to see or manage commercial values.
- Produce an import-error file or review table; never silently skip malformed rows.
- Treat cumulative measured quantities from an IPC as historical certified progress, not as today's DSR output.

## Questions to ask after the demonstration

1. Does “Work Activities” match the terminology used by your engineers and quantity surveyors?
2. Is the BOQ maintained by item, section, chainage, structure or all four?
3. Which Excel sheet is considered authoritative for the approved BOQ?
4. Are rates visible to project managers, site engineers and storekeepers, or only commercial staff?
5. Can one DSR cover several locations, or is one report required per site/section each day?
6. Do subcontractors report measured quantities through the main DSR or a separate certificate?
7. Must store issues always begin with a requisition?
8. Which documents are mandatory before a DSR or expense can be approved?
9. What approval limits depend on amount, project or role?
10. What three reports would make the pilot successful?

## Pilot proposal script

“Rather than attempting a company-wide launch immediately, I recommend a controlled pilot on one active project. We will import or enter its baseline estimate, configure one or two sites, one store, selected equipment and approximately six users. The team will run real daily reports, material requisitions and approvals for a defined period.”

Suggested pilot roles:

- one director or senior approver;
- one project manager;
- one site engineer/reporter;
- one quantity surveyor or commercial reviewer;
- one storekeeper;
- one equipment/fleet user.

Suggested success measures:

- daily reports submitted on time;
- management can trace approved progress to source evidence;
- issued material can be compared with reported consumption;
- project users see only authorised branches/projects and cost data;
- one management report is produced without rebuilding several spreadsheets.

## Closing script

“What you have seen today is the operational backbone: plan the work, control access, issue resources, report site execution, approve the record and compare plan with actual. The next step is not more presentation data. It is to select one pilot project, map your current Excel and approval process, and let real users validate the workflow.”

Ask for these decisions before ending:

1. Pilot project and site.
2. Client-side process owner.
3. Initial user list and roles.
4. Authoritative BOQ/estimate workbook.
5. Pilot start date and review date.
6. The three reports or decisions the pilot must improve.

## Demonstration cautions

- Do not promise full accounting, payroll, tax or IPC certification as completed features.
- Do not call provisional dashboard figures live production analytics.
- Do not upload the client's workbook directly into production during the meeting.
- Do not demonstrate every CRUD screen.
- Do not expose rates while using a site-level account.
- Do not improvise around a failing screen. Return to the prepared scenario and explain the intended control.
- Do not lead with technical architecture. Lead with the client's operating problem and the connected record.
