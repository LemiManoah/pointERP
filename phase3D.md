# PointERP Phase 3D: Operational Expenses and Accountability

## 1. Decision and Purpose

An expenses module is necessary because some genuine construction costs do not originate from inventory, a purchase order, equipment fuel, equipment maintenance or payroll. Examples include site meals, local transport, permits, utilities, communication, accommodation, small tools consumed immediately and emergency services.

The module must not become a duplicate purchasing or accounting system. Its purpose is:

> Define reusable expense items, record a non-stock cost, show who was paid or reimbursed, allocate it to the correct branch/project/site, attach evidence, approve it, record its payments, and expose the approved amount to project cost reporting.

Phase 3D is an operational expense and payment sub-ledger. It may record that money was paid and preserve the payment method/reference, but formal accounts payable, bank reconciliation, tax accounting, journal entries and financial statements remain in Phase 4.

## 2. Source-of-Truth Boundaries

| Cost | Source module | Expense module treatment |
|---|---|---|
| Stock material | PO/direct receipt and inventory ledger | Do not enter again as an expense |
| Equipment fuel | Equipment fuel transaction | Do not enter again as an expense |
| Equipment maintenance | Maintenance work order and parts | Do not enter again as an expense |
| Salaried staff/payroll | Future HR/payroll module | Do not enter again as an expense |
| Supplier PO purchase | Purchase order, goods receipt and later supplier invoice | Do not enter again as an expense |
| Subcontracted measured work | Future subcontract/commercial workflow | Do not enter again as an expense |
| Site petty cash, travel, meals, permits, utilities and incidental services | Expense module | Record here |
| DSR other cost | Expense draft linked directly to the DSR | Record once in Expenses; never create a duplicate DSR cost line |

An expense records a cost incurred. Approval confirms that the expense is accepted as a company/project cost. Approval does not create an accounting journal and does not by itself prove that a bank or cash payment occurred.

## 3. Simplified User Flow

1. A permitted user selects **New expense**.
2. The current branch is selected automatically and is changeable only by a user with cross-branch authority.
3. The user selects a category and then an expense item, such as Utilities -> Yaka electricity.
4. The user enters quantity, unit amount and narration, then adds the line to an expense cart.
5. The user enters the expense date, payee, currency, supplier invoice/reference and project allocation.
6. The user records whether it is unpaid, already paid, or partly paid. An initial payment can be captured in the same screen when permitted.
7. The user attaches a receipt or other supporting evidence where available.
8. The expense is saved as a draft and submitted for approval.
9. A user with `expenses.approve` reviews the allocation and evidence, then approves or rejects it. Self-approval is allowed when the user has this permission.
10. Further payments may be recorded against an approved outstanding balance.
11. An approved expense contributes to project and branch actual costs.
12. DSR other costs create linked Expense drafts directly, so reporting has one cost record rather than a later matching step.

The initial release should not require a separate expense request before an expense can be recorded.

## 4. Scope

### 4.1 In Scope

- Tenant-managed expense categories and expense items.
- Draft, submit, approve, reject, cancel and correct workflows.
- Multi-line expenses.
- Supplier, staff or free-text payee snapshots.
- Branch, project, site and optional work-item allocation.
- Tenant-enabled currencies and immutable exchange-rate snapshots where conversion is required.
- Receipt/document evidence.
- Zero, partial and full payment recording with payment history.
- Cash, bank, card, mobile-money, cheque and other payment methods.
- Supplier/staff outstanding balances for operational follow-up.
- Permission-based cost visibility and actions.
- Direct DSR expense-draft creation and traceability.
- Project and branch expense summaries and exports.
- Audit trail for all workflow and financial-field changes.

### 4.2 Explicitly Out of Scope

- General ledger and accounting journals.
- Bank and cashbook reconciliation or statement import.
- Supplier invoice matching and accounts payable.
- VAT, withholding tax and statutory tax returns.
- Payroll, salary advances and staff loans.
- Full petty-cash float/cashier management and physical cash balancing.
- Budget authorization and departmental budgets.
- Recurring expense automation.
- Subcontractor measurement and certification.

## 5. Terminology

- **Expense:** A non-stock cost incurred by the business.
- **Expense type:** A reporting group such as Utilities, Travel or Site Welfare.
- **Expense item:** A reusable selectable cost such as Yaka electricity, Water, Internet, Site meals or Permit fee.
- **Expense line:** An expense item, amount and allocation within an expense.
- **Payee:** The supplier, staff member or other party that received or is owed the money.
- **Payment:** An immutable record of money paid against an expense.
- **Outstanding balance:** Approved expense total less valid payments.
- **Evidence:** A receipt, invoice, permit, ticket or other supporting document.
- **DSR expense:** A normal expense draft created from a DSR and linked through `expenses.daily_site_report_id`.
- **Correction:** A controlled replacement or adjustment to an approved expense; approved records are not silently edited.

Avoid calling expense approval a payment. Approval accepts the cost; a payment records settlement. Phase 4 will later translate approved expenses and payments into accounting entries.

## 6. Domain Rules

1. Expenses belong to one tenant and one branch.
2. The branch defaults from the user's current branch context.
3. A project must belong to the expense branch; a site and work item must belong to the selected project.
4. Draft expenses may be edited. Submitted and approved expenses are immutable except through controlled workflow actions.
5. Rejection and cancellation require a reason.
6. Approval is permission based, not based on whether the approver created the record.
7. Cost fields are omitted from server responses for users without cost visibility.
8. Only active expense items may be used on new lines. Historical lines retain item/type snapshots.
9. Only tenant-enabled currencies may be selected.
10. Store both transaction currency amounts and the exchange-rate snapshot used for base-currency reporting.
11. Every approved expense has an auditable allocation. An expense with no project is a branch/overhead cost.
12. Payment total cannot exceed the expense total unless a controlled reversal first corrects an erroneous payment.
13. Payment records are never edited or deleted; mistakes are reversed with a reason and replacement payment.
14. Expense approval and payment recording require independent permissions. A user may hold both permissions.
15. An initial payment may be captured while creating the expense, but it becomes reportable only with an accepted expense and retains its own actor/reference.
16. DSR Other costs create expense drafts directly; the DSR must not create a second provisional cost record.
17. Approved expenses are never permanently deleted.
18. Drafts with no downstream links may be permanently deleted by a user with explicit delete permission.
19. Document evidence is access-controlled through the existing document module.

## 7. Status Model

```text
Draft -> Submitted -> Approved
                  -> Rejected -> Draft
Draft/Submitted -> Cancelled
Approved -> Corrected (through a replacement/correction record)
```

Recommended enum: `ExpenseStatus` with `Draft`, `Submitted`, `Approved`, `Rejected`, `Cancelled` and `Corrected`.

Expense workflow status and payment status are separate:

```text
Unpaid -> Partially paid -> Paid
```

Payment status is derived from valid payment records and is never manually selected or used as the expense approval status.

## 8. Data Model

### 8.1 `expense_categories`

- `id`, `tenant_id`
- `code` autogenerated from name but editable
- `name`, `description`
- `requires_evidence`
- `is_active`
- timestamps and soft deletes

Expense types are tenant-managed and are themselves the reporting classification; a second classification field would duplicate the same decision. Inactive types remain visible on historical expenses but cannot be selected for new lines.

Examples:

- Utilities: Yaka electricity, Water, Internet, Telephone
- Travel: Fuel reimbursement, Taxi, Bus fare, Accommodation
- Site welfare: Site meals, Drinking water, First-aid consumables
- Statutory and permits: Local authority permit, Inspection fee
- Administration: Printing, Courier, Airtime, Office refreshments

### 8.2 `expense_items`

- `id`, `tenant_id`, `expense_category_id`
- `code` autogenerated from name but editable
- `name`, `description`
- nullable `default_unit_of_measure_id`
- `requires_evidence`
- `is_active`
- timestamps and soft deletes

The category is inherited through the expense item. The transaction form should not make users independently select conflicting category and item values: category filters the item selector, and the selected item determines the category stored on the line.

### 8.3 `expenses`

- `id`, `tenant_id`, `branch_id`
- nullable `daily_site_report_id` for an expense created directly from a DSR
- `expense_number`, unique per tenant
- `expense_date`
- `payee_type` enum: supplier, staff or other
- nullable `customer_id` for an existing company/supplier
- nullable `staff_id`
- `payee_name_snapshot`
- `currency_code`
- nullable `exchange_rate_id`
- `exchange_rate`, `base_currency_code`
- `subtotal`, `total_amount`, `base_currency_total`
- derived `payment_status` may be exposed by the model/service but is not manually maintained
- `description`, `reference`
- `status`
- submission, approval, rejection, cancellation and correction actor/timestamp fields
- `decision_reason`
- nullable `corrects_expense_id`
- `created_by`, `updated_by`
- timestamps and soft deletes

`reference` is optional and may contain an external receipt, invoice, mobile-money or transaction reference. It is not the system expense number.

### 8.4 `expense_lines`

- `id`, `tenant_id`, `expense_id`, `expense_item_id`
- `expense_category_name_snapshot`, `expense_item_name_snapshot`
- nullable `project_id`, `site_id`, `project_activity_id`
- `description`
- `quantity`, `unit_amount`, `amount`, `base_currency_amount`
- `sort_order`
- timestamps

Quantity defaults to one so a user can simply enter an amount. It remains useful for costs such as 25 electricity units or three nights of accommodation. This quantity is descriptive and does not create inventory.

### 8.5 `expense_payments`

- `id`, `tenant_id`, `branch_id`, `expense_id`
- `payment_number`, unique per tenant
- `paid_at`
- `amount`, `currency_code`
- `base_currency_amount`, exchange-rate snapshot fields
- `payment_method` enum
- nullable `reference`
- nullable `notes`
- `status` enum: recorded or reversed
- nullable `reverses_payment_id`
- `recorded_by`, nullable `reversed_by`, `reversed_at`, `reversal_reason`
- timestamps

An expense may have many payments. The outstanding balance is calculated from non-reversed payments. Do not use a single mutable `amount_paid` column on the expense.

### 8.6 DSR Expense Link

`expenses.daily_site_report_id` is the single link between a DSR and an expense. Creating an Other cost from an editable DSR creates one ordinary expense draft with the DSR's tenant, branch, project, site, date and currency context. The expense then follows the normal submit, approve, reject, cancel and payment workflow.

There is no DSR cost snapshot or expense-reconciliation table. DSR operational input totals cover work resources; approved linked expenses enter project financial actuals exactly once through the expense ledger.

### 8.7 Documents

Extend the existing controlled document links to support `Expense` as a linkable record. Do not add raw file paths or a second attachment table.

## 9. Permissions and Policies

Recommended permissions:

- `expenses.view`
- `expenses.view-all`
- `expenses.view-costs`
- `expenses.create`
- `expenses.update`
- `expenses.submit`
- `expenses.approve`
- `expenses.reject`
- `expenses.cancel`
- `expenses.correct`
- `expenses.delete-drafts`
- `expenses.export`
- `expense-categories.manage`
- `expense-items.manage`
- `expense-payments.view`
- `expense-payments.record`
- `expense-payments.reverse`

`ExpensePolicy` must check tenant, branch access, permission, record state and project scope where applicable. Direct route calls must return 403 when unauthorized even if the interface hides the action.

## 10. Backend Components

### Models and Enums

- `ExpenseCategory`
- `ExpenseItem`
- `Expense`
- `ExpenseLine`
- `ExpensePayment`
- `ExpenseStatus`
- `ExpensePayeeType`
- `ExpensePaymentMethod`
- `ExpensePaymentStatus`

### Actions

- `SaveExpense`
- `SubmitExpense`
- `ApproveExpense`
- `RejectExpense`
- `CancelExpense`
- `CorrectExpense`
- `RecordExpensePayment`
- `ReverseExpensePayment`

Actions own state transitions, totals, currency snapshots and audit records. Controllers remain thin.

### Services

- `ExpenseFormOptions`
- `ExpenseNumberGenerator`
- `ExpenseCostSummary`
- `ExpenseBalanceCalculator`

Project performance should consume `ExpenseCostSummary`; it should not independently reproduce expense queries.

## 11. Interface Plan

### Navigation

Add one permission-guarded **Expenses** sidebar item. Keep Expenses, Payments, Categories, Expense items and Reports as tabs within the module rather than adding several sidebar items.

### Expenses Page

Tabs:

- Active: draft, submitted, approved and rejected records
- Cancelled/Corrected
- Payments, visible to payment viewers
- Categories, visible only to category managers
- Expense items, visible only to item managers
- Reports

Controls under the page heading:

- Debounced search
- Status searchable dropdown
- Branch, project, category, payee and date filters where authorized
- New expense button on the extreme right

Use the established table style rather than the stacked expense cards shown in the reference screenshot. Show expense number, date, payee, allocation summary, currency/amount where permitted, approval status, payment status, paid amount, balance and actions.

### Create/Edit Expense

Use a full page rather than a modal because evidence, allocations and multiple lines need room. Adapt the useful cart pattern from the reference screenshots to the PointERP theme:

- Desktop: a wide cart/details area and a narrower **Add expense item** panel
- Mobile: add-item panel first, then the cart and transaction details
- Add-item panel: category, filtered searchable expense item, quantity, unit amount and narration
- Cart table: item, allocation, quantity, unit amount, total and icon actions
- Transaction details: payee, expense date, currency, supplier invoice/reference, project/site and evidence
- Settlement section: unpaid, partly paid or paid-now choice; payment method, amount and reference only when recording an initial payment
- Sticky or clearly visible totals and save/submit actions

Project, site and work-item selectors must cascade and remain fixed width when long values are selected.

Do not copy the reference screen's ambiguous **Clear balance** action. Use **Record payment**, open a confirmation dialog showing the old balance, payment and resulting balance, and show payment history on the expense details page.

### Expense Details

- Status and action toolbar
- Payee and transaction summary
- Allocation lines
- Evidence table
- Approval/rejection timeline
- Payment history with record/reverse actions
- Audit activity
- Related DSR cost where reconciled

## 12. Reporting Integration

Project actual-cost reporting should distinguish:

- inventory material issues
- equipment/fuel/maintenance costs
- approved operational expenses
- approved expenses created from DSRs

The project dashboard counts an approved expense once, whether it was created from a DSR or from the Expenses workspace. Draft, submitted, rejected and cancelled expenses do not enter actual cost.

Approved overhead expenses without a project appear in branch expense reports, not project profitability.

Initial expense reports:

- Expenses by category and expense item
- Expenses by project, site and work item
- Expenses by branch and payee
- Unpaid and partially paid expenses (operational creditors/outstanding balances)
- Payments by date and payment method
- Expenses created from DSRs by status

Reports must use approved expense amounts for accepted cost and valid payment records for settlement. They must not treat an unpaid approved expense as unapproved, or a paid draft expense as an accepted project cost.

## 13. Notifications and Audit

Notify relevant users when an expense is submitted, approved, rejected or returned through correction. Use the existing notification infrastructure.

Audit:

- creation and edits
- submission and decisions
- allocation changes
- currency/exchange-rate snapshot
- evidence links and versions
- DSR expense creation and cancellation
- cancellation and correction

Store old/new values, actor, tenant, branch, record, reason and request metadata through the existing audit logger.

## 14. Implementation Chunks

### 3D.1 Expense Foundation

- Enums, migrations, models and factories
- Categories and expense-items CRUD
- Expense draft create/edit/details/index
- Cart-based expense entry page
- Tenant/branch/project validation
- Permissions, policies and seed data

### 3D.2 Approval and Evidence

- Submit, approve, reject, cancel and correction transitions
- Existing document-control integration
- Notifications and audit trail
- Approved-record immutability
- Initial, partial and final payments
- Payment history, balance calculation and controlled reversal

### 3D.3 DSR and Project Cost Integration

- Direct DSR expense creation
- Create a linked draft expense directly from the editable DSR Other costs section; do not create a second provisional line first
- Require the ordinary expense submit/approve/payment workflow; DSR approval never approves or pays the expense
- Lock the generated allocation against independent editing or deletion. It may be cancelled with a reason and replaced; the DSR keeps the linked history and project actual cost includes only approved expenses.
- Remove double counting from project cost summaries
- Expense filters, exports and dashboard summaries
- Isolation, permission and state-machine tests

### Deferred 3D.4 Cash Advances and Reimbursements

Only implement after confirming the client's real process for petty-cash floats and staff advances. It would add advance issuance, accountability, balance return and reimbursement, but should not complicate the initial expense workflow.

## 15. Seed Data

For Point Investment, seed realistic examples:

- Site meals allocated to BKH Road
- Local transport allocated to one site
- Permit fee allocated to the project
- Kampala office internet as branch overhead
- One submitted expense awaiting approval
- One approved expense created directly from a DSR
- One partially paid utility expense and one unpaid supplier expense
- One rejected expense with a reason

Use users with different branch and cost permissions to prove separation in the interface.

## 16. Required Tests

- Tenant and branch isolation
- Project/site/work-item ownership validation
- Cost fields absent for users without cost permission
- Expense type filters expense items and an item cannot be posted under another type
- Permission-guarded creation, submission and approval
- Permission-based self-approval allowed
- Approved records immutable
- Partial and full payment balance calculations
- Overpayment rejected
- Payment reversal preserves the original payment and requires a reason
- Payment actions require payment permission independently of expense approval
- Rejection/cancellation reason required
- Only enabled tenant currencies accepted
- Exchange-rate snapshot remains unchanged historically
- Evidence access follows document permissions
- Direct DSR expense creation produces one expense and no duplicate provisional cost
- Project actual cost includes only approved expenses
- Draft deletion allowed only without downstream links
- Audit events contain actor, allocation changes and decision reason

## 17. Acceptance Criteria

- Users can record and approve genuine non-stock expenses without creating fake inventory items or purchase orders.
- Categories contain reusable expense items, and expense lines use those items consistently.
- The same cost cannot be counted through both a DSR and an approved expense.
- Expense approval is fully permission guarded and audited.
- Branch/project/site selections obey existing access rules.
- Users without cost permission receive no amount fields from the server.
- Approved expenses appear in project actual costs; branch overhead does not distort project cost.
- Approved expenses cannot be silently edited or deleted.
- Users can record zero, partial or full settlement and see an auditable outstanding balance.
- The module creates no accounting journals before Phase 4.

## 18. Recommendation

Build 3D.1 through 3D.3 before IPC and full project financial control. Approved DSR work quantities represent output; approved inventory, equipment and expense records represent inputs. Phase 4 can then compare commercial value earned against controlled actual cost without relying on unverified free-text DSR costs.

## 19. Implementation Record

Implemented in the initial Phase 3D release:

- UUID migrations, enum classes, tenant-scoped models and factories for categories, items, expenses, lines and payments.
- One permission-guarded Expenses sidebar entry with local tabs for expenses, payments, categories, expense items and reports.
- Searchable filters, separate active/inactive reference-data views, controlled deactivation, restoration and guarded permanent deletion.
- A responsive full-page expense cart with cascading category/item and project/site/Work item selectors.
- Current-branch defaults. Branch changes require `expenses.change-branch`, and the backend independently enforces the same restriction.
- Draft creation/editing, evidence-aware submission, permission-based approval, rejection and cancellation.
- Existing document control linked to expenses for receipt and invoice evidence.
- Zero/initial/partial/full payment support, derived balances and reason-controlled payment reversal.
- Direct DSR expense creation and project performance integration that counts approved operational expenses exactly once.
- The editable DSR Other costs section now creates permission-guarded Expense drafts directly. Branch, date, project, site and currency are inherited; the user selects the expense item, payee, quantity and amount once. The Expense links back to the DSR immediately and follows normal submission, approval and payment controls.
- Database notifications for expense submission and workflow decisions.
- Permission-aware CSV and PDF expense register exports.
- Audit events for masters, expense changes, workflow transitions, payments and DSR expense creation.
- Point Investment demo data for utilities, site welfare, partial payment, evidence, a DSR-linked expense and a draft awaiting evidence.
- Focused Phase 3D feature tests covering visibility, branch isolation, evidence, payment control and direct DSR expense linkage.

Deferred deliberately:

- Correction/replacement workflow for approved expenses. Approved records remain immutable in this release.
- Full analytical report builder with server-side branch, category, project, payee and date filters.
- Petty-cash advances, staff accountability and reimbursements.
- General-ledger, cashbook, tax and bank-reconciliation postings, which belong to Phase 4.
