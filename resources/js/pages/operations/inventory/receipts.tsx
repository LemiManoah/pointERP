import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Branch = {
    id: string;
    name: string;
    code: string;
    default_currency_code: string;
};
type Store = { id: string; branch_id: string; name: string; code: string };
type Unit = { id: string; name: string; code: string; symbol: string | null };
type Item = {
    id: string;
    name: string;
    code: string;
    tracking_type: string;
    is_expires: boolean;
    stock_unit_id: string;
    stock_unit: Unit | null;
};
type Supplier = { id: string; name: string; code: string };
type Receipt = {
    id: string;
    reference: string;
    received_on: string;
    currency_code: string | null;
    total_amount: string | null;
    amount_paid: string | null;
    payment_status: string | null;
    lines_count: number;
    store: Store;
    supplier: Supplier;
};
type Line = {
    inventory_item_id: string;
    quantity: string;
    unit_of_measure_id: string;
    unit_cost: string;
    batch_number: string;
    manufactured_on: string;
    expires_on: string;
};
type Props = {
    receipts: Receipt[];
    branches: Branch[];
    defaultBranchId: string;
    canChangeBranch: boolean;
    canViewCosts: boolean;
    stores: Store[];
    items: Item[];
    units: Unit[];
    suppliers: Supplier[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock balances', href: '/inventory/stock' },
    { title: 'Receive stock', href: '/inventory/receipts' },
];
const emptyLine = (): Line => ({
    inventory_item_id: '',
    quantity: '',
    unit_of_measure_id: '',
    unit_cost: '',
    batch_number: '',
    manufactured_on: '',
    expires_on: '',
});

export default function InventoryReceipts(props: Props) {
    const form = useForm({
        branch_id: props.defaultBranchId,
        inventory_store_id: '',
        supplier_id: '',
        supplier_reference: '',
        received_on: new Date().toISOString().slice(0, 10),
        amount_paid: '0',
        notes: '',
        lines: [emptyLine()],
    });
    const branch = props.branches.find((row) => row.id === form.data.branch_id);
    const stores = props.stores.filter(
        (row) => row.branch_id === form.data.branch_id,
    );
    const total = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) =>
                    sum +
                    Number(line.quantity || 0) * Number(line.unit_cost || 0),
                0,
            ),
        [form.data.lines],
    );
    const updateLine = (index: number, values: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, current) =>
                current === index ? { ...line, ...values } : line,
            ),
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/inventory/receipts', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receive stock" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Receive stock</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record a direct supplier delivery. Purchase-order
                        receipts will use this same goods-receipt ledger.
                    </p>
                </div>
                <form onSubmit={submit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Delivery details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {props.canChangeBranch && (
                                <Field
                                    label="Branch"
                                    error={form.errors.branch_id}
                                >
                                    <SearchableSelect
                                        value={form.data.branch_id}
                                        options={props.branches.map((row) => ({
                                            value: row.id,
                                            label: row.name,
                                            description: row.code,
                                        }))}
                                        onValueChange={(value) => {
                                            form.setData('branch_id', value);
                                            form.setData(
                                                'inventory_store_id',
                                                '',
                                            );
                                        }}
                                        placeholder="Select branch"
                                    />
                                </Field>
                            )}
                            <Field
                                label="Receiving store"
                                error={form.errors.inventory_store_id}
                            >
                                <SearchableSelect
                                    value={form.data.inventory_store_id}
                                    options={stores.map((row) => ({
                                        value: row.id,
                                        label: row.name,
                                        description: row.code,
                                    }))}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'inventory_store_id',
                                            value,
                                        )
                                    }
                                    placeholder="Select store"
                                />
                            </Field>
                            <Field
                                label="Supplier"
                                error={form.errors.supplier_id}
                            >
                                <SearchableSelect
                                    value={form.data.supplier_id}
                                    options={props.suppliers.map((row) => ({
                                        value: row.id,
                                        label: row.name,
                                        description: row.code,
                                    }))}
                                    onValueChange={(value) =>
                                        form.setData('supplier_id', value)
                                    }
                                    placeholder="Select supplier"
                                />
                            </Field>
                            <Field
                                label="Delivery date"
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
                            <Field label="Supplier invoice / delivery note">
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
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                Received items
                            </CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        emptyLine(),
                                    ])
                                }
                            >
                                <Plus />
                                Add item
                            </Button>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {form.data.lines.map((line, index) => {
                                const item = props.items.find(
                                    (row) => row.id === line.inventory_item_id,
                                );
                                return (
                                    <div
                                        key={index}
                                        className="grid gap-4 border-b pb-4 last:border-0 md:grid-cols-2 xl:grid-cols-6"
                                    >
                                        <Field
                                            label="Item"
                                            error={
                                                form.errors[
                                                    `lines.${index}.inventory_item_id`
                                                ]
                                            }
                                        >
                                            <SearchableSelect
                                                value={line.inventory_item_id}
                                                options={props.items.map(
                                                    (row) => ({
                                                        value: row.id,
                                                        label: row.name,
                                                        description: row.code,
                                                    }),
                                                )}
                                                onValueChange={(value) => {
                                                    const selected =
                                                        props.items.find(
                                                            (row) =>
                                                                row.id ===
                                                                value,
                                                        );
                                                    updateLine(index, {
                                                        inventory_item_id:
                                                            value,
                                                        unit_of_measure_id:
                                                            selected?.stock_unit_id ??
                                                            '',
                                                        batch_number: '',
                                                        expires_on: '',
                                                    });
                                                }}
                                                placeholder="Select item"
                                            />
                                        </Field>
                                        <Field
                                            label="Quantity"
                                            error={
                                                form.errors[
                                                    `lines.${index}.quantity`
                                                ]
                                            }
                                        >
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                value={line.quantity}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        quantity:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Received unit"
                                            error={
                                                form.errors[
                                                    `lines.${index}.unit_of_measure_id`
                                                ]
                                            }
                                        >
                                            <SearchableSelect
                                                value={line.unit_of_measure_id}
                                                options={props.units.map(
                                                    (row) => ({
                                                        value: row.id,
                                                        label: row.name,
                                                        description:
                                                            row.symbol ??
                                                            row.code,
                                                    }),
                                                )}
                                                onValueChange={(value) =>
                                                    updateLine(index, {
                                                        unit_of_measure_id:
                                                            value,
                                                    })
                                                }
                                                placeholder="Select unit"
                                            />
                                        </Field>
                                        {props.canViewCosts && (
                                            <Field
                                                label={`Unit cost (${branch?.default_currency_code ?? ''})`}
                                                error={
                                                    form.errors[
                                                        `lines.${index}.unit_cost`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.0001"
                                                    value={line.unit_cost}
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            unit_cost:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        )}
                                        {item?.tracking_type === 'batch' && (
                                            <>
                                                <Field label="Batch number">
                                                    <Input
                                                        value={
                                                            line.batch_number
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                batch_number:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                {item.is_expires && (
                                                    <Field label="Expiry date">
                                                        <Input
                                                            type="date"
                                                            value={
                                                                line.expires_on
                                                            }
                                                            onChange={(event) =>
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
                                            </>
                                        )}
                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Remove item"
                                                disabled={
                                                    form.data.lines.length === 1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter(
                                                            (_, current) =>
                                                                current !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="grid gap-4 pt-6 md:grid-cols-3">
                            {props.canViewCosts && (
                                <>
                                    <Field
                                        label="Amount paid"
                                        error={form.errors.amount_paid}
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            value={form.data.amount_paid}
                                            onChange={(event) =>
                                                form.setData(
                                                    'amount_paid',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            Receipt total
                                        </div>
                                        <div className="mt-2 text-xl font-semibold">
                                            {formatCurrencyAmount(
                                                branch?.default_currency_code ??
                                                    '',
                                                total,
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            Balance due to supplier
                                        </div>
                                        <div className="mt-2 text-xl font-semibold">
                                            {formatCurrencyAmount(
                                                branch?.default_currency_code ??
                                                    '',
                                                Math.max(
                                                    0,
                                                    total -
                                                        Number(
                                                            form.data
                                                                .amount_paid ||
                                                                0,
                                                        ),
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}
                            <div className="md:col-span-3">
                                <Field label="Notes">
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
                            </div>
                            <div className="flex justify-end md:col-span-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
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
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Reference</Th>
                                        <Th>Supplier</Th>
                                        <Th>Store</Th>
                                        <Th>Items</Th>
                                        {props.canViewCosts && (
                                            <>
                                                <Th>Total</Th>
                                                <Th>Paid</Th>
                                                <Th>Payment</Th>
                                            </>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.receipts.map((receipt) => (
                                        <tr
                                            key={receipt.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                {receipt.reference}
                                                <div className="text-muted-foreground">
                                                    {receipt.received_on}
                                                </div>
                                            </Td>
                                            <Td>{receipt.supplier.name}</Td>
                                            <Td>{receipt.store.name}</Td>
                                            <Td>
                                                {formatNumber(
                                                    receipt.lines_count,
                                                )}
                                            </Td>
                                            {props.canViewCosts && (
                                                <>
                                                    <Td>
                                                        {formatCurrencyAmount(
                                                            receipt.currency_code,
                                                            receipt.total_amount,
                                                        )}
                                                    </Td>
                                                    <Td>
                                                        {formatCurrencyAmount(
                                                            receipt.currency_code,
                                                            receipt.amount_paid,
                                                        )}
                                                    </Td>
                                                    <Td>
                                                        {receipt.payment_status?.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </Td>
                                                </>
                                            )}
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

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function Th({ children }: { children: ReactNode }) {
    return <th className="py-3 pr-4 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="py-3 pr-4 align-top capitalize">{children}</td>;
}
