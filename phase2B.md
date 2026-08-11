# Construction ERP - Phase 2B Detailed Implementation Plan

## Implementation Status

Implemented in PointERP:

- Central document register, document types, versions and polymorphic links.
- Private file uploads, downloads and version history.
- Context upload from project, site and daily site report pages.
- Document metadata for document number, revision, discipline, issuer and received date.
- Expiry filtering and summary counts for permits/controlled records.
- Confidential document permission checks.
- Tenant, branch and linked-record access checks.
- Audit events for create, update, upload/version, link, unlink, archive, supersession and download.
- Demo road-project documents, including a superseded drawing and a revised drawing.
- Feature tests for document visibility, confidential downloads and drawing supersession.

Deferred deliberately to later phases:

- Full document approval/transmittal workflow.
- PDF/CAD preview, OCR, e-signatures and external client/subcontractor portals.
- Advanced drawing comparison and markup.

## 1. Purpose

Phase 2B builds the document control and evidence foundation for PointERP.

Phase 2A created the operational hierarchy:

```text
Tenant -> Branch -> Customer/Contract -> Project -> Site -> Activity/BOQ -> DSR
```

Phase 2B adds the controlled records that prove and support that work:

- Contracts and contract addenda.
- Drawings and revised drawings.
- Method statements.
- Permits and approvals.
- Test results and inspection records.
- DSR photos and field evidence.
- Instructions, RFIs/RFAs and correspondence.
- HSE, environmental and social evidence.

This is the right next slice because construction work without evidence becomes hard to defend commercially and technically. The daily site report says what happened; documents and evidence prove it.

---

## 2. Goal

At the end of Phase 2B, Point Investment should be able to upload, classify, version, search, link and permission-check project documents.

The first pilot project should support:

1. Uploading a contract document.
2. Uploading drawings and new drawing revisions.
3. Uploading permits with expiry dates.
4. Uploading DSR evidence/photos and linking them to a DSR.
5. Linking one document to a project, site, contract or DSR.
6. Keeping document versions instead of replacing files silently.
7. Restricting confidential documents.
8. Auditing uploads, version changes, metadata changes, confidentiality changes and archive actions.
9. Showing expiring or expired documents in a way that later dashboards can consume.

---

## 3. Architectural Position

Documents must be a shared operational service, not a feature hidden inside one module.

Do not add separate upload tables like:

```text
project_files
site_files
contract_files
dsr_photos
hse_files
```

That design scatters permissions, search, versioning and expiry logic. Instead, use a central document model with polymorphic links:

```text
document_types
documents
document_versions
document_links
```

Then link documents to business records:

```text
Contract -> Document
Project -> Document
Site -> Document
DailySiteReport -> Document
Future HSE incident -> Document
Future IPC -> Document
Future equipment transfer -> Document
```

This lets the system answer construction questions later:

- Which drawings were current when this DSR was submitted?
- Which photos support this measured quantity?
- Which permit was expired during this work?
- Which document version did the project manager approve?
- Which evidence supports an IPC line?

---

## 4. Civil Engineering Domain Reasoning

In civil engineering, documents are not just attachments. They are controlled project records.

A road project typically depends on:

- Contract documents and amendments.
- Approved drawings.
- Revised drawings and superseded drawings.
- Method statements.
- Inspection and test plans.
- Material test results.
- Site instructions.
- RFIs, RFAs and responses.
- Environmental and social approvals.
- Traffic management plans.
- Permits and statutory approvals.
- Photos, sketches and chainage-based measurement evidence.

For the Busunju - Kiboga - Hoima demo project, document control must support chainage-aware field evidence. A photo or sketch should eventually be linkable to:

```text
Project: BKH-ROAD
Site: Busunju Section
DSR: DSR-BUSUNJU-20241207
BOQ item: 31.01(b)(i)
Chainage: Km 10+000 - Km 11+500
Side: LHS
```

Phase 2B does not need a full drawing viewer or map/plan markers yet. But it must store clean document metadata and version history so that later drawing markup, measurement books and IPCs can trust the source.

---

## 5. Scope

### 5.1 In Scope

- Document type CRUD or seeded/default document types.
- Document records.
- Document versions.
- Document links to contract/project/site/DSR.
- Uploading files to private storage.
- Download/view authorization.
- Version history.
- Archive/restore status behaviour.
- Expiry date support for permits, insurance, licences and approvals.
- Confidentiality controls.
- Search and filters.
- UI pages for documents and document detail.
- Upload UI with progress/error states.
- Seeded documents for the demo project.
- Policies, form requests, actions, factories and tests.
- Audit trail events.

### 5.2 Out of Scope

- Full drawing viewer.
- CAD/BIM parsing.
- OCR.
- E-signatures.
- Public client portal.
- External subcontractor portal.
- Advanced document transmittals.
- Approval workflow for every document type.
- Automatic PDF previews.
- Offline upload queue.
- Version diffing.
- Cloud antivirus integration.

Phase 2B should prepare for those features without pretending to complete them.

---

## 6. Terminology

| Term | Meaning |
|---|---|
| Document Type | Classification such as Contract, Drawing, Permit, DSR Evidence, Test Result or Method Statement. |
| Document | The controlled business record users search and link. |
| Document Version | The actual uploaded file version, including disk path, checksum and version number. |
| Document Link | A relationship between a document and a business record such as a project, site, contract or DSR. |
| Current Version | Latest non-archived version used for normal viewing. |
| Superseded Version | Older version kept for history and audit. |
| Confidential Document | A document requiring special permission beyond ordinary project/site access. |
| Expiring Document | A document with `expires_on` nearing or past due. |
| Evidence | Field proof such as photos, sketches, delivery notes or test certificates linked to a DSR or work item. |

---

## 7. Permissions

Add permissions idempotently to `RolePermissionSeeder`.

```text
documents.view
documents.upload
documents.update
documents.archive
documents.view-confidential
documents.manage-types
documents.download
documents.link
documents.unlink
documents.version
documents.expiry.view
```

Suggested role behaviour:

- Director: all document permissions, including confidential documents.
- Administrator: manage document metadata/types and ordinary documents.
- Project Manager: view/upload/update/version/link project/site/DSR documents for assigned projects.
- Site Manager / Engineer: upload DSR evidence and view non-confidential documents for assigned sites.
- Auditor: view/download non-confidential documents and audit trail; confidential only if explicitly granted.
- Store Keeper: no document access unless later inventory documents require it.

Do not check role names in policies. Use permissions plus tenant, branch, project and site assignment.

---

## 8. Database Design

Use Artisan to create each migration separately.

Do not write one large migration.

### 8.1 `document_types`

```text
id                    uuid primary key
tenant_id             uuid nullable foreign key
name                  string
code                  string
description           text nullable
requires_expiry_date  boolean default false
is_confidential       boolean default false
is_system             boolean default false
is_active             boolean default true
created_by            uuid nullable foreign key users
updated_by            uuid nullable foreign key users
created_at
updated_at
deleted_at
```

Rules:

- `tenant_id` nullable means global/system document type.
- Tenant-specific type can override/add local classifications.
- `code` unique per tenant, with global codes seeded first.
- Inactive types appear in inactive tab, not mixed into active list.

Suggested seeded types:

```text
CONTRACT
CONTRACT_ADDENDUM
DRAWING
REVISED_DRAWING
METHOD_STATEMENT
PERMIT
TEST_RESULT
INSPECTION_RECORD
SITE_INSTRUCTION
RFI
RFA
DSR_EVIDENCE
PHOTO
SKETCH
HSE_RECORD
ENVIRONMENT_RECORD
SOCIAL_RECORD
IPC_SUPPORT
CORRESPONDENCE
```

### 8.2 `documents`

```text
id                  uuid primary key
tenant_id           uuid foreign key
branch_id           uuid nullable foreign key
document_type_id    uuid foreign key
owner_id            uuid nullable foreign key users
title               string
reference           string nullable
description         text nullable
document_date       date nullable
expires_on          date nullable
confidentiality     string default normal
status              string default active
current_version_id  uuid nullable
created_by          uuid nullable foreign key users
updated_by          uuid nullable foreign key users
created_at
updated_at
deleted_at
```

Statuses:

```text
active
superseded
expired
archived
```

Confidentiality:

```text
normal
restricted
confidential
commercial
```

Rules:

- Document belongs to a tenant.
- Branch can be nullable for tenant-wide documents.
- Project/site-specific visibility comes from `document_links`.
- Confidentiality affects view/download permission.
- A document may have no current version only during a failed/incomplete upload transaction; avoid persisting that state.
- Expired documents are not automatically archived. Expiry is an exception state, not deletion.

### 8.3 `document_versions`

```text
id              uuid primary key
tenant_id       uuid foreign key
document_id     uuid foreign key
version_number  unsigned integer
disk            string
path            string
original_name   string
mime_type       string
size_bytes      unsigned big integer
checksum        string nullable
notes           text nullable
uploaded_by     uuid foreign key users
uploaded_at     timestamp
created_at
updated_at
```

Rules:

- Version numbers increment per document.
- Never overwrite an existing version file.
- Compute checksum where possible.
- Store private files under tenant/project-aware paths, for example:

```text
documents/{tenant_id}/{document_id}/v{version_number}/{uuid-filename}
```

- `current_version_id` on `documents` points to the latest current version.
- Old versions remain downloadable to authorised users.

### 8.4 `document_links`

```text
id             uuid primary key
tenant_id      uuid foreign key
document_id    uuid foreign key
linkable_type  string
linkable_id    uuid
created_by     uuid nullable foreign key users
created_at
updated_at
```

Allowed initial linkable models:

```text
App\Models\Contract
App\Models\Project
App\Models\Site
App\Models\DailySiteReport
```

Rules:

- Link target must belong to the same tenant.
- If link target has branch, branch must match or be authorised.
- A document can link to multiple records.
- Avoid duplicate links by unique index:

```text
document_id + linkable_type + linkable_id
```

Use short explicit index names because MySQL identifier length can fail on long table/column names.

---

## 9. Storage Design

Use Laravel storage with a private disk by default.

Recommended first implementation:

```text
disk: local/private
root: storage/app/private
download: controller streamed response after policy check
```

Do not expose document files through public URLs in Phase 2B.

Minimum file rules:

- Allow common pilot formats:

```text
pdf
doc
docx
xls
xlsx
csv
jpg
jpeg
png
webp
txt
dwg optional later
```

- Use max file size from config, default 20 MB for now.
- Validate MIME type and extension.
- Generate server-side storage name.
- Keep original filename for display only.

Future-ready design:

- Storage disk can move to S3 later without changing document/version schema.
- Antivirus scan status can be added later to `document_versions`.
- Preview generation can be added later without changing core metadata.

---

## 10. Backend Components

### 10.1 Models

Create:

```text
App\Models\DocumentType
App\Models\Document
App\Models\DocumentVersion
App\Models\DocumentLink
```

Model requirements:

- UUID primary keys.
- `BelongsToTenant` where appropriate.
- `SoftDeletes` for document types/documents.
- Relationships:

```text
Document belongsTo DocumentType
Document belongsTo Branch nullable
Document belongsTo Owner User nullable
Document hasMany DocumentVersion
Document belongsTo currentVersion
Document hasMany DocumentLink
DocumentVersion belongsTo Document
DocumentLink morphTo linkable
```

Scopes:

```text
active()
archived()
expired()
expiringSoon(int $days = 30)
visibleTo(User $user)
linkedTo(Model $model)
```

### 10.2 Policies

Create:

```text
DocumentTypePolicy
DocumentPolicy
DocumentVersionPolicy if needed
DocumentLinkPolicy if needed
```

DocumentPolicy must check:

1. Same tenant.
2. Branch access.
3. Document permission.
4. Link target access where linked.
5. Confidentiality.
6. Status.

Policy rules:

- `view`: user has `documents.view`, same tenant, branch/link access, confidentiality allowed.
- `download`: same as view plus `documents.download`.
- `upload`: user has `documents.upload` and can access at least one selected link target.
- `update`: user has `documents.update`, same tenant, not archived.
- `archive`: user has `documents.archive`.
- `viewConfidential`: user has `documents.view-confidential`.
- `version`: user has `documents.version` and can update the document.
- `link`: user has `documents.link` and can access both document and target.
- `unlink`: user has `documents.unlink` and can access both document and target.

Important:

- Hiding a download button is not enough. Unauthorized download routes must return 403.
- A user assigned only to one site can upload DSR evidence for that site but cannot browse confidential contract documents unless permission allows it.
- A project manager can see project evidence for assigned project scope.
- `documents.view-confidential` does not cross tenant boundaries.

### 10.3 Actions

Create focused actions:

```text
CreateDocument
UpdateDocument
ArchiveDocument
UploadDocumentVersion
LinkDocumentToRecord
UnlinkDocumentFromRecord
ToggleDocumentTypeStatus
UpdateDocumentType
```

Action rules:

- Use `TenantContext`.
- Use transactions for document + version + links.
- Store file only after validation.
- If DB transaction fails after storage write, delete the newly stored file.
- Audit every controlled change.
- Never overwrite a file path.
- Do not silently delete old versions.

Audit events:

```text
operations.document.created
operations.document.updated
operations.document.archived
operations.document.version_uploaded
operations.document.linked
operations.document.unlinked
operations.document_type.created
operations.document_type.updated
operations.document_type.status_changed
```

### 10.4 Requests

Create request classes under:

```text
App\Http\Requests\Operations\Documents
App\Http\Requests\Operations\DocumentTypes
```

Validation:

- `document_type_id` exists in same tenant or global.
- `branch_id` exists in same tenant and accessible branch.
- `expires_on` required when selected type requires expiry.
- `confidentiality` in allowed values.
- `links` array only supports allowed linkable types.
- Each link target must exist in current tenant.
- File required for create/version upload.
- File max size controlled through config.

Do not use unscoped `exists`.

### 10.5 Controllers

Use cruddy controllers and single-action controllers where needed:

```text
DocumentController
DocumentVersionController
DocumentDownloadController
DocumentLinkController
DocumentTypeController
```

Routes:

```text
GET    /documents
POST   /documents
GET    /documents/{document}
PUT    /documents/{document}
DELETE /documents/{document}
POST   /documents/{document}/versions
GET    /documents/{document}/versions/{documentVersion}/download
POST   /documents/{document}/links
DELETE /documents/{document}/links/{documentLink}
GET    /document-types
POST   /document-types
PUT    /document-types/{documentType}
DELETE /document-types/{documentType}
```

Route names:

```text
documents.index
documents.store
documents.show
documents.update
documents.destroy
documents.versions.store
documents.versions.download
documents.links.store
documents.links.destroy
document-types.index
document-types.store
document-types.update
document-types.destroy
```

---

## 11. UI Plan

Follow the professional page pattern already agreed:

- Heading and description at the top.
- Search/filter controls under heading on the left.
- Active/archive or active/inactive tab switcher on the same horizontal line at the extreme right.
- Add button at the extreme right of the filter row.
- Use modals for compact metadata edits.
- Use full page for document detail/version history.
- Use global confirmation modal for archive/unlink.
- Use Sonner toasts.
- Use combobox for long dropdowns.

### 11.1 `/documents`

Purpose:

Central document register.

Controls:

- Search by title, reference, original filename, type, project, site, contract.
- Filter by document type.
- Filter by project.
- Filter by site.
- Filter by confidentiality.
- Filter by expiry state:

```text
all
expiring
expired
no-expiry
```

Tabs:

```text
Active
Archived
```

Table columns:

```text
Document
Type
Linked records
Current version
Expiry
Confidentiality
Status
Actions
```

### 11.2 Create/Upload Document Modal

Use modal for first upload metadata, but keep it roomy:

- Title.
- Reference.
- Document type.
- Document date.
- Expiry date.
- Confidentiality.
- Description.
- Branch.
- Links:
  - Contract.
  - Project.
  - Site.
  - DSR.
- File upload.

If file upload progress UI becomes cramped, move create flow to a full page.

### 11.3 `/documents/{document}`

Purpose:

Document detail and version history.

Sections:

- Document header: title, type, status, confidentiality, expiry.
- Current version download/open action.
- Linked records.
- Version history.
- Upload new version.
- Metadata edit.
- Audit summary or link to audit trail filtered by subject.

Version table:

```text
Version
Original file
Uploaded by
Uploaded at
Size
Checksum
Notes
Download
```

### 11.4 Project And Site Integration

Add Documents tabs/sections to:

```text
/projects/{project}
/sites/{site}
/daily-site-reports/{dailySiteReport}
/contracts
```

Phase 2B minimum:

- Project show page includes a Documents tab listing linked documents.
- Site show page includes linked documents.
- DSR show page includes Evidence/Documents section.
- Contract row/detail links to contract documents.

Avoid building a full duplicate document manager inside each record. Use linked filtered views or lightweight embedded lists that point back to `/documents/{document}`.

---

## 12. Construction Document Types And Required Metadata

### 12.1 Drawings

Recommended metadata:

```text
reference
title
revision
document_date
discipline optional
status active/superseded/archived
linked project/site
```

Notes:

- A revised drawing should upload as a new version when it is the same drawing reference.
- A different drawing number should be a separate document.
- Later, drawing register features can add drawing number, revision code, package and discipline fields.

### 12.2 Permits

Required:

```text
expires_on
issuing_authority in description for now
linked project/site
```

Expiry should surface in filters and later dashboards.

### 12.3 DSR Evidence

Recommended metadata:

```text
document_date = report date
linked DSR
linked site
description can include chainage/side
```

Later enhancement:

- Link evidence directly to DSR work line.
- Add GPS/photo timestamp extraction.
- Add sketch/photo marker annotations.

### 12.4 Test Results

Recommended metadata:

```text
test type in title or type-specific metadata later
linked project/site
document_date
reference
```

Later enhancement:

- Material sample IDs.
- Chainage/location.
- Pass/fail.
- Lab certificate fields.

---

## 13. Seed Data

Extend `PointInvestmentSeeder`.

Seed document types:

```text
CONTRACT
DRAWING
PERMIT
METHOD_STATEMENT
DSR_EVIDENCE
PHOTO
TEST_RESULT
SITE_INSTRUCTION
RFI
RFA
IPC_SUPPORT
```

Seed demo documents for the Uganda road project:

```text
Contract:
- UNRA/WORKS/2021-2022/00369 signed contract

Drawing:
- BKH-ROAD-GA-001 General alignment drawing
- v1 original issue
- v2 revised issue

Permit:
- Traffic management permit
- expires_on within 30 days to test expiry filter

Method Statement:
- Topsoil removal method statement

DSR Evidence:
- Photo evidence linked to DSR-BUSUNJU-20241207
- Sketch/measurement support linked to DSR-BUSUNJU-20241207

Test Result:
- Subbase material test certificate linked to Kiboga-Hoima Section
```

Seed South Sudan branch with at least one ordinary document to prove branch isolation.

Important:

- Seeded files can be small text/PDF-placeholder files generated under storage for local demo.
- Do not seed every document as confidential.
- Include one confidential commercial document for permission testing.

---

## 14. Tests

### 14.1 Migration/Model Tests

- Document type uses UUID.
- Document has current version.
- Document version increments per document.
- Document links morph correctly.
- Soft-deleted/archived documents are not shown in active tab.

### 14.2 Policy Tests

- User with no `documents.view` receives 403.
- Site manager can view non-confidential evidence linked to their assigned site.
- Site manager cannot view confidential contract document.
- Project manager can view documents linked to assigned project.
- Auditor can view non-confidential evidence but not confidential documents unless granted.
- South Sudan user cannot view Uganda documents.
- Director with cross-branch permissions can view tenant documents.
- Download route returns 403 if view/download denied.

### 14.3 Upload/Version Tests

- Upload creates document and first version.
- Uploading a new version increments version number.
- Old version remains available.
- File path is not overwritten.
- Current version points to latest version.
- Failed validation does not store file.
- Failed DB transaction removes newly stored file.

### 14.4 Link Tests

- Document can link to project.
- Document can link to site.
- Document can link to contract.
- Document can link to DSR.
- Cross-tenant link is rejected.
- Duplicate link is rejected or idempotently ignored.
- Unlink action is audited.

### 14.5 Expiry Tests

- Permit with future expiry appears active.
- Permit expiring within configured days appears in expiring filter.
- Expired permit appears in expired filter.
- Expiry state does not bypass normal view permission.

### 14.6 Inertia/UI Tests

- `/documents` returns expected component and visible records only.
- Search filters by title/reference/type.
- Active/archive tabs do not mix archived records.
- Confidentiality flag is present only where authorised.
- Document detail includes version history.
- Project show document tab only includes linked authorised documents.

### 14.7 Audit Tests

- Document create audited.
- Version upload audited.
- Metadata update audited.
- Archive audited.
- Link/unlink audited.
- Confidentiality change audited.

---

## 15. Acceptance Criteria

Phase 2B is complete only when:

- Documents can be uploaded from the UI.
- Document files are stored privately.
- Users cannot download unauthorised files.
- Documents have types, metadata, status and confidentiality.
- Documents can link to project, site, contract and DSR.
- A document can have multiple versions.
- New upload creates a new version, not file replacement.
- Version history is visible to authorised users.
- Confidential documents require `documents.view-confidential`.
- Expiring/expired documents can be filtered.
- Project/site/DSR screens show linked evidence.
- Seed data demonstrates ordinary, confidential, expired/expiring and versioned documents.
- Audit trail records document create/update/archive/version/link events.
- Tests prove tenant, branch, project/site and confidentiality isolation.
- Local `composer test` and frontend checks pass after formatting.

---

## 16. Implementation Order

1. Confirm Phase 2A migrations and tests pass locally.
2. Generate document migrations with Artisan, one migration per table.
3. Add document permissions to `RolePermissionSeeder`.
4. Add document models and relationships.
5. Add policies.
6. Add actions for create/update/archive/upload/link/unlink.
7. Add form requests.
8. Add controllers and routes.
9. Add `/documents` index UI.
10. Add upload/create document modal.
11. Add `/documents/{document}` detail/version page.
12. Add project/site/DSR linked document sections.
13. Add seeded document types and demo documents.
14. Add factories.
15. Add feature/policy/Inertia/audit tests.
16. User runs local migration/test/format commands.
17. Fix issues from local output.

---

## 17. Local Commands

Use Artisan to generate migrations before editing them:

```powershell
php artisan make:migration create_document_types_table
php artisan make:migration create_documents_table
php artisan make:migration create_document_versions_table
php artisan make:migration create_document_links_table
```

After implementation:

```powershell
php artisan migrate:fresh --seed
php vendor/bin/pest tests/Feature/Operations/PhaseTwoBDocumentControlTest.php --compact
composer test
bun run test:types
bun run test:lint
```

Formatting when ready:

```powershell
vendor/bin/pint --dirty
bun run test:format
```

---

## 18. Open Decisions

These should be confirmed before implementation, but safe defaults are included.

1. Maximum file size.
   Recommended default: 20 MB.

2. Allowed file types.
   Recommended default: PDF, Office docs, images and text/CSV.

3. Should DWG/CAD files be allowed immediately?
   Recommended default: not in first pass unless Point Investment needs drawing file storage now.

4. Should site engineers upload DSR evidence directly?
   Recommended default: yes, only for assigned sites/DSRs.

5. Should project managers upload confidential commercial documents?
   Recommended default: no unless they have `documents.view-confidential`.

6. Should archived documents remain downloadable?
   Recommended default: yes for authorised users, because archive is not deletion.

7. Should expired permits block DSR approval?
   Recommended default: not in Phase 2B. Show exception only; enforce blocking later after management agrees.

8. Should each document require a link?
   Recommended default: yes, at least one link for operational documents, except tenant-wide policies/templates.

9. Should document references be unique?
   Recommended default: not globally. Same reference may exist in different branches/projects. Enforce uniqueness only where business rules become clear.

---

## 19. Senior Architect Recommendation

Build Phase 2B before deepening DSR workflow further.

Reason:

DSRs without evidence are useful for reporting, but weak for construction control. Once documents exist, every later DSR, approval, return, missing-report dashboard, IPC quantity and dispute trail can point to controlled evidence.

The first implementation should be boring and reliable:

- Private file storage.
- Clean metadata.
- Version history.
- Strong policies.
- Good search.
- Clear links.
- Audit trail.

Do not spend Phase 2B on flashy previews. The critical business value is trust: knowing who uploaded what, which version was current, who could see it, what it was linked to, and whether it had expired.
