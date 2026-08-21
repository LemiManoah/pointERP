import { Head, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Branch = { id: string; name: string; code: string };
type Store = {
    id: string;
    branch_id: string;
    name: string;
    item_ids: string[];
};
type Item = {
    id: string;
    name: string;
    code: string;
    stock_unit_id: string;
    stock_unit: { name: string; symbol: string | null } | null;
    tracking_type: string;
};
type Batch = { id: string; inventory_item_id: string; batch_number: string };
type Movement = {
    id: string;
    movement_type: string;
    quantity: string;
    reason: string;
    posted_at: string;
    status: string;
    store: Store;
    item: Item;
    posted_by: { name: string };
};
type Props = {
    movements: Movement[];
    branches: Branch[];
    defaultBranchId: string;
    canChangeBranch: boolean;
    stores: Store[];
    items: Item[];
    batches: Batch[];
    can: {
        adjust: boolean;
        issue: boolean;
        return: boolean;
        transfer: boolean;
        reverse: boolean;
    };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock balances', href: '/inventory/stock' },
    { title: 'Stock movements', href: '/inventory/stock-movements' },
];

export default function InventoryMovements(props: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Stock movements" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock movements
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Record reconciliations, issues, returns, opening
                            balances and transfers.
                        </p>
                    </div>
                    <MovementDialog {...props} />
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Recorded</Th>
                                        <Th>Item</Th>
                                        <Th>Store</Th>
                                        <Th>Movement</Th>
                                        <Th>Unit</Th>
                                        <Th>Quantity</Th>
                                        <Th>Reason</Th>
                                        <Th>Status</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.movements.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                {row.posted_at}
                                                <div className="text-muted-foreground">
                                                    {row.posted_by.name}
                                                </div>
                                            </Td>
                                            <Td>
                                                {row.item.name}
                                                <div className="text-muted-foreground">
                                                    {row.item.code}
                                                </div>
                                            </Td>
                                            <Td>{row.store.name}</Td>
                                            <Td>
                                                <Badge variant="outline">
                                                    {label(row.movement_type)}
                                                </Badge>
                                            </Td>
                                            <Td>
                                                {row.item.stock_unit?.symbol ??
                                                    row.item.stock_unit?.name}
                                            </Td>
                                            <Td>
                                                {formatNumber(row.quantity)}
                                            </Td>
                                            <Td>{row.reason}</Td>
                                            <Td>{label(row.status)}</Td>
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

function MovementDialog(props: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        branch_id: props.defaultBranchId,
        inventory_store_id: '',
        destination_store_id: '',
        inventory_item_id: '',
        movement_type: props.can.adjust
            ? 'adjustment'
            : props.can.issue
              ? 'issue'
              : 'return',
        adjustment_direction: 'increase',
        original_quantity: '',
        original_unit_id: '',
        inventory_batch_id: '',
        reason: '',
        return_to: 'movements',
    });
    const stores = props.stores.filter(
        (row) => row.branch_id === form.data.branch_id,
    );
    const source = props.stores.find(
        (row) => row.id === form.data.inventory_store_id,
    );
    const items = props.items.filter((row) =>
        source?.item_ids.includes(row.id),
    );
    const item = props.items.find(
        (row) => row.id === form.data.inventory_item_id,
    );
    const destinations = props.stores.filter(
        (row) =>
            row.id !== source?.id &&
            row.item_ids.includes(form.data.inventory_item_id),
    );
    const options = [
        ...(props.can.adjust
            ? [
                  { value: 'adjustment', label: 'Reconciliation' },
                  { value: 'opening_balance', label: 'Opening balance' },
              ]
            : []),
        ...(props.can.issue ? [{ value: 'issue', label: 'Issue stock' }] : []),
        ...(props.can.return
            ? [{ value: 'return', label: 'Return to store' }]
            : []),
        ...(props.can.transfer
            ? [{ value: 'transfer', label: 'Transfer between stores' }]
            : []),
    ];
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            `/inventory/stores/${form.data.inventory_store_id}/items/${form.data.inventory_item_id}/movements`,
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    };
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Record movement
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record stock movement</DialogTitle>
                    <DialogDescription>
                        Use Receive stock for supplier deliveries. This form
                        records internal inventory changes.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    {props.canChangeBranch && (
                        <Field label="Branch">
                            <SearchableSelect
                                value={form.data.branch_id}
                                options={props.branches.map((row) => ({
                                    value: row.id,
                                    label: row.name,
                                    description: row.code,
                                }))}
                                onValueChange={(value) => {
                                    form.setData('branch_id', value);
                                    form.setData('inventory_store_id', '');
                                }}
                                placeholder="Select branch"
                            />
                        </Field>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Source store"
                            error={form.errors.inventory_store_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_store_id}
                                options={stores.map((row) => ({
                                    value: row.id,
                                    label: row.name,
                                }))}
                                onValueChange={(value) => {
                                    form.setData('inventory_store_id', value);
                                    form.setData('inventory_item_id', '');
                                }}
                                placeholder="Select store"
                            />
                        </Field>
                        <Field label="Movement">
                            <NativeSelect
                                value={form.data.movement_type}
                                onChange={(event) =>
                                    form.setData(
                                        'movement_type',
                                        event.target.value,
                                    )
                                }
                            >
                                {options.map((option) => (
                                    <NativeSelectOption
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>
                    <Field label="Item" error={form.errors.inventory_item_id}>
                        <SearchableSelect
                            value={form.data.inventory_item_id}
                            options={items.map((row) => ({
                                value: row.id,
                                label: row.name,
                                description: row.code,
                            }))}
                            onValueChange={(value) => {
                                const selected = props.items.find(
                                    (row) => row.id === value,
                                );
                                form.setData('inventory_item_id', value);
                                form.setData(
                                    'original_unit_id',
                                    selected?.stock_unit_id ?? '',
                                );
                            }}
                            placeholder="Select item"
                        />
                    </Field>
                    {form.data.movement_type === 'transfer' && (
                        <Field
                            label="Destination store"
                            error={form.errors.destination_store_id}
                        >
                            <SearchableSelect
                                value={form.data.destination_store_id}
                                options={destinations.map((row) => ({
                                    value: row.id,
                                    label: row.name,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('destination_store_id', value)
                                }
                                placeholder="Select destination"
                            />
                        </Field>
                    )}
                    {form.data.movement_type === 'adjustment' && (
                        <Field label="Reconciliation direction">
                            <NativeSelect
                                value={form.data.adjustment_direction}
                                onChange={(event) =>
                                    form.setData(
                                        'adjustment_direction',
                                        event.target.value,
                                    )
                                }
                            >
                                <NativeSelectOption value="increase">
                                    Increase to match count
                                </NativeSelectOption>
                                <NativeSelectOption value="decrease">
                                    Decrease to match count
                                </NativeSelectOption>
                            </NativeSelect>
                        </Field>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={`Quantity${item?.stock_unit ? ` (${item.stock_unit.symbol ?? item.stock_unit.name})` : ''}`}
                            error={form.errors.original_quantity}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.original_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'original_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        {item?.tracking_type === 'batch' && (
                            <Field
                                label="Batch"
                                error={form.errors.inventory_batch_id}
                            >
                                <SearchableSelect
                                    value={form.data.inventory_batch_id}
                                    options={props.batches
                                        .filter(
                                            (row) =>
                                                row.inventory_item_id ===
                                                item.id,
                                        )
                                        .map((row) => ({
                                            value: row.id,
                                            label: row.batch_number,
                                        }))}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'inventory_batch_id',
                                            value,
                                        )
                                    }
                                    placeholder="Select batch"
                                />
                            </Field>
                        )}
                    </div>
                    <Field label="Reason" error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            !form.data.inventory_store_id ||
                            !form.data.inventory_item_id
                        }
                    >
                        <ArrowRightLeft />
                        Record movement
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
function Field({
    label: heading,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{heading}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function Th({ children }: { children: ReactNode }) {
    return <th className="py-3 pr-4 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="py-3 pr-4 align-top">{children}</td>;
}
