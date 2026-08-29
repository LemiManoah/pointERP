# PointERP Phase 3C: Estimation, Work Planning and Actual Performance

## 1. Purpose

Phase 3C simplifies the relationship between contractual scope, daily reporting and inventory. Users should understand one operating story:

> Estimate the work, approve a project baseline, execute work, record actual resources, and compare the baseline with actual performance.

This phase does not replace the existing DSR or inventory ledgers. It adds the missing planning layer above them and presents `project_activities` to users as **Work items**.

Status: initial end-to-end implementation complete, pending local migration, static analysis, regression tests and UI acceptance.

## 2. Domain Boundaries

- **Estimate:** an internal, revision-controlled prediction of work quantities, selling rates and expected resources.
- **Baseline:** the approved estimate version used to measure project performance. Only one current baseline exists per project.
- **Work item:** the executable project scope generated from a baseline estimate line. It may retain an optional external BOQ reference.
- **DSR work line:** the quantity of a work item reported on a particular day.
- **Material requisition:** a request for inventory to a project/site. A work item link is optional.
- **Stock movement:** immutable evidence that material entered, left or moved between stores.
- **DSR material line:** the site snapshot of material consumed. Reconciliation connects it to stock issues without deducting stock twice.
- **Certification / IPC:** formal client or consultant payment certification. It is not created by approving a DSR and remains a later commercial workflow.

## 3. Simplified Workflow

1. Create a project in planning state.
2. Create a draft estimate version for the project.
3. Add work lines with name, unit, estimated quantity, optional BOQ reference, selling rate and estimated unit cost.
4. Optionally add expected resources to a work line, such as seven cement bags per cubic metre of concrete.
5. Review and approve the estimate. Approval locks the version and makes it the current project baseline.
6. Approval creates or updates project work items from the estimate lines. Progress always starts at zero for new work items.
7. Site users requisition stock for the project/site and may optionally select the work item it supports.
8. Store issue reduces stock. DSR entry never performs a second automatic deduction.
9. A DSR records work completed, materials consumed, labour, equipment, delays and evidence.
10. DSR approval increases approved work progress and preserves resource actuals.
11. Project performance compares baseline quantity/value/cost with approved progress and actual DSR costs.

## 4. Estimate Rules

- Estimate versions are immutable after approval.
- Only draft versions may be edited or deleted.
- Approving a new estimate supersedes the previous baseline but does not rewrite historical DSRs.
- Every generated work item retains its source estimate line.
- BOQ references are optional and are copied from external tender or contract documents; PointERP does not invent them.
- Rates and estimated costs are visible only to users with project cost permissions.
- Approved progress is system-maintained from approved DSR work lines and is not manually edited through normal work-item forms.
- Estimate approval is permission guarded and audited.

## 5. Performance Measures

For each baseline work item:

```text
planned quantity       = approved estimate quantity
approved progress      = sum of approved DSR work quantities
remaining quantity     = max(planned - approved, 0)
completion percentage  = approved / planned * 100
baseline revenue       = planned quantity * selling rate
earned output          = approved progress * selling rate
baseline cost          = planned quantity * estimated unit cost
```

At project level, actual input cost remains the sum of approved DSR labour, equipment, material and other cost lines. Formal accounting valuation remains outside this phase.

## 6. Permissions and Scope

- `estimates.view`: view estimates for assigned projects.
- `estimates.create`: create draft revisions for assigned projects.
- `estimates.update`: edit draft estimates for assigned projects.
- `estimates.approve`: approve a draft as the project baseline.
- `estimates.view-costs`: view rates, costs and value comparisons.

Every action also requires the correct tenant, branch and project scope. Project assignment limits which projects a user can reach; permissions determine what the user can do there.

## 7. Acceptance Criteria

- A project can have multiple estimate revisions but only one current baseline.
- Approving an estimate creates project work items with zero approved progress.
- A later baseline preserves historical DSR links and progress.
- Users without approval permission receive 403 even if they call the route directly.
- DSR work entry selects a work item using plain operational terminology.
- Material requisitions may identify a work item but do not require one.
- Stock is reduced at issue/transfer/receipt boundaries, never again when a DSR is saved.
- The project page displays baseline quantity, approved progress, remaining quantity, completion and value/cost only where authorised.
- Estimate approval and baseline replacement appear in the audit trail.

## 8. Implemented Slice

- Versioned project estimates, estimate work lines and optional resource assumptions.
- Draft-only editing and permission-guarded baseline approval.
- Stable work-item keys across estimate revisions.
- Approved estimate lines synchronized into existing `project_activities` without replacing DSR history.
- Estimate, Work items and Performance tabs on project details.
- Baseline quantity, approved DSR progress, remaining quantity, completion, estimated revenue/cost and actual DSR input-cost comparison.
- Material assumptions compared with approved DSR material actuals at project level.
- Cost fields removed from responses for users without `estimates.view-costs`.
- Operational UI terminology changed from Activities/BOQ to Work items; BOQ reference remains optional.
- Manual editing of cumulative approved progress removed. Approved DSR work lines remain the only normal progress-posting path.
- Demo baseline separates real road work from petrol and excavator resource records.
