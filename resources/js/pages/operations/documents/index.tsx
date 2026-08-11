import { Head, Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentRecord,
    type DocumentTypeOption,
    type LinkOptions,
    type Option,
} from './partials/document-dialog';

type DocumentRow = DocumentRecord & {
    is_expired: boolean;
    current_version: {
        id: string;
        version_number: number;
        original_name: string;
        size_bytes: number;
    } | null;
    links: Array<{ id: string; label: string; type: string }>;
};

type Props = {
    documents: DocumentRow[];
    documentTypes: DocumentTypeOption[];
    branches: Option[];
    linkOptions: LinkOptions;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Documents', href: '/documents' },
];

export default function DocumentsIndex({
    documents,
    documentTypes,
    branches,
    linkOptions,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('active');
    const [expiry, setExpiry] = useState('all');
    const debouncedSearch = useDebouncedValue(search);
    const { soonDate, today } = useMemo(() => {
        const currentDate = new Date().toISOString().slice(0, 10);
        const soon = new Date();
        soon.setDate(soon.getDate() + 30);

        return {
            today: currentDate,
            soonDate: soon.toISOString().slice(0, 10),
        };
    }, []);
    const activeCount = documents.filter(
        (document) => document.status !== 'archived',
    ).length;
    const expiringCount = documents.filter(
        (document) =>
            document.expires_on !== null &&
            document.expires_on >= today &&
            document.expires_on <= soonDate,
    ).length;
    const expiredCount = documents.filter(
        (document) =>
            document.expires_on !== null && document.expires_on < today,
    ).length;

    const filteredDocuments = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return documents.filter((document) => {
            const matchesTab =
                tab === 'active'
                    ? document.status !== 'archived'
                    : document.status === 'archived';
            const matchesSearch =
                !term ||
                [
                    document.title,
                    document.reference ?? '',
                    document.document_number ?? '',
                    document.revision ?? '',
                    document.discipline ?? '',
                    document.issuer ?? '',
                    document.type_name,
                    document.branch_name ?? '',
                    document.current_version?.original_name ?? '',
                    ...document.links.map((link) => link.label),
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);
            const matchesExpiry =
                expiry === 'all' ||
                (expiry === 'expired' &&
                    document.expires_on !== null &&
                    document.expires_on < today) ||
                (expiry === 'expiring' &&
                    document.expires_on !== null &&
                    document.expires_on >= today &&
                    document.expires_on <= soonDate) ||
                (expiry === 'no-expiry' && document.expires_on === null);

            return matchesTab && matchesSearch && matchesExpiry;
        });
    }, [debouncedSearch, documents, expiry, soonDate, tab, today]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Documents" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Documents
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Controlled project records, versions and field
                                evidence.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-[20rem_12rem]">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search documents"
                                    className="pl-9"
                                />
                            </div>
                            <NativeSelect
                                value={expiry}
                                onChange={(event) =>
                                    setExpiry(event.target.value)
                                }
                            >
                                <NativeSelectOption value="all">
                                    All expiry
                                </NativeSelectOption>
                                <NativeSelectOption value="expiring">
                                    Expiring soon
                                </NativeSelectOption>
                                <NativeSelectOption value="expired">
                                    Expired
                                </NativeSelectOption>
                                <NativeSelectOption value="no-expiry">
                                    No expiry
                                </NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>
                    <DocumentDialog
                        documentTypes={documentTypes}
                        branches={branches}
                        linkOptions={linkOptions}
                    />
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="archived">Archived</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <SummaryCard label="Active documents" value={activeCount} />
                    <SummaryCard
                        label="Expiring within 30 days"
                        value={expiringCount}
                    />
                    <SummaryCard label="Expired" value={expiredCount} />
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Links
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Expiry
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
                                    {filteredDocuments.map((document) => (
                                        <tr
                                            key={document.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {document.title}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {document.reference ??
                                                        document.document_number ??
                                                        document.current_version
                                                            ?.original_name ??
                                                        'No reference'}
                                                    {document.revision &&
                                                        ` · Rev ${document.revision}`}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {document.type_name}
                                                </Badge>
                                                <div className="mt-1 text-muted-foreground">
                                                    {document.confidentiality}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {document.links.length > 0
                                                    ? document.links
                                                          .map(
                                                              (link) =>
                                                                  link.label,
                                                          )
                                                          .join(', ')
                                                    : 'Unlinked'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {document.expires_on ?? 'None'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        document.is_expired
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                >
                                                    {document.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/documents/${document.id}`}
                                                        >
                                                            Open
                                                        </Link>
                                                    </Button>
                                                    {document.current_version && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/documents/${document.id}/versions/${document.current_version.id}/download`}
                                                            >
                                                                <Download />
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Change document archive status?',
                                                                description: `${document.title} will move between active and archive lists.`,
                                                                confirmLabel:
                                                                    'Continue',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/documents/${document.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        Archive
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredDocuments.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No documents match this view.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="text-sm text-muted-foreground">{label}</div>
                <div className="mt-2 text-2xl font-semibold">{value}</div>
            </CardContent>
        </Card>
    );
}
