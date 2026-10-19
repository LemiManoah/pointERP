# Reports plan

## Purpose

Give the business owner one place to assess project delivery, costs, cash transactions, and operational risks. Administration > Reports is the entry point. It lists only reports the signed-in user may open; each report keeps its own branch and record-level authorization.

## Available now

| Report | Entry point | Meaning |
| --- | --- | --- |
| Project budgets | Reports > Project budgets | Budget, approved site-report input costs, approved project expenses, and remaining budget by project. Amounts are grouped by budget currency. A project whose branch cost currency differs from its budget currency has no spend comparison until conversion rules exist. |
| Expenses | Reports > Expenses | Existing expense register filters and CSV/PDF exports. |
| Inventory | Reports > Inventory | Existing inventory dashboard and stock/export reports. |
| Equipment | Reports > Equipment | Existing fuel and maintenance activity and exports. |
| Operations | Reports > Operations | Existing daily site-report activity and exception export. |

The landing page links to these existing areas; it does not duplicate their calculations.

## Next reports, in priority order

1. **Owner overview.** Show approved expenses, recorded POS receipts, recorded expense payments, active project budgets, recorded project spend, and exceptions for a selected date range. Label transaction-based money in/out clearly; it is not a bank balance or full cash-flow statement.
2. **Project and BOQ performance.** Show approved BOQ value, measured/earned work, approved site-report costs, approved project expenses, and progress by project and BOQ item. Reconcile the definitions with the project performance pages before adding totals so costs are not counted twice.
3. **Procurement and inventory.** Show purchase orders, received quantities, on-hand and reserved stock, stock movement, and reconciliation variances by branch/store. Keep purchase-order commitments separate from recorded spending.
4. **Workforce and equipment.** Show attendance, deployments, approved labour use, fuel, maintenance, and utilization by project and period.

## Shared rules

- Every report states its source records, inclusion rules, date field, and last refresh time.
- Filter by branch, period, and status where relevant. Never add unlike currencies together; show separate currency totals unless an approved conversion rate and date are defined.
- Hide reports without the required permission and enforce the same permission and branch scope on the server and exports.
- Use a summary followed by a drill-down table. Link each row back to its source record. Add CSV/PDF exports only when the report's figures and filters are settled.
- Prefer recorded/approved facts. Clearly distinguish budgets, commitments, incurred costs, payments, and outstanding balances.

## Acceptance checks for each new report

- Totals reconcile with the underlying register for the same filters.
- Branch-restricted users cannot see another branch's records or exports.
- Empty, multi-currency, partly paid, reversed, and missing-budget cases are explicit.
- Desktop and mobile layouts remain readable, and the report opens from the Reports landing page.
