import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { RequisitionDialog } from './partials/requisition-dialog';
import type { RequisitionFormOptions } from './types';

type RequisitionRow = {
    id: string;
    reference: string;
    branch_name: string;
    store_name: string;
    requester_name: string;
    project_name: string | null;
    site_name: string | null;
    required_by_date: string;
    priority: string;
    status: string;
    lines_count: number;
};
type Props = RequisitionFormOptions & {
    requisitions: RequisitionRow[];
    canCreate: boolean;
};
type StatusTab = 'open' | 'fulfilled' | 'rejected' | 'cancelled';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Material requisitions', href: '/inventory/requisitions' },
];
const openStatuses = [
    'draft',
    'submitted',
    'returned',
    'approved',
    'partially_issued',
];

export default function MaterialRequisitionIndex(props: Props) {
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState<StatusTab>('open');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const filtered = useMemo(
        () =>
            props.requisitions.filter((row) => {
                const matchesTab =
                    tab === 'open'
                        ? openStatuses.includes(row.status)
                        : row.status === tab;
                const matchesSearch =
                    !term ||
                    `${row.reference} ${row.branch_name} ${row.store_name} ${row.requester_name} ${row.project_name ?? ''} ${row.site_name ?? ''}`
                        .toLowerCase()
                        .includes(term);
                return matchesTab && matchesSearch;
            }),
        [props.requisitions, tab, term],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Material requisitions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Material requisitions
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Request, approve, reserve, issue and return materials
                        through controlled store records.
                    </p>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex w-full min-w-0 flex-wrap items-center gap-3 lg:w-auto">
                        <div className="relative w-full max-w-md">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search reference, store or requester"
                                className="pl-9"
                            />
                        </div>
                        <Tabs
                            value={tab}
                            onValueChange={(value) =>
                                setTab(value as StatusTab)
                            }
                        >
                            <TabsList className="flex-wrap">
                                <TabsTrigger value="open">Open</TabsTrigger>
                                <TabsTrigger value="fulfilled">
                                    Fulfilled
                                </TabsTrigger>
                                <TabsTrigger value="rejected">
                                    Rejected
                                </TabsTrigger>
                                <TabsTrigger value="cancelled">
                                    Cancelled
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                    {props.canCreate && <RequisitionDialog options={props} />}
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Requisition</Th>
                                        <Th>Store</Th>
                                        <Th>Destination</Th>
                                        <Th>Requested by</Th>
                                        <Th>Required by</Th>
                                        <Th>Lines</Th>
                                        <Th>Status</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                <Link
                                                    href={`/inventory/requisitions/${row.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.reference}
                                                </Link>
                                                <div className="mt-1 flex gap-2">
                                                    <Badge
                                                        variant={
                                                            row.priority ===
                                                            'urgent'
                                                                ? 'destructive'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {label(row.priority)}
                                                    </Badge>
                                                </div>
                                            </Td>
                                            <Td>
                                                {row.store_name}
                                                <div className="text-muted-foreground">
                                                    {row.branch_name}
                                                </div>
                                            </Td>
                                            <Td>
                                                {row.site_name ??
                                                    row.project_name ??
                                                    'Department / branch'}
                                            </Td>
                                            <Td>{row.requester_name}</Td>
                                            <Td>{row.required_by_date}</Td>
                                            <Td>
                                                {formatNumber(row.lines_count)}
                                            </Td>
                                            <Td>
                                                <StatusBadge
                                                    status={row.status}
                                                />
                                            </Td>
                                        </tr>
                                    ))}
                                    {filtered.length === 0 && (
                                        <tr>
                                            <Td
                                                colSpan={7}
                                                className="py-12 text-center text-muted-foreground"
                                            >
                                                No requisitions match this view.
                                            </Td>
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

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'rejected' || status === 'cancelled'
            ? 'destructive'
            : status === 'fulfilled'
              ? 'default'
              : 'secondary';
    return <Badge variant={variant}>{label(status)}</Badge>;
}
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function Th({ children }: { children: ReactNode }) {
    return <th className="px-3 py-3 font-medium">{children}</th>;
}
function Td({
    children,
    colSpan,
    className = '',
}: {
    children: ReactNode;
    colSpan?: number;
    className?: string;
}) {
    return (
        <td colSpan={colSpan} className={`px-3 py-4 align-top ${className}`}>
            {children}
        </td>
    );
}
