# PointERP Phase 3B: Materials, Procurement and Inventory

## 1. Purpose and Status

Phase 3B is the next major implementation phase after Phase 3A. It introduces controlled materials, suppliers, procurement and stores while preserving the operational history already captured by Daily Site Reports and the fleet module.

Status: implementation in progress. Chunks 3B.1 and 3B.2 are implemented and awaiting local migration, focused tests, static analysis and UI acceptance before requisitions begin in Chunk 3B.3.

The roadmap and SRS are the authority for this phase. `phase3A.md` remains the authority for equipment, fuel, maintenance, meter, custody and fleet location behaviour. This document owns stock, procurement and material issue workflows.

## 2. Business Outcome

At the end of Phase 3B, a construction company should be able to answer:

1. What materials and consumables are defined, and in what units?
2. What stock is physically held at each depot, warehouse or site store?
3. What has been requested, approved, ordered, received, inspected, issued, returned or transferred?
4. Which supplier and purchase order supports a receipt?
5. Which project, site, activity or equipment received a material issue?
6. What quantity is on hand, reserved, available, in transit or rejected?
7. Which stock is below its reorder level or cannot be issued?
8. Which approved DSR material lines are supported by stock movements, and which remain an external or unregistered snapshot?
9. Who approved, changed or posted each procurement and stock event?

The phase replaces uncontrolled material names and spreadsheet balances with master data plus an immutable movement ledger. It does not turn a reported DSR quantity into stock consumption automatically without an explicit posting or reconciliation decision.

## 3. Scope and Boundaries

### 3.1 In scope

- Material categories and tenant-managed item master.
- Units of measure and controlled conversion factors.
- Suppliers using the existing customer/supplier foundation.
- Warehouses, depots and site stores.
- Requisitions and approval workflow.
- Supplier quotation capture and comparison.
- Purchase orders and line-level commitments.
- Goods receipts, inspection outcomes and rejected quantities.
- Stock issues, returns, transfers, adjustments and stock counts.
- On-hand, reserved, available and in-transit balances.
- Reorder levels and low-stock exceptions.
- DSR material-line linking and approved stock-posting evidence.
- Document links for requisitions, quotations, orders, receipts and stock events.
- Tenant, branch, project/site, permission, state and audit controls.
- Low-bandwidth list views, filters, exports, notifications and seed data.

### 3.2 Out of scope

- General ledger, accounts payable, tax calculation and supplier payment.
- Full cost accounting, project actuals and financial forecasting; Phase 4 owns these.
- Automatic supplier price selection without approval.
- Barcode scanners, RFID and warehouse automation.
- Quantity-bearing lot/batch/serial stock ledgers in the first slice. Chunk 3B.1 records batch references and expiry metadata; Chunk 3B.2 movements will own batch quantities and balances.
- Multi-level warehouse bin optimisation.
- Manufacturing, recipes, production orders and material requirements planning.
- Automatic stock deduction from every DSR save or submission.

## 4. Domain Rules

### 4.1 Master identity versus reported snapshot

`inventory_items` owns the current identity of a material. A DSR material line must retain its original `material_name`, `material_type`, `quantity`, `unit`, rate, amount, currency and delivery reference. Linking a line to an item must never rewrite an approved report.

An authorised user may link a draft or approved DSR line to an inventory item for reconciliation. The original text remains visible as the historical snapshot.

### 4.2 Stock is a ledger, not an editable balance

Every stock change is a posted movement. Balances are calculated or cached from posted movements and may not be edited directly. Corrections use a reversal plus replacement movement or an approved stock adjustment with a reason.

The system must prevent a posted issue from being changed in place, a receipt from being deleted, and a negative balance unless a tenant-level policy explicitly permits controlled negative stock.

Negative-stock protection must be concurrency-safe. Posting actions run in a database transaction and lock the affected store-item balance row before checking available quantity and writing movements. A read-then-insert check without a lock is not sufficient because two simultaneous issues could otherwise both spend the same stock.

### 4.3 Units are explicit

Each item has a stock unit, such as `tonne`, `kg`, `bag`, `litre`, `piece` or `m3`. Transaction lines may use a permitted purchase, issue or reporting unit with an explicit conversion to the stock unit.

Conversions must be versioned or copied onto the transaction line. Changing an item conversion later must not change historical quantities.

### 4.4 Availability calculation

For an item at a store:

```text
on_hand       = posted receipts + positive adjustments + returns
                 - posted issues - negative adjustments
reserved      = approved open requisition allocations
available     = on_hand - reserved
in_transit    = dispatched transfers not yet received
```

The UI must show these as separate values. `available = 0` is not the same as missing stock data.

### 4.5 Procurement is not receipt

A purchase order is a supplier commitment. It does not increase stock. Stock increases only when a receipt is posted after quantity and inspection checks.

Partial receipts, over-receipts, rejected quantities and backorders must be represented explicitly. The remaining purchase-order commitment stays open until completed, cancelled or closed by an authorised user.

### 4.6 DSR material reporting

The DSR remains the source of truth for what the site reported on a date. Inventory is the source of truth for what stock was posted into or out of a store.

The integration will support:

- an item selector on draft DSR material lines;
- preservation of the entered material snapshot;
- a warning when a reported line has no item or stock movement;
- a controlled action to post or reconcile a material issue after approval;
- a source link from the stock movement back to the DSR line;
- quantity and unit-conversion checks before posting;
- additive corrections for already posted DSR-linked movements.

The first release must not silently consume stock merely because a user typed a material quantity into a DSR.

### 4.7 Branch and tenant isolation

Every operational record carries `tenant_id`. Store records carry a responsible `branch_id`. A tenant with one branch still gets a real store branch and defaults it automatically.

- A user may act only in an authorised tenant and branch.
- Cross-branch visibility requires the existing cross-branch permission.
- A requisition may request stock for a project/site in the same tenant and authorised branch scope.
- A transfer may move stock between authorised stores only within the tenant.
- Cross-tenant stock movements are forbidden.
- Manager-app tenant/branch administration remains in `pointManager`; ERP owns operational stores and stock transactions.

### 4.8 Currency and cost visibility

Purchase, receipt and issue costs store the transaction currency and preserve the original amount. Currency must be enabled for the tenant and branch. Conversion for reporting uses the approved exchange-rate service and never overwrites source values.

Cost fields, supplier prices and quotation comparisons require dedicated permissions. Users who cannot view costs may still see quantities, statuses and operational references.

The item form does not ask users to select a pricing currency and the item table stores no separate currency column. Optional default purchase cost and default selling price are displayed in the active branch/facility default currency; tenant default currency is the fallback only in a legitimate all-branches context. Every quotation, order, receipt, issue with cost, and sale still preserves its own currency and source amount.

### 4.9 Tracking and expiry

Each item declares a tracking type: `none`, `serial`, `batch` or `other`.

- `none`: quantities are interchangeable and no serial/batch identity is required;
- `serial`: each tracked unit receives a unique serial identity before receipt is posted;
- `batch`: setup requires an initial/default batch number and expiry tracking; receipts and issues identify their actual batch;
- `other`: a controlled reference is required, with the exact pilot convention recorded in the item specification.

`is_expires` indicates that expiry dates must be captured at the tracked stock-record level. Expiry belongs to a batch or serial stock record, not to the item master itself. The item-level `batch_number` is the initial/default setup reference only; it cannot replace receipt-level batch records because one item may have many batches. The first ledger slice may defer full batch/serial stock tables, but it must prevent tracked items from being posted until the required tracking implementation exists.

### 4.10 Saleability and selling prices

`is_for_sale` distinguishes materials that may be sold externally from internal-use-only stock. An optional default unit selling price provides a simple fallback in the active branch/facility default currency.

Multiple selling prices are represented by named price lists, not columns such as `retail_price` and `wholesale_price` on the item. `inventory_price_tiers` defines tenant-owned reusable lists such as Retail, Wholesale or Staff. Users create those lists in the dedicated Price lists register, then attach a list, branch, selling unit, amount, effective dates and optional minimum quantity to an item. Currency is resolved from the selected branch/facility and is not selected again on the item price. A sales transaction copies the selected price and currency snapshot so later configuration changes do not rewrite history.

## 5. Proposed Data Model

All primary keys are UUIDs. Every migration must be created separately with Artisan. Foreign keys and indexes must use explicit short names because MySQL identifier limits have already affected this project.

### 5.1 `inventory_categories`

- `id`, `tenant_id`, `code`, `name`, `description`;
- `is_active`, `created_by`, `updated_by`, timestamps, soft deletes.

Unique category code and name within a tenant. Inactive categories remain available to historical records but cannot be used for new items.

### 5.2 `units_of_measure`

- global or tenant-owned `id`;
- `code`, `name`, `symbol`, `quantity_dimension`;
- `is_base_unit`, `is_active`, timestamps.

Dimensions initially include `mass`, `volume`, `length`, `area`, `count` and `time`. Generic conversions remain within one dimension. Item-specific conversions may cross dimensions when the packaging or specification makes the relationship explicit, such as one cement bag equalling 50 kg; the factor and reason are audited and copied to transactions.

### 5.3 `inventory_items`

- `id`, `tenant_id`, `inventory_category_id`;
- stable item `code`, `name`, description and specification; code is suggested from the name but remains editable and tenant-unique;
- stock unit and optional issue/purchase units;
- tracking type (`none`, `serial`, `batch`, `other`) and `is_expires`; batch tracking requires a batch number and expiry;
- `is_for_sale` and optional default unit selling price using the branch/facility currency;
- optional minimum-stock warning and reorder quantity;
- preferred supplier and optional lead time;
- default unit cost, permission-controlled and valued in the resolved branch/facility currency;
- material class: consumable, construction_material, spare_part, fuel_related or other;
- `is_active`, audit users, timestamps and soft delete.

Item codes are tenant-unique. Retired items cannot receive new requisition lines or stock movements.

### 5.3.1 `inventory_store_items`

Defines which items a store carries and owns store-specific controls:

- tenant, store and item;
- minimum-stock warning, reorder quantity and optional maximum stock;
- preferred issue unit and storage location note;
- is stocked/is active flags and audit users.

Item-level reorder values are defaults used when enabling an item at a store. Operational low-stock calculations use the store-item values because Kampala and a site store may require different minimum quantities.

### 5.3.2 `inventory_price_tiers` and `inventory_item_prices`

Price tier:

- tenant-owned code, name, description, priority and active state.

Item price:

- item, price tier, selling unit and amount in the resolved branch/facility default currency;
- optional minimum quantity, effective-from/effective-until dates and active state;
- audit users and timestamps.

The item default selling price supports simple tenants. Tier prices override it when a matching active tier is selected. Customer-specific contract pricing remains a later extension unless the pilot requires it.

Item-linked documents are optional technical records, not stock transactions. They are useful for safety data sheets, product specifications, certificates of conformity, approved material submissions and handling instructions that apply to the item across many receipts. Delivery notes, supplier invoices and inspection evidence belong to the goods receipt or purchase order instead of the item master.

### 5.4 `inventory_unit_conversions`

- item, from-unit, to-unit;
- multiplier and optional divisor;
- effective date and optional end date;
- reason, created/approved users and status.

The conversion used by a posted transaction is copied to that transaction. A conversion cannot be changed if it is referenced by posted movements.

### 5.5 `inventory_stores`

Represents warehouses, depots, site stores and temporary compounds.

- `id`, `tenant_id`, `branch_id`;
- optional `equipment_location_id`, project and site;
- `code`, `name`, type, address and storekeeper;
- `is_active`, audit users, timestamps and soft delete.

A site store is not automatically created for every site. Store creation is an explicit operational decision. Existing equipment locations may be referenced where the physical location is shared.

### 5.6 `inventory_store_permissions`

Optional store-level access for tenants that need tighter control than branch access:

- store, user or role reference;
- permission type: view, request, issue, receive, count, approve;
- created/revoked users and timestamps.

This is an additional restriction, never an expansion beyond tenant, branch and role permissions.

### 5.7 `material_requisitions` and `material_requisition_lines`

Header:

- tenant, branch, requesting user and department;
- project/site, destination store and required-by date;
- reference, reason, priority and status;
- submitted, approved, rejected, cancelled users/timestamps and reason.

Line:

- inventory item or unregistered description;
- requested quantity/unit and converted stock quantity;
- purpose, DSR/project activity reference and notes;
- approved, issued and outstanding quantities;
- historical item/name/unit snapshots.

Recommended statuses: `draft`, `submitted`, `returned`, `approved`, `partially_issued`, `fulfilled`, `rejected`, `cancelled`.

### 5.8 `supplier_quotations` and `supplier_quotation_lines`

- supplier/customer, requisition, quotation reference and quotation date;
- validity date, currency, delivery terms, tax/discount fields reserved for Phase 4;
- received document and notes;
- line item, quantity, unit price, lead time, availability and selected status.

Quotation comparison is advisory until an authorised user selects a supplier and creates a purchase order. The system must retain non-selected quotations.

### 5.9 `purchase_orders` and `purchase_order_lines`

Header:

- supplier, tenant, branch, requisition and project/site;
- order number, order date, expected date, currency and delivery store;
- status and approval evidence;
- submitted, approved, cancelled and closed users/timestamps.

Line:

- item or controlled description, ordered quantity/unit and stock conversion;
- unit price, line amount, received quantity and outstanding quantity;
- specification, requested delivery date and notes.

Recommended statuses: `draft`, `submitted`, `approved`, `partially_received`, `received`, `cancelled`, `closed`.

### 5.10 `inventory_receipts` and `inventory_receipt_lines`

Header:

- purchase order and supplier;
- receiving store, branch, received date and delivery reference;
- inspection status, receiver, verifier and notes;
- status: `draft`, `received`, `accepted`, `partially_rejected`, `rejected`, `cancelled`.

Line:

- ordered line, item snapshot, delivered/accepted/rejected quantities;
- unit, conversion snapshot, condition and rejection reason;
- unit cost/currency snapshot and document references.

Only accepted quantity creates a posted receipt movement. Rejected quantity does not increase on-hand stock.

### 5.11 `inventory_stock_movements`

The immutable stock ledger:

- item, store, tenant and branch;
- movement type: `receipt`, `issue`, `return`, `transfer_out`, `transfer_in`, `adjustment`, `opening_balance`;
- signed quantity in stock unit and original quantity/unit;
- unit conversion snapshot;
- source type/id, requisition/PO/receipt/DSR references;
- project/site and optional equipment reference;
- reason, status, posted/reversed users and timestamps.

Posted movements are append-only. A reversal points to the original movement and records a reason.

The ledger must enforce source idempotency with a stable source key and use locked store-item rows when posting. A transfer dispatch and receipt are separate movements joined by the transfer line, and their posting occurs through transactional domain actions only.

### 5.12 `inventory_reservations`

- item, source store, requisition line and reserved quantity;
- released, issued and remaining quantities;
- status and timestamps.

Reservations must not reduce physical on-hand. They reduce available stock and are released when a requisition is cancelled, fulfilled or expires.

### 5.13 `inventory_transfers` and `inventory_transfer_lines`

- source/destination stores and branches;
- transfer reference, reason and expected date;
- dispatch, receipt, rejection and cancellation lifecycle;
- item quantities, unit conversion and condition snapshots.

Dispatch creates `transfer_out`; receipt creates `transfer_in`. A dispatched transfer is in transit and is not available at the destination until received.

### 5.14 `inventory_counts` and `inventory_count_lines`

- store, count date, counter, verifier and status;
- item, system quantity, counted quantity, variance and reason;
- approval and resulting adjustment movement.

Count entry does not change stock. Only approval posts the variance.

### 5.15 DSR integration fields

Add optional item and reconciliation-state fields to the existing material line through a focused migration:

- `inventory_item_id`;
- `inventory_store_id`;
- `stock_unit_quantity` and conversion snapshot;
- `inventory_posting_status`;
- `inventory_posted_at`.

Do not place a single `inventory_stock_movement_id` on the DSR line. One reported line may be fulfilled by multiple partial issues, stores, returns or additive corrections. Stock movements reference the DSR material line as their source, and a reconciliation table/state summarizes reported, posted, returned and outstanding stock-unit quantities.

Existing material snapshot columns remain unchanged and nullable additions preserve old reports.

## 6. Permissions and Policies

Permissions should be module-specific and action-specific, for example:

- `inventory.items.view`, `.manage`, `.archive`;
- `inventory.stores.view`, `.manage`;
- `inventory.requisitions.create`, `.submit`, `.approve`, `.cancel`;
- `inventory.quotations.view`, `.manage`, `.select`;
- `inventory.purchase-orders.create`, `.approve`, `.cancel`, `.view-costs`;
- `inventory.receipts.create`, `.verify`, `.post`;
- `inventory.stock.view`, `.issue`, `.return`, `.adjust`, `.reverse`;
- `inventory.transfers.create`, `.dispatch`, `.receive`, `.cancel`;
- `inventory.counts.create`, `.approve`;
- `inventory.dsr-posting.view`, `.post`, `.correct`;
- `inventory.reports.export`.

Policies must check:

1. tenant identity;
2. branch/store access;
3. permission;
4. record state and allowed transition;
5. project/site access where present;
6. explicit approval permission, including self-approval when the requester holds that permission;
7. cost visibility independently from quantity visibility;
8. authority to administer a store or assign a supplier.

Direct routes must return 403 when the interface control is hidden. A requester may approve their own requisition or purchase order only when they hold the explicit approval permission. The audit trail records both requester and approver even when they are the same user.

## 7. Workflow Design

### 7.1 Item and store setup

1. Administrator creates category, units and item.
2. Store manager creates or activates a store for an authorised branch.
3. Item is enabled for the tenant/store and reorder settings are confirmed.
4. Supplier is selected from active supplier customers.

### 7.2 Requisition to purchase order

```text
Draft requisition
  -> Submit
  -> Approve or Return
  -> Select supplier/quotation
  -> Create purchase order
  -> Submit
  -> Approve
```

Approval reserves stock when an existing-store issue is planned. A purchase order does not reserve or increase stock unless the business action explicitly creates a reservation.

### 7.3 Receipt and inspection

```text
Purchase order approved
  -> Receive delivery
  -> Record delivered quantities
  -> Inspect/accept/reject
  -> Record accepted receipt movement
  -> Update PO outstanding quantity
```

The receipt screen must make rejected and accepted quantities visually distinct. Partial receipt must be a normal path, not an error state.

### 7.4 Issue and return

```text
Approved requisition
  -> Select store and available stock
  -> Issue quantity
  -> Record stock movement
  -> Update reservation and requisition line
  -> Optional return
```

An issue requires sufficient available stock unless an authorised adjustment/negative-stock policy applies. Returns reference the original issue where possible.

### 7.5 Transfer

```text
Draft transfer -> Submit -> Approve -> Dispatch -> Receive
```

Dispatch moves stock to in-transit. Receipt creates destination stock. A rejected or short-received transfer records the difference and requires a resolution.

### 7.6 Stock count

```text
Open count -> Enter physical quantity -> Review variance -> Approve -> Post adjustment
```

The count snapshot and approval remain immutable evidence. A later recount is a new count.

### 7.7 DSR material reconciliation

After DSR approval:

1. Display item-linked and unlinked material lines.
2. Show required stock unit conversion.
3. Let an authorised store/project user select the source store and post an issue, or mark the line as externally supplied/non-stock with a reason.
4. Create one or more idempotent stock movements linked to the DSR line through their source identity.
5. Prevent duplicate source posting, support partial fulfilment and use additive correction for later changes.

### 7.8 Phase 3A stock integration

- An equipment fuel transaction may link to an inventory item, source store and stock movement without replacing its existing fuel snapshot.
- A maintenance part line may link to an inventory item and issue movement without replacing its part-name, quantity and cost snapshot.
- Posting is explicit and idempotent. Historical Phase 3A records remain valid when inventory is not linked.

## 8. UI Plan

### 8.1 Navigation

Add a single `Materials & Stores` navigation group with:

- Items;
- Stores;
- Requisitions;
- Quotations;
- Purchase orders;
- Receipts;
- Stock ledger;
- Transfers;
- Stock counts;
- Low-stock exceptions.

Keep list pages consistent with the existing app: heading/description, search and filters beneath the heading, primary action at the far right, separate active/inactive tabs, modal forms where appropriate, comboboxes for large controlled selections, confirmation dialog for destructive or posting actions, and Sonner toasts for success/failure.

### 8.2 Item page

Show identity, category, stock unit, active state, reorder policy, preferred suppliers, recent stock balance and linked documents. Costs are omitted unless the user has cost permission.

### 8.3 Store dashboard

Show on-hand, reserved, available, in-transit, low-stock and pending-receipt summaries. Each metric drills into the same authorised query used by the export.

### 8.4 Transaction pages

Use clear status badges and a timeline for approval/posting events. Disable edits after posting. Show the reason and source link for reversals, adjustments and rejected quantities.

### 8.5 DSR material panel

Keep the current material snapshot visible. Add item/store selectors, conversion preview, stock availability and posting status. A user must understand whether the quantity is reported, reserved, issued or merely unlinked.

## 9. Notifications and Audit

Notifications:

- requisition submitted, returned and approved;
- low stock and unavailable issue;
- purchase order awaiting approval;
- delivery received and partially rejected;
- transfer dispatched, received or short;
- stock count variance awaiting approval;
- DSR material line unlinked or awaiting stock reconciliation.

Audit events should include actor, tenant, branch, event, record type/id, old/new values, reason, IP/user agent where applicable and timestamp. Important events include item/store changes, approval decisions, supplier selection, PO changes, receipt inspection, stock posting/reversal, transfers, count approval and DSR reconciliation.

## 10. Implementation Chunks

### Chunk 3B.1: Reference data and store foundation

Status: implemented, pending validation. Category, unit, item and store foundations now include secure price-field serialization, equipment-style tables, item tracking/saleability fields, dedicated unit-conversion and price-list registers, batch references, explicit store availability, document links, permission-based lifecycle controls and stronger isolation tests.

- Create category, unit, item and store schema separately.
- Add models, factories, policies, requests, actions and CRUD pages.
- Reuse equipment locations where appropriate.
- Add item/store documents and active/inactive views.
- Add tracking, saleability, default selling price and generated-but-editable item codes.
- Add store-item stocking and reorder settings; add price tiers after the simple default selling price is stable.
- Add permissions and seed roles.
- Add tests for tenant, branch, inactive records and cost omission.

Acceptance: an authorised manager can define an item and store, configure warning thresholds, conversions, price lists, store availability and optional technical documents, and permanently delete only inactive unused reference records. Batches are created by receiving stock, never from item setup; unrelated branches and tenants cannot manage records.

### Chunk 3B.2: Stock ledger and balances

Status: implemented, pending validation. The current slice provides append-only stock movements, store-item row locking, unit-conversion snapshots, batch enforcement, idempotent posting, negative-stock prevention, reservation-aware balances, controlled reversals, low-stock views, CSV export, audit events and seeded opening balances.

- Create movement, reservation and balance query/service.
- Use locked store-item rows and transactional posting to prevent concurrent negative stock.
- Add opening balance, receipt-like controlled seed path, issue, return, adjustment and reversal actions.
- Enforce append-only posted movements and idempotency keys/source uniqueness.
- Add stock ledger UI, balance cards, low-stock query and CSV export.

Acceptance: balances reconcile from movements, negative stock is blocked by default, and reversals leave the original event visible.

### Chunk 3B.2A: Direct receiving and movement workspace

Status: implemented, pending validation.

- The stock balance register reports every quantity in the item's stock unit and shows that unit once in its own column.
- Direct supplier deliveries use a multi-line goods-receipt cart with supplier, store, delivery reference, source unit/cost, amount paid and automatically calculated supplier balance due.
- Facility currency and branch default automatically. Branch choice is exposed only to a user with multiple accessible branches and the dedicated change-branch permission.
- Batch number and expiry are captured while receiving a batch-tracked item. Item setup only declares the tracking rule.
- Internal opening balances, reconciliations, issues, returns and atomic transfers use the separate Stock movements workspace.
- User-facing language says `record movement`; `posted` remains the internal immutable ledger state.

Acceptance: one receipt records several items and creates auditable stock movements; a transfer records matching source and destination entries atomically; users cannot bypass their default branch without permission.

### Chunk 3B.3: Requisitions and internal issues

- Create requisition header/line workflow.
- Add approval and reservation lifecycle.
- Add issue and return actions linked to requisition lines.
- Add store-level availability and partial issue behaviour.

Acceptance: an approved requisition can be partially issued and the outstanding quantity is correct; an unauthorised user receives 403.

### Chunk 3B.4: Procurement

- **3B.4.1 Requisition handoff:** allow approved material requisition lines to be selected for procurement; retain requested, already ordered and remaining quantities.
- **3B.4.2 Supplier quotations:** capture one or more supplier quotations, quotation documents, validity, delivery lead time, currency and line prices; provide a permission-controlled comparison without losing rejected quotations.
- **3B.4.3 Purchase-order draft:** create a PO from selected requisition/quotation lines or as a controlled direct PO. Snapshot supplier identity, item description, unit, conversion, quantity, unit price, currency, tax/discount inputs, destination branch/store and terms.
- **3B.4.4 Approval:** implement `draft -> submitted -> approved/rejected/returned -> cancelled/closed` transitions. Approval is permission-based; an originator may approve only when they hold the explicit approval permission. Every transition records actor, reason and audit values.
- **3B.4.5 Dispatch and document output:** generate the numbered PO document only after approval, preserve document versions, and prevent commercial line edits after approval. Changes require a revision or cancellation trail.
- **3B.4.6 Receipt matching:** the goods-receipt cart gains an optional approved PO selector. PO lines populate the cart; the receiver records delivered, accepted and rejected quantities plus batch/expiry. Accepted quantities use the existing goods-receipt and stock-movement services.
- **3B.4.7 Partial completion:** calculate ordered, accepted, rejected, outstanding and cancelled quantities per line. Keep a PO partially received until all non-cancelled quantities are accepted or formally closed.
- **3B.4.8 Payment handoff:** expose approved PO commitments and goods-receipt supplier balances to Phase 4. Do not create accounting journals or mark supplier invoices paid from PO approval.
- Required permissions: view/manage requisitions, manage quotations, create/submit/approve/cancel POs, receive POs, view costs and export.
- Required tests: tenant/branch isolation, direct-route 403s, authorised self-approval, immutable approved snapshots, quotation comparison cost omission, duplicate receipt idempotency, partial/rejected quantities and over-receipt prevention.

Acceptance: a purchase order does not affect stock until a receipt is accepted and posted.

### Chunk 3B.5: Receipts and inspection

- Add receipt entry, partial delivery, accepted/rejected quantities and verification.
- Post accepted receipt movements idempotently.
- Update purchase-order outstanding quantities.
- Add supplier delivery documents and audit.

Acceptance: rejected quantity is excluded from on-hand and the order remains open when partially received.

### Chunk 3B.6: Transfers and stock counts

- Add store transfers with dispatch/receive separation.
- Add count sheets, variance approval and adjustment posting.
- Add short/failed transfer resolution and notifications.

Acceptance: dispatched stock is in transit, not destination on-hand; count entry alone never changes stock.

### Chunk 3B.7: DSR material integration

- Add nullable item/store/posting fields to DSR material lines.
- Add item selectors and conversion preview to DSR draft UI.
- Add approved-line reconciliation and stock issue posting.
- Add external/non-stock reason and additive correction path.
- Add project/site material summaries and exception indicators.
- Link fuel transactions and maintenance part lines to inventory issues while retaining their Phase 3A snapshots.

Acceptance: the approved DSR snapshot remains unchanged, partial and multiple source movements can reconcile one line, duplicate posting is blocked, Phase 3A snapshots remain unchanged and users can distinguish reported quantity from posted stock.

### Chunk 3B.8: Reporting, seed data and hardening

- Add store and procurement dashboards.
- Add low-stock, outstanding PO, unfulfilled requisition and DSR reconciliation exports.
- Add notifications, audit views and direct-route authorization tests.
- Add realistic road-project seed data with aggregates and branch separation.
- Complete mobile/empty/loading/error states and accessibility review.

Acceptance: a seeded company can follow one material from requisition through PO, receipt, store issue and DSR reconciliation.

## 11. Test Contract

Create focused feature suites, split by chunk:

- `PhaseThreeBReferenceDataTest`;
- `PhaseThreeBStockLedgerTest`;
- `PhaseThreeBRequisitionTest`;
- `PhaseThreeBProcurementTest`;
- `PhaseThreeBReceiptTest`;
- `PhaseThreeBTransferAndCountTest`;
- `PhaseThreeBMaterialReconciliationTest`;
- `PhaseThreeBAccessIsolationTest`.

Tests must prove:

1. tenant and branch isolation;
2. single-branch default behaviour;
3. permission enforcement through direct requests;
4. state transition enforcement;
5. approval permission enforcement, including authorised self-approval and rejection of users without approval permission;
6. cost-field omission;
7. unit conversion and historical snapshot preservation;
8. partial receipt and rejected quantity accounting;
9. reservation versus available balance;
10. issue, return, transfer and reversal arithmetic;
11. count approval before adjustment posting;
12. idempotent source-linked movements;
13. DSR snapshot immutability and correction rules;
14. audit and notification creation;
15. exports using the same authorised filters as the UI.
16. concurrent issues cannot create negative stock;
17. server responses and exports omit cost and selling-price fields without cost permission;
18. batch tracking requires batch identity at receipt time and expiry where configured; item setup itself does not create batches;
19. store-specific reorder settings override item defaults;
20. one DSR line can reconcile multiple partial movements without losing its snapshot.

## 12. Seed Scenario

Extend the existing Point Investment road demo with:

- cement, aggregate, fuel-related consumable, reinforcement steel, culvert component and PPE items;
- tonne, kilogram, bag, litre and piece units;
- non-tracked cement, batch-and-expiry-tracked consumables and one serial-tracked spare/tool example;
- retail and wholesale price tiers for at least one saleable item;
- Kampala depot, Gulu site store and one inactive historical store;
- one supplier and one subcontractor supplier;
- a submitted and approved requisition;
- two quotations, one selected and one not selected;
- an approved PO with a partial receipt and rejected quantity;
- on-hand, reserved, low-stock and in-transit examples;
- a partial issue to a road site and a returned quantity;
- a transfer awaiting receipt;
- a stock count with a pending variance;
- an approved DSR material line linked to an issue;
- an approved DSR material line intentionally left as external/non-stock;
- users showing storekeeper, procurement officer, project manager, site manager and director separation.

## 13. Delivery and Quality Gates

Each chunk is complete only when its migration, model, action, policy, UI, seed data and focused tests exist. The user will run the commands locally; implementation updates should include the exact commands to run.

Suggested commands after each chunk:

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=PointInvestmentSeeder
php vendor/bin/pest tests/Feature/Inventory/PhaseThreeBReferenceDataTest.php --compact
vendor/bin/phpstan analyse
composer lint
```

The complete phase requires the focused Phase 3B suite, the existing Phase 1-3A regression suite, PHPStan, Rector/Pint, frontend type/lint checks and final UI acceptance with seeded users. Formatting and tests should not be silently skipped in the final acceptance report.

## 14. Handoff to Later Phases

Phase 4 may consume posted receipt, issue and commitment records for project cost reporting, but must not replace the inventory movement ledger. Phase 3B should expose stable source references and transaction currency without prematurely implementing accounting journals.

Phase 3C may use requisition and issue data to compare planned workforce/material demand, but workforce remains a separate master and access-control domain.

## 15. Confirmed Decisions for 3B.1

The following decisions are now confirmed for implementation:

1. Negative stock is blocked. A store cannot issue more than its available quantity; an exception workflow may be designed later if the pilot proves it necessary.
2. Each item has one primary stock unit. Purchase, issue and DSR units require explicit conversions copied onto the transaction.
3. A requester may approve their own requisition or purchase order only when they hold the explicit approval permission. The policy will not impose a blanket requester/approver separation.
4. Accepted receipt quantity posts stock; rejected quantity remains outside on-hand.
5. One store may serve several sites. Every movement still names its store and project/site context where applicable.
6. DSR stock posting is an explicit reconciliation action after approval, never automatic on draft save.
7. Cost visibility is separate from quantity visibility. A storekeeper can see item names, units, reorder levels, store balances and movement quantities without seeing supplier prices, unit costs or currency amounts. The server also strips or preserves cost fields according to permission, so hiding a column is not the security control.
8. Existing `Customer` records of type supplier or subcontractor are the initial supplier master.
9. The item master records the tracking requirement. Batch identity and expiry are captured from the supplier delivery or PO receipt, because a single item may have many batches; users do not create operational batches directly from item setup.
10. Inventory tracking type, material class, store type and unit dimension are represented by PHP backed enum classes and stored in ordinary string columns, keeping business validation in code instead of database-specific enum constraints.
11. Phase 3B stores source costs and operational quantities for traceability. Formal valuation methods, accounting journals, payable recognition, tax and financial reporting belong to Phase 4; inventory must not pretend that a receipt is already a posted accounting transaction.
12. Item codes are suggested from the item name and remain editable before save. The saved code is stable and tenant-unique.
13. The user-facing term is `Stock unit`, not `canonical stock unit`.
14. Reorder controls are ultimately store-specific; item values are setup defaults.
15. Selling prices support a simple item default and extensible named tiers such as Retail and Wholesale.
16. Cost and selling-price fields are removed from server payloads and exports when the user lacks cost permission.
