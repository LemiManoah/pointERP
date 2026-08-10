import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentTypeDialog,
    type DocumentType,
} from './partials/document-type-dialog';

type Props = {
    documentTypes: DocumentType[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Document types', href: '/document-types' },
];

export default function DocumentTypesIndex({ documentTypes }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('active');
    const debouncedSearch = useDebouncedValue(search);

    const filteredTypes = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return documentTypes.filter(
            (type) =>
                (tab === 'active' ? type.is_active : !type.is_active) &&
                (!term ||
                    [type.name, type.code, type.description ?? '']
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [debouncedSearch, documentTypes, tab]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Document types" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Document types
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Classifications for controlled project documents
                                and evidence.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search document types"
                                className="w-full pl-9 sm:w-80"
                            />
                        </div>
                    </div>
                    <DocumentTypeDialog />
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Rules
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Scope
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredTypes.map((type) => (
                                        <tr
                                            key={type.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {type.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {type.code}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {type.requires_expiry_date && (
                                                        <Badge variant="secondary">
                                                            Expiry
                                                        </Badge>
                                                    )}
                                                    {type.is_confidential && (
                                                        <Badge variant="outline">
                                                            Confidential
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {type.tenant_specific
                                                    ? 'Tenant'
                                                    : 'Global'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <DocumentTypeDialog
                                                        type={type}
                                                    />
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Change document type status?',
                                                                description: `${type.name} will move between active and inactive lists.`,
                                                                confirmLabel:
                                                                    'Continue',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/document-types/${type.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        Toggle
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredTypes.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No document types match this
                                                view.
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
