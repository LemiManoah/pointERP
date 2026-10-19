# Reports plan

## Purpose

Give the business owner one place to assess project delivery, costs, cash transactions, and operational risks. Administration > Reports is the entry point. It lists only reports the signed-in user may open; each report keeps its own branch and record-level authorization.

## Available now

| Report | Entry point | Meaning |
| --- | --- | --- |
| Owner overview | Reports > Owner overview | Period-filtered approved expenses, recorded POS receipts, and recorded expense payments, alongside current active-project budgets, incurred project cost, and visible exceptions. The source tables show the latest 25 records in each category. |
| Project budgets | Reports > Project budgets | Budget, incurred project cost from approved site reports and approved project expenses, and remaining budget by project. Amounts are grouped by budget currency. A project whose branch cost currency differs from its budget currency has no cost comparison until conversion rules exist. |
| Expenses | Reports > Expenses | Existing expense register filters and CSV/PDF exports. |
| Inventory | Reports > Inventory | Existing inventory dashboard and stock/export reports. |
| Equipment | Reports > Equipment | Existing fuel and maintenance activity and exports. |
| Operations | Reports > Operations | Existing daily site-report activity and exception export. |

The landing page links to these existing areas; it does not duplicate their calculations.

The owner overview uses the current branch context and one selected currency. Its period applies to approved expenses by `expense_date`, recorded POS payments by `recorded_at`, and recorded expense payments by `paid_at`. Reversed payments are excluded. Active-project budgets, incurred cost, and exceptions are current snapshots; changing the period does not recalculate them historically. The amount cards include all eligible records, while each source table shows the latest 25 with links to the underlying record.

## Next reports, in priority order

1. **Project and BOQ performance.** Show approved BOQ value, measured/earned work, approved site-report costs, approved project expenses, and progress by project and BOQ item. Reconcile the definitions with the project performance pages before adding totals so costs are not counted twice.
2. **Procurement and inventory.** Show purchase orders, received quantities, on-hand and reserved stock, stock movement, and reconciliation variances by branch/store. Keep purchase-order commitments separate from recorded spending.
3. **Workforce and equipment.** Show attendance, deployments, approved labour use, fuel, maintenance, and utilization by project and period.

## Shared rules

- Every report states its source records, inclusion rules, date field, and last refresh time.
- Filter by branch, period, and status where relevant. Never add unlike currencies together; show separate currency totals unless an approved conversion rate and date are defined.
- Hide reports without the required permission and enforce the same permission and branch scope on the server and exports.
- Use a summary followed by a drill-down table. Link each row back to its source record. Add CSV/PDF exports only when the report's figures and filters are settled.
- Prefer recorded/approved facts. Break project financial reports into **budget**, **committed**, **incurred**, and **paid** amounts, with outstanding balances shown separately. An approved purchase order is a commitment; material or service received and charged to a project is incurred cost; settlement is paid cash. Do not add these stages together as if they were different costs.
- The current project budget report calculates incurred cost only. Before adding committed and paid columns, define how purchase-order lines and expense payments are allocated to individual projects, and reconcile material charges between site reports, stock issues, and expense lines so one item is counted once.

## Acceptance checks for each new report

- Totals reconcile with the underlying register for the same filters.
- Branch-restricted users cannot see another branch's records or exports.
- Empty, multi-currency, partly paid, reversed, and missing-budget cases are explicit.
- Desktop and mobile layouts remain readable, and the report opens from the Reports landing page.
