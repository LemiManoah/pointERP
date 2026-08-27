import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatDate, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Reference = { id: string; name: string; code: string };
type PurchaseOrderLine = {
    id: string;
    item_name: string;
    item_code: string;
    unit_symbol: string;
    outstanding_quantity: string;
    unit_cost: string | null;
    tracking_type: string;
    is_expires: boolean;
};
type PurchaseOrder = {
    id: string;
    order_number: string;
    currency_code: string;
    branch: Reference;
    store: Reference;
    supplier: Reference;
    lines: PurchaseOrderLine[];
};
type Receipt = {
    id: string;
    reference: string;
    received_on: string;
    currency_code: string | null;
    total_amount: string | null;
    inspection_status: string;
    lines_count: number;
    store: Pick<Reference, 'id' | 'name'>;
    supplier: Pick<Reference, 'id' | 'name'>;
    purchase_order: { id: string; order_number: string };
};
type ReceiptLine = {
    receive: boolean;
    purchase_order_line_id: string;
    quantity: string;
    accepted_quantity: string;
    rejected_quantity: string;
    rejection_reason: string;
    batch_number: string;
    manufactured_on: string;
    expires_on: string;
};
type Props = {
    receipts: Receipt[];
    canViewCosts: boolean;
    purchaseOrders: PurchaseOrder[];
    selectedPurchaseOrderId: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock balances', href: '/inventory/stock' },
    { title: 'Receive purchase order', href: '/inventory/receipts' },
];

const receiptLines = (order?: PurchaseOrder): ReceiptLine[] =>
    order?.lines.map((line) => ({
        receive: true,
        purchase_order_line_id: line.id,
        quantity: line.outstanding_quantity,
        accepted_quantity: line.outstanding_quantity,
        rejected_quantity: '0',
        rejection_reason: '',
        batch_number: '',
        manufactured_on: '',
        expires_on: '',
    })) ?? [];

export default function InventoryReceipts(props: Props) {
    const initialOrder = props.purchaseOrders.find(
        (order) => order.id === props.selectedPurchaseOrderId,
    );
    const form = useForm({
        purchase_order_id: initialOrder?.id ?? '',
        supplier_reference: '',
        received_on: new Date().toISOString().slice(0, 10),
        notes: '',
        lines: receiptLines(initialOrder),
    });
    const purchaseOrder = props.purchaseOrders.find(
        (order) => order.id === form.data.purchase_order_id,
    );
    const total = useMemo(
        () =>
            form.data.lines.reduce((sum, line) => {
                if (!line.receive) return sum;
                const orderLine = purchaseOrder?.lines.find(
                    (candidate) => candidate.id === line.purchase_order_line_id,
                );
                return (
                    sum +
                    Number(line.accepted_quantity || 0) *
                        Number(orderLine?.unit_cost || 0)
                );
            }, 0),
        [form.data.lines, purchaseOrder],
    );
    const updateLine = (index: number, values: Partial<ReceiptLine>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, current) =>
                current === index ? { ...line, ...values } : line,
            ),
        );
    const selectPurchaseOrder = (id: string) => {
        const order = props.purchaseOrders.find(
            (candidate) => candidate.id === id,
        );
        form.setData((data) => ({
            ...data,
            purchase_order_id: id,
            lines: receiptLines(order),
        }));
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines
                .filter((line) => line.receive)
                .map(({ receive: _receive, ...line }) => line),
        }));
        form.post('/inventory/receipts', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receive purchase order" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Receive purchase order
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Inspect an approved supplier delivery. Only accepted
                        quantities increase stock.
                    </p>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Delivery
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <Field
                                label="Purchase order"
                                required
                                error={form.errors.purchase_order_id}
                            >
                                <SearchableSelect
                                    value={form.data.purchase_order_id}
                                    options={props.purchaseOrders.map(
                                        (order) => ({
                                            value: order.id,
                                            label: order.order_number,
                                            description: `${order.supplier.name} - ${order.store.name}`,
                                        }),
                                    )}
                                    onValueChange={selectPurchaseOrder}
                                    placeholder="Select approved PO"
                                />
                            </Field>
                            <Field
                                label="Delivery date"
                                required
                                error={form.errors.received_on}
                            >
                                <Input
                                    type="date"
                                    value={form.data.received_on}
                                    onChange={(event) =>
                                        form.setData(
                                            'received_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Supplier delivery note / invoice">
                                <Input
                                    value={form.data.supplier_reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'supplier_reference',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {props.canViewCosts && purchaseOrder && (
                                <div className="grid content-end gap-1 rounded-md border px-3 py-2">
                                    <span className="text-xs text-muted-foreground">
                                        Accepted value
                                    </span>
                                    <span className="font-semibold">
                                        {formatCurrencyAmount(
                                            purchaseOrder.currency_code,
                                            total,
                                        )}
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {purchaseOrder && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Purchase order context
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-3">
                                <Summary
                                    label="Supplier"
                                    value={purchaseOrder.supplier.name}
                                />
                                <Summary
                                    label="Receiving store"
                                    value={purchaseOrder.store.name}
                                />
                                <Summary
                                    label="Branch"
                                    value={purchaseOrder.branch.name}
                                />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Delivery inspection
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {!purchaseOrder ? (
                                <div className="flex min-h-40 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                                    <ClipboardCheck className="size-8" />
                                    <p>
                                        Select an approved purchase order to
                                        inspect its outstanding items.
                                    </p>
                                </div>
                            ) : (
                                <div className="grid gap-5">
                                    {form.data.lines.map((line, index) => {
                                        const orderLine =
                                            purchaseOrder.lines.find(
                                                (candidate) =>
                                                    candidate.id ===
                                                    line.purchase_order_line_id,
                                            );
                                        if (!orderLine) return null;

                                        return (
                                            <div
                                                key={
                                                    line.purchase_order_line_id
                                                }
                                                className="grid gap-4 border-b pb-5 last:border-0 xl:grid-cols-[minmax(220px,1.5fr)_repeat(3,minmax(120px,1fr))]"
                                            >
                                                <div className="flex gap-3">
                                                    <Checkbox
                                                        checked={line.receive}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            updateLine(index, {
                                                                receive:
                                                                    checked ===
                                                                    true,
                                                            })
                                                        }
                                                        aria-label={`Receive ${orderLine.item_name}`}
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="truncate font-medium">
                                                            {
                                                                orderLine.item_name
                                                            }
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {
                                                                orderLine.item_code
                                                            }{' '}
                                                            · Outstanding{' '}
                                                            {formatNumber(
                                                                orderLine.outstanding_quantity,
                                                            )}{' '}
                                                            {
                                                                orderLine.unit_symbol
                                                            }
                                                        </div>
                                                    </div>
                                                </div>
                                                <Field
                                                    label={`Delivered (${orderLine.unit_symbol})`}
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
                                                        disabled={!line.receive}
                                                        value={line.quantity}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                quantity:
                                                                    event.target
                                                                        .value,
                                                                accepted_quantity:
                                                                    event.target
                                                                        .value,
                                                                rejected_quantity:
                                                                    '0',
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label={`Accepted (${orderLine.unit_symbol})`}
                                                    required
                                                    error={
                                                        form.errors[
                                                            `lines.${index}.accepted_quantity`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                        disabled={!line.receive}
                                                        value={
                                                            line.accepted_quantity
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                accepted_quantity:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label={`Rejected (${orderLine.unit_symbol})`}
                                                    required
                                                    error={
                                                        form.errors[
                                                            `lines.${index}.rejected_quantity`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                        disabled={!line.receive}
                                                        value={
                                                            line.rejected_quantity
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                rejected_quantity:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                {line.receive &&
                                                    Number(
                                                        line.rejected_quantity,
                                                    ) > 0 && (
                                                        <div className="xl:col-span-3 xl:col-start-2">
                                                            <Field
                                                                label="Rejection reason"
                                                                required
                                                                error={
                                                                    form.errors[
                                                                        `lines.${index}.rejection_reason`
                                                                    ]
                                                                }
                                                            >
                                                                <Input
                                                                    placeholder="e.g. damaged in transit, spoilt or wrong specification"
                                                                    value={
                                                                        line.rejection_reason
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                rejection_reason:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                        </div>
                                                    )}
                                                {line.receive &&
                                                    Number(
                                                        line.accepted_quantity,
                                                    ) > 0 &&
                                                    orderLine.tracking_type ===
                                                        'batch' && (
                                                        <div className="grid gap-4 md:grid-cols-3 xl:col-span-3 xl:col-start-2">
                                                            <Field
                                                                label="Batch number"
                                                                required
                                                                error={
                                                                    form.errors[
                                                                        `lines.${index}.batch_number`
                                                                    ]
                                                                }
                                                            >
                                                                <Input
                                                                    value={
                                                                        line.batch_number
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                batch_number:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                            <Field label="Manufactured on">
                                                                <Input
                                                                    type="date"
                                                                    value={
                                                                        line.manufactured_on
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                manufactured_on:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </Field>
                                                            {orderLine.is_expires && (
                                                                <Field
                                                                    label="Expires on"
                                                                    required
                                                                    error={
                                                                        form
                                                                            .errors[
                                                                            `lines.${index}.expires_on`
                                                                        ]
                                                                    }
                                                                >
                                                                    <Input
                                                                        type="date"
                                                                        value={
                                                                            line.expires_on
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                index,
                                                                                {
                                                                                    expires_on:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                </Field>
                                                            )}
                                                        </div>
                                                    )}
                                            </div>
                                        );
                                    })}
                                    <InputError message={form.errors.lines} />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="grid gap-4 pt-6">
                            <Field label="Receipt notes">
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !purchaseOrder ||
                                        !form.data.lines.some(
                                            (line) => line.receive,
                                        )
                                    }
                                >
                                    Record receipt
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent receipts
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <Th>Receipt</Th>
                                    <Th>Purchase order</Th>
                                    <Th>Supplier</Th>
                                    <Th>Store</Th>
                                    <Th>Items</Th>
                                    <Th>Inspection</Th>
                                    {props.canViewCosts && <Th>Total</Th>}
                                </tr>
                            </thead>
                            <tbody>
                                {props.receipts.map((receipt) => (
                                    <tr
                                        key={receipt.id}
                                        className="border-b last:border-0"
                                    >
                                        <Td>
                                            <Link
                                                href={`/inventory/receipts/${receipt.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {receipt.reference}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                {formatDate(receipt.received_on)}
                                            </div>
                                        </Td>
                                        <Td>
                                            <Link
                                                href={`/inventory/purchase-orders/${receipt.purchase_order.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {receipt.purchase_order.order_number}
                                            </Link>
                                        </Td>
                                        <Td>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <span className="block max-w-48 truncate">
                                                        {receipt.supplier.name}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    {receipt.supplier.name}
                                                </TooltipContent>
                                            </Tooltip>
                                        </Td>
                                        <Td>{receipt.store.name}</Td>
                                        <Td>
                                            {formatNumber(receipt.lines_count)}
                                        </Td>
                                        <Td>
                                            {receipt.inspection_status.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </Td>
                                        {props.canViewCosts && (
                                            <Td>
                                                {formatCurrencyAmount(
                                                    receipt.currency_code,
                                                    receipt.total_amount,
                                                )}
                                            </Td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    error,
    required = false,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
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
function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}
function Th({ children }: { children: ReactNode }) {
    return (
        <th className="py-3 pr-4 font-medium whitespace-nowrap">{children}</th>
    );
}
function Td({ children }: { children: ReactNode }) {
    return (
        <td className="py-3 pr-4 align-top whitespace-nowrap capitalize">
            {children}
        </td>
    );
}
