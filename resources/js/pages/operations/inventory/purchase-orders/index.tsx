import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { TabsContent } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ProcurementOptions } from '../procurement-types';
import {
    PurchaseOrderReceiptForm,
    type PurchaseOrderReceiptOptions,
} from '../receipts';
import { PurchaseOrderForm } from './partials/purchase-order-form';
import {
    PurchaseOrderTabs,
    type PurchaseOrderTab,
} from './partials/purchase-order-tabs';

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
type Props = {
    purchaseOrders: Row[];
    canCreate: boolean;
    canReceive: boolean;
    canViewCosts: boolean;
    purchaseOrderOptions: ProcurementOptions | null;
    receiptOptions: PurchaseOrderReceiptOptions | null;
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase orders', href: '/inventory/purchase-orders' },
];
const statusOptions = [
    'all',
    'draft',
    'submitted',
    'returned',
    'approved',
    'partially_received',
    'received',
    'closed',
    'rejected',
    'cancelled',
].map((value) => ({
    value,
    label: value === 'all' ? 'All statuses' : label(value),
}));

export default function PurchaseOrdersIndex(props: Props) {
    const [workspaceTab, setWorkspaceTab] =
        useState<PurchaseOrderTab>('orders');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const rows = useMemo(
        () =>
            props.purchaseOrders.filter(
                (row) =>
                    (status === 'all' || row.status === status) &&
                    (!term ||
                        `${row.order_number} ${row.supplier_name} ${row.store_name}`
                            .toLowerCase()
                            .includes(term)),
            ),
        [props.purchaseOrders, status, term],
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
                <PurchaseOrderTabs
                    active={workspaceTab}
                    canReceive={props.canReceive}
                    canCreate={props.canCreate}
                    onValueChange={setWorkspaceTab}
                >
                    <TabsContent value="orders" className="grid gap-6">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div className="relative w-full md:max-w-md">
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
                            <div className="flex w-full items-center gap-3 md:w-auto">
                                <SearchableSelect
                                    value={status}
                                    options={statusOptions}
                                    onValueChange={setStatus}
                                    placeholder="All statuses"
                                    searchPlaceholder="Search statuses..."
                                    className="min-w-0 flex-1 md:w-52 md:flex-none"
                                />
                                {props.canCreate && (
                                    <Button
                                        type="button"
                                        className="shrink-0"
                                        onClick={() =>
                                            setWorkspaceTab('create')
                                        }
                                    >
                                        <Plus />
                                        New purchase order
                                    </Button>
                                )}
                            </div>
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
                                                {props.canViewCosts && (
                                                    <Th>Total</Th>
                                                )}
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
                                                        {formatNumber(
                                                            row.lines_count,
                                                        )}
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
                                                                ].includes(
                                                                    row.status,
                                                                )
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
                                                        No purchase orders match
                                                        this view.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    {props.canReceive && props.receiptOptions && (
                        <TabsContent value="receive">
                            <PurchaseOrderReceiptForm
                                {...props.receiptOptions}
                            />
                        </TabsContent>
                    )}
                    {props.canCreate && props.purchaseOrderOptions && (
                        <TabsContent value="create">
                            <PurchaseOrderForm
                                options={props.purchaseOrderOptions}
                            />
                        </TabsContent>
                    )}
                </PurchaseOrderTabs>
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
