import { Head, Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Row = {
    id: string;
    item_id: string;
    item_code: string;
    item_name: string;
    unit: string;
    store_name: string;
    branch_name: string;
    minimum_stock: string | null;
    is_low_stock: boolean;
    on_hand: string;
    reserved: string;
    available: string;
};
type Props = {
    rows: Row[];
    summary: { stocked_items: number; stores: number; low_stock: number };
    canExport: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock balances', href: '/inventory/stock' },
];

export default function InventoryStockIndex({
    rows,
    summary,
    canExport,
}: Props) {
    const [status, setStatus] = useState<'all' | 'low'>('all');
    const [search, setSearch] = useState('');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const filtered = useMemo(
        () =>
            rows.filter(
                (row) =>
                    (status === 'all' || row.is_low_stock) &&
                    (!term ||
                        `${row.item_code} ${row.item_name} ${row.store_name} ${row.branch_name}`
                            .toLowerCase()
                            .includes(term)),
            ),
        [rows, status, term],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock balances" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock balances
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            On-hand, reserved and available quantities by
                            operational store.
                        </p>
                    </div>
                    {canExport && (
                        <Button asChild variant="outline">
                            <a href="/inventory/stock/export">
                                <Download />
                                Export ledger
                            </a>
                        </Button>
                    )}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Metric
                        label="Stocked item locations"
                        value={summary.stocked_items}
                    />
                    <Metric label="Operational stores" value={summary.stores} />
                    <Metric
                        label="Low-stock warnings"
                        value={summary.low_stock}
                        warning={summary.low_stock > 0}
                    />
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="relative w-full max-w-md">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search item, store or branch"
                            className="pl-9"
                        />
                    </div>
                    <Tabs
                        value={status}
                        onValueChange={(value) =>
                            setStatus(value as 'all' | 'low')
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">All stock</TabsTrigger>
                            <TabsTrigger value="low">Low stock</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Item</Th>
                                        <Th>Store</Th>
                                        <Th>Unit</Th>
                                        <Th>On hand</Th>
                                        <Th>Reserved</Th>
                                        <Th>Available</Th>
                                        <Th>Minimum warning</Th>
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
                                                    href={`/inventory/items/${row.item_id}?tab=stock`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.item_name}
                                                </Link>
                                                <div className="text-muted-foreground">
                                                    {row.item_code}
                                                </div>
                                            </Td>
                                            <Td>
                                                {row.store_name}
                                                <div className="text-muted-foreground">
                                                    {row.branch_name}
                                                </div>
                                            </Td>
                                            <Td>{row.unit}</Td>
                                            <Td>{formatNumber(row.on_hand)}</Td>
                                            <Td>
                                                {formatNumber(row.reserved)}
                                            </Td>
                                            <Td>
                                                {formatNumber(row.available)}
                                            </Td>
                                            <Td>
                                                {row.minimum_stock === null
                                                    ? 'Not set'
                                                    : formatNumber(
                                                          row.minimum_stock,
                                                      )}
                                            </Td>
                                            <Td>
                                                {row.is_low_stock ? (
                                                    <Badge variant="destructive">
                                                        Low stock
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        In range
                                                    </Badge>
                                                )}
                                            </Td>
                                        </tr>
                                    ))}
                                    {filtered.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="py-12 text-center text-muted-foreground"
                                            >
                                                No stock balances match these
                                                filters.
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

function Metric({
    label,
    value,
    warning = false,
}: {
    label: string;
    value: number;
    warning?: boolean;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    className={
                        warning
                            ? 'text-2xl font-semibold text-destructive'
                            : 'text-2xl font-semibold'
                    }
                >
                    {formatNumber(value)}
                </div>
            </CardContent>
        </Card>
    );
}
function Th({ children }: { children: ReactNode }) {
    return <th className="py-3 pr-4 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="py-3 pr-4 align-top">{children}</td>;
}
