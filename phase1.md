# Construction ERP — Phase 1 Implementation Plan

## Instructions for Codex

Implement Phase 1 of a multi-tenant, multi-branch construction ERP in the existing Laravel Inertia/React application.

This document is the implementation contract for this phase. Before changing code, inspect the repository and follow its existing architecture, naming, strictness, AI guidelines, testing conventions and frontend patterns. Do not replace the starter kit's conventions with generic Laravel patterns.

The project uses the **Nuno Maduro Laravel Starter Kit — Inertia & React** and follows:

- Actions-oriented architecture.
- Cruddy controllers and pages.
- Strict types and maximum PHPStan enforcement.
- Immutable-first data structures.
- Fail-fast behaviour.
- Type-safe PHP and TypeScript.
- Pest with 100% type and test coverage requirements.
- Rector, Pint, OxLint and Oxfmt.
- UUID primary keys.
- Soft deletes for business entities where appropriate.
- Laravel Herd for local development.

Do not implement later ERP modules in this phase.

---

## 1. Phase objective

Build the secure organisational foundation on which every later ERP module will depend:

1. Tenant isolation.
2. Single-branch and multi-branch configuration.
3. Countries and ISO currencies.
4. Tenant and branch currency enablement.
5. Manual, dated exchange rates.
6. User-to-branch assignments.
7. Current branch selection.
8. Permission-ready all-branch access.
9. Tests proving that cross-tenant and unauthorised cross-branch access is impossible.

At the end of Phase 1, an authenticated user must operate only inside their tenant and authorised branches. A user with all-branch permission must be able to select **All branches** and filter data by a particular branch without obtaining access to another tenant.

---

## 2. Domain terminology

Use these terms consistently in PHP, TypeScript, database tables and interface copy:

| Term | Meaning |
|---|---|
| Tenant | A company/customer subscribed to the ERP. |
| Branch | A country office or operational office belonging to a tenant, such as Uganda, South Sudan or DRC. |
| Current tenant | The tenant resolved from the authenticated user's trusted server-side identity. |
| Current branch | The branch selected for current operational work. It may be absent when an authorised user selects All branches. |
| Base currency | The principal accounting/reporting currency of a tenant or branch. |
| Enabled currency | A currency that may be used by the tenant or branch. |

Do not use `facility`, `organisation`, `company` and `tenant` interchangeably in code. Use `tenant` for the SaaS boundary and `branch` for its offices.

---

## 3. Scope

### 3.1 In scope

- Tenant model and tenant context.
- Branch model and branch access.
- Countries reference data.
- Currencies reference data.
- Tenant currency settings.
- Branch currency settings.
- Exchange-rate storage and approval-ready states.
- User membership in exactly one tenant for this version.
- User membership in one or more branches.
- A default branch per user.
- Current branch selector in the authenticated layout.
- An All branches option for users with permission.
- Tenant and branch administration pages.
- Policies, validation, actions, query scopes and tests.
- Seeders/factories needed for development and testing.
- Audit fields required for later audit-log integration.

### 3.2 Explicitly out of scope

- Projects and sites.
- Equipment and fleet.
- Inventory and procurement.
- HR, leave and attendance.
- Finance transactions, expenses and invoices.
- Tax calculation engine.
- Inter-branch transfers.
- Full role/permission administration UI beyond the minimum permission needed for branch access.
- GPS.
- Offline synchronisation.
- Automatic exchange-rate APIs.
- Full audit-trail UI.
- Platform subscriptions and billing.
- Self-service tenant registration.

Do not add placeholder CRUD modules for out-of-scope features.

---

## 4. Required preflight

Before implementing:

1. Read the repository's `AGENTS.md`, AI guidelines and README completely when present.
2. Inspect existing actions, controllers, requests, models, factories, tests and Inertia pages.
3. Inspect how the starter kit generates UUIDs and models before creating a second competing implementation.
4. Inspect the existing authentication flow and shared Inertia data.
5. Inspect the existing permission packages before installing anything.
6. Run the current test suite and record the clean baseline.
7. Do not update unrelated dependencies.
8. Preserve the starter kit's strict typing and immutable conventions.

Use the repository's configured PHP and Bun commands. Under Laravel Herd, run commands from the project directory with the PHP version configured for the project.

Baseline checks:

```bash
composer test
```

If the complete suite fails before changes, report the existing failure before implementing. Do not hide or weaken tests, coverage, PHPStan, Rector or lint rules.

---

## 5. Architectural rules

### 5.1 Shared-database tenancy

Use a shared database with a required `tenant_id` on tenant-owned tables.

Tenant isolation is a security boundary. It must be enforced server-side and tested. A frontend filter is not a tenancy control.

### 5.2 Tenant resolution

For Phase 1, resolve the current tenant from the authenticated user:

```text
authenticated user → tenant_id → TenantContext
```

Never accept `tenant_id` from an Inertia form as the source of truth. Actions must obtain it from the trusted tenant context.

Unauthenticated requests and authenticated users without a valid active tenant must fail safely.

### 5.3 Tenant global scope

A tenant global scope and reusable `BelongsToTenant` trait are acceptable and recommended for tenant-owned models.

The implementation must:

- Add `where tenant_id = current tenant` automatically.
- Assign `tenant_id` automatically during creation.
- Fail when tenant context is missing instead of silently creating unscoped data.
- Prevent ordinary application code from changing a record's `tenant_id`.
- Provide an explicit and narrowly controlled bypass only for platform-level operations and tests.

Do not place this trait on global reference models such as `Country` and `Currency`.

### 5.4 Branch scoping

Do **not** add a permanent global branch scope.

Branch visibility is contextual because:

- Users may belong to multiple branches.
- Directors may view all branches.
- Reports may consolidate branches.
- Later transfers will reference source and destination branches.

Use policies, action validation and explicit query scopes such as `visibleTo(User $user)`.

### 5.5 Authorization

Do not use `is_director` as the only security mechanism.

The capability for consolidated branch access must be represented as a permission, for example:

```text
branches.view-all
```

If the existing domain still requires `users.is_director`, treat it as a business-profile flag only. Authorization must continue to depend on permissions and branch membership.

### 5.6 UUIDs

Use UUID primary and foreign keys consistently.

- Follow the starter kit's existing UUID strategy.
- Use `foreignUuid()` or the repository's equivalent.
- Do not mix incrementing IDs into new domain tables.
- Factories and tests must generate valid UUIDs through the model's normal mechanism.

### 5.7 Soft deletion

Use soft deletes for:

- Tenants.
- Branches.
- Tenant/branch configuration records when historical recovery is meaningful.

Do not soft-delete immutable/global reference records simply because every model normally has soft deletes.

`countries` and `currencies` should use `is_active`, not tenant-driven deletion.

Pivot membership tables should normally use explicit activation/removal logic rather than unnecessary soft deletes unless existing repository conventions require them.

---

## 6. Proposed database design

Inspect existing migrations first and adjust names only where needed to preserve repository conventions.

### 6.1 `tenants`

```text
id                       uuid primary key
name                     string
code                     string unique
default_currency_code    char(3)
is_multibranch           boolean default false
multi_currency_enabled   boolean default false
timezone                 string default 'Africa/Kampala'
status                   string/enum default 'active'
created_at
updated_at
deleted_at
```

Rules:

- `code` must be normalised consistently, such as uppercase.
- `default_currency_code` defaults to `USD` and references `currencies.code`.
- Saving `USD` as the currency code is correct. Use ISO 4217 codes, not symbols or display names.
- Only one tenant is assigned to a normal user in this version.

### 6.2 `countries`

```text
code                     char(2) primary key
name                     string
iso3_code                char(3)
default_currency_code    char(3)
is_active                boolean default true
created_at
updated_at
```

Seed at minimum:

```text
UG | Uganda       | UGA | UGX
SS | South Sudan  | SSD | SSP
CD | DRC          | COD | CDF
```

Country currency is a suggested default, not an enforced branch currency. A South Sudan branch may choose USD if the business approves it.

### 6.3 `currencies`

```text
code             char(3) primary key
name             string
symbol           string nullable
decimal_places   unsigned tiny integer default 2
is_active        boolean default true
created_at
updated_at
```

Seed at minimum:

```text
USD | United States Dollar | $   | 2
UGX | Ugandan Shilling     | UGX | 0
SSP | South Sudanese Pound | SSP | 2
CDF | Congolese Franc      | CDF | 2
```

Do not permit tenants to invent arbitrary currency codes through ordinary UI.

### 6.4 `branches`

```text
id                       uuid primary key
tenant_id                uuid foreign key
name                     string
code                     string
country_code             char(2) foreign key
base_currency_code       char(3) foreign key default 'USD'
multi_currency_enabled   boolean default false
timezone                 string
tax_registration_number  string nullable
address                  text nullable
is_active                boolean default true
created_by               uuid nullable
updated_by               uuid nullable
created_at
updated_at
deleted_at
```

Constraints:

- Unique branch code per tenant, including the agreed soft-delete behaviour.
- A branch always belongs to the current tenant.
- Branch base currency must be active.
- The base currency must also be enabled for the branch.
- A single-branch tenant still uses a branch record.
- `is_multibranch` controls whether additional branches may be created; it does not remove the branch layer.

### 6.5 User changes

Add to `users` only if not already present:

```text
tenant_id       uuid foreign key
is_active       boolean default true
is_director     boolean default false only if still required as business metadata
last_login_at   timestamp nullable
```

`is_director` must not grant access by itself.

### 6.6 `branch_user`

```text
branch_id       uuid foreign key
user_id         uuid foreign key
is_default      boolean default false
created_at
updated_at
```

Constraints and rules:

- Unique combination of `branch_id` and `user_id`.
- Both records must belong to the same tenant.
- A user may have at most one default branch.
- A branch-restricted user must have at least one active branch before normal ERP access.
- Removing access to the current/default branch must select another authorised branch or clear the session safely.

### 6.7 `tenant_currencies`

```text
id             uuid primary key
tenant_id      uuid foreign key
currency_code  char(3) foreign key
is_enabled     boolean default true
is_default     boolean default false
created_at
updated_at
deleted_at nullable according to repository convention
```

Constraints:

- Unique currency per tenant.
- Exactly one enabled default tenant currency.
- The tenant's `default_currency_code` must correspond to its default enabled currency.
- The default currency cannot be disabled.

### 6.8 `branch_currencies`

```text
id                               uuid primary key
tenant_id                        uuid foreign key
branch_id                        uuid foreign key
currency_code                    char(3) foreign key
is_enabled                       boolean default true
is_default_transaction_currency  boolean default false
can_receive                      boolean default true
can_pay                          boolean default true
created_at
updated_at
deleted_at nullable according to repository convention
```

Constraints:

- Unique currency per branch.
- Branch and tenant IDs must agree.
- Currency must already be enabled for the tenant.
- Base currency must always be enabled for the branch.
- Only one default transaction currency per branch.
- Multi-currency remains independent from multi-branch.

### 6.9 `exchange_rates`

```text
id                    uuid primary key
tenant_id             uuid foreign key
branch_id             uuid nullable foreign key
from_currency_code    char(3) foreign key
to_currency_code      char(3) foreign key
rate                  decimal(20, 10)
effective_date        date
expires_at            timestamp nullable
source                string/enum default 'manual'
status                string/enum default 'draft'
approved_by            uuid nullable
approved_at            timestamp nullable
created_by             uuid
updated_by             uuid nullable
created_at
updated_at
deleted_at
```

Rules:

- Rate must be positive.
- `from_currency_code` and `to_currency_code` must be different.
- Define direction consistently: `1 FROM = rate TO`.
- A branch-specific rate may override a tenant-level rate.
- Rates are dated historical records. Never overwrite an old approved rate to represent a new period.
- Draft rates may be edited. Approved rates should be superseded by a new rate rather than silently changed.
- This phase stores rates; transaction conversion snapshots are implemented when financial transactions are built.

Use a real enum or repository-standard status representation:

```text
draft
approved
superseded
```

---

## 7. Database integrity

Application validation is not enough. Add suitable database constraints and indexes.

Required indexes should support:

- Tenant-scoped lookups.
- Branch-scoped lookups.
- User branch memberships.
- Currency enablement.
- Exchange-rate lookup by tenant, optional branch, currency pair, status and effective date.

At minimum consider:

```text
branches: tenant_id + code
branch_user: user_id + branch_id
tenant_currencies: tenant_id + currency_code
branch_currencies: tenant_id + branch_id + currency_code
exchange_rates: tenant_id + branch_id + from_currency_code + to_currency_code + effective_date
```

Prevent tenant/branch mismatches in actions and requests even if the database cannot express every composite relationship cleanly.

---

## 8. Backend components

Use repository conventions for namespaces and file placement. The names below describe responsibilities, not mandatory exact paths.

### 8.1 Context services

Create typed services such as:

```text
TenantContext
BranchContext
```

`TenantContext` must expose the active tenant and ID and fail when unresolved.

`BranchContext` must expose:

- Selected branch or null for authorised All branches.
- IDs of branches accessible to a user.
- Safe methods to select and clear current branch.
- Validation that a selected branch belongs to the current tenant and is active.

Bind contexts with the container using the repository's preferred providers or middleware lifecycle.

Avoid stale singleton state in queue workers and tests.

### 8.2 Tenant scope and trait

Create:

```text
TenantScope
BelongsToTenant
```

Add focused tests for both. Do not hide unsafe `withoutGlobalScope()` calls inside broad helpers.

### 8.3 Query scopes

Create explicit visibility scopes for branch-owned records:

```php
public function scopeVisibleTo(Builder $query, User $user): Builder
```

Rules:

- A user with `branches.view-all` receives all branches in the current tenant.
- Other users receive only active assigned branches.
- An explicit branch filter must still be validated against accessible branch IDs.

### 8.4 Actions

Keep controllers thin and implement operations as single-purpose actions following the starter kit.

Suggested actions:

```text
CreateTenant
UpdateTenant
CreateBranch
UpdateBranch
ArchiveBranch
AssignUserToBranches
SetDefaultUserBranch
SelectCurrentBranch
EnableTenantCurrency
DisableTenantCurrency
EnableBranchCurrency
DisableBranchCurrency
CreateExchangeRate
UpdateDraftExchangeRate
ApproveExchangeRate
SupersedeExchangeRate
```

Every action must:

- Declare strict parameter and return types.
- Validate business invariants even when request validation already exists.
- Obtain the tenant from trusted context.
- Use a database transaction when multiple records change.
- Fail before partial mutation.
- Avoid associative arrays where the starter kit expects typed data objects.

### 8.5 Controllers

Follow Cruddy and single-action conventions already present in the starter kit.

Controllers should only:

1. Authorise.
2. Receive validated/typed input.
3. Call one action or query object.
4. Return an Inertia response or redirect.

Do not place currency, tenancy or branch mutation logic inside controllers.

### 8.6 Form Requests

Create requests for each mutation.

Validation must include:

- UUID existence checks scoped to the current tenant.
- Active country/currency checks.
- Unique branch code within tenant.
- Cross-field currency rules.
- Positive exchange rates.
- Valid effective dates.
- Branch access validation.

Never use an unscoped `exists:branches,id` rule for tenant-owned data.

Use `Rule::exists()` with tenant constraints or an equivalent repository-standard approach.

### 8.7 Policies

Create policies for:

```text
Tenant
Branch
TenantCurrency
BranchCurrency
ExchangeRate
```

Every policy must check tenant identity first, then permissions and branch access.

For cross-tenant records, prefer a non-disclosing response (`404`) where the existing application convention supports it.

---

## 9. Minimum permissions

If Spatie Laravel Permission is already installed, extend the existing implementation. If it is not installed, do not introduce a second authorization system; install/configure it only after checking compatibility with the starter kit and documenting the decision.

Seed the minimum Phase 1 permissions:

```text
tenants.view
tenants.update
branches.view
branches.create
branches.update
branches.archive
branches.view-all
branch-users.manage
currencies.manage
exchange-rates.view
exchange-rates.create
exchange-rates.update
exchange-rates.approve
```

Do not hardcode director role names in policies.

For initial local development, seed a tenant administrator role with all Phase 1 permissions and an executive/director role with `branches.view-all` plus appropriate read access.

---

## 10. Inertia and React requirements

Use existing TypeScript and page conventions from the starter kit.

### 10.1 Shared Inertia data

Provide only the data needed globally:

```text
currentTenant
currentBranch nullable
accessibleBranches
canViewAllBranches
enabledTenantCurrencies where required
```

Do not share full models or sensitive settings globally.

### 10.2 Branch selector

Add a branch selector to the authenticated application shell.

Behaviour:

- Single accessible branch: display it; selection may be disabled.
- Multiple accessible branches: show assigned active branches.
- User with `branches.view-all`: include `All branches`.
- Selecting a branch makes a server request that validates access and stores the selected branch in session.
- Selecting All branches stores a null current branch only when permission allows it.
- If the selected branch is archived or access is revoked, automatically fall back safely.
- Do not trust a branch ID held only in browser state.

### 10.3 Required pages

Build only the necessary administration pages:

```text
Branches/Index
Branches/Create
Branches/Edit
Branches/Show or a focused details/settings page
Currencies/Index or tenant currency settings
ExchangeRates/Index
ExchangeRates/Create
ExchangeRates/Edit for drafts only
```

Use existing shared components before creating new ones.

### 10.4 UI expectations

- Search and filters use URL query parameters where practical.
- Forms show server validation clearly.
- Currency direction is explicit: `1 FROM = RATE TO`.
- Destructive/archival operations require confirmation.
- Disabled base/default currencies explain why they cannot be disabled.
- Permission gates hide inaccessible controls, but server policies remain authoritative.
- Pages have useful empty states and loading/disabled behaviour.

---

## 11. Required workflows

### 11.1 Initial tenant bootstrap

Because users now require tenants, implement a safe bootstrap strategy for development and first deployment.

Preferred initial process:

1. Seed global countries and currencies.
2. Create a tenant with `USD` as the default currency.
3. Enable USD in `tenant_currencies` as default.
4. Create the first branch.
5. Enable the branch base currency in `branch_currencies`.
6. Attach the initial administrator to the tenant and branch.
7. Make the branch the user's default/current branch.
8. Assign the initial tenant-administrator permissions.

This must be idempotent in seeders or implemented as a documented one-time bootstrap command/action.

### 11.2 Branch creation

When creating a branch:

1. Obtain tenant from context.
2. Authorise branch creation.
3. Validate the country and base currency.
4. Create the branch.
5. Enable the base currency for the tenant if business rules allow automatic enablement; otherwise require it first.
6. Enable the base currency for the branch.
7. Mark it as branch default transaction currency when it is the first enabled currency.
8. Record creator/updater IDs.
9. Complete all related writes in one database transaction.

Default the form to USD, but show the selection clearly and require valid server-side confirmation.

### 11.3 Enabling multi-currency

Multi-currency must work independently of multi-branch.

Rules:

- A single-branch tenant can enable multiple currencies.
- A multi-branch tenant may still use only one currency.
- A branch may use only currencies enabled by its tenant.
- Disabling the tenant setting should not delete historical configuration.
- The base/default currency cannot be disabled.
- Later transactions will enforce whether a currency has historical usage.

### 11.4 Creating an exchange rate

1. Select optional branch scope.
2. Select enabled from and to currencies.
3. Prevent identical pairs.
4. Enter a positive rate and effective date.
5. Store as draft.
6. Allow authorised approval.
7. Supersede rather than mutate an old approved rate when a new period starts.

Do not calculate inverse rates unless a single central exchange-rate service owns the behaviour and precision rules. Avoid storing contradictory independently editable inverse values.

### 11.5 Selecting current branch

1. User chooses a branch or All branches.
2. Server verifies current tenant, active branch, membership or `branches.view-all` permission.
3. Server writes validated selection to session.
4. Shared Inertia props refresh.
5. Later branch-owned queries use explicit branch filters based on this context.

---

## 12. Factories and seeders

Create factories for:

```text
Tenant
Branch
TenantCurrency
BranchCurrency
ExchangeRate
```

Factory states should support:

```text
active/inactive tenant
single/multi-branch tenant
single/multi-currency tenant
active/inactive branch
draft/approved/superseded exchange rate
```

Seed development examples:

```text
Tenant: Point Investment Co. Ltd
Branches:
- Uganda Branch — UG — base currency UGX or the explicitly selected business currency
- South Sudan Branch — SS — selected base currency
- DRC Branch — CD — selected base currency

Currencies:
- USD
- UGX
- SSP
- CDF
```

Do not assume the business has confirmed every branch base currency; keep seeder values clearly development-only.

---

## 13. Pest test plan

Maintain the starter kit's 100% test and type-coverage requirements. Do not exclude new code from coverage to make the suite pass.

### 13.1 Tenant isolation tests

Prove that:

- Tenant A cannot list Tenant B branches.
- Tenant A cannot view Tenant B branch by guessed UUID.
- Tenant A cannot update/archive Tenant B branch.
- Tenant A cannot attach a Tenant B branch to its user.
- Tenant A cannot use Tenant B currencies or exchange rates.
- Tenant-owned models automatically receive the current tenant ID.
- Creating a tenant-owned model without tenant context fails.
- Tenant ID cannot be changed through mass assignment or action input.

### 13.2 Branch access tests

Prove that:

- A single-branch user sees only their branch.
- A multi-branch user sees only assigned branches.
- `branches.view-all` returns all active branches in the current tenant.
- `branches.view-all` never exposes another tenant.
- A user cannot select an unauthorised branch.
- A user without permission cannot select All branches.
- A revoked or archived current branch is cleared/falls back safely.
- A user has at most one default branch.
- Branch and user tenant IDs must match.

### 13.3 Currency tests

Prove that:

- USD is stored as the ISO code `USD`.
- A tenant has one default enabled currency.
- A branch base currency is enabled for that branch.
- A branch cannot enable a currency disabled at tenant level.
- Base/default currencies cannot be disabled.
- A single-branch tenant may enable multi-currency.
- Multi-branch and multi-currency settings are independent.

### 13.4 Exchange-rate tests

Prove that:

- Rates are positive.
- Currency pairs must differ.
- Direction means `1 FROM = RATE TO`.
- Branch-specific rates belong to the same tenant as the branch.
- Users require permission to create, update and approve rates.
- Approved rates cannot be silently edited.
- A newer rate creates/supersedes history according to the action rules.
- Lookup returns the correct applicable rate for tenant, optional branch, pair and date.

### 13.5 Inertia tests

Test:

- Correct branch selector props.
- No inaccessible branches in props.
- All branches only appears for authorised users.
- Form responses use the expected Inertia components.
- Validation errors are returned for invalid cross-tenant IDs.

### 13.6 Quality tests

Include unit tests for context, scopes and currency-rate lookup, plus feature tests for every mutation and permission boundary.

---

## 14. Implementation order

Implement in this order so each layer supports the next:

1. Repository preflight and clean baseline.
2. Countries and currencies migrations/models/seeders.
3. Tenants migration/model/factory.
4. Tenant currency configuration.
5. Add tenant relationship to users and bootstrap existing user data safely.
6. Tenant context, scope and `BelongsToTenant` trait.
7. Tenant-isolation tests.
8. Branches migration/model/factory/actions/requests/policy.
9. `branch_user` membership and default-branch rules.
10. Branch context and session selection.
11. Permission-aware branch visibility.
12. Shared Inertia props and branch selector.
13. Branch administration pages.
14. Branch currencies.
15. Exchange rates and lookup service.
16. Complete feature/unit/Inertia tests.
17. Run lint, type checks, coverage and the full test suite.
18. Update documentation and provide a concise implementation summary.

Do not build all migrations first and defer security tests until the end. Complete and test each foundation incrementally.

---

## 15. Suggested commits

Keep changes reviewable:

```text
feat: add global country and currency references
feat: add tenant context and tenant isolation
test: prove tenant data isolation
feat: add branch management and memberships
feat: add permission-aware branch context
feat: add tenant and branch currency settings
feat: add dated exchange rates
test: cover tenancy branch and currency workflows
docs: document phase one architecture
```

Do not make commits unless the user explicitly asks Codex to commit.

---

## 16. Definition of done

Phase 1 is complete only when all conditions below are satisfied.

### Domain

- Tenant, branch, country and currency concepts are consistently named.
- Every user belongs to a valid tenant.
- Every operational branch belongs to a tenant.
- Single-branch tenants still use the branch layer.
- Multi-branch and multi-currency settings are independent.

### Security

- Tenant isolation is automatic and tested.
- Branch access is explicit and tested.
- All-branch access uses permission checks.
- `is_director` alone grants nothing.
- Cross-tenant UUID guessing cannot expose or mutate records.

### Currency

- ISO currency codes are used.
- USD may be the default but is stored as `USD`.
- Tenant and branch currencies can be enabled safely.
- Dated exchange rates preserve history.
- Rate direction is unambiguous.

### Interface

- Authorised users can manage branches.
- Users can select an authorised current branch.
- Authorised users can select All branches.
- Currency and exchange-rate screens have clear validation and empty states.

### Engineering quality

- Actions contain business logic.
- Controllers remain thin.
- Requests and policies protect every mutation.
- UUID and soft-delete conventions are respected.
- Factories and seeders support realistic tests.
- No new PHPStan, lint, type-coverage or test failures exist.
- `composer test` passes with the starter kit's required coverage.

---

## 17. Required final handoff from Codex

When implementation finishes, Codex should report:

1. Files and migrations added or changed.
2. Important architectural decisions made after inspecting the starter kit.
3. Tenant and branch security behaviour.
4. Database and seed data created.
5. Tests added and exact commands run.
6. Results of `composer test`.
7. Any assumptions or unresolved business decisions.
8. The next recommended vertical slice, without implementing it.

Do not claim completion if the full strict test suite is failing.

---

## 18. Business decisions that may remain configurable

Do not block the technical foundation on these, but document chosen development defaults:

- Point Investment's tenant base currency.
- Each branch's confirmed base currency.
- Whether tenant administrators may automatically enable a tenant currency during branch creation.
- Who may approve exchange rates.
- Whether exchange rates are tenant-wide by default with optional branch overrides.
- Whether archived branches remain selectable in historical reports.
- Whether users must always have a default branch when they have `branches.view-all`.

Defaults must be centralised and replaceable, not scattered through controllers or React components.

