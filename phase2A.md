# Construction ERP - Phase 2A Detailed Implementation Plan

## 1. Purpose

Phase 2A starts the operational ERP by creating the project/site spine.

This is the correct first build slice because every later Phase 2 feature depends on it:

- Documents need projects/sites to link to.
- DSRs need projects/sites, assignments, activities and reporting rules.
- Daily costing needs BOQ/activity context.
- Equipment, inventory, HR and finance later need project/site allocation.
- Dashboards need trustworthy project/site source records.

Do not start with dashboards, documents or DSR forms before the project/site scope, assignment and permission model exists.

---

## 2. Goal

Build enough project/site foundation for Point Investment to configure one real Uganda pilot project and prove that different users see different operational scopes.

At the end of Phase 2A:

1. Customers can be created and maintained.
2. Contracts can be created and linked to customers.
3. Projects can be created under a tenant branch.
4. Sites can be created under projects.
5. Project activities/BOQ items can be created.
6. Users can be assigned to projects and sites.
7. Policies enforce tenant, branch, project and site access.
8. Seed data demonstrates Director, Project Manager, Site Manager, Auditor and branch-restricted separation.
9. UI pages exist for customers, contracts, projects and sites.
10. Tests prove isolation and permission behaviour before Phase 2B starts.

---

## 3. Where to start

Start in PointERP.

PointERP owns shared migrations and the operational ERP UI. The manager app is only for support-team management of tenants/branches and should not own project/site operational workflows.

Recommended first file areas to inspect before coding:

```text
app/Models
app/Actions
app/Http/Controllers
app/Http/Requests
app/Policies
database/migrations
database/factories
database/seeders
routes/web.php
resources/js/pages
resources/js/components
resources/js/layouts
tests/Feature
tests/Unit
```

Follow existing Phase 1 patterns:

- UUID primary keys.
- `BelongsToTenant` for tenant-owned models.
- Explicit branch visibility scopes instead of a global branch scope.
- Cruddy controllers.
- Actions for business logic.
- Form requests for validation.
- Policies for server-side authorization.
- Modals for compact create/edit workflows.
- Full pages for large operational workflows.
- Search/filter controls below headings.
- Active/inactive tabs instead of mixing inactive records into active lists.
- Global confirmation modal and Sonner toasts.

---

## 4. Scope

### 4.1 In scope

Models and CRUD:

- Customer.
- Contract.
- Project.
- Site.
- ProjectActivity.
- ProjectUser assignment.
- SiteUser assignment.

Security:

- Policies for every model above.
- Tenant isolation.
- Branch isolation.
- Project/site assignment checks.
- Permission checks.

UI:

- `/customers`
- `/contracts`
- `/projects`
- `/projects/{project}`
- `/projects/{project}/sites`
- `/sites/{site}`

Seed data:

- Uganda pilot project.
- South Sudan separate project.
- Two Uganda sites.
- One South Sudan site.
- Users with different roles and project/site access.

Tests:

- Feature tests for index/create/update/archive.
- Policy tests for cross-tenant, cross-branch and assignment behaviour.
- Inertia tests for props and page components.
- Seeder test proving access separation.

### 4.2 Out of scope

- Document upload/versioning.
- Drawing management.
- DSR submission workflow.
- Expected DSR calendar.
- Missing DSR escalation.
- Equipment register.
- Inventory/procurement.
- HR attendance/leave.
- Finance postings and IPC generation.
- Full offline sync.

Do not add placeholder menu items for out-of-scope modules unless they are clearly disabled/later.

---

## 5. Domain model

### 5.1 Customers

Customers are external clients/contracting parties.

Fields:

```text
id
tenant_id
branch_id nullable
name
code
email nullable
phone nullable
address nullable
status active/inactive
created_by
updated_by
timestamps
soft deletes
```

Rules:

- Code unique per tenant.
- Customer can be tenant-wide or branch-associated.
- Inactive customers move to an inactive tab.
- Used customers should not be hard-deleted.

### 5.2 Contracts

Contracts link commercial scope to customers and projects.

Fields:

```text
id
tenant_id
branch_id
customer_id
reference
title
scope_summary nullable
contract_value nullable
currency_code
starts_on nullable
ends_on nullable
retention_percent nullable
payment_terms nullable
status draft/active/completed/closed/archived
created_by
updated_by
timestamps
soft deletes
```

Rules:

- Reference unique per tenant.
- Customer, branch and contract tenant must agree.
- Currency must be enabled for tenant and branch.
- Contracts can exist before a project is created.

### 5.3 Projects

Projects are the main operational unit below branch.

Fields:

```text
id
tenant_id
branch_id
customer_id nullable
contract_id nullable
reference
name
description nullable
manager_id nullable
base_currency_code
budget_amount nullable
starts_on nullable
ends_on nullable
reporting_deadline nullable
status planned/active/on_hold/completed/closed/archived
created_by
updated_by
timestamps
soft deletes
```

Rules:

- Reference unique per tenant.
- Manager must belong to the same tenant.
- Manager must have branch access.
- Contract/customer must belong to same tenant and branch.
- Only active/planned/on-hold projects show in active tab.
- Completed/closed/archived projects show in inactive/archive tab.

### 5.4 Sites

Sites are physical work locations under a project.

Fields:

```text
id
tenant_id
branch_id
project_id
reference
name
location_name nullable
latitude nullable
longitude nullable
manager_id nullable
reporting_deadline nullable
status planned/active/suspended/completed/closed/archived
created_by
updated_by
timestamps
soft deletes
```

Rules:

- Reference unique per project.
- Branch must match project branch for Phase 2A.
- Manager must belong to same tenant and have branch access.
- Active sites appear in active tab.
- Suspended/completed/closed/archived sites appear outside active tab.

### 5.5 Project activities / BOQ items

Activities are the bridge between DSR quantities and later commercial measurement.

Fields:

```text
id
tenant_id
branch_id
project_id
site_id nullable
milestone_id nullable later
code nullable
boq_item_number nullable
name
unit nullable
planned_quantity nullable
approved_quantity default 0
rate_amount nullable
currency_code nullable
status active/inactive
sort_order default 0
created_by
updated_by
timestamps
soft deletes
```

Rules:

- An activity may be project-wide or site-specific.
- BOQ item number is not required for every activity, but must be searchable when present.
- Rate is a snapshot for reporting and later DSR output calculations.
- Do not build full IPC/valuation logic here.

### 5.6 Project and site assignments

Use explicit assignment pivots.

`project_user`:

```text
project_id
user_id
role nullable
can_manage boolean
timestamps
```

`site_user`:

```text
site_id
user_id
role nullable
can_submit_dsr boolean default false
can_review_dsr boolean default false
timestamps
```

Rules:

- User and assigned record must belong to the same tenant.
- User must have branch access for the project/site branch.
- Site assignment must belong to a project visible to the user being assigned.
- Assignment changes are audited.

---

## 6. Permissions

Add these permissions idempotently to `RolePermissionSeeder`:

```text
customers.view
customers.manage
contracts.view
contracts.manage
projects.view
projects.create
projects.update
projects.archive
projects.view-all
sites.view
sites.create
sites.update
sites.archive
sites.view-all
project-users.manage
site-users.manage
project-activities.manage
dashboards.projects.view
```

Suggested seeded role behaviour:

- Director: all Phase 2A permissions and cross-project/cross-site view.
- Administrator: management permissions except business approvals where not needed.
- Project Manager: view/update assigned projects, manage assigned sites and activities.
- Site Manager / Engineer: view assigned project/site, prepare for later DSR submit rights.
- Auditor / Viewer: read-only assigned project/site access.

Policies must not rely on role names. Use permissions and assignments.

---

## 7. Backend implementation plan

### 7.1 Migrations

Create migrations in this order:

1. `customers`
2. `contracts`
3. `projects`
4. `sites`
5. `project_activities`
6. `project_user`
7. `site_user`

Indexes:

```text
customers: tenant_id + code
contracts: tenant_id + reference
contracts: tenant_id + branch_id
projects: tenant_id + reference
projects: tenant_id + branch_id + status
sites: tenant_id + project_id + reference
sites: tenant_id + branch_id + status
project_activities: tenant_id + project_id + status
project_activities: tenant_id + boq_item_number
project_user: project_id + user_id unique
site_user: site_id + user_id unique
```

### 7.2 Models

Create:

```text
App\Models\Customer
App\Models\Contract
App\Models\Project
App\Models\Site
App\Models\ProjectActivity
```

Model requirements:

- Strict casts for dates, decimals and status where appropriate.
- Relationships to tenant, branch, customer, contract, project, sites, users and activities.
- `scopeVisibleTo(User $user)` for project and site.
- `scopeActive()` and `scopeInactive()` or equivalent local pattern.

### 7.3 Actions

Create focused actions:

```text
CreateCustomer
UpdateCustomer
ToggleCustomerStatus
CreateContract
UpdateContract
ArchiveContract
CreateProject
UpdateProject
ArchiveProject
CreateSite
UpdateSite
ArchiveSite
CreateProjectActivity
UpdateProjectActivity
ToggleProjectActivityStatus
AssignProjectUsers
AssignSiteUsers
```

Action rules:

- Always use trusted `TenantContext`.
- Validate tenant/branch consistency again even if request validation exists.
- Use transactions when mutating assignments or related records.
- Write audit events for create/update/archive/assignment changes.

### 7.4 Requests

Create request classes under module-specific namespaces:

```text
App\Http\Requests\Operations\Customers
App\Http\Requests\Operations\Contracts
App\Http\Requests\Operations\Projects
App\Http\Requests\Operations\Sites
App\Http\Requests\Operations\ProjectActivities
```

Validation must scope `exists` rules by current tenant and branch.

No unscoped `exists:projects,id`, `exists:sites,id`, `exists:customers,id` or `exists:users,id`.

### 7.5 Controllers

Use cruddy controllers:

```text
CustomerController
ContractController
ProjectController
SiteController
ProjectActivityController
ProjectUserController
SiteUserController
```

Keep controller public methods within allowed conventions.

Recommended route names:

```text
customers.index
customers.store
customers.update
customers.destroy/status
contracts.index
contracts.store
contracts.update
contracts.destroy/archive
projects.index
projects.show
projects.store
projects.update
projects.destroy/archive
sites.index
sites.show
sites.store
sites.update
sites.destroy/archive
project-activities.store
project-activities.update
project-activities.destroy/status
project-users.store
site-users.store
```

Prefer top-level URLs:

```text
/customers
/contracts
/projects
/projects/{project}
/sites/{site}
```

Do not use `/operations/...` unless the app's route grouping later requires it.

---

## 8. UI implementation plan

### 8.1 Navigation

Add a sidebar group such as `Operations`.

Initial links:

```text
Projects
Customers
Contracts
```

Sites should be accessible from project detail and optionally through a global sites list after the workflow is clear.

### 8.2 Customers page

Layout:

- Heading and description.
- Search/filter left under heading.
- Active/inactive tabs on the right of filter row.
- Add Customer button extreme right.
- Table with customer name, code, branch, contact, status.
- Create/edit modal.

Filters:

- Search by name/code/email/phone.
- Branch where useful.
- Active/inactive tab.

### 8.3 Contracts page

Layout:

- Search by reference/title/customer.
- Filter by customer, branch, status.
- Add Contract modal.
- Table with reference, customer, value, currency, dates, status.

Note:

Contract details can remain compact in Phase 2A. Documents arrive in Phase 2B.

### 8.4 Projects page

Layout:

- Search by reference/name/customer/contract.
- Filter by branch, status, manager.
- Active/archive tabs.
- Add Project modal or larger dialog.
- Table with reference, project name, branch, manager, status, dates, site count.

Project show page:

- Header with project identity, status, branch, manager.
- Tabs:
  - Overview
  - Sites
  - Activities / BOQ
  - Access
  - Documents placeholder for Phase 2B
  - DSRs placeholder for Phase 2C

### 8.5 Sites

Project sites tab:

- Search by reference/name/location.
- Active/inactive tabs.
- Add Site modal.
- Table with reference, site name, manager, reporting deadline, status.

Site show page:

- Overview.
- Assigned users.
- Activities for that site.
- DSR placeholder for Phase 2C.

### 8.6 Project activities / BOQ

Project activities tab:

- Search by BOQ item/name/code.
- Filter by site and status.
- Add Activity modal.
- Table with BOQ item, name, site, unit, planned quantity, approved quantity, rate, status.

This is the minimum bridge to DSR quantities. Avoid building full valuation screens.

### 8.7 Access management

Project access tab:

- Assign existing users from same tenant and branch.
- Use combobox for user selection.
- Checkboxes for `can_manage`.
- Show user's email and staff name where available.

Site access:

- Assign users from same tenant and branch.
- Checkboxes for `can_submit_dsr` and `can_review_dsr`.

Use global confirmation modal when removing access.

---

## 9. Seed data plan

Extend the current seeders without making everyone powerful.

Suggested data:

### Uganda

```text
Customer: Uganda National Roads Authority
Contract: UNRA/WORKS/2021-2022/00369
Project: Busunju - Kiboga - Hoima Road Rehabilitation
Sites:
- Busunju Section
- Kiboga Section
Activities:
- 12.03(a) Maintenance of existing road
- 31.01(b)(i) Removal of top soil
- 36.01(a) Excavation to spoil
- 37.02(c) Natural material for subbase
- 82.02 Petrol
- 83.05 Excavator
```

### South Sudan

```text
Customer: South Sudan Roads Authority
Contract: demo reference
Project: Juba Access Road Works
Site:
- Juba Main Site
```

### Users

```text
lemi@gmail.com
- Support/Director/admin-style testing user as currently seeded where appropriate.

admin.kla@point.test
- Uganda administrator/project access.

pm.kla@point.test
- Project Manager, assigned to Uganda project.

site.kla@point.test
- Site Manager, assigned only to Busunju Section.

auditor.kla@point.test
- Read-only assigned/audit view.

store.jub@point.test or equivalent
- South Sudan branch user, no Uganda project visibility.
```

Final emails can follow the current seeder naming already used in Phase 1. Keep passwords consistent for local UI testing.

---

## 10. Tests

### 10.1 Required feature tests

- Customer index scopes by tenant.
- Customer create/update/status requires permission.
- Contract create rejects cross-tenant customer/branch/currency.
- Project create rejects cross-tenant contract/customer/manager.
- Project index shows assigned or permission-visible projects only.
- Project show blocks guessed UUID from another tenant.
- Site create rejects project/branch mismatch.
- Site index shows assigned or permission-visible sites only.
- Activity create stores BOQ/unit/rate data and tenant/branch/project IDs.
- Assignment actions reject users without branch access.
- Removing project/site access changes visibility immediately.

### 10.2 Required policy tests

- User with no permission receives 403.
- Branch access alone is not enough to update a project unless policy permits it.
- `projects.view-all` sees all tenant projects but not another tenant.
- `sites.view-all` sees all tenant sites but not another tenant.
- Project manager can manage assigned project.
- Site manager can view assigned site but cannot manage unrelated sites.

### 10.3 Required Inertia tests

- `/projects` returns the expected component and only visible projects.
- Project show has tabs/props for overview, sites, activities and access.
- Active/inactive tabs do not mix archived records.
- User assignment props do not include cross-tenant users.

### 10.4 Required audit tests

- Project create/update/archive audited.
- Site create/update/archive audited.
- Project user assignment audited.
- Site user assignment audited.
- Activity create/update/status audited.

---

## 11. Acceptance criteria

Phase 2A is complete only when:

- One Uganda pilot project can be configured in the UI.
- The project has at least two sites.
- The project has BOQ/activity items.
- A project manager can see and manage the assigned project.
- A site manager can see only the assigned site.
- A South Sudan user cannot see Uganda projects/sites.
- A director can see tenant-wide projects/sites through permissions, not hardcoded role names.
- Archived/inactive records are moved out of active lists.
- Server policies return 403 for unauthorised actions.
- Audit trail records project/site/activity/assignment changes.
- Seeded data demonstrates realistic separation.
- `composer test` passes after local formatting/linting.

---

## 12. Implementation checklist

1. Inspect existing Phase 1 patterns again.
2. Add permissions to `RolePermissionSeeder`.
3. Add migrations.
4. Add models and factories.
5. Add policies.
6. Add actions.
7. Add form requests.
8. Add controllers and routes.
9. Add seed data.
10. Add React pages/components.
11. Add tests for tenant/branch/project/site isolation.
12. Add audit tests.
13. Run local commands.
14. Fix strictness/lint/test failures.
15. Update implementation notes.

---

## 13. Commands for local verification

Use the project's normal commands:

```bash
composer test
bun run test:types
bun run test:lint
```

If you are refreshing the local database for UI testing:

```bash
php artisan migrate:fresh --seed
```

---

## 14. Required input before implementation

Please confirm these before or during Phase 2A:

1. What exact Uganda pilot project should be seeded?
2. Should the Busunju - Kiboga - Hoima Road data be used as demo seed data, or should it remain only as reference?
3. What should we call the first two pilot sites?
4. Should `Customer` be the client/employer only, or should contractors/subcontractors also be stored as customers for now?
5. Are BOQ rates visible to site managers, or only project/commercial/director roles?
6. Should project managers be allowed to assign site users, or only administrators/directors?
7. Should completed projects go into the inactive tab or a separate closed/archive tab?

Recommended default if you want me to proceed without more discussion:

- Use the road project as demo seed data.
- Treat customers as clients/employers only in Phase 2A.
- Hide BOQ rates from site managers.
- Allow project managers to propose site assignments but only administrators/directors manage assignments in Phase 2A.
- Use active and inactive/archive tabs, with closed/archived outside active lists.
