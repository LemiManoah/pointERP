import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
    type Option,
} from '../documents/partials/document-dialog';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';

type Site = {
    id: string;
    project_id: string;
    project_name: string;
    branch_id: string;
    branch_name: string;
    reference: string;
    name: string;
    location_name: string | null;
    latitude: string | null;
    longitude: string | null;
    manager_id: string | null;
    manager_name: string | null;
    reporting_deadline: string | null;
    status: string;
};

type UserOption = {
    id: string;
    name: string;
    email: string;
};

type AssignedSiteUser = UserOption & {
    role: string | null;
    can_submit_dsr: boolean;
    can_review_dsr: boolean;
};

type Props = {
    site: Site;
    assignedUsers: AssignedSiteUser[];
    users: UserOption[];
    dsrSummary: Record<string, number | string | null>;
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    canUploadDocuments: boolean;
};

type Assignment = {
    user_id: string;
    role: string;
    can_submit_dsr: boolean;
    can_review_dsr: boolean;
};

export default function SiteShow({
    site,
    assignedUsers,
    users,
    dsrSummary,
    documents,
    documentTypes,
    documentBranches,
    documentLinkOptions,
    canUploadDocuments,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: site.project_name, href: `/projects/${site.project_id}` },
        { title: site.reference, href: `/sites/${site.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={site.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {site.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {site.reference} · {site.project_name} ·{' '}
                            {site.branch_name}
                        </p>
                    </div>
                    <SiteAccessDialog
                        siteId={site.id}
                        users={users}
                        assignedUsers={assignedUsers}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily reporting status</CardTitle>
                        <CardDescription>
                            Site-level DSR exceptions and latest approved
                            output.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <SummaryMetric
                            label="Last report"
                            value={dsrSummary.last_report_date}
                        />
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
                            label="Approved output"
                            value={dsrSummary.latest_approved_output}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Site profile</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <InfoRow label="Status" value={site.status} />
                            <InfoRow
                                label="Manager"
                                value={site.manager_name ?? 'Unassigned'}
                            />
                            <InfoRow
                                label="Location"
                                value={site.location_name ?? 'Not set'}
                            />
                            <InfoRow
                                label="DSR deadline"
                                value={site.reporting_deadline ?? 'Project'}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Site users</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
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
                                        <div>{user.role ?? 'Member'}</div>
                                        <div>
                                            {user.can_submit_dsr
                                                ? 'Submit DSR'
                                                : 'No submit'}
                                            {' · '}
                                            {user.can_review_dsr
                                                ? 'Review DSR'
                                                : 'No review'}
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {assignedUsers.length === 0 && (
                                <div className="py-8 text-center text-sm text-muted-foreground">
                                    No site users assigned.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <DocumentEvidenceTable
                    documents={documents}
                    emptyText="No documents linked to this site."
                    actions={
                        canUploadDocuments && (
                            <DocumentDialog
                                documentTypes={documentTypes}
                                branches={documentBranches}
                                linkOptions={documentLinkOptions}
                                defaultBranchId={site.branch_id}
                                defaultLink={{ type: 'site', id: site.id }}
                                buttonLabel="Upload evidence"
                            />
                        )
                    }
                />
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
                    : typeof value === 'string' && value.includes('-')
                      ? value
                      : formatNumber(value)}
            </div>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

function SiteAccessDialog({
    siteId,
    users,
    assignedUsers,
}: {
    siteId: string;
    users: UserOption[];
    assignedUsers: AssignedSiteUser[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ users: Assignment[] }>({
        users: assignedUsers.map((user) => ({
            user_id: user.id,
            role: user.role ?? '',
            can_submit_dsr: user.can_submit_dsr,
            can_review_dsr: user.can_review_dsr,
        })),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(`/sites/${siteId}/users`, {
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ShieldCheck />
                    Manage site access
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Site access</DialogTitle>
                    <DialogDescription>
                        Assign users who can submit or review site DSRs later.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    {form.data.users.map((assignment, index) => (
                        <div
                            key={index}
                            className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_140px_auto_auto_auto]"
                        >
                            <SearchableSelect
                                value={assignment.user_id}
                                onValueChange={(value) =>
                                    form.setData(
                                        'users',
                                        form.data.users.map(
                                            (user, userIndex) =>
                                                userIndex === index
                                                    ? {
                                                          ...user,
                                                          user_id: value,
                                                      }
                                                    : user,
                                        ),
                                    )
                                }
                                options={users.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                }))}
                                placeholder="Select user"
                                searchPlaceholder="Search users..."
                            />
                            <Input
                                value={assignment.role}
                                onChange={(event) =>
                                    form.setData(
                                        'users',
                                        form.data.users.map(
                                            (user, userIndex) =>
                                                userIndex === index
                                                    ? {
                                                          ...user,
                                                          role: event.target
                                                              .value,
                                                      }
                                                    : user,
                                        ),
                                    )
                                }
                                placeholder="Role"
                            />
                            <Label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={assignment.can_submit_dsr}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'users',
                                            form.data.users.map(
                                                (user, userIndex) =>
                                                    userIndex === index
                                                        ? {
                                                              ...user,
                                                              can_submit_dsr:
                                                                  checked ===
                                                                  true,
                                                          }
                                                        : user,
                                            ),
                                        )
                                    }
                                />
                                Submit
                            </Label>
                            <Label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={assignment.can_review_dsr}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'users',
                                            form.data.users.map(
                                                (user, userIndex) =>
                                                    userIndex === index
                                                        ? {
                                                              ...user,
                                                              can_review_dsr:
                                                                  checked ===
                                                                  true,
                                                          }
                                                        : user,
                                            ),
                                        )
                                    }
                                />
                                Review
                            </Label>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData(
                                        'users',
                                        form.data.users.filter(
                                            (_, userIndex) =>
                                                userIndex !== index,
                                        ),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                    <div className="flex justify-between gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('users', [
                                    ...form.data.users,
                                    {
                                        user_id: '',
                                        role: '',
                                        can_submit_dsr: true,
                                        can_review_dsr: false,
                                    },
                                ])
                            }
                        >
                            Add user
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save access
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
