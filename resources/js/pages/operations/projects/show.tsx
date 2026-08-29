import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrencyAmount,
    formatDateTime,
    formatNumber,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
} from '../documents/partials/document-dialog';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';
import {
    EquipmentScopePanel,
    type EquipmentScopeData,
} from '../equipment/partials/equipment-scope-panel';
import {
    ProjectAccessDialog,
    type AssignedProjectUser,
} from './partials/access-dialog';
import {
    ActivityDialog,
    type ProjectActivity,
} from './partials/activity-dialog';
import {
    ProjectDialog,
    type Option,
    type Project,
} from './partials/project-dialog';
import { SiteDialog, type Site } from './partials/site-dialog';

type Props = {
    project: Project;
    sites: Site[];
    activities: ProjectActivity[];
    estimates: EstimateSummary[];
    performance: ProjectPerformance | null;
    assignedUsers: AssignedProjectUser[];
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    canUploadDocuments: boolean;
    dsrSummary: Record<string, number | string | null>;
    branches: Option[];
    customers: Option[];
    contracts: Option[];
    users: Option[];
    currencies: Option[];
    canViewRates: boolean;
    canViewEstimates: boolean;
    canCreateEstimate: boolean;
    fleet: EquipmentScopeData | null;
    canViewFleet: boolean;
    canUpdateProject: boolean;
};

type EstimateSummary = {
    id: string;
    version_number: number;
    title: string;
    status: string;
    status_label: string;
    is_baseline: boolean;
    currency_code: string;
    lines_count: number;
    approved_by: string | null;
    approved_at: string | null;
};

type ProjectPerformance = {
    baseline: {
        id: string;
        title: string;
        version_number: number;
        currency_code: string;
        approved_at: string | null;
    };
    totals: {
        planned_items: number;
        baseline_revenue: string | null;
        earned_output: string | null;
        baseline_cost: string | null;
        actual_input_cost: string | null;
        operational_expenses: string | null;
    };
    work_items: Array<{
        id: string;
        work_item_id: string | null;
        boq_reference: string | null;
        name: string;
        unit: string;
        planned_quantity: string;
        approved_progress: string;
        remaining_quantity: string;
        completion_percent: string;
        baseline_revenue: string | null;
        earned_output: string | null;
        baseline_cost: string | null;
    }>;
    resources: Array<{
        inventory_item_id: string;
        name: string;
        unit: string;
        planned_quantity: string;
        expected_to_date: string;
        actual_quantity: string;
        variance_quantity: string;
    }>;
};

export default function ProjectShow({
    project,
    sites,
    activities,
    estimates,
    performance,
    assignedUsers,
    documents,
    documentTypes,
    documentBranches,
    documentLinkOptions,
    canUploadDocuments,
    dsrSummary,
    branches,
    customers,
    contracts,
    users,
    currencies,
    canViewRates,
    canViewEstimates,
    canCreateEstimate,
    fleet,
    canViewFleet,
    canUpdateProject,
}: Props) {
    const confirm = useConfirmDialog();
    const [tab, setTab] = useState('sites');
    const activeSites = sites.filter((site) =>
        ['planned', 'active', 'suspended'].includes(site.status),
    );
    const inactiveSites = sites.filter((site) =>
        ['completed', 'closed', 'archived'].includes(site.status),
    );
    const activeActivities = activities.filter(
        (activity) => activity.status === 'active',
    );
    const inactiveActivities = activities.filter(
        (activity) => activity.status === 'inactive',
    );
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.reference, href: `/projects/${project.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {project.name}
                            </h1>
                            <Badge variant="secondary">{project.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {project.reference} · {project.branch_name} ·{' '}
                            {project.manager_name ?? 'No manager'}
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <ProjectAccessDialog
                            projectId={project.id}
                            users={users}
                            assignedUsers={assignedUsers}
                        />
                        {canUpdateProject && (
                            <ProjectDialog
                                project={project}
                                branches={branches}
                                customers={customers}
                                contracts={contracts}
                                users={users}
                                currencies={currencies}
                            />
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily reporting control</CardTitle>
                        <CardDescription>
                            Current DSR exceptions and operational totals for
                            this project.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        <SummaryMetric
                            label="Pending"
                            value={dsrSummary.pending}
                        />
                        <SummaryMetric
                            label="Returned"
                            value={dsrSummary.returned}
                        />
                        <SummaryMetric
                            label="Missing"
                            value={dsrSummary.missing}
                        />
                        <SummaryMetric
                            label="Approved"
                            value={dsrSummary.approved}
                        />
                        <SummaryMetric
                            label="Output"
                            value={dsrSummary.output_value}
                        />
                        <SummaryMetric
                            label="Profit/loss"
                            value={dsrSummary.profit_loss}
                        />
                    </CardContent>
                </Card>

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList className="h-auto flex-wrap justify-start">
                        <TabsTrigger value="sites">Sites</TabsTrigger>
                        {canViewEstimates && (
                            <TabsTrigger value="estimates">
                                Estimates
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="activities">Work items</TabsTrigger>
                        {canViewEstimates && performance && (
                            <TabsTrigger value="performance">
                                Performance
                            </TabsTrigger>
                        )}
                        {canViewFleet && (
                            <TabsTrigger value="equipment">
                                Equipment
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="access">Access</TabsTrigger>
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                    </TabsList>

                    <TabsContent value="sites" className="mt-6 grid gap-6">
                        <div className="flex justify-end">
                            <SiteDialog projectId={project.id} users={users} />
                        </div>
                        <SiteTable
                            sites={activeSites}
                            users={users}
                            title="Active sites"
                            onArchive={(site) =>
                                confirm({
                                    title: 'Archive site?',
                                    description: `${site.name} will move out of active site lists.`,
                                    confirmLabel: 'Archive',
                                    onConfirm: () =>
                                        router.delete(`/sites/${site.id}`, {
                                            preserveScroll: true,
                                        }),
                                })
                            }
                        />
                        {inactiveSites.length > 0 && (
                            <SiteTable
                                sites={inactiveSites}
                                users={users}
                                title="Completed/archive sites"
                                onArchive={(site) =>
                                    router.delete(`/sites/${site.id}`, {
                                        preserveScroll: true,
                                    })
                                }
                            />
                        )}
                    </TabsContent>

                    {canViewEstimates && (
                        <TabsContent value="estimates" className="mt-6">
                            <EstimateTable
                                projectId={project.id}
                                estimates={estimates}
                                canCreate={canCreateEstimate}
                            />
                        </TabsContent>
                    )}

                    <TabsContent value="activities" className="mt-6 grid gap-6">
                        {!performance && (
                            <div className="flex justify-end">
                                <ActivityDialog
                                    projectId={project.id}
                                    sites={sites}
                                    currencies={currencies}
                                    canViewRates={canViewRates}
                                />
                            </div>
                        )}
                        <ActivityTable
                            activities={activeActivities}
                            sites={sites}
                            currencies={currencies}
                            canViewRates={canViewRates}
                            title="Active work items"
                        />
                        {inactiveActivities.length > 0 && (
                            <ActivityTable
                                activities={inactiveActivities}
                                sites={sites}
                                currencies={currencies}
                                canViewRates={canViewRates}
                                title="Inactive work items"
                            />
                        )}
                    </TabsContent>

                    {canViewEstimates && performance && (
                        <TabsContent value="performance" className="mt-6">
                            <PerformanceTable
                                performance={performance}
                                canViewCosts={
                                    performance.totals.baseline_revenue !== null
                                }
                            />
                        </TabsContent>
                    )}

                    {canViewFleet && fleet && (
                        <TabsContent value="equipment" className="mt-6">
                            <EquipmentScopePanel fleet={fleet} />
                        </TabsContent>
                    )}

                    <TabsContent value="access" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Assigned users</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-3 pr-4 font-medium">
                                                    User
                                                </th>
                                                <th className="py-3 pr-4 font-medium">
                                                    Role
                                                </th>
                                                <th className="py-3 font-medium">
                                                    Project access
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {assignedUsers.map((user) => (
                                                <tr
                                                    key={user.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-3 pr-4">
                                                        <div className="font-medium">
                                                            {user.name}
                                                        </div>
                                                        <div className="text-muted-foreground">
                                                            {user.email}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {user.role ?? 'Member'}
                                                    </td>
                                                    <td className="py-3">
                                                        <Badge variant="outline">
                                                            {user.can_manage
                                                                ? 'Can manage'
                                                                : 'View only'}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                            {assignedUsers.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        No project users
                                                        assigned.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="documents" className="mt-6">
                        <DocumentEvidenceTable
                            documents={documents}
                            emptyText="No documents linked to this project."
                            actions={
                                canUploadDocuments && (
                                    <DocumentDialog
                                        documentTypes={documentTypes}
                                        branches={documentBranches}
                                        linkOptions={documentLinkOptions}
                                        defaultBranchId={project.branch_id}
                                        defaultLink={{
                                            type: 'project',
                                            id: project.id,
                                        }}
                                        buttonLabel="Upload document"
                                    />
                                )
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function SummaryMetric({
    label,
    value,
}: {
    label: string;
    value: number | string | null | undefined;
}) {
    return (
        <div className="rounded-md border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">
                {value === null || value === undefined
                    ? 'None'
                    : formatNumber(value)}
            </div>
        </div>
    );
}

function SiteTable({
    sites,
    users,
    title,
    onArchive,
}: {
    sites: Site[];
    users: Option[];
    title: string;
    onArchive: (site: Site) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-3 pr-4 font-medium">Site</th>
                                <th className="py-3 pr-4 font-medium">
                                    Manager
                                </th>
                                <th className="py-3 pr-4 font-medium">
                                    Deadline
                                </th>
                                <th className="py-3 pr-4 font-medium">
                                    Status
                                </th>
                                <th className="py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sites.map((site) => (
                                <tr
                                    key={site.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3 pr-4">
                                        <Link
                                            href={`/sites/${site.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {site.name}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {site.reference}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-4">
                                        {site.manager_name ?? 'Unassigned'}
                                    </td>
                                    <td className="py-3 pr-4">
                                        {site.reporting_deadline ?? 'Project'}
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge variant="secondary">
                                            {site.status}
                                        </Badge>
                                    </td>
                                    <td className="py-3">
                                        <div className="flex justify-end gap-2">
                                            <SiteDialog
                                                site={site}
                                                projectId={site.project_id}
                                                users={users}
                                            />
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => onArchive(site)}
                                            >
                                                Archive
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {sites.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No sites in this section.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

function EstimateTable({
    projectId,
    estimates,
    canCreate,
}: {
    projectId: string;
    estimates: EstimateSummary[];
    canCreate: boolean;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>Estimate revisions</CardTitle>
                    <CardDescription>
                        Draft and approved project baselines.
                    </CardDescription>
                </div>
                {canCreate && (
                    <Button asChild>
                        <Link href={`/projects/${projectId}/estimates/create`}>
                            {estimates.length > 0
                                ? 'New revision'
                                : 'New estimate'}
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-3 pr-4 font-medium">
                                    Version
                                </th>
                                <th className="py-3 pr-4 font-medium">Title</th>
                                <th className="py-3 pr-4 font-medium">Items</th>
                                <th className="py-3 pr-4 font-medium">
                                    Status
                                </th>
                                <th className="py-3 font-medium">Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            {estimates.map((estimate) => (
                                <tr
                                    key={estimate.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3 pr-4">
                                        v{estimate.version_number}
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Link
                                            href={`/estimates/${estimate.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {estimate.title}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {estimate.currency_code}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-4">
                                        {formatNumber(estimate.lines_count)}
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge
                                            variant={
                                                estimate.is_baseline
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {estimate.status_label}
                                        </Badge>
                                    </td>
                                    <td className="py-3">
                                        {estimate.approved_by ?? 'Not approved'}
                                        {estimate.approved_at && (
                                            <div className="text-muted-foreground">
                                                {formatDateTime(
                                                    estimate.approved_at,
                                                )}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {estimates.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No estimate revisions recorded.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

function PerformanceTable({
    performance,
    canViewCosts,
}: {
    performance: ProjectPerformance;
    canViewCosts: boolean;
}) {
    const currency = performance.baseline.currency_code;

    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Baseline performance</CardTitle>
                    <CardDescription>
                        Version {performance.baseline.version_number} ·{' '}
                        {performance.baseline.title}
                    </CardDescription>
                </CardHeader>
                <CardContent
                    className={`grid gap-4 sm:grid-cols-2 ${canViewCosts ? 'lg:grid-cols-6' : ''}`}
                >
                    <SummaryMetric
                        label="Work items"
                        value={performance.totals.planned_items}
                    />
                    {canViewCosts && (
                        <>
                            <SummaryMetric
                                label="Baseline revenue"
                                value={formatCurrencyAmount(
                                    currency,
                                    performance.totals.baseline_revenue,
                                )}
                            />
                            <SummaryMetric
                                label="Earned output"
                                value={formatCurrencyAmount(
                                    currency,
                                    performance.totals.earned_output,
                                )}
                            />
                            <SummaryMetric
                                label="Baseline cost"
                                value={formatCurrencyAmount(
                                    currency,
                                    performance.totals.baseline_cost,
                                )}
                            />
                            <SummaryMetric
                                label="Actual input cost"
                                value={formatCurrencyAmount(
                                    currency,
                                    performance.totals.actual_input_cost,
                                )}
                            />
                            <SummaryMetric
                                label="Operational expenses"
                                value={formatCurrencyAmount(
                                    currency,
                                    performance.totals.operational_expenses,
                                )}
                            />
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Work progress</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-3 pr-4 font-medium">
                                        Work item
                                    </th>
                                    <th className="py-3 pr-4 font-medium">
                                        Planned
                                    </th>
                                    <th className="py-3 pr-4 font-medium">
                                        Approved
                                    </th>
                                    <th className="py-3 pr-4 font-medium">
                                        Remaining
                                    </th>
                                    <th className="py-3 pr-4 font-medium">
                                        Completion
                                    </th>
                                    {canViewCosts && (
                                        <th className="py-3 font-medium">
                                            Earned output
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {performance.work_items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-3 pr-4">
                                            <div className="font-medium">
                                                {item.name}
                                            </div>
                                            {item.boq_reference && (
                                                <div className="text-muted-foreground">
                                                    {item.boq_reference}
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {formatNumber(
                                                item.planned_quantity,
                                            )}{' '}
                                            {item.unit}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {formatNumber(
                                                item.approved_progress,
                                            )}{' '}
                                            {item.unit}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {formatNumber(
                                                item.remaining_quantity,
                                            )}{' '}
                                            {item.unit}
                                        </td>
                                        <td className="min-w-40 py-3 pr-4">
                                            <div className="mb-1 flex justify-between gap-3">
                                                <span>
                                                    {formatNumber(
                                                        item.completion_percent,
                                                    )}
                                                    %
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-primary"
                                                    style={{
                                                        width: `${Math.min(Number(item.completion_percent), 100)}%`,
                                                    }}
                                                />
                                            </div>
                                        </td>
                                        {canViewCosts && (
                                            <td className="py-3">
                                                {formatCurrencyAmount(
                                                    currency,
                                                    item.earned_output,
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {performance.resources.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Material plan and actual use</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Material
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Baseline total
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Expected at current progress
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Reported actual
                                        </th>
                                        <th className="py-3 font-medium">
                                            Variance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {performance.resources.map((resource) => (
                                        <tr
                                            key={resource.inventory_item_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {resource.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {formatNumber(
                                                    resource.planned_quantity,
                                                )}{' '}
                                                {resource.unit}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {formatNumber(
                                                    resource.expected_to_date,
                                                )}{' '}
                                                {resource.unit}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {formatNumber(
                                                    resource.actual_quantity,
                                                )}{' '}
                                                {resource.unit}
                                            </td>
                                            <td className="py-3">
                                                {formatNumber(
                                                    resource.variance_quantity,
                                                )}{' '}
                                                {resource.unit}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ActivityTable({
    activities,
    sites,
    currencies,
    canViewRates,
    title,
}: {
    activities: ProjectActivity[];
    sites: Site[];
    currencies: Option[];
    canViewRates: boolean;
    title: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-3 pr-4 font-medium">
                                    Work item
                                </th>
                                <th className="py-3 pr-4 font-medium">
                                    BOQ reference
                                </th>
                                <th className="py-3 pr-4 font-medium">Site</th>
                                <th className="py-3 pr-4 font-medium">
                                    Approved progress / planned
                                </th>
                                {canViewRates && (
                                    <th className="py-3 pr-4 font-medium">
                                        Rate
                                    </th>
                                )}
                                <th className="py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {activities.map((activity) => (
                                <tr
                                    key={activity.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3 pr-4">
                                        <div className="font-medium">
                                            {activity.name}
                                        </div>
                                        {activity.code && (
                                            <div className="text-muted-foreground">
                                                {activity.code}
                                            </div>
                                        )}
                                    </td>
                                    <td className="py-3 pr-4">
                                        {activity.boq_item_number ??
                                            'Not linked'}
                                    </td>
                                    <td className="py-3 pr-4">
                                        {activity.site_name ?? 'Project-wide'}
                                    </td>
                                    <td className="py-3 pr-4">
                                        {formatNumber(
                                            activity.approved_quantity,
                                        )}{' '}
                                        /{' '}
                                        {activity.planned_quantity
                                            ? formatNumber(
                                                  activity.planned_quantity,
                                              )
                                            : '-'}{' '}
                                        {activity.unit ?? ''}
                                    </td>
                                    {canViewRates && (
                                        <td className="py-3 pr-4">
                                            {formatCurrencyAmount(
                                                activity.currency_code,
                                                activity.rate_amount,
                                            )}
                                        </td>
                                    )}
                                    <td className="py-3">
                                        <div className="flex justify-end">
                                            {activity.managed_by_estimate ? (
                                                <Badge variant="outline">
                                                    Baseline
                                                </Badge>
                                            ) : (
                                                <ActivityDialog
                                                    activity={activity}
                                                    projectId={
                                                        activity.project_id
                                                    }
                                                    sites={sites}
                                                    currencies={currencies}
                                                    canViewRates={canViewRates}
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {activities.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canViewRates ? 6 : 5}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No work items in this section.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
