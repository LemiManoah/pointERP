import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
} from '../documents/partials/document-dialog';
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
    assignedUsers: AssignedProjectUser[];
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    canUploadDocuments: boolean;
    branches: Option[];
    customers: Option[];
    contracts: Option[];
    users: Option[];
    currencies: Option[];
    canViewRates: boolean;
};

export default function ProjectShow({
    project,
    sites,
    activities,
    assignedUsers,
    documents,
    documentTypes,
    documentBranches,
    documentLinkOptions,
    canUploadDocuments,
    branches,
    customers,
    contracts,
    users,
    currencies,
    canViewRates,
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
                        <ProjectDialog
                            project={project}
                            branches={branches}
                            customers={customers}
                            contracts={contracts}
                            users={users}
                            currencies={currencies}
                        />
                    </div>
                </div>

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="sites">Sites</TabsTrigger>
                        <TabsTrigger value="activities">
                            Activities / BOQ
                        </TabsTrigger>
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

                    <TabsContent value="activities" className="mt-6 grid gap-6">
                        <div className="flex justify-end">
                            <ActivityDialog
                                projectId={project.id}
                                sites={sites}
                                currencies={currencies}
                                canViewRates={canViewRates}
                            />
                        </div>
                        <ActivityTable
                            activities={activeActivities}
                            sites={sites}
                            currencies={currencies}
                            canViewRates={canViewRates}
                            title="Active activities"
                        />
                        {inactiveActivities.length > 0 && (
                            <ActivityTable
                                activities={inactiveActivities}
                                sites={sites}
                                currencies={currencies}
                                canViewRates={canViewRates}
                                title="Inactive activities"
                            />
                        )}
                    </TabsContent>

                    <TabsContent value="access" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Assigned users</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3">
                                    {assignedUsers.map((user) => (
                                        <div
                                            key={user.id}
                                            className="flex items-center justify-between rounded-md border p-3 text-sm"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {user.email}
                                                </div>
                                            </div>
                                            <div className="text-right text-muted-foreground">
                                                <div>
                                                    {user.role ?? 'Member'}
                                                </div>
                                                <div>
                                                    {user.can_manage
                                                        ? 'Can manage'
                                                        : 'View only'}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {assignedUsers.length === 0 && (
                                        <div className="py-8 text-center text-sm text-muted-foreground">
                                            No project users assigned.
                                        </div>
                                    )}
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
                                    Activity
                                </th>
                                <th className="py-3 pr-4 font-medium">Site</th>
                                <th className="py-3 pr-4 font-medium">Qty</th>
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
                                        <div className="text-muted-foreground">
                                            {activity.boq_item_number ??
                                                activity.code ??
                                                'No BOQ item'}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-4">
                                        {activity.site_name ?? 'Project-wide'}
                                    </td>
                                    <td className="py-3 pr-4">
                                        {activity.approved_quantity} /{' '}
                                        {activity.planned_quantity ?? '-'}{' '}
                                        {activity.unit ?? ''}
                                    </td>
                                    {canViewRates && (
                                        <td className="py-3 pr-4">
                                            {activity.rate_amount
                                                ? `${activity.currency_code} ${activity.rate_amount}`
                                                : 'Not set'}
                                        </td>
                                    )}
                                    <td className="py-3">
                                        <div className="flex justify-end">
                                            <ActivityDialog
                                                activity={activity}
                                                projectId={activity.project_id}
                                                sites={sites}
                                                currencies={currencies}
                                                canViewRates={canViewRates}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {activities.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canViewRates ? 5 : 4}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No activities in this section.
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
