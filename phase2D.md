# Construction ERP - Phase 2D Detailed Implementation Plan

## 1. Status

Implementation completed on 15 August 2026. Local migration, automated-test, static-analysis and UI acceptance runs remain required before production release.

Delivered in this implementation:

- Tenant, project and site reporting calendars with dated exceptions and precedence resolution.
- Idempotent expected-DSR generation, overdue detection, first reminder and repeated-miss escalation commands.
- DSR workflow and document-expiry notifications with an in-app centre and header unread bell.
- Optional immediate, daily-digest and weekly-digest email delivery with attempt/failure tracking.
- Role- and scope-aware operations control dashboard, source drill-down, filters and CSV export.
- Audited reporting-obligation excuse workflow with a mandatory reason.
- Permissions, realistic demo data and focused Phase 2D feature tests.

Phase 2A established projects, sites, assignments and BOQ activities. Phase 2B established controlled documents and evidence. Phase 2C established structured Daily Site Reports, expected-report records, approval/return/correction workflow and initial dashboard summaries.

Phase 2D closes Phase 2 by turning those records into a dependable management-control loop: expected work calendars, automated exceptions, notifications, escalation, drill-down reporting and exports.

## 2. Objective

At the end of Phase 2D, Point Investment should not need someone to manually inspect every site to discover that information is missing.

The system must:

1. Know whether each active site is expected to report on a date.
2. Generate one expected DSR obligation for each reporting day.
3. Detect overdue draft or absent reports.
4. Notify the accountable site users once without duplicate alerts.
5. Escalate consecutive misses to project management and authorised executives.
6. Notify users about submitted, returned, approved and corrected DSRs.
7. Alert document owners and control roles before expiry.
8. Give users an in-app notification centre with unread state and source links.
9. Give authorised managers a role- and scope-aware exception dashboard.
10. Export filtered exception data for follow-up and management meetings.

## 3. SRS Coverage

Primary requirements:

```text
PRJ-005 Expected DSRs for every active reporting day.
PRJ-006 Missing status, owner notification and exception dashboard.
PRJ-007 Configurable consecutive-miss escalation.
PRJ-008 Timeliness, completeness, approvals and evidence dashboards.
COM-003 In-app notification centre.
COM-004 Configured email delivery with safe record links.
COM-005 Non-critical preferences; critical alerts cannot be disabled.
RPT-001 Role/scope-aware dashboards with data freshness.
RPT-003 Expected record status by site.
RPT-004 Drill-down from portfolio to source record.
RPT-005 Filtered CSV/Excel and printable exports.
RPT-006 Scheduled role-aware digests.
DOC-007 Document expiry alerts.
SEC-003 to SEC-006 Scope, audit and controlled corrections.
NFR-002 Low-bandwidth lists and payloads.
NFR-006 Auditability.
NFR-010 Controlled data and exception reporting.
NFR-011 Accessible navigation and forms.
```

## 4. Scope

### 4.1 In Scope

- Tenant reporting calendar defaults.
- Optional project and site calendar overrides.
- Working weekdays and dated working/non-working exceptions.
- Reporting timezone, deadline and consecutive-miss threshold.
- Expected DSR generation using calendar precedence.
- Missing detection and draft-late handling.
- Accountable owner reminders.
- Consecutive-miss escalation.
- DSR workflow notifications.
- Document expiry notifications.
- In-app notification centre, unread count, read/unread and mark-all-read.
- Optional queued email delivery with attempt status.
- User preferences for non-critical email categories.
- Exception dashboard, filters, drill-down and CSV export.
- Audit events for calendar/configuration and automated exception processing.
- Realistic seed data and focused feature tests.

### 4.2 Out of Scope

- SMS and WhatsApp delivery.
- A full direct-message/chat product.
- Push notifications and native mobile applications.
- Custom report-builder UI.
- Automated board-pack generation.
- Equipment, inventory, HR, finance and HSE-specific alert engines.
- Production mail-provider credentials.

The notification foundation must be reusable by those later modules.

## 5. Domain Decisions

### 5.1 Calendar Precedence

Resolve reporting rules in this order:

```text
site calendar -> project calendar -> tenant calendar -> system default
```

The first active match supplies:

- Timezone.
- Reporting deadline.
- Working weekdays.
- Consecutive missing-day escalation threshold.

A dated exception on the selected calendar overrides the normal weekday rule.

### 5.2 Default Rules

Development defaults:

- Timezone: tenant timezone, otherwise `Africa/Kampala`.
- Working days: Monday to Saturday.
- Non-working day: Sunday.
- DSR deadline: 18:00 local time.
- First reminder: when the deadline passes.
- Escalation: two consecutive expected reporting days missed.

These are configurable and must not remain scattered through commands/controllers.

### 5.3 Exception Semantics

- `expected`: obligation exists and deadline has not passed.
- `submitted`: submitted on or before deadline.
- `late`: submitted after deadline, or a draft is overdue but can still be completed.
- `missing`: deadline passed without a submitted report.
- `excused`: authorised user recorded a non-reporting reason.

`missing` is not the same as zero work. A no-work day should be an excused obligation or a submitted DSR explaining the condition.

### 5.4 Notification Semantics

Notifications are immutable event records. Reading a notification only updates `read_at`; it does not alter the source business record.

Categories:

```text
dsr_assignment
dsr_expected
dsr_missing
dsr_escalation
dsr_submitted
dsr_returned
dsr_approved
dsr_correction
document_expiry
approval_pending
```

Severities:

```text
info
success
warning
critical
```

Critical notifications cannot be disabled. Missing-report escalation and critical future compliance/safety events are critical.

## 6. Data Model

Create each migration separately with Artisan.

### 6.1 `reporting_calendars`

```text
id uuid primary
tenant_id uuid indexed
branch_id nullable uuid indexed
project_id nullable uuid indexed
site_id nullable uuid indexed
name string
timezone string
reporting_deadline time
working_days json
missing_escalation_days unsigned tiny integer default 2
is_active boolean default true
created_by uuid
updated_by nullable uuid
timestamps
soft deletes
```

Rules:

- A site calendar must belong to the same tenant/project/branch as the site.
- A project calendar must belong to the same tenant/branch as the project.
- Tenant default has no project or site.
- Only one active calendar is allowed for the same scope.
- Used calendars are deactivated/archived rather than destructively deleted.

### 6.2 `reporting_calendar_exceptions`

```text
id uuid primary
tenant_id uuid indexed
branch_id nullable uuid indexed
reporting_calendar_id uuid indexed
exception_date date
type non_working/working_override
name string
reason nullable text
created_by uuid
updated_by nullable uuid
timestamps
unique reporting_calendar_id + exception_date
```

Examples: public holiday, rain shutdown, client stoppage, planned Sunday work.

### 6.3 `notifications`

Use Laravel's database-notification structure with UUID-compatible notifiable columns:

```text
id uuid primary
type string
notifiable_type string
notifiable_id uuid
data text/json
read_at nullable timestamp
timestamps
```

The data payload must contain tenant, optional branch/project/site, category, severity, title, message and safe action URL.

### 6.4 `notification_deliveries`

```text
id uuid primary
tenant_id uuid indexed
notification_id uuid indexed
user_id uuid indexed
channel email
status pending/sent/failed
attempts unsigned integer default 0
last_error nullable text
attempted_at nullable timestamp
sent_at nullable timestamp
timestamps
```

This records delivery state separately from the authoritative in-app notification.

### 6.5 `notification_preferences`

```text
id uuid primary
tenant_id uuid indexed
user_id uuid unique
email_enabled boolean default true
muted_email_categories json nullable
digest_frequency immediate/daily/weekly default immediate
timestamps
```

Critical categories ignore the muted list.

## 7. Models and Services

### Models

```text
ReportingCalendar
ReportingCalendarException
NotificationDelivery
NotificationPreference
```

Use Laravel's `DatabaseNotification` for in-app notification rows.

### Services and Actions

```text
ReportingCalendarResolver
GenerateExpectedDailySiteReports
ProcessOverdueDailySiteReports
ResolveDailySiteReportRecipients
SendOperationalNotification
QueueOperationalNotificationEmail
NotifyExpiringDocuments
BuildDsrExceptionSummary
ExportDsrExceptions
```

Business logic belongs in actions/services. Controllers should only authorise, validate, invoke and respond.

## 8. Commands and Scheduling

Commands:

```text
php artisan dsr:generate-expected --date= --from= --to= --site= --tenant=
php artisan dsr:process-overdue --date= --site= --tenant=
php artisan documents:notify-expiring --date= --tenant=
```

Scheduling defaults:

- Generate expected reports daily shortly after midnight in the application's scheduler timezone, resolving tenant/site timezone when computing deadlines.
- Process overdue reports hourly.
- Check document expiry daily.
- Queue workers send configured email notifications.

Commands must be idempotent. Re-running them must not duplicate obligations, notifications or escalations.

## 9. Recipient Rules

### Expected/Missing DSR Owner

- Site manager.
- Assigned site users with `can_submit_dsr = true`.

### DSR Submitted

- Assigned site reviewers with `can_review_dsr = true`.
- Project manager.
- Project users with `can_manage = true` where appropriate.

### DSR Returned/Approved/Correction Decision

- Submitter/requester.
- Site manager where different.

### Consecutive Missing Escalation

- Project manager.
- Project users with management assignment.
- Authorised directors/executives with dashboard permission and project/branch access.

### Document Expiry

- Document owner/uploader.
- Project manager for project-linked documents.
- Authorised document-control users in scope.

All recipient queries must enforce tenant and active-user status and remove duplicates.

## 10. Permissions and Policies

Add idempotent permissions:

```text
reporting-calendars.view
reporting-calendars.manage
notifications.view
notifications.manage-preferences
operations-dashboard.view
operations-dashboard.export
expected-daily-reports.excuse
```

Policy checks:

- Same tenant.
- Branch/project/site access for scoped calendar and exception records.
- Explicit permission.
- Active state where required.
- A user may only read or update their own notification/read state.
- Dashboard and exports include only records the user can view directly.

## 11. In-App Notification Centre

Route: `/notifications`.

Header behaviour:

- Bell button in the application header.
- Unread count badge.
- Compact popover with latest unread notifications.
- `View all` link.

Page behaviour:

- Tabs: Unread and All.
- Search and category/severity filters under the heading.
- Mark one as read/unread.
- Mark all visible notifications as read.
- Clicking a notification marks it read and opens its authorised source URL.
- Empty state and pagination-ready list layout.
- Preferences modal for non-critical email categories.

Do not place notifications in the sidebar as a full module; the bell is the primary entry point.

## 12. Operations Exception Dashboard

Route: `/operations-dashboard`.

Filters:

- Date range.
- Project.
- Site.
- Branch when applicable.
- Exception type/status.

Summary:

- Expected reports.
- On-time submitted.
- Late submitted.
- Missing.
- Returned.
- Pending approval.
- Missing evidence.
- Compliance percentage.
- Last processed/refreshed timestamp.

Views:

- `Needs attention`: missing, late, returned, pending approval and missing evidence.
- `Reporting calendar`: expected obligations by date/site.
- `Completed`: submitted/approved obligations.

Every row must link to the DSR or site. The dashboard must distinguish missing data from zero activity.

## 13. Exports

Provide CSV first because it is reliable and low bandwidth.

Export fields:

```text
tenant
branch
project
site
report date
deadline
expected status
DSR reference/status
submitted at
owner/site manager
first notified at
escalated at
exception age
generated at
generated by
filters
```

The export endpoint must reuse the same authorised query/filter object as the UI. It must never export records hidden from the current user.

Excel and printable PDF can follow using the same report data contract; CSV satisfies the first Phase 2D implementation.

## 14. Workflow Notifications

Integrate notifications into existing actions:

- Site/project assignment saved.
- DSR submitted.
- DSR returned.
- DSR approved.
- DSR correction requested, approved or rejected.

Notification failures must not roll back the successful business transaction. In-app creation should be attempted after commit where practical; email is queued and independently retryable.

## 15. Audit Events

Audit:

```text
operations.reporting_calendar.created
operations.reporting_calendar.updated
operations.reporting_calendar.archived
operations.reporting_calendar_exception.created
operations.reporting_calendar_exception.deleted
operations.expected_dsr.generated
operations.expected_dsr.marked_missing
operations.expected_dsr.notified
operations.expected_dsr.escalated
operations.expected_dsr.excused
operations.notification.preference_updated
operations.exception_export.generated
```

Automated events use an anonymous/system actor where no authenticated user exists and retain tenant/branch/source identifiers in properties.

## 16. Seed Data

Extend the Point Investment demo with:

- Tenant default Monday-Saturday calendar.
- One site-specific reporting deadline override.
- Public holiday/non-working exception.
- Planned working-day override.
- Expected, on-time, late, missing, escalated and excused obligations.
- Unread and read notifications for the site engineer, project manager and director.
- One pending and one sent/failed email delivery example.
- Document-expiry notification.

The seed data must visibly demonstrate that site users only see their own operational notifications while managers/directors see authorised escalations.

## 17. Tests

Create:

```text
tests/Feature/Operations/PhaseTwoDOperationalControlTest.php
```

Cover:

1. Calendar resolver uses site, project, tenant and default precedence.
2. Non-working weekdays and dated exceptions are respected.
3. Expected generation is idempotent.
4. Overdue obligation becomes missing.
5. Overdue draft is marked missing/late according to defined rule.
6. First reminder reaches submitters only once.
7. Two consecutive misses escalate once to authorised management.
8. Users cannot see another tenant's notifications.
9. Branch/site-restricted users cannot see unrelated dashboard exceptions.
10. DSR submit/return/approve creates the correct notification.
11. Mark-read and mark-all-read affect only the authenticated user.
12. Critical categories cannot be muted.
13. CSV export contains only authorised filtered records.
14. Document-expiry notification is idempotent.
15. Calendar changes are audited.

## 18. Implementation Order

1. Create this Phase 2D contract.
2. Generate each migration with Artisan.
3. Add models, relationships and policies.
4. Build calendar resolver and calendar CRUD.
5. Move expected/missing logic from route closures into actions/commands.
6. Add notification payload, sender and email-delivery job.
7. Integrate DSR workflow and document-expiry notifications.
8. Add notification centre and header bell.
9. Add exception dashboard and authorised CSV export.
10. Extend permission and demo seeders.
11. Add focused Phase 2D tests.
12. Update `PROJECT_ROADMAP.md` and this document with final status.
13. Run the focused tests and full local quality suite.

## 19. Acceptance Criteria

Phase 2D is accepted when:

- Reporting obligations follow configurable working calendars and deadlines.
- Missing reports are detected automatically and visibly separated from submitted records.
- Accountable site users receive one first reminder.
- Configured consecutive misses produce one escalation to authorised managers/executives.
- DSR submit, return, approval and correction decisions generate useful source-linked notifications.
- Users have an unread-count bell and a complete notification page.
- Non-critical email preferences work while critical alerts remain mandatory.
- Document expiry alerts are generated idempotently.
- The operations dashboard is role/scope aware and drills to source records.
- CSV exports use the same authorised filters as the dashboard.
- Automated actions and configuration changes are auditable.
- Focused tests prove tenant, branch, project/site and user isolation.
- Local PHP/backend and frontend quality checks pass.

## 20. Handoff to Phase 3

After Phase 2D, Phase 2 is functionally complete and the next architecture document should be `phase3.md`.

The first detailed Phase 3 slice should be `phase3A.md`: Equipment, Fleet and Fuel Control. It should reuse:

- Project/site scope.
- Documents and evidence.
- DSR equipment/fuel snapshots.
- Notifications and escalation.
- Audit trail.
- Dashboard drill-down and export patterns.
