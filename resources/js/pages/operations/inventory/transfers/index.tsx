import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRightLeft,
    Check,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Batch = { id: string; batch_number: string; system_quantity: string };
type Item = {
    id: string;
    name: string;
    code: string;
    stock_unit_id: string;
    unit: string;
    tracking_type: string;
    system_quantity: string;
    batches: Batch[];
};
type Store = {
    id: string;
    name: string;
    code: string;
    branch_name: string;
    items: Item[];
};
type Line = {
    inventory_item_id: string;
    unit_of_measure_id: string;
    inventory_batch_id: string;
    quantity: string;
};
type Transfer = {
    id: string;
    reference: string;
    status: string;
    reason: string;
    decision_reason: string | null;
    source_store: string;
    destination_store: string;
    requested_by: string;
    requested_at: string;
    lines_count: number;
    can_approve: boolean;
    can_reject: boolean;
};
type Props = {
    stores: Store[];
    transferKey: string;
    transfers: Transfer[];
    canCreate: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock movements', href: '/inventory/stock-movements' },
    { title: 'New transfer', href: '/inventory/transfers' },
];
const emptyLine = (): Line => ({
    inventory_item_id: '',
    unit_of_measure_id: '',
    inventory_batch_id: '',
    quantity: '',
});

export default function InventoryTransfer({
    stores,
    transferKey,
    transfers,
    canCreate,
}: Props) {
    const confirm = useConfirmDialog();
    const form = useForm({
        source_store_id: '',
        destination_store_id: '',
        transfer_key: transferKey,
        reason: '',
        lines: [emptyLine()],
    });
    const source = stores.find(
        (store) => store.id === form.data.source_store_id,
    );
    const destination = stores.find(
        (store) => store.id === form.data.destination_store_id,
    );
    const availableItems =
        source?.items.filter((item) =>
            destination?.items.some((candidate) => candidate.id === item.id),
        ) ?? [];

    const updateLine = (index: number, changes: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...changes } : line,
            ),
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/inventory/transfers');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New store transfer" />
            <form
                onSubmit={submit}
                className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Store transfers
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Request stock movement between stores. Balances
                            update only after approval.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/inventory/stock-movements">
                            <ArrowLeft />
                            Back to movements
                        </Link>
                    </Button>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Transfer requests
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Reference</Th>
                                        <Th>Route</Th>
                                        <Th>Items</Th>
                                        <Th>Requested</Th>
                                        <Th>Status</Th>
                                        <Th>Actions</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transfers.map((transfer) => (
                                        <tr
                                            key={transfer.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                <span className="font-medium">
                                                    {transfer.reference}
                                                </span>
                                                <div
                                                    className="max-w-64 truncate text-muted-foreground"
                                                    title={transfer.reason}
                                                >
                                                    {transfer.reason}
                                                </div>
                                            </Td>
                                            <Td>
                                                {transfer.source_store}
                                                <div className="text-muted-foreground">
                                                    to{' '}
                                                    {transfer.destination_store}
                                                </div>
                                            </Td>
                                            <Td>{transfer.lines_count}</Td>
                                            <Td>
                                                {transfer.requested_at}
                                                <div className="text-muted-foreground">
                                                    {transfer.requested_by}
                                                </div>
                                            </Td>
                                            <Td>
                                                <Badge variant="outline">
                                                    {transfer.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                                {transfer.decision_reason && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            transfer.decision_reason
                                                        }
                                                    </div>
                                                )}
                                            </Td>
                                            <Td>
                                                <div className="flex gap-2">
                                                    {transfer.can_approve && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                confirm({
                                                                    title: 'Approve transfer?',
                                                                    description:
                                                                        'Source and destination stock balances will be updated immediately.',
                                                                    confirmLabel:
                                                                        'Approve',
                                                                    onConfirm:
                                                                        () =>
                                                                            router.post(
                                                                                `/inventory/transfers/${transfer.id}/approve`,
                                                                            ),
                                                                })
                                                            }
                                                        >
                                                            <Check />
                                                            Approve
                                                        </Button>
                                                    )}
                                                    {transfer.can_reject && (
                                                        <RejectDialog
                                                            url={`/inventory/transfers/${transfer.id}/reject`}
                                                            title="Reject transfer"
                                                        />
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                    {transfers.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No transfer requests yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                {canCreate && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Transfer route
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <Field
                                    label="Source store"
                                    required
                                    error={form.errors.source_store_id}
                                >
                                    <SearchableSelect
                                        value={form.data.source_store_id}
                                        options={stores.map((store) => ({
                                            value: store.id,
                                            label: store.name,
                                            description: store.branch_name,
                                        }))}
                                        onValueChange={(value) => {
                                            form.setData(
                                                'source_store_id',
                                                value,
                                            );
                                            form.setData('lines', [
                                                emptyLine(),
                                            ]);
                                        }}
                                        placeholder="Select source store"
                                    />
                                </Field>
                                <Field
                                    label="Destination store"
                                    required
                                    error={form.errors.destination_store_id}
                                >
                                    <SearchableSelect
                                        value={form.data.destination_store_id}
                                        options={stores
                                            .filter(
                                                (store) =>
                                                    store.id !==
                                                    form.data.source_store_id,
                                            )
                                            .map((store) => ({
                                                value: store.id,
                                                label: store.name,
                                                description: store.branch_name,
                                            }))}
                                        onValueChange={(value) => {
                                            form.setData(
                                                'destination_store_id',
                                                value,
                                            );
                                            form.setData('lines', [
                                                emptyLine(),
                                            ]);
                                        }}
                                        placeholder="Select destination store"
                                    />
                                </Field>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    Items
                                </CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            emptyLine(),
                                        ])
                                    }
                                    disabled={!source || !destination}
                                >
                                    <Plus />
                                    Add item
                                </Button>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {form.data.lines.map((line, index) => {
                                    const item = availableItems.find(
                                        (candidate) =>
                                            candidate.id ===
                                            line.inventory_item_id,
                                    );
                                    const batch = item?.batches.find(
                                        (candidate) =>
                                            candidate.id ===
                                            line.inventory_batch_id,
                                    );
                                    const available =
                                        batch?.system_quantity ??
                                        item?.system_quantity;
                                    return (
                                        <div
                                            key={index}
                                            className="grid gap-4 border-b pb-4 last:border-0 last:pb-0 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                                        >
                                            <Field
                                                label="Item"
                                                required
                                                error={
                                                    form.errors[
                                                        `lines.${index}.inventory_item_id`
                                                    ]
                                                }
                                            >
                                                <SearchableSelect
                                                    value={
                                                        line.inventory_item_id
                                                    }
                                                    options={availableItems.map(
                                                        (candidate) => ({
                                                            value: candidate.id,
                                                            label: candidate.name,
                                                            description:
                                                                candidate.code,
                                                        }),
                                                    )}
                                                    onValueChange={(value) => {
                                                        const selected =
                                                            availableItems.find(
                                                                (candidate) =>
                                                                    candidate.id ===
                                                                    value,
                                                            );
                                                        updateLine(index, {
                                                            inventory_item_id:
                                                                value,
                                                            unit_of_measure_id:
                                                                selected?.stock_unit_id ??
                                                                '',
                                                            inventory_batch_id:
                                                                '',
                                                            quantity: '',
                                                        });
                                                    }}
                                                    placeholder="Select item"
                                                />
                                            </Field>
                                            {item?.tracking_type === 'batch' ? (
                                                <Field
                                                    label="Batch"
                                                    required
                                                    error={
                                                        form.errors[
                                                            `lines.${index}.inventory_batch_id`
                                                        ]
                                                    }
                                                >
                                                    <SearchableSelect
                                                        value={
                                                            line.inventory_batch_id
                                                        }
                                                        options={item.batches
                                                            .filter(
                                                                (row) =>
                                                                    Number(
                                                                        row.system_quantity,
                                                                    ) > 0,
                                                            )
                                                            .map((row) => ({
                                                                value: row.id,
                                                                label: row.batch_number,
                                                                description: `${formatNumber(row.system_quantity)} ${item.unit}`,
                                                            }))}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateLine(index, {
                                                                inventory_batch_id:
                                                                    value,
                                                            })
                                                        }
                                                        placeholder="Select batch"
                                                    />
                                                </Field>
                                            ) : (
                                                <Field label="Stock unit">
                                                    <Input
                                                        value={item?.unit ?? ''}
                                                        disabled
                                                    />
                                                </Field>
                                            )}
                                            <Field
                                                label={`Quantity${item ? ` (${item.unit})` : ''}`}
                                                required
                                                error={
                                                    form.errors[
                                                        `lines.${index}.quantity`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    min="0.0001"
                                                    step="0.0001"
                                                    value={line.quantity}
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            quantity:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Available:{' '}
                                                    {available === undefined
                                                        ? '-'
                                                        : formatNumber(
                                                              available,
                                                          )}
                                                </p>
                                            </Field>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="self-end"
                                                disabled={
                                                    form.data.lines.length === 1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter(
                                                            (_, lineIndex) =>
                                                                lineIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                title="Remove item"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    );
                                })}
                                <InputError message={form.errors.lines} />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="grid gap-4 pt-6">
                                <Field
                                    label="Reason"
                                    required
                                    error={form.errors.reason}
                                >
                                    <Textarea
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Why is this stock being transferred?"
                                    />
                                </Field>
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !source ||
                                            !destination
                                        }
                                    >
                                        <ArrowRightLeft />
                                        Submit for approval
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </form>
        </AppLayout>
    );
}

function RejectDialog({ url, title }: { url: string; title: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { onSuccess: () => setOpen(false) });
    };
    return (
        <>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setOpen(true)}
            >
                <X />
                Reject
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            Give the requester a clear reason.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <Field
                            label="Reason"
                            required
                            error={form.errors.reason}
                        >
                            <Textarea
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </Field>
                        <DialogFooter>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={form.processing}
                            >
                                Reject
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Field({
    label,
    required = false,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
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
