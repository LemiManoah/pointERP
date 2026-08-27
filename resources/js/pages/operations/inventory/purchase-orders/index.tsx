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
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ProcurementOptions } from '../procurement-types';
import { PurchaseOrderDialog } from './partials/purchase-order-dialog';

type Row = {
    id: string;
    order_number: string;
    branch_name: string;
    store_name: string;
    supplier_name: string;
    order_date: string;
    expected_date: string | null;
    currency_code: string | null;
    total_amount: string | null;
    status: string;
    lines_count: number;
};
type Props = ProcurementOptions & {
    purchaseOrders: Row[];
    canCreate: boolean;
    canViewCosts: boolean;
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase orders', href: '/inventory/purchase-orders' },
];
const active = [
    'draft',
    'submitted',
    'returned',
    'approved',
    'partially_received',
];
export default function PurchaseOrdersIndex(props: Props) {
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('active');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const rows = useMemo(
        () =>
            props.purchaseOrders.filter(
                (row) =>
                    (tab === 'active'
                        ? active.includes(row.status)
                        : row.status === tab) &&
                    (!term ||
                        `${row.order_number} ${row.supplier_name} ${row.store_name}`
                            .toLowerCase()
                            .includes(term)),
            ),
        [props.purchaseOrders, tab, term],
    );
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase orders" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Purchase orders</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Control supplier commitments, approvals and delivery
                        progress.
                    </p>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex w-full flex-wrap items-center gap-3 lg:w-auto">
                        <div className="relative w-full max-w-md">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search PO, supplier or store"
                                className="pl-9"
                            />
                        </div>
                        <Tabs value={tab} onValueChange={setTab}>
                            <TabsList>
                                <TabsTrigger value="active">Active</TabsTrigger>
                                <TabsTrigger value="received">
                                    Received
                                </TabsTrigger>
                                <TabsTrigger value="closed">Closed</TabsTrigger>
                                <TabsTrigger value="rejected">
                                    Rejected
                                </TabsTrigger>
                                <TabsTrigger value="cancelled">
                                    Cancelled
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                    {props.canCreate && <PurchaseOrderDialog options={props} />}
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Purchase order</Th>
                                        <Th>Supplier</Th>
                                        <Th>Store</Th>
                                        <Th>Delivery</Th>
                                        <Th>Lines</Th>
                                        {props.canViewCosts && <Th>Total</Th>}
                                        <Th>Status</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                <Link
                                                    href={`/inventory/purchase-orders/${row.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.order_number}
                                                </Link>
                                                <div className="text-muted-foreground">
                                                    {row.order_date}
                                                </div>
                                            </Td>
                                            <Td>{row.supplier_name}</Td>
                                            <Td>
                                                {row.store_name}
                                                <div className="text-muted-foreground">
                                                    {row.branch_name}
                                                </div>
                                            </Td>
                                            <Td>
                                                {row.expected_date ??
                                                    'Not specified'}
                                            </Td>
                                            <Td>
                                                {formatNumber(row.lines_count)}
                                            </Td>
                                            {props.canViewCosts && (
                                                <Td>
                                                    {formatCurrencyAmount(
                                                        row.currency_code,
                                                        row.total_amount,
                                                    )}
                                                </Td>
                                            )}
                                            <Td>
                                                <Badge
                                                    variant={
                                                        [
                                                            'rejected',
                                                            'cancelled',
                                                        ].includes(row.status)
                                                            ? 'destructive'
                                                            : row.status ===
                                                                'received'
                                                              ? 'default'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {label(row.status)}
                                                </Badge>
                                            </Td>
                                        </tr>
                                    ))}
                                    {rows.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-12 text-center text-muted-foreground"
                                            >
                                                No purchase orders match this
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
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function Th({ children }: { children: ReactNode }) {
    return <th className="px-3 py-3 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="px-3 py-4 align-top">{children}</td>;
}
