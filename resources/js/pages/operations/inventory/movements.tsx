import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeft, ClipboardCheck, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Store = { id: string; branch_id: string; name: string };
type Movement = {
    id: string;
    movement_type: string;
    quantity: string;
    reason: string;
    posted_at: string;
    posted_on: string;
    status: string;
    source: string;
    store: Store;
    item: {
        id: string;
        name: string;
        code: string;
        stock_unit: { name: string; symbol: string | null } | null;
    };
    posted_by: { name: string };
};
type Props = {
    movements: Movement[];
    stores: Store[];
    can: { adjust: boolean; transfer: boolean; reverse: boolean };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock balances', href: '/inventory/stock' },
    { title: 'Stock movements', href: '/inventory/stock-movements' },
];

export default function InventoryMovements({ movements, stores, can }: Props) {
    const [search, setSearch] = useState('');
    const [movementType, setMovementType] = useState('all');
    const [storeId, setStoreId] = useState('all');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const filtered = useMemo(
        () =>
            movements.filter(
                (movement) =>
                    (movementType === 'all' ||
                        movement.movement_type === movementType) &&
                    (storeId === 'all' || movement.store.id === storeId) &&
                    (!dateFrom || movement.posted_on >= dateFrom) &&
                    (!dateTo || movement.posted_on <= dateTo) &&
                    (!term ||
                        `${movement.item.name} ${movement.item.code} ${movement.store.name} ${movement.reason} ${movement.source}`
                            .toLowerCase()
                            .includes(term)),
            ),
        [dateFrom, dateTo, movementType, movements, storeId, term],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock movements" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock movements
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            The read-only history of receipts, requisition
                            issues, returns, transfers and stock corrections.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.adjust && (
                            <Button asChild variant="outline">
                                <Link href="/inventory/stock-counts">
                                    <ClipboardCheck />
                                    New stock count
                                </Link>
                            </Button>
                        )}
                        {can.transfer && (
                            <Button asChild>
                                <Link href="/inventory/transfers">
                                    <ArrowRightLeft />
                                    New transfer
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="relative sm:col-span-2">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search item, store, reason or source"
                            className="pl-9"
                        />
                    </div>
                    <NativeSelect value={movementType} onChange={(event) => setMovementType(event.target.value)} aria-label="Movement type filter">
                        <NativeSelectOption value="all">All movements</NativeSelectOption>
                        {['receipt', 'issue', 'return', 'transfer_out', 'transfer_in', 'adjustment', 'opening_balance', 'reversal'].map((type) => (
                            <NativeSelectOption key={type} value={type}>{label(type)}</NativeSelectOption>
                        ))}
                    </NativeSelect>
                    <NativeSelect value={storeId} onChange={(event) => setStoreId(event.target.value)} aria-label="Store filter">
                        <NativeSelectOption value="all">All stores</NativeSelectOption>
                        {stores.map((store) => <NativeSelectOption key={store.id} value={store.id}>{store.name}</NativeSelectOption>)}
                    </NativeSelect>
                    <div className="grid grid-cols-2 gap-2">
                        <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} aria-label="Recorded from" />
                        <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} aria-label="Recorded to" />
                    </div>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead><tr className="border-b text-left text-muted-foreground"><Th>Recorded</Th><Th>Item</Th><Th>Store</Th><Th>Movement</Th><Th>Unit</Th><Th>Quantity</Th><Th>Reason</Th><Th>Source</Th><Th>Status</Th></tr></thead>
                                <tbody>
                                    {filtered.map((row) => (
                                        <tr key={row.id} className="border-b last:border-0">
                                            <Td>{formatDateTime(row.posted_at)}<div className="text-muted-foreground">{row.posted_by.name}</div></Td>
                                            <Td>{row.item.name}<div className="text-muted-foreground">{row.item.code}</div></Td>
                                            <Td>{row.store.name}</Td>
                                            <Td><Badge variant="outline">{label(row.movement_type)}</Badge></Td>
                                            <Td>{row.item.stock_unit?.symbol ?? row.item.stock_unit?.name}</Td>
                                            <Td>{formatNumber(row.quantity)}</Td>
                                            <Td>{row.reason}</Td>
                                            <Td>{row.source}</Td>
                                            <Td>{label(row.status)}</Td>
                                        </tr>
                                    ))}
                                    {filtered.length === 0 && <tr><td colSpan={9} className="py-12 text-center text-muted-foreground">No stock movements match these filters.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
function Th({ children }: { children: ReactNode }) { return <th className="py-3 pr-4 font-medium">{children}</th>; }
function Td({ children }: { children: ReactNode }) { return <td className="py-3 pr-4 align-top">{children}</td>; }
