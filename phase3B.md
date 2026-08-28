# PointERP Phase 3B: Materials, Procurement and Inventory

## 1. Purpose and Status

Phase 3B is the next major implementation phase after Phase 3A. It introduces controlled materials, suppliers, procurement and stores while preserving the operational history already captured by Daily Site Reports and the fleet module.

Status: implementation complete, pending local regression and UI acceptance. Chunks 3B.1 through 3B.8 are implemented. Procurement intentionally uses a direct purchase-order workflow; RFQs and supplier quotation comparison remain deferred unless a real pilot proves they are needed.

The roadmap and SRS are the authority for this phase. `phase3A.md` remains the authority for equipment, fuel, maintenance, meter, custody and fleet location behaviour. This document owns stock, procurement and material issue workflows.

## 2. Business Outcome

At the end of Phase 3B, a construction company should be able to answer:

1. What materials and consumables are defined, and in what units?
2. What stock is physically held at each depot, warehouse or site store?
3. What has been requested, approved, ordered, received, inspected, issued, returned or transferred?
4. Which company and purchase order or authorised direct receipt supports incoming stock?
5. Which project, site, activity or equipment received a material issue?
6. What quantity is on hand, reserved, available, transferred or rejected?
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
- Direct purchase orders and line-level commitments.
- PO goods receipts, inspection outcomes, rejected quantities and permission-guarded direct stock receipts.
- Stock issues, returns, transfers, adjustments and stock reconciliations.
- On-hand, reserved and available balances, plus completed transfer evidence.
- Reorder levels and low-stock exceptions.
- DSR material-line linking and approved stock-posting evidence.
- Document links for requisitions, orders, receipts and stock events.
- Tenant, branch, project/site, permission, state and audit controls.
- Low-bandwidth list views, filters, exports, notifications and seed data.

### 3.2 Out of scope

- General ledger, accounts payable, tax calculation and supplier payment.
- Full cost accounting, project actuals and financial forecasting; Phase 4 owns these.
- Automatic supplier price selection without approval.
- Barcode scanners, RFID and warehouse automation.
- Serial-number custody and advanced lot genealogy. Batch-tracked quantities and expiry references are supported, but full warehouse lot genealogy remains deferred.
- Multi-level warehouse bin optimisation.
- Manufacturing, recipes, production orders and material requirements planning.
- Automatic stock deduction from every DSR save or submission.
- RFQs, tender comparison and supplier quotation comparison. These may be introduced later as a separate optional workflow without coupling internal store requisitions to purchase orders.

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

A purchase order is a supplier commitment. It does not increase stock. PO stock increases only when a receipt is posted after quantity and inspection checks.

Stock received outside procurement uses the separate **Add new stock** workflow. It requires the `inventory.stock.add` permission, posts immediately without approval, records a mandatory reason, and may identify any active company as its source. It creates an immutable direct-receipt header and lines so its ledger movements cannot be confused with PO goods receipts. Batch identity and expiry remain mandatory where the item requires them.

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

Cost fields and supplier prices require dedicated permissions. Users who cannot view costs may still see quantities, statuses and operational references.

The item form does not ask users to select a pricing currency and the item table stores no separate currency column. Optional default purchase cost and default selling price are displayed in the active branch/facility default currency; tenant default currency is the fallback only in a legitimate all-branches context. Every order, receipt, issue with cost, and sale still preserves its own currency and source amount.

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

- registered inventory item;
- requested quantity/unit and converted stock quantity;
- purpose, DSR/project activity reference and notes;
- approved, issued and outstanding quantities;
- historical item/name/unit snapshots.

Recommended statuses: `draft`, `submitted`, `returned`, `approved`, `partially_issued`, `fulfilled`, `rejected`, `cancelled`.

### 5.8 Deferred RFQ and quotation comparison

Supplier RFQs and competing quotation comparison are not part of the initial operational workflow. A purchase order is created directly from registered items and their recorded purchase costs. An authorised price-override permission may change a draft line price, and the order records whether the value came from the item master or a manual override.

If pilot operations later require formal tender comparison, it must be introduced as an optional module that feeds a draft PO. It must not turn an internal store requisition into a procurement request automatically.

### 5.9 `purchase_orders` and `purchase_order_lines`

Header:

- supplier, tenant, branch and receiving store;
- order number, order date, expected date, currency and delivery store;
- status and approval evidence;
- submitted, approved, cancelled and closed users/timestamps.

Line:

- registered inventory item, ordered quantity/allowed unit and stock conversion;
- unit price, line amount, received quantity and outstanding quantity;
- recorded-cost or authorised-override price source.

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

The ledger must enforce source idempotency with a stable source key and use locked store-item rows when posting. In the initial workflow, one transfer action records its matching `transfer_out` and `transfer_in` entries in a single database transaction.

### 5.12 `inventory_reservations`

- item, source store, requisition line and reserved quantity;
- released, issued and remaining quantities;
- status and timestamps.

Reservations must not reduce physical on-hand. They reduce available stock and are released when a requisition is cancelled, fulfilled or expires.

### 5.13 Deferred transfer documents

Use transfer header and line tables to preserve the request, item/unit/batch snapshots and approval decision. Approval records paired ledger entries with stable source keys. Add dispatch/receipt separation and in-transit stock only when stores genuinely need independent handover confirmation.

### 5.14 Deferred count documents

Use reconciliation header and line tables to preserve the server-calculated ledger snapshot, physical count, variance and approval decision. Approval rechecks the snapshot and records only the variance. Saved multi-stage count sheets remain deferred.

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
- `inventory.purchase-orders.create`, `.submit`, `.approve`, `.cancel`, `.close`, `.view-costs`, `.override-price`;
- `inventory.stock.receive`;
- `inventory.stock.view`, `.issue`, `.return`, `.adjust`, `.reverse`;
- `inventory.transfers.view`, `.create`, `.approve`, `.reject`;
- `inventory.reconciliations.view`, `.create`, `.approve`, `.reject`;
- `inventory.dsr-reconciliation.view`, `.manage`, `.direct-issue`, `.mark-external`, `.export`;
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

### 7.2 Internal requisition

```text
Draft requisition
  -> Submit
  -> Approve or Return
  -> Issue available items from the selected store
  -> Partial issue, return or fulfil
```

Approval reserves stock for the internal store issue. A requisition does not select a supplier and does not create a purchase order.

### 7.3 Direct purchase order

```text
Draft purchase order
  -> Select supplier, receiving store and registered items
  -> Use recorded item costs and allowed units
  -> Submit
  -> Approve or Return
  -> Receive against the approved PO
```

A purchase order is independent from an internal material requisition. It does not reserve or increase stock.

### 7.4 Receipt and inspection

```text
Purchase order approved
  -> Receive delivery
  -> Record delivered quantities
  -> Inspect/accept/reject
  -> Record accepted receipt movement
  -> Update PO outstanding quantity
```

The receipt screen must make rejected and accepted quantities visually distinct. Partial receipt must be a normal path, not an error state.

### 7.5 Issue and return

```text
Approved requisition
  -> Select store and available stock
  -> Issue quantity
  -> Record stock movement
  -> Update reservation and requisition line
  -> Optional return
```

An issue requires sufficient available stock unless an authorised adjustment/negative-stock policy applies. Returns reference the original issue where possible.

### 7.6 Transfer

```text
Select source and destination stores -> Add items -> Submit -> Approve or reject
```

Submitting a transfer does not change stock. Approval is permission guarded and records matching `transfer_out` and `transfer_in` movements atomically. Rejection requires a reason. The approved request and both immutable ledger movements form the custody evidence. Dispatch and separate destination receipt states remain deferred until a pilot proves that stores need an in-transit workflow.

### 7.7 Stock reconciliation

```text
Select store -> Enter physical quantity -> Submit -> Approve or reject -> Post variance
```

Submission records the ledger snapshot, physical count and proposed variance without changing stock. An authorised approver verifies that the live balance still matches the snapshot, then posts only the variance. A stale snapshot is rejected and must be recounted. Rejection requires a reason. The approved reconciliation, adjustment movement and audit events are the immutable evidence.

### 7.8 DSR material reconciliation

After DSR approval:

1. Display item-linked and unlinked material lines.
2. Show required stock unit conversion.
3. Allocate existing requisition issues first; allocation does not post stock again.
4. Let an authorised store/project user explicitly post only an unmatched direct issue, or mark the line externally supplied/non-stock with a reason.
5. Preserve allocations in reconciliation rows, prevent duplicate source posting, support partial fulfilment and use additive correction for later changes.

### 7.9 Phase 3A stock integration

- An equipment fuel transaction may link to an inventory item, source store and stock movement without replacing its existing fuel snapshot.
- A maintenance part line may link to an inventory item and issue movement without replacing its part-name, quantity and cost snapshot.
- Posting is explicit and idempotent. Historical Phase 3A records remain valid when inventory is not linked.

## 8. UI Plan

### 8.1 Navigation

Keep a single `Materials & Stores` navigation group with:

- item/category/unit/store setup;
- inventory operations dashboard after 3B.8;
- stock balances, including the low-stock tab;
- Requisitions;
- Purchase orders;
- Receipts;
- Stock movements as the read-only ledger.

Expose **New transfer** and **New reconciliation** as permission-controlled actions from the stock-movement page instead of adding more sidebar entries.

Keep list pages consistent with the existing app: heading/description, search and filters beneath the heading, primary action at the far right, separate active/inactive tabs, modal forms where appropriate, comboboxes for large controlled selections, confirmation dialog for destructive or posting actions, and Sonner toasts for success/failure.

### 8.2 Item page

Show identity, category, stock unit, active state, reorder policy, preferred suppliers, recent stock balance and linked documents. Costs are omitted unless the user has cost permission.

### 8.3 Inventory operations dashboard

Show on-hand, reserved, available, low-stock, outstanding requisition, outstanding PO/receipt and DSR reconciliation summaries. Each metric drills into the same authorised query used by the corresponding register and export.

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
- transfer submitted, approved or rejected;
- physical count variance recorded;
- DSR material line unlinked or awaiting stock reconciliation.

Audit events should include actor, tenant, branch, event, record type/id, old/new values, reason, IP/user agent where applicable and timestamp. Important events include item/store changes, approval decisions, supplier selection, PO changes, receipt inspection, stock posting/reversal, transfers, physical-count variances and DSR reconciliation.

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
- Supplier deliveries must reference an approved purchase order. Supplier, branch, store, item, unit, currency and price are inherited from the PO and cannot be re-entered during receipt.
- Facility currency and branch default automatically. Branch choice is exposed only to a user with multiple accessible branches and the dedicated change-branch permission.
- Batch number and expiry are captured while receiving a batch-tracked item. Item setup only declares the tracking rule.
- Opening quantities and corrections use stock reconciliation, issues and returns use requisitions, and transfers use the dedicated transfer page. The Stock movements workspace is their read-only ledger.
- User-facing language says `record movement`; `posted` remains the internal immutable ledger state.

Acceptance: one receipt records several items and creates auditable stock movements; a transfer records matching source and destination entries atomically; users cannot bypass their default branch without permission.

### Chunk 3B.3: Requisitions and internal issues

Status: implemented, pending validation. The current slice provides draft requisition carts for registered store items, branch/project/site/store scoping, permission-based submission and review, explicit self-approval authority, stock-unit approval snapshots, reservations, partial issues, returns, cancellation release, status separation, audit events and seeded open/approved demonstrations.

- Create requisition header/line workflow.
- Add approval and reservation lifecycle.
- Add issue and return actions linked to requisition lines.
- Add store-level availability and partial issue behaviour.

Acceptance: an approved requisition can be partially issued and the outstanding quantity is correct; an unauthorised user receives 403.

### Chunk 3B.4: Procurement

Status: implemented, pending validation. The current slice provides direct purchase-order drafts, recorded item costs and allowed units, controlled price override, permission-based review, immutable approved commercial snapshots, PO-only receiving, accepted/rejected inspection quantities, partial receipt, outstanding quantity tracking and audit events. Printable/versioned PO output remains part of document-control hardening and does not change the stock acceptance boundary.

- **3B.4.1 Workflow boundary:** keep internal store requisitions independent from procurement. A requisition asks a store for stock; a PO commits the company to buy stock from a supplier.
- **3B.4.2 Purchase-order draft:** create a direct PO from registered items. Snapshot supplier identity, item description, allowed unit, conversion, quantity, recorded unit price, price source, branch currency, destination store and terms.
- **3B.4.3 Price authority:** load recorded item purchase cost automatically. Only `inventory.purchase-orders.override-price` may change the draft price, and the audit snapshot preserves that choice.
- **3B.4.4 Approval:** implement `draft -> submitted -> approved/rejected/returned -> cancelled/closed` transitions. Approval is permission-based; an originator may approve only when they hold the explicit approval permission. Every transition records actor, reason and audit values.
- **3B.4.5 Dispatch and document output:** generate the numbered PO document only after approval, preserve document versions, and prevent commercial line edits after approval. Changes require a revision or cancellation trail.
- **3B.4.6 Receipt matching:** every supplier goods receipt requires an approved PO. PO lines populate the inspection form; the receiver records delivered, accepted and rejected quantities plus batch/expiry. Accepted quantities use the existing stock-movement service.
- **3B.4.7 Partial completion:** calculate ordered, accepted, rejected, outstanding and cancelled quantities per line. Keep a PO partially received until all non-cancelled quantities are accepted or formally closed.
- **3B.4.8 Payment handoff:** expose approved PO commitments and goods-receipt supplier balances to Phase 4. Do not create accounting journals or mark supplier invoices paid from PO approval.
- Required permissions: view/manage requisitions, create/submit/approve/cancel POs, override draft price, receive POs, view costs and export.
- Required tests: tenant/branch isolation, direct-route 403s, authorised self-approval, immutable approved snapshots, cost omission, PO-only receipt enforcement, partial/rejected quantities and over-receipt prevention.

Acceptance: a purchase order does not affect stock until a receipt is accepted and posted.

### Chunk 3B.5: Receipts and inspection

Status: core workflow implemented, pending validation. Approved-PO selection, partial delivery, accepted/rejected quantities, batch/expiry capture, stock posting, outstanding PO updates, printable receipt details and audit logging are present. Delivery-document links remain.

- Add receipt entry, partial delivery, accepted/rejected quantities and verification.
- Post accepted receipt movements idempotently.
- Update purchase-order outstanding quantities.
- Add supplier delivery documents and audit.

Acceptance: rejected quantity is excluded from on-hand and the order remains open when partially received.

### Chunk 3B.6: Transfers and stock reconciliation

Status: implemented, pending local validation. Transfers and physical reconciliations are now request-and-approval workflows. Submission does not affect stock; an authorised approval creates the immutable ledger entries, and rejection records a mandatory decision reason. Approval remains permission based, so the requester may self-approve only when they explicitly hold the approval permission.

- Add a multi-item store-transfer request page. Submission preserves item/unit/batch snapshots; approval records matching source and destination movements atomically.
- Add a physical reconciliation page. Submission captures the server-calculated ledger snapshot and counted quantity; approval rechecks the snapshot and posts only the variance.
- Keep the stock-movements page as a read-only operational and audit register. Supplier receipts come from approved POs, site issues and returns come from requisitions, transfers come from the transfer page, and corrections come from physical counts.
- Apply the existing branch-change permission when exposing stores, require batch selection for batch-tracked transfers and counts, reject stale count snapshots, block negative item and batch balances, and keep source keys idempotent.
- Defer transfer-in-transit, dispatch/receive separation and discrepancy notifications until pilot evidence shows that the approval workflow is insufficient.

Acceptance: a transfer updates both stores or neither store; a physical count posts only its variance; repeating the same source key does not duplicate stock; unauthorised and out-of-scope requests receive 403.

### Chunk 3B.7: DSR material integration

Status: core workflow implemented, pending local regression and UI acceptance.

The DSR material form now links a reported material snapshot to an inventory item, allowed unit and optional source store. The system stores the conversion multiplier and stock-unit quantity with the DSR line so later reference-data edits do not rewrite history. After report approval, a separate permission-guarded reconciliation panel supports three explicit outcomes:

1. match an existing issue for the same item, project and site without deducting stock again;
2. issue only the unmatched balance from an authorised store, creating one idempotent stock movement;
3. classify externally supplied or non-stock material with a mandatory reason and no stock movement.

Partial allocations are supported, every allocation is retained as its own audit row, and the DSR displays reported, allocated and outstanding quantities in the item stock unit. Draft saving and DSR approval never deduct inventory automatically.

The lean 3B.7 release deliberately defers return-allocation links, material-line correction allocations and reconciliation export to the reporting/hardening work. Those paths need a pilot-approved rule for whether returned material corrects the DSR, the requisition, or both. Existing Phase 3A fuel and maintenance inventory links remain unchanged; automatic cross-linking is deferred to avoid creating a second stock deduction path.

#### 3B.7.1 Purpose and accounting boundary

The DSR records what the site says was used during the reporting day. The stock ledger records what physically entered or left a store. They are related records, but they are not interchangeable.

- Do not change inventory while a DSR is a draft, submitted or returned.
- DSR approval makes the reported material snapshot eligible for reconciliation; approval itself does not post stock.
- Reconcile an existing requisition issue before considering a new direct issue. Linking an existing issue does not change stock again.
- Post a direct DSR issue only for an approved, unmatched quantity and only through an explicit user action protected by a dedicated permission.
- Allow an approved line to be marked external/non-stock when the material came from a subcontractor, client, petty purchase or another source outside the managed stores. Require a reason and do not create a stock movement.
- Keep inventory valuation and accounting journals outside this workflow. Phase 4 may consume the source cost already preserved on inventory transactions.

#### 3B.7.2 Data model

Extend `daily_site_report_material_lines` through a focused migration with nullable integration fields while preserving the existing description, quantity, unit, rate and amount snapshots:

- `inventory_item_id` and `inventory_store_id`;
- `unit_of_measure_id` for the selected transaction unit;
- `conversion_multiplier` copied when the line is saved;
- `stock_unit_quantity`, the reported quantity converted into the item's stock unit;
- `inventory_reconciliation_status`, backed by a PHP enum with `not_linked`, `pending`, `partial`, `reconciled`, `external` and `exception` values;
- `external_material_reason`, nullable except when status is `external`;
- `reconciled_at` and `reconciled_by` for the latest completed state.

Create `dsr_material_reconciliations` as the many-to-many allocation record between reported usage and stock evidence:

- UUID, tenant, branch and DSR material-line IDs;
- optional inventory movement and requisition-line IDs;
- reconciliation type backed by a PHP enum: `requisition_issue`, `direct_issue`, `external_non_stock`, `return`, `correction`;
- allocated quantity in the item's stock unit;
- source quantity/unit and conversion snapshot where applicable;
- reason, actor and timestamp;
- a stable source key with a tenant-unique constraint for idempotency.

Do not place a single `inventory_stock_movement_id` on the DSR line. One reported line may be covered by several partial requisition issues, batches, stores or approved corrections. Reconciliation rows preserve those allocations without rewriting the approved DSR snapshot.

Add tenant/branch indexes and foreign keys with explicit short names. Foreign keys should restrict deletion of operational evidence. DSR material lines and stock movements remain append-only once approved/posted.

#### 3B.7.3 Model and service layer

- Add item, store, unit and reconciliation relationships to `DailySiteReportMaterialLine`.
- Add a `DsrMaterialReconciliation` model using tenant scope, UUIDs, enum casts and audit logging.
- Add `DsrMaterialReconciliationSummary` to calculate reported, linked, directly posted, returned and outstanding stock-unit quantities from reconciliation rows.
- Add `ReconcileDsrMaterialLine` as the transaction boundary. It must lock the DSR line and candidate movement rows, verify approval and scope, reject over-allocation, use stable source keys and refresh the reconciliation status.
- Reuse `PostInventoryStockMovement` for an authorised unmatched direct issue. Use `DailySiteReportMaterialLine::class` as `source_type`, the DSR line ID as `source_id`, and include project/site/store/batch context.
- Never call `PostInventoryStockMovement` when allocating an existing requisition issue. That operation creates only a reconciliation row.
- Add an explicit `MarkDsrMaterialExternal` action that requires a reason and proves the line has no inventory allocations.
- Add an additive correction action for approved DSR corrections. It creates new reconciliation evidence and never edits or deletes an earlier allocation.

When an approved correction reduces reported usage below previously reconciled stock, do not silently return stock. Mark the line as `exception`. A physical return must use the requisition return workflow or another explicit authorised movement, after which that return can be linked to the reconciliation.

#### 3B.7.4 DSR form and review UI

On editable DSR material rows:

- offer a searchable inventory-item combobox limited to active items available to the DSR branch/site context;
- allow `External/non-stock material` as an explicit alternative instead of forcing every free-text material into inventory;
- when an item is selected, default its stock unit, show allowed conversions and display the converted stock quantity before save;
- copy the selected item name, code and unit into the existing DSR snapshot fields;
- default the site/store from the report context and expose another accessible store only to a user with branch/store authority;
- keep rate and amount fields governed by the existing DSR cost-visibility permission.

On the approved DSR details page, add a **Material reconciliation** section showing:

- reported quantity and stock-unit equivalent;
- existing candidate issues filtered to the same tenant, branch access, project/site, item and sensible date range;
- allocations already linked, outstanding quantity and reconciliation status;
- actions to allocate an issue, post an unmatched direct issue, mark external, or link a return;
- source links back to the requisition, movement register and inventory item;
- clear exceptions for over-issued, under-reconciled, missing conversion, inactive item or inaccessible store conditions.

The UI may hide unavailable actions, but every endpoint must independently authorize and validate the operation.

#### 3B.7.5 Policies and permissions

Add permissions without reusing broad item-management authority:

- `inventory.dsr-reconciliation.view`;
- `inventory.dsr-reconciliation.manage` for allocating existing evidence;
- `inventory.dsr-reconciliation.direct-issue` for posting unmatched stock;
- `inventory.dsr-reconciliation.mark-external`;
- `inventory.dsr-reconciliation.export`.

The policy must verify tenant, branch access, project/site access, approved DSR state, item/store compatibility and the requested operation. A project manager may reconcile only projects/sites they can access. Cost visibility remains separate from quantity and reconciliation visibility.

#### 3B.7.6 Fuel and maintenance integration

Preserve the Phase 3A operational snapshots and add optional inventory references rather than replacing them:

- an equipment fuel transaction may reference its fuel inventory item, store and issue movement;
- a maintenance-part line may reference an inventory item and issue movement while retaining part name, quantity, unit and cost snapshots;
- if fuel or a part was already issued through a requisition, link that existing issue instead of posting it again;
- if an approved DSR generated the operational fuel transaction, use one stable reconciliation path so the DSR, fuel transaction and stock movement cannot each deduct the same fuel;
- equipment meter, usage, fuel and maintenance state remain owned by Phase 3A; inventory owns only quantities held and moved through stores.

#### 3B.7.7 Routes, controllers and tests

Use focused invokable controllers for allocation, direct issue, external classification and return linking. Keep query/presentation work in dedicated services rather than expanding `DailySiteReportController` further.

Create `PhaseThreeBMaterialReconciliationTest.php` to prove:

1. draft, submitted and returned DSRs cannot reconcile stock;
2. an approved line can allocate several partial requisition issues;
3. allocating an existing issue does not create another movement;
4. an authorised direct issue posts only the unmatched quantity and is idempotent;
5. over-allocation and negative stock are rejected;
6. external material requires a reason and creates no movement;
7. tenant, branch, project and site boundaries return 403;
8. cost fields are omitted without cost permission;
9. approved corrections are additive and the original DSR snapshot remains unchanged;
10. fuel and maintenance links do not duplicate stock deductions.

Acceptance: users can explain every approved DSR material quantity as existing issued stock, an explicit direct issue, a return/correction or external material. The approved report remains immutable, multiple allocations are supported and duplicate stock deduction is impossible.

### Chunk 3B.8: Reporting, seed data and hardening

Status: implemented, pending local validation. This is the Phase 3B completion and pilot-readiness chunk.

The implemented slice adds an `Inventory operations` dashboard at `/inventory-dashboard`, shared server-side filters and seven permission-guarded CSV/PDF reports. Cost columns are added only when the viewer has the relevant PO or receipt cost permission. The dashboard exposes low stock, outstanding requisitions, overdue purchase orders, rejected receipt quantities and approved DSR material reconciliation exceptions with links to the underlying records.

Filters cover accessible branch, store, project, supplier, category, item and date range. A user with one accessible branch receives that branch as the effective locked selection. Dashboard and export queries use the same `InventoryOperationsReport` service so an identical filter set has one scope definition.

`inventory:process-alerts` now runs daily and sends branch-scoped notifications for low-stock/recovery transitions, overdue POs and DSR material lines left unreconciled for two days. Stable alert keys, state markers and seven-day reminder windows prevent a scheduler run from producing repeated notifications. The existing notification center, preference and delivery pipeline remain the only notification mechanism.

`PointInvestmentSeeder` now includes a separate pilot story that does not mutate the fixtures used by earlier chunk tests: `PO-2026-PILOT01`, a partially accepted/rejected delivery, an approved Kampala-to-Gulu transfer, `MR-PILOT-GULU`, fully reconciled and partial DSR evidence, an external subcontractor-supplied line, an approved physical-count variance, low stock and an overdue order. Dedicated pilot cement keeps prior cement opening-balance tests deterministic.

Focused composite indexes cover PO due-date operations, supplier receipt dates and branch/reconciliation status. `PhaseThreeBReportingAndAlertsTest` covers dashboard exception data, cost-column omission, direct export authorization and scheduled-alert deduplication.

#### 3B.8.1 Inventory operations dashboard

Create one operational inventory dashboard rather than separate decorative dashboards. It should reuse the existing stock, requisition, PO, receipt and DSR reconciliation query services and apply the same authorization scopes as their index pages.

Top-level metrics:

- active stores and stocked item locations;
- items below their store-specific minimum stock;
- on-hand, reserved and available quantities;
- submitted requisitions awaiting review and approved requisitions awaiting issue;
- approved/partially received POs with outstanding quantities;
- overdue expected deliveries;
- rejected receipt quantities requiring supplier follow-up;
- approved DSR material lines that are pending, partial or in exception.

Operational tables should show the highest-priority low-stock items, overdue POs, unfulfilled requisitions and unreconciled DSR materials with links to their detail pages. Use charts only where they communicate a useful comparison; tables remain the primary operating surface. Quantity viewers must not automatically receive supplier costs or financial totals.

Filters must include accessible branch, store, project/site, item/category, supplier, status and date range where relevant. A single-branch user receives their working branch automatically and cannot change it. Server-side queries, exports and dashboard totals must share the same filter objects to prevent conflicting figures.

#### 3B.8.2 Reports and exports

Add focused CSV/XLSX exports with predictable columns and generated-at/filter metadata:

- stock balance and low-stock report by store and stock unit;
- immutable movement ledger with source references and reversal status;
- requisition fulfilment report showing requested, approved, issued, returned and outstanding quantities;
- PO commitment and delivery report showing ordered, accepted, rejected and outstanding quantities;
- receipt inspection report;
- DSR material reconciliation report showing reported, allocated, directly issued, returned, external and outstanding quantities;
- supplier delivery-performance summary derived from approved POs and receipts.

Do not calculate formal accounting inventory valuation in these reports. Cost-enabled users may see source unit costs and commercial PO/receipt amounts; users without cost permission receive no cost keys in server payloads or export rows.

Use `inventory.reports.export` for operational quantity reports, existing PO/receipt cost permissions for commercial columns, and `inventory.dsr-reconciliation.export` for DSR reconciliation exports. Export endpoints must reject unauthorised direct requests and use the same tenant/branch/project filters as the UI.

#### 3B.8.3 Notifications and scheduled checks

Add actionable notifications with links to the affected record:

- stock falls to or below the store minimum;
- a previously low-stock item recovers, closing the warning state;
- a requisition is submitted, approved, returned, cancelled or remains unfulfilled past its required date;
- a PO is submitted, approved, returned, rejected, nearing its expected date or overdue with outstanding quantity;
- a receipt contains rejected/damaged/spoilt quantity;
- a DSR material line remains unreconciled after the configured period or enters exception status.

Avoid one notification per page view or scheduler run. Use stable deduplication keys and notify again only after a meaningful state transition, threshold recovery/re-entry or configured reminder interval. Respect the existing notification preferences and branch/project visibility rules.

#### 3B.8.4 Audit and operational traceability

Ensure the audit trail records:

- inventory item, unit conversion, price, category and store-setting changes;
- requisition submission, review, issue, return and cancellation;
- PO creation, commercial changes, submission, review, close and cancellation;
- receipt inspection and rejected-quantity reasons;
- direct stock receipts, their source company, destination store and reason;
- transfer, physical count, reversal and negative-stock rejection context where appropriate;
- DSR allocation, direct issue, external classification, correction and exception resolution.

Audit records must preserve actor, tenant, branch, event, model type/ID, old/new values, reason and timestamp. Keep the audit trail separate from user notifications and business activity feeds. Add source links from audit details when the viewer is still authorized to open the underlying record.

#### 3B.8.5 Demo seed scenario

Extend `PointInvestmentSeeder` idempotently so `migrate:fresh --seed` demonstrates the complete story without manual database work:

1. Kampala creates an approved PO for cement and PPE from an active supplier.
2. A partial PO receipt accepts usable quantity, rejects a damaged quantity and updates Kampala store stock.
3. An approved transfer moves part of the accepted cement to the Gulu site store.
4. A site requisition is submitted, approved and partially issued against the road project/site.
5. One approved DSR material line allocates that issue fully.
6. A second DSR line is only partially reconciled and appears as an exception/dashboard task.
7. A third DSR material line is classified as subcontractor-supplied external material.
8. A physical stock count records a small, explained variance.
9. Low-stock, overdue PO and rejected-receipt examples appear in reports without corrupting the core balances.

Seed users must demonstrate director, procurement officer, Kampala storekeeper, project manager and Gulu site manager permissions. The seeded story must remain tenant/branch separated and be safe to rerun through `updateOrCreate` or stable source keys.

#### 3B.8.6 Performance and data integrity

- Add composite indexes for the actual report filters: tenant/branch/store/item/status/date and source type/source ID/source key.
- Keep all stock mutations inside database transactions and lock store-item rows before balance-sensitive posting.
- Avoid N+1 queries on dashboards and exports; preload relationships or use grouped aggregate queries.
- Paginate operational registers instead of loading an arbitrary large client-side history.
- Preserve decimal quantities and conversion snapshots; do not use floating-point arithmetic for posting decisions.
- Keep append-only ledger and reconciliation evidence. Corrections use reversals or additive records.
- Verify all long MySQL index and foreign-key names explicitly before migration execution.

#### 3B.8.7 UI and accessibility hardening

- Apply the established equipment/index table pattern, consistent page headings, left-side search/filters and right-side primary actions.
- Keep active/inactive tabs aligned with existing page tabs and never mix inactive records into active lists.
- Use searchable comboboxes for database-backed choices and constrain selected text so it cannot resize or overlap neighbouring fields.
- Keep large forms as full pages; use modals only for short decisions or small edits.
- Add mobile-safe table overflow, wrapped dashboard legends, stable control widths, skeleton/loading states, empty states and useful server-error messages.
- Mark required fields with a red asterisk and connect labels, errors and descriptions accessibly.
- Use the global confirmation dialog for destructive/status actions and Sonner for success, failure, information and warning feedback.
- Verify desktop and mobile workflows for requisition, PO, receipt, transfer, count and DSR reconciliation.

#### 3B.8.8 Security and completion tests

Extend `PhaseThreeBAccessIsolationTest.php` and the focused feature suites to cover direct-route 403s, tenant/branch leakage, project/site scope, cost-field omission, stale state transitions, duplicate source keys and cross-branch mutations. Add concurrency tests for competing issues/transfers where the database driver supports locking semantics.

Final Phase 3B validation should include:

```powershell
php artisan migrate:fresh --seed
php vendor/bin/pest tests/Feature/Inventory --compact
vendor/bin/phpstan analyse
composer lint
bun run test:types
```

Also run the existing Phase 2 DSR and Phase 3A equipment suites because 3B.7 touches their contracts. Record any database-driver limitation when SQLite cannot prove the same locking behaviour as MySQL, and complete the transfer/count/DSR flows manually with the seeded roles.

Acceptance: a seeded authorized user can trace one material from item setup through PO, accepted receipt, store transfer, requisition issue and approved DSR reconciliation; quantities agree across every screen and export; costs remain permission-separated; audit and notifications identify the important actions; and unauthorized direct requests return 403.

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
11. stale physical-count snapshots are rejected before variance posting;
12. idempotent source-linked movements;
13. DSR snapshot immutability and correction rules;
14. audit and notification creation;
15. exports using the same authorised filters as the UI.
16. concurrent issues cannot create negative stock;
17. server responses and exports omit cost and selling-price fields without cost permission;
18. batch tracking requires batch identity at receipt time and expiry where configured; item setup itself does not create batches;
19. store-specific reorder settings override item defaults;
20. one DSR line can reconcile multiple partial movements without losing its snapshot.
21. direct stock receipt routes require `inventory.stock.add`, are idempotent, preserve their source record and post batch-aware receipt movements without a PO.

## 12. Seed Scenario

Extend the existing Point Investment road demo with:

- cement, aggregate, fuel-related consumable, reinforcement steel, culvert component and PPE items;
- tonne, kilogram, bag, litre and piece units;
- non-tracked cement, batch-and-expiry-tracked consumables and one serial-tracked spare/tool example;
- retail and wholesale price tiers for at least one saleable item;
- Kampala depot, Gulu site store and one inactive historical store;
- one supplier and one subcontractor supplier;
- a submitted and approved requisition;
- an approved direct PO with a pending delivery;
- on-hand, reserved and low-stock examples;
- a partial issue to a road site and a returned quantity;
- a completed approved store transfer;
- a completed physical-count variance;
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
8. Existing active `Customer` records are the company master. Any company type may be selected as a PO supplier or direct-stock source because a client or subcontractor may also supply materials on a project.
9. The item master records the tracking requirement. Batch identity and expiry are captured from the supplier delivery or PO receipt, because a single item may have many batches; users do not create operational batches directly from item setup.
10. Inventory tracking type, material class, store type and unit dimension are represented by PHP backed enum classes and stored in ordinary string columns, keeping business validation in code instead of database-specific enum constraints.
11. Phase 3B stores source costs and operational quantities for traceability. Formal valuation methods, accounting journals, payable recognition, tax and financial reporting belong to Phase 4; inventory must not pretend that a receipt is already a posted accounting transaction.
12. Item codes are suggested from the item name and remain editable before save. The saved code is stable and tenant-unique.
13. The user-facing term is `Stock unit`, not `canonical stock unit`.
14. Reorder controls are ultimately store-specific; item values are setup defaults.
15. Selling prices support a simple item default and extensible named tiers such as Retail and Wholesale.
16. Cost and selling-price fields are removed from server payloads and exports when the user lacks cost permission.
