# Phase 3E - Point of Sale for Sellable Inventory

## 1. Goal

Build a fast, permission-controlled point of sale that sells existing inventory without creating a second stock system.

The POS must:

- show only active inventory items marked `is_for_sale`;
- sell from a specific branch and store the user can access;
- resolve prices from the existing price lists and branch currency;
- verify stock and batch availability at checkout;
- post completed sales to the existing append-only inventory ledger;
- record payments and print a clear sales receipt;
- support controlled returns without editing completed sales;
- preserve tenant, branch, cost and price permissions;
- produce useful sales summaries without pretending to be the Phase 4 accounting ledger.

The POS is a sales interface over inventory. Inventory remains the source of truth for quantities, units, batches and movements.

## 2. Recommended First Release

The first release supports ordinary counter sales plus controlled partial and credit sales for known customers.

It should include:

1. Walk-in sales and optional selection of an existing company/customer.
2. One branch, one source store and one branch-default currency per sale.
3. Searchable sellable-item catalogue with current available quantity.
4. A persistent cart with quantity, selling unit, resolved unit price and optional permitted discount.
5. Retail, wholesale or another existing price list selected for the sale.
6. Batch allocation using earliest-expiring stock first, with the allocation visible to the cashier.
7. Cash, mobile money, card or bank payment, including an initial partial payment or no initial payment when credit is authorised.
8. Atomic checkout: sale, payments and stock issues either all succeed or all fail.
9. Printable receipt and searchable sales history.
10. Controlled full or partial returns against the original sale.

This phase includes only a bounded POS receivable: known customer, immutable payment records, amount paid and balance due. Credit limits, payment schedules, statements, ageing, collection allocation across invoices and accounting entries remain Phase 4 concerns. Do not label the receipt a tax invoice until VAT and local fiscal compliance have been designed.

## 3. User Flow

### 3.1 Start a Sale

The user opens `POS` from the sidebar.

- Branch comes from the current branch context.
- A single-branch user cannot change it.
- A multi-branch user may change it only with `pos.change-branch` and branch access.
- The source store defaults to the branch's preferred sales store.
- Only stores enabled for POS and accessible to the user appear.
- Price list defaults to the branch's configured default, normally Retail.
- Customer defaults to `Walk-in customer`; any active company may be selected because a company can have more than one commercial relationship with the tenant.

### 3.2 Build the Cart

The main screen uses a practical two-panel layout:

- left: search, category filter and sellable item results;
- right: current cart, totals and checkout action;
- mobile: item search first with a fixed cart button showing line count and total; the cart opens as a full-height sheet.

Selecting an item adds it to the cart. The cashier may:

- change quantity;
- select one of the item's configured selling units;
- remove the line;
- change price list for the whole sale if permitted;
- apply a line or sale discount only with the relevant permission;
- see available quantity expressed in the selected unit.

The interface must never allow manually typed arbitrary item names. Every sale line must reference a sellable inventory item.

### 3.3 Price Resolution

Resolve the price deterministically at the sale date:

1. active branch-specific item price for the selected price list and unit;
2. active tenant-wide item price for the selected price list and unit;
3. the item's default selling price, only for its base stock unit;
4. otherwise block the line and explain that a selling price must be configured.

Effective dates must be honoured. A price is copied to the sale line as a snapshot. Later price-list edits must not change historical sales.

Changing the price list recalculates draft cart prices after confirmation. A user with `pos.override-price` may override a price, but must enter a reason and the old and new values must be audited.

### 3.4 Stock and Batch Selection

The cart shows available stock, not merely on-hand stock, so requisition reservations remain protected.

- No sale may create negative stock.
- Quantity is converted to the item's base stock unit using the existing explicit conversion service.
- Non-tracked items need no allocation selection.
- Batch-tracked items are allocated from the selected store using FEFO: earliest expiry first, then oldest batch.
- Expired, inactive or zero-balance batches cannot be sold.
- One sale line may be fulfilled from several batches, so batch allocations require a separate table.
- The cashier can inspect allocations but should not need to choose batches in the normal flow.
- A permitted supervisor may change batch allocation before checkout.
- Serial-tracked sales are deferred until serial-level stock identity is fully implemented; the POS must hide or block those items rather than treating them as ordinary quantity stock.

Stock is rechecked under database locks during checkout. Availability shown while building the cart is advisory; checkout is authoritative.

### 3.5 Checkout and Payment

At checkout the user sees a compact confirmation dialog containing:

- customer;
- branch and store;
- item count and total quantity;
- subtotal, discount and amount due;
- payment methods and entered amounts;
- warning that completing the sale reduces stock and creates an immutable receipt.

Checkout has one payment amount field, defaulted to the sale total. If an authorised user enters less than the total for a known customer, the system automatically records the remainder as customer credit. Walk-in customers must pay in full. Users with `pos.record-payment` may record later collections without reposting stock. Cash change may be calculated and displayed but is not stored as a payment. Non-cash payments require a reference.

Completion runs in one database transaction:

1. lock and revalidate the draft sale;
2. revalidate item saleability, prices, units and batches;
3. lock store-item balances and prevent negative stock;
4. create immutable payment records and snapshot the sale's amount paid, balance due and payment status;
5. post one inventory `Issue` movement per stock allocation using `PostInventoryStockMovement`;
6. mark the sale completed;
7. write the audit events;
8. redirect to the printable receipt.

Stable source keys such as `pos-sale:{sale-line-allocation-id}` make checkout idempotent.

### 3.6 Receipt

The receipt page should show:

- company and branch identity;
- receipt number and completion date/time;
- cashier and customer;
- item, quantity, unit price, discount and line total;
- subtotal, discount and total;
- payment method totals and change due;
- return status where applicable;
- a print button.

Batch details may appear on the receipt where traceability is useful, but should remain visually secondary.

### 3.7 Returns

Returns always reference a completed sale and cannot exceed the unreturned quantity.

- The user selects original lines and return quantities.
- A reason is compulsory.
- A restockable return posts an inventory `Return` movement to the original store and batch.
- A damaged or unusable return must not silently increase saleable on-hand stock. Defer this disposition until a quarantine/damaged-stock workflow exists, or require the user to record it through the controlled inventory adjustment workflow.
- Refund recording requires `pos.refunds.record` and creates an immutable negative payment/reversal record linked to the original payment.
- The original sale remains unchanged and becomes `partially_returned` or `returned`.

Completed sales are never deleted. Draft sales may be discarded by their owner or a user with `pos.drafts.manage`.

## 4. Statuses and Enums

Use enum classes rather than database enum columns.

### `PosSaleStatus`

- `draft`
- `held`
- `completed`
- `partially_returned`
- `returned`
- `cancelled`

### `PosPaymentMethod`

- `cash`
- `mobile_money`
- `card`
- `bank`

### `PosPaymentStatus`

- `recorded`
- `reversed`

### `PosReturnDisposition`

- `restock`
- `damaged`

Only `restock` is posted automatically in the first release.

## 5. Data Model

### 5.1 `pos_sales`

- UUID `id`, `tenant_id`, `branch_id`, `inventory_store_id`
- nullable `customer_id`; null means walk-in
- `sale_number`
- `status`
- `inventory_price_tier_id`
- `currency_code` copied from the branch
- `subtotal`, `discount_total`, `total_amount`
- nullable `notes`
- `sold_by`, nullable `completed_by`, `completed_at`
- nullable `cancelled_by`, `cancelled_at`, `cancellation_reason`
- timestamps

Use a tenant-unique sale number and branch/date indexes. Do not soft-delete completed sales.

### 5.2 `pos_sale_lines`

- UUID `id`, `tenant_id`, `pos_sale_id`, `inventory_item_id`
- `unit_of_measure_id`
- `quantity`, `conversion_multiplier`, `stock_quantity`
- `unit_price`, `discount_amount`, `line_total`
- nullable `inventory_item_price_id`
- item code/name, unit and price-list snapshots
- nullable `price_override_reason`
- `sort_order`

### 5.3 `pos_sale_line_allocations`

- UUID `id`, `tenant_id`, `pos_sale_line_id`
- nullable `inventory_batch_id`
- `stock_quantity`
- nullable `inventory_stock_movement_id`
- batch-number and expiry snapshots

This table lets one line consume several batches and gives every stock issue an exact source record.

### 5.4 `pos_payments`

- UUID `id`, `tenant_id`, `branch_id`, `pos_sale_id`
- `payment_number`, `method`, `amount`, `currency_code`
- nullable `reference`, `notes`
- `status`, `recorded_by`, `recorded_at`
- nullable `reversal_of_id`, `reversed_by`, `reversed_at`, `reversal_reason`

Payments are immutable. Corrections are reversals and replacements.

### 5.5 `pos_returns` and `pos_return_lines`

The return header records the sale, return number, reason, status, actor and date. Each line references the original sale line, quantity, conversion snapshot, disposition, refund amount and any return stock movement.

Do not combine returns into negative sale lines; explicit return records are easier to authorize, audit and report.

## 6. Existing App Integration

### Inventory

- `InventoryItem.is_for_sale` controls catalogue eligibility.
- `InventoryStoreItem` controls whether an item is available in the selected store.
- `InventoryQuantityConverter` handles selling-unit conversion.
- `InventoryStockBalance` supplies available quantities.
- `InventoryBatch` supplies FEFO allocations.
- `PostInventoryStockMovement` posts completed sale issues and restockable returns.
- Stock movement `source_type` points to the sale allocation or return line.
- Inventory movement pages link back to the POS receipt.

### Pricing and Currency

- `InventoryPriceTier` provides Retail, Wholesale and other named lists.
- `InventoryItemPrice` provides branch/unit/effective-date prices.
- The sale currency is the selected branch's default currency.
- Multicurrency POS is deferred; accepting foreign payment introduces conversion, cash-control and accounting requirements.

### Companies

- Existing active `Customer` records are selectable buyers regardless of their current company type.
- Walk-in sales do not require creating a customer.

### Documents and Audit

- Receipts are generated from structured sale data; optional external evidence may use the existing document-link system later.
- Audit price overrides, discounts, checkout, cancellation, return and payment reversal.
- Normal sale creation belongs in the business activity stream; security-sensitive changes remain in the audit trail.

### Dashboard

Add permission-aware metrics:

- today's completed sales;
- today's amount by currency;
- top-selling items by quantity;
- payment-method split;
- returns today;
- low-stock sellable items.

Do not display gross profit until Phase 4 defines a formal stock valuation method.

## 7. Permissions and Policies

Recommended permissions:

- `pos.view`
- `pos.sell`
- `pos.change-branch`
- `pos.change-store`
- `pos.change-price-list`
- `pos.override-price`
- `pos.apply-discount`
- `pos.hold-sales`
- `pos.view-all-sales`
- `pos.view-payments`
- `pos.returns.create`
- `pos.returns.approve`
- `pos.refunds.record`
- `pos.payments.reverse`
- `pos.reports.view`
- `pos.reports.export`
- `pos.drafts.manage`

`PosSalePolicy`, `PosPaymentPolicy` and `PosReturnPolicy` must check:

- correct tenant;
- branch access;
- explicit permission;
- record status;
- selected store access;
- restricted price/cost visibility;
- special action authority.

Buttons being hidden is not security. Direct unauthorized requests must return 403.

## 8. Interface

### Sidebar

Add one permission-guarded `POS` item. Do not scatter separate receipt, payment and return links throughout the sidebar.

### POS Page

Use tabs within the POS page:

- `New sale`
- `Sales history`
- `Returns` when permitted
- `Reports` when permitted

The `New sale` tab is the working screen, not a modal. Product results should be dense rows or compact tiles with item name, code, price and available quantity. Avoid decorative cards and nested cards.

The cart should use the established white content surface, stable column widths, searchable comboboxes and comma-separated quantities and money. Long item or customer names must truncate with tooltips instead of resizing controls.

### Sales History

Columns:

- receipt and date beneath it;
- customer and cashier beneath it;
- store;
- total;
- payment summary;
- status;
- actions.

Filters:

- search by receipt, customer or item;
- date range;
- branch/store where authorized;
- status;
- payment method.

### Receipt Details

Use a details page with printable receipt content, payment table, stock allocations, returns and audit-aware actions. Do not place the entire receipt in a modal.

## 9. Implementation Chunks

### 3E.1 POS Foundation and Catalogue

Status: implemented, pending local validation. The first slice includes generated schema files, typed domain models/enums, tenant-and-branch policy boundaries, seeded Cashier access, a responsive sellable-stock catalogue, deterministic price-list/unit resolution and the working cart.

- Add enum classes, migrations, models, relationships and factories.
- Add permissions, policies, routes and sidebar entry.
- Build the POS option service scoped by tenant, branch and store.
- Implement sellable-item search, price resolution and available-stock presentation.
- Build the responsive cart page with unit conversion and totals.

Acceptance: an authorized cashier sees only sellable, active, store-enabled items in accessible branches and receives a deterministic current price.

### 3E.2 Atomic Checkout and Receipt

Status: implemented, pending local validation. Checkout recalculates prices and decimal totals on the server, allocates valid batches using FEFO, posts idempotent inventory issues in the same transaction and opens a printable immutable receipt with sales history.

- Save/hold draft carts.
- Validate totals server-side using decimal arithmetic.
- Re-resolve and snapshot prices at checkout.
- Allocate batches using FEFO.
- Record one or more payments.
- Post idempotent inventory issues atomically.
- Generate receipt details and print view.
- Add sales history and filters.

Acceptance: completing a sale exactly once records the receipt, payments and matching stock reduction, and a checkout failure records none of them.

### 3E.2A Controlled Credit and Collections

Status: implemented, pending local validation.

- Require full payment for walk-in sales.
- Permit partial or credit sales only for a known customer and a user with `pos.sell-on-credit`.
- Infer paid, partially paid or unpaid status directly from the entered payment amount.
- Store `amount_paid`, `balance_due` and a typed payment status on the sale.
- Record later permission-guarded payments against the original receipt under a database lock.
- Block overpayment and require references for non-cash collections.
- Keep stock posting tied to sale completion; later payments never move stock again.

Acceptance: an authorised credit sale creates one stock issue and a visible customer balance, and later collections reduce only that balance until it is paid.

### 3E.3 Returns and Reversals

Status: planned. The return schema and model boundaries exist, but return authorization, refund and restocking actions are intentionally left for the next implementation chunk.

- Add return request and authorization flow.
- Enforce remaining returnable quantity.
- Restock to the original store/batch where allowed.
- Record refund or payment reversal with reason.
- Add partial-return and returned statuses.
- Link returns to sale details and stock movements.

Acceptance: returns preserve the original sale, cannot exceed sold quantities and restore only approved restockable stock.

### 3E.4 Reporting, Audit and Polish

- Add daily sales and payment-method summaries.
- Add sellable-item low-stock warnings.
- Add CSV and PDF exports.
- Complete audit events and document links.
- Seed realistic retail and wholesale prices, sales, payments and returns.
- Verify desktop/mobile layout and printable receipts.

Acceptance: managers can reconcile sales totals to payment records and inventory movements without cost leakage across roles or branches.

## 10. Required Tests

- tenant and branch isolation;
- store access and default branch/store behavior;
- non-sellable and inactive items excluded;
- deterministic branch/price-list/unit price resolution;
- expired prices ignored;
- price override and discount permissions;
- conversion to base stock unit;
- reservation-aware negative-stock prevention;
- FEFO allocation across multiple batches;
- expired batches blocked;
- checkout idempotency;
- atomic rollback when payment or stock posting fails;
- exact payment requirement and split-payment totals;
- receipt snapshots unaffected by later item or price edits;
- completed sale immutability;
- partial and full returns;
- return quantity cannot exceed remaining sold quantity;
- restock returns create matching inventory movements;
- payment reversals preserve originals;
- cost and payment fields omitted without permission;
- direct unauthorized requests return 403;
- audit events contain actor, tenant, branch, old/new values and reason.

## 11. Deferred Features

- full accounts receivable: credit limits, statements, ageing, collection allocation and accounting entries;
- quotations, layaway and sales orders;
- VAT calculation and fiscal/e-invoicing integration;
- loyalty points and promotions engine;
- barcode hardware integration and label printing;
- serial-number selling until serial stock identity exists;
- cash drawer sessions, opening floats and till reconciliation;
- multicurrency tender;
- formal cost of goods sold, valuation and gross margin;
- offline checkout and synchronization;
- e-commerce integration.

These are not rejected ideas. They are separated so the first POS remains understandable and operationally reliable.

## 12. Recommended Decisions Before Implementation

The recommended defaults are:

1. Walk-in sales require full payment; bounded credit is allowed only for known customers and authorised users.
2. Branch default currency only.
3. Retail is the default price list, with permitted switching to Wholesale or another list.
4. Walk-in customer is allowed.
5. Initial and later payments may settle one sale; their sum may never exceed the total.
6. Batch selection is automatic FEFO with supervisor override.
7. Discounts require permission; price overrides require stronger permission and a reason.
8. Only restockable returns affect inventory automatically.
9. Printed output is called a sales receipt, not a tax invoice.
10. No cashier shift/till management in the first release.

These choices produce a useful POS without pulling Phase 4 accounting and compliance work into inventory sales.
