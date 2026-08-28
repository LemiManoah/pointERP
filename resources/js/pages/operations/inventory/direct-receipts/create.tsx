import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Unit = { id: string; name: string; symbol: string | null };
type Item = {
    id: string;
    name: string;
    code: string;
    stock_unit_id: string;
    unit: string;
    tracking_type: string;
    is_expires: boolean;
    units: Unit[];
};
type Store = {
    id: string;
    branch_id: string;
    name: string;
    code: string;
    branch_name: string;
    items: Item[];
};
type Company = {
    id: string;
    branch_id: string | null;
    name: string;
    code: string;
    type: string;
};
type Line = {
    key: string;
    inventory_item_id: string;
    unit_of_measure_id: string;
    quantity: string;
    batch_number: string;
    manufactured_on: string;
    expires_on: string;
};
type Props = {
    stores: Store[];
    defaultStoreId: string | null;
    companies: Company[];
    receiptKey: string;
    receivedOn: string;
    returnTo: string;
    reasons: Array<{ value: string; label: string }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Stock movements', href: '/inventory/stock-movements' },
    { title: 'Add new stock', href: '/inventory/add-stock' },
];

let nextLine = 0;
const blankLine = (): Line => ({
    key: `stock-line-${Date.now()}-${nextLine++}`,
    inventory_item_id: '',
    unit_of_measure_id: '',
    quantity: '',
    batch_number: '',
    manufactured_on: '',
    expires_on: '',
});

export default function AddStock({
    stores,
    defaultStoreId,
    companies,
    receiptKey,
    receivedOn,
    returnTo,
    reasons,
}: Props) {
    const confirm = useConfirmDialog();
    const form = useForm({
        receipt_key: receiptKey,
        return_to: returnTo,
        inventory_store_id: defaultStoreId ?? stores[0]?.id ?? '',
        source_company_id: '',
        received_on: receivedOn,
        source_reference: '',
        reason: '',
        lines: [blankLine()],
    });
    const store = stores.find(
        (candidate) => candidate.id === form.data.inventory_store_id,
    );
    const availableCompanies = companies.filter(
        (company) =>
            company.branch_id === null ||
            company.branch_id === store?.branch_id,
    );

    function updateLine(index: number, changes: Partial<Line>) {
        form.setData(
            'lines',
            form.data.lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...changes } : line,
            ),
        );
    }

    function selectItem(index: number, itemId: string) {
        const item = store?.items.find((candidate) => candidate.id === itemId);
        updateLine(index, {
            inventory_item_id: itemId,
            unit_of_measure_id: item?.stock_unit_id ?? '',
            batch_number: '',
            manufactured_on: '',
            expires_on: '',
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (form.processing) return;

        const changes = form.data.lines
            .map((line) => {
                const item = store?.items.find(
                    (candidate) => candidate.id === line.inventory_item_id,
                );
                const unit = item?.units.find(
                    (candidate) => candidate.id === line.unit_of_measure_id,
                );

                return item && line.quantity
                    ? `${line.quantity} ${unit?.symbol ?? unit?.name ?? item.unit} ${item.name}`
                    : null;
            })
            .filter((change): change is string => change !== null);
        const visibleChanges = changes.slice(0, 3).join(', ');
        const remaining = changes.length - 3;
        const quantitySummary = visibleChanges
            ? `${visibleChanges}${remaining > 0 ? ` and ${remaining} more` : ''}.`
            : 'The entered item quantities will be added.';
        const reason = reasons.find(
            (option) => option.value === form.data.reason,
        )?.label;

        confirm({
            title: 'Add this stock now?',
            description: `This will immediately increase stock in ${store?.name ?? 'the selected store'}${reason ? ` as ${reason.toLowerCase()}` : ''}. ${quantitySummary} There is no approval step after confirmation.`,
            confirmLabel: 'Add stock',
            onConfirm: () => form.post('/inventory/add-stock'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add new stock" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Add new stock
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Record stock received outside the purchase order
                            process.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={returnTo}>
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardContent className="grid gap-6 pt-6">
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <Field label="Destination store" required>
                                    <SearchableSelect
                                        value={form.data.inventory_store_id}
                                        onValueChange={(value) => {
                                            form.setData((data) => ({
                                                ...data,
                                                inventory_store_id: value,
                                                source_company_id: '',
                                                lines: [blankLine()],
                                            }));
                                        }}
                                        options={stores.map((row) => ({
                                            value: row.id,
                                            label: `${row.name} - ${row.branch_name}`,
                                        }))}
                                        placeholder="Select store"
                                        searchPlaceholder="Search stores..."
                                    />
                                    <InputError
                                        message={form.errors.inventory_store_id}
                                    />
                                </Field>
                                <Field label="Source company">
                                    <SearchableSelect
                                        value={form.data.source_company_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'source_company_id',
                                                value,
                                            )
                                        }
                                        options={[
                                            {
                                                value: '',
                                                label: 'No company selected',
                                            },
                                            ...availableCompanies.map(
                                                (row) => ({
                                                    value: row.id,
                                                    label: `${row.name} (${label(row.type)})`,
                                                }),
                                            ),
                                        ]}
                                        placeholder="Select company"
                                        searchPlaceholder="Search companies..."
                                    />
                                    <InputError
                                        message={form.errors.source_company_id}
                                    />
                                </Field>
                                <Field label="Date received" required>
                                    <Input
                                        type="date"
                                        value={form.data.received_on}
                                        max={receivedOn}
                                        onChange={(event) =>
                                            form.setData(
                                                'received_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.received_on}
                                    />
                                </Field>
                                <Field label="Source reference">
                                    <Input
                                        value={form.data.source_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'source_reference',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Delivery note or invoice"
                                    />
                                    <InputError
                                        message={form.errors.source_reference}
                                    />
                                </Field>
                            </div>

                            <Field label="Reason" required>
                                <NativeSelect
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData('reason', event.target.value)
                                    }
                                >
                                    <NativeSelectOption value="" disabled>
                                        Select reason
                                    </NativeSelectOption>
                                    {reasons.map((reason) => (
                                        <NativeSelectOption
                                            key={reason.value}
                                            value={reason.value}
                                        >
                                            {reason.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <InputError message={form.errors.reason} />
                            </Field>

                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-medium">Items</h2>
                                    <p className="text-sm text-muted-foreground">
                                        Quantities are converted into each
                                        item&apos;s stock unit.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            blankLine(),
                                        ])
                                    }
                                >
                                    <Plus />
                                    Add item
                                </Button>
                            </div>

                            <div className="grid gap-4">
                                {form.data.lines.map((line, index) => {
                                    const item = store?.items.find(
                                        (candidate) =>
                                            candidate.id ===
                                            line.inventory_item_id,
                                    );
                                    const isBatch =
                                        item?.tracking_type === 'batch';

                                    return (
                                        <div
                                            key={line.key}
                                            className="grid gap-4 border-b pb-5 last:border-0 last:pb-0 lg:grid-cols-12"
                                        >
                                            <div className="grid gap-2 lg:col-span-4">
                                                <Label>
                                                    Item{' '}
                                                    <span className="text-destructive">
                                                        *
                                                    </span>
                                                </Label>
                                                <SearchableSelect
                                                    value={
                                                        line.inventory_item_id
                                                    }
                                                    onValueChange={(value) =>
                                                        selectItem(index, value)
                                                    }
                                                    options={(
                                                        store?.items ?? []
                                                    ).map((row) => ({
                                                        value: row.id,
                                                        label: `${row.name} (${row.code})`,
                                                    }))}
                                                    placeholder="Select item"
                                                    searchPlaceholder="Search items..."
                                                />
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            `lines.${index}.inventory_item_id`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2 lg:col-span-2">
                                                <Label>
                                                    Unit{' '}
                                                    <span className="text-destructive">
                                                        *
                                                    </span>
                                                </Label>
                                                <SearchableSelect
                                                    value={
                                                        line.unit_of_measure_id
                                                    }
                                                    onValueChange={(value) =>
                                                        updateLine(index, {
                                                            unit_of_measure_id:
                                                                value,
                                                        })
                                                    }
                                                    options={(
                                                        item?.units ?? []
                                                    ).map((row) => ({
                                                        value: row.id,
                                                        label:
                                                            row.symbol ??
                                                            row.name,
                                                    }))}
                                                    placeholder="Select unit"
                                                    searchPlaceholder="Search units..."
                                                />
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            `lines.${index}.unit_of_measure_id`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2 lg:col-span-2">
                                                <Label>
                                                    Quantity{' '}
                                                    <span className="text-destructive">
                                                        *
                                                    </span>
                                                </Label>
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
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            `lines.${index}.quantity`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            {isBatch ? (
                                                <>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Batch number{' '}
                                                            <span className="text-destructive">
                                                                *
                                                            </span>
                                                        </Label>
                                                        <Input
                                                            value={
                                                                line.batch_number
                                                            }
                                                            onChange={(event) =>
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
                                                        <InputError
                                                            message={
                                                                form.errors[
                                                                    `lines.${index}.batch_number`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Expiry date
                                                            {item?.is_expires && (
                                                                <span className="text-destructive">
                                                                    {' '}
                                                                    *
                                                                </span>
                                                            )}
                                                        </Label>
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
                                                        <InputError
                                                            message={
                                                                form.errors[
                                                                    `lines.${index}.expires_on`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2 lg:col-start-9">
                                                        <Label>
                                                            Manufactured on
                                                        </Label>
                                                        <Input
                                                            type="date"
                                                            value={
                                                                line.manufactured_on
                                                            }
                                                            onChange={(event) =>
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
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="lg:col-span-3" />
                                            )}
                                            <div className="flex items-end justify-end lg:col-span-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="Remove item"
                                                    disabled={
                                                        form.data.lines
                                                            .length === 1
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
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                                <InputError message={form.errors.lines} />
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-5">
                                <Button asChild type="button" variant="outline">
                                    <Link href={returnTo}>
                                        Cancel
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <PackagePlus />
                                    )}
                                    Add stock
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({
    label: fieldLabel,
    required = false,
    children,
}: {
    label: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>
                {fieldLabel}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children}
        </div>
    );
}

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
