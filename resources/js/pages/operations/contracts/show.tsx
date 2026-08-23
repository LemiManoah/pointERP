import { Head, Link, router } from '@inertiajs/react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount } from '@/lib/utils';
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
    ContractDialog,
    type Contract,
    type Option,
} from './partials/contract-dialog';

type ProjectRow = {
    id: string;
    reference: string;
    name: string;
    status: string;
    manager_name: string | null;
};

type Props = {
    contract: Contract;
    projects: ProjectRow[];
    documents: LinkedDocumentRow[];
    can: { update: boolean; archive: boolean; uploadDocuments: boolean };
    branches: Option[];
    customers: Option[];
    currencies: Option[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
};

export default function ContractShow({
    contract,
    projects,
    documents,
    can,
    branches,
    customers,
    currencies,
    documentTypes,
    documentBranches,
    documentLinkOptions,
}: Props) {
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Contracts', href: '/contracts' },
        { title: contract.reference, href: `/contracts/${contract.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={contract.reference} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {contract.title}
                            </h1>
                            <Badge variant="secondary">{contract.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {contract.reference} · {contract.customer_name} ·{' '}
                            {contract.branch_name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.update && (
                            <ContractDialog
                                contract={contract}
                                branches={branches}
                                customers={customers}
                                currencies={currencies}
                            />
                        )}
                        {can.archive && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirm({
                                        title: 'Change contract archive status?',
                                        description: `${contract.reference} will move between active and archive lists.`,
                                        confirmLabel: 'Continue',
                                        onConfirm: () =>
                                            router.delete(
                                                `/contracts/${contract.id}`,
                                            ),
                                    })
                                }
                            >
                                Archive
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Info
                        label="Contract value"
                        value={formatCurrencyAmount(
                            contract.currency_code,
                            contract.contract_value,
                        )}
                    />
                    <Info
                        label="Retention"
                        value={
                            contract.retention_percent
                                ? `${contract.retention_percent}%`
                                : 'Not set'
                        }
                    />
                    <Info
                        label="Start date"
                        value={contract.starts_on ?? 'Not set'}
                    />
                    <Info
                        label="End date"
                        value={contract.ends_on ?? 'Not set'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scope</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {contract.scope_summary ??
                                'No scope summary recorded.'}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment terms</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {contract.payment_terms ??
                                'No payment terms recorded.'}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Projects under this contract</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={`/projects/${project.id}`}
                                className="flex min-w-0 items-center justify-between gap-3 rounded-md border p-3 hover:bg-muted/50"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {project.reference} · {project.name}
                                    </span>
                                    <span className="block truncate text-sm text-muted-foreground">
                                        {project.manager_name ?? 'No manager'}
                                    </span>
                                </span>
                                <Badge variant="outline">
                                    {project.status}
                                </Badge>
                            </Link>
                        ))}
                        {projects.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No projects are linked to this contract.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <DocumentEvidenceTable
                    documents={documents}
                    emptyText="No documents are linked to this contract."
                    title="Contract documents"
                    description="Controlled files and protected Google links attached to this contract."
                    actions={
                        can.uploadDocuments && (
                            <DocumentDialog
                                documentTypes={documentTypes}
                                branches={documentBranches}
                                linkOptions={documentLinkOptions}
                                defaultBranchId={contract.branch_id}
                                defaultLink={{
                                    type: 'contract',
                                    id: contract.id,
                                }}
                                buttonLabel="Add document"
                            />
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="text-sm text-muted-foreground">{label}</div>
                <div className="mt-1 font-semibold">{value}</div>
            </CardContent>
        </Card>
    );
}
