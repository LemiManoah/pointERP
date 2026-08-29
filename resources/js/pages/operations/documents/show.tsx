import { Head, router } from '@inertiajs/react';
import { Download, ExternalLink, Trash2 } from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentRecord,
    type DocumentTypeOption,
    type LinkOptions,
    type Option,
} from './partials/document-dialog';
import { LinkDialog } from './partials/link-dialog';
import { VersionDialog } from './partials/version-dialog';

type Version = {
    id: string;
    version_number: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    checksum: string | null;
    notes: string | null;
    uploaded_by: string;
    uploaded_at: string | null;
};

type DocumentDetail = DocumentRecord & {
    branch_id: string | null;
    document_type_id: string;
    description: string | null;
    document_date: string | null;
    is_expired: boolean;
    external_url: string | null;
    current_version: {
        id: string;
        version_number: number;
        original_name: string;
        size_bytes: number;
    } | null;
    versions: Version[];
    links: Array<{ id: string; label: string; type: string }>;
};

type Props = {
    document: DocumentDetail;
    can: {
        update: boolean;
        archive: boolean;
        download: boolean;
        version: boolean;
        link: boolean;
        unlink: boolean;
    };
    documentTypes: DocumentTypeOption[];
    branches: Option[];
    linkOptions: LinkOptions;
};

export default function DocumentShow({
    document,
    can,
    documentTypes,
    branches,
    linkOptions,
}: Props) {
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Documents', href: '/documents' },
        { title: document.title, href: `/documents/${document.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={document.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {document.type_name}
                            </Badge>
                            <Badge variant="outline">
                                {document.confidentiality}
                            </Badge>
                            <Badge
                                variant={
                                    document.is_expired
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {document.status}
                            </Badge>
                        </div>
                        <h1 className="mt-3 text-2xl font-semibold tracking-tight">
                            {document.title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.reference ?? 'No reference'} ·{' '}
                            {document.branch_name ?? 'Tenant-wide'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {document.external_url && (
                            <Button variant="outline" asChild>
                                <a
                                    href={document.external_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink />
                                    Open Google file
                                </a>
                            </Button>
                        )}
                        {can.update && (
                            <DocumentDialog
                                document={document}
                                documentTypes={documentTypes}
                                branches={branches}
                                linkOptions={linkOptions}
                            />
                        )}
                        {can.link && (
                            <LinkDialog
                                documentId={document.id}
                                linkOptions={linkOptions}
                            />
                        )}
                        {can.version && (
                            <VersionDialog documentId={document.id} />
                        )}
                        {can.archive && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirm({
                                        title: 'Change archive status?',
                                        description: `${document.title} will move between active and archive lists.`,
                                        confirmLabel: 'Continue',
                                        onConfirm: () =>
                                            router.delete(
                                                `/documents/${document.id}`,
                                            ),
                                    })
                                }
                            >
                                Archive
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <InfoCard
                        label="Document date"
                        value={document.document_date ?? 'None'}
                    />
                    <InfoCard
                        label="Expiry"
                        value={document.expires_on ?? 'None'}
                    />
                    <InfoCard
                        label="Current version"
                        value={
                            document.current_version
                                ? `v${document.current_version.version_number}`
                                : 'None'
                        }
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <InfoCard
                        label="Document no."
                        value={document.document_number ?? 'None'}
                    />
                    <InfoCard
                        label="Revision"
                        value={document.revision ?? 'None'}
                    />
                    <InfoCard
                        label="Discipline"
                        value={document.discipline ?? 'None'}
                    />
                    <InfoCard
                        label="Issuer"
                        value={document.issuer ?? 'None'}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Linked records</CardTitle>
                        <CardDescription>
                            Where this document is used as controlled evidence.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {document.links.map((link) => (
                            <div
                                key={link.id}
                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                            >
                                <div>
                                    <span className="font-medium">
                                        {link.label}
                                    </span>
                                    <span className="ml-2 text-muted-foreground">
                                        {link.type}
                                    </span>
                                </div>
                                {can.unlink && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            confirm({
                                                title: 'Remove document link?',
                                                description:
                                                    'The document file and versions will remain.',
                                                confirmLabel: 'Remove',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/documents/${document.id}/links/${link.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {document.links.length === 0 && (
                            <div className="text-sm text-muted-foreground">
                                No linked records.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Version history</CardTitle>
                        <CardDescription>
                            Files are versioned, not overwritten.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Version
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            File
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Uploaded
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Checksum
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {document.versions.map((version) => (
                                        <tr
                                            key={version.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                v{version.version_number}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {version.original_name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {version.mime_type} ·{' '}
                                                    {formatNumber(
                                                        version.size_bytes,
                                                    )}{' '}
                                                    bytes
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>{version.uploaded_by}</div>
                                                <div className="text-muted-foreground">
                                                    {version.uploaded_at}
                                                </div>
                                            </td>
                                            <td className="max-w-64 truncate py-3 pr-4 text-muted-foreground">
                                                {version.checksum ?? 'None'}
                                            </td>
                                            <td className="py-3 text-right">
                                                {can.download && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/documents/${document.id}/versions/${version.id}/download`}
                                                        >
                                                            <Download />
                                                            Download
                                                        </a>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-base">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}
