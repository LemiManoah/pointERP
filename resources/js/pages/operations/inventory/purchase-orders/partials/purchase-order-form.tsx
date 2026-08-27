import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrencyAmount } from '@/lib/utils';
import type {
    ProcurementItemOption,
    ProcurementOptions,
} from '../../procurement-types';

type Line = {
    inventory_item_id: string;
    unit_of_measure_id: string;
    ordered_quantity: string;
    unit_price: string;
};

export type EditablePurchaseOrder = {
    id: string;
    branch_id: string;
    inventory_store_id: string;
    supplier_id: string;
    order_date: string;
    expected_date: string | null;
    currency_code: string;
    discount_amount: string | null;
    tax_amount: string | null;
    delivery_terms: string | null;
    payment_terms: string | null;
    notes: string | null;
    lines: Line[];
};

const emptyLine = (): Line => ({
    inventory_item_id: '',
    unit_of_measure_id: '',
    ordered_quantity: '',
    unit_price: '',
});

export function PurchaseOrderForm({
    options,
    purchaseOrder,
}: {
    options: ProcurementOptions;
    purchaseOrder?: EditablePurchaseOrder;
}) {
    const defaultBranch = options.branches.find(
        (branch) => branch.id === options.defaultBranchId,
    );
    const form = useForm({
        branch_id: purchaseOrder?.branch_id ?? options.defaultBranchId,
        inventory_store_id: purchaseOrder?.inventory_store_id ?? '',
        supplier_id: purchaseOrder?.supplier_id ?? '',
        order_date:
            purchaseOrder?.order_date ?? new Date().toISOString().slice(0, 10),
        expected_date: purchaseOrder?.expected_date ?? '',
        currency_code:
            purchaseOrder?.currency_code ??
            defaultBranch?.default_currency_code ??
            '',
        discount_amount: purchaseOrder?.discount_amount ?? '0',
        tax_amount: purchaseOrder?.tax_amount ?? '0',
        delivery_terms: purchaseOrder?.delivery_terms ?? '',
        payment_terms: purchaseOrder?.payment_terms ?? '',
        notes: purchaseOrder?.notes ?? '',
        lines: purchaseOrder?.lines ?? [emptyLine()],
    });
    const branch = options.branches.find(
        (row) => row.id === form.data.branch_id,
    );
    const stores = options.stores.filter(
        (row) => row.branch_id === form.data.branch_id,
    );
    const suppliers = options.suppliers.filter(
        (row) =>
            row.branch_id === null || row.branch_id === form.data.branch_id,
    );
    const subtotal = useMemo(
        () =>
            form.data.lines.reduce(
                (total, line) =>
                    total +
                    Number(line.ordered_quantity || 0) *
                        Number(line.unit_price || 0),
                0,
            ),
        [form.data.lines],
    );
    const total =
        subtotal -
        Number(form.data.discount_amount || 0) +
        Number(form.data.tax_amount || 0);

    const updateLine = (index: number, values: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, current) =>
                current === index ? { ...line, ...values } : line,
            ),
        );

    const selectItem = (index: number, itemId: string) => {
        const item = options.items.find((row) => row.id === itemId);
        if (!item) return;

        updateLine(index, {
            inventory_item_id: item.id,
            unit_of_measure_id: item.stock_unit_id,
            unit_price: recordedPrice(item, item.stock_unit_id),
        });
        if (
            !form.data.supplier_id &&
            item.preferred_supplier_id &&
            suppliers.some(
                (supplier) => supplier.id === item.preferred_supplier_id,
            )
        ) {
            form.setData('supplier_id', item.preferred_supplier_id);
        }
    };

    const selectUnit = (
        index: number,
        item: ProcurementItemOption,
        unitId: string,
    ) =>
        updateLine(index, {
            unit_of_measure_id: unitId,
            unit_price: recordedPrice(item, unitId),
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
        };
        if (purchaseOrder) {
            form.put(`/inventory/purchase-orders/${purchaseOrder.id}`, options);
            return;
        }

        form.post('/inventory/purchase-orders', options);
    };

    return (
        <form onSubmit={submit} className="grid gap-6">
            <Section
                title="Order details"
                description="Choose the supplier, receiving store and expected delivery date."
            >
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {options.canChangeBranch && (
                        <Field
                            label="Branch"
                            required
                            error={form.errors.branch_id}
                        >
                            <SearchableSelect
                                value={form.data.branch_id}
                                options={options.branches.map((row) => ({
                                    value: row.id,
                                    label: row.name,
                                    description: row.code,
                                }))}
                                onValueChange={(value) => {
                                    const selected = options.branches.find(
                                        (row) => row.id === value,
                                    );
                                    form.setData((data) => ({
                                        ...data,
                                        branch_id: value,
                                        inventory_store_id: '',
                                        supplier_id: '',
                                        currency_code:
                                            selected?.default_currency_code ??
                                            '',
                                        lines: [emptyLine()],
                                    }));
                                }}
                                placeholder="Select branch"
                            />
                        </Field>
                    )}
                    <Field
                        label="Receiving store"
                        required
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
                                form.setData('inventory_store_id', value)
                            }
                            placeholder="Select store"
                        />
                    </Field>
                    <Field
                        label="Supplier"
                        required
                        error={form.errors.supplier_id}
                    >
                        <SearchableSelect
                            value={form.data.supplier_id}
                            options={suppliers.map((row) => ({
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
                    <Field label="Currency">
                        <Input
                            value={branch?.default_currency_code ?? ''}
                            disabled
                        />
                    </Field>
                    <Field
                        label="Order date"
                        required
                        error={form.errors.order_date}
                    >
                        <Input
                            type="date"
                            value={form.data.order_date}
                            onChange={(event) =>
                                form.setData('order_date', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Expected delivery"
                        error={form.errors.expected_date}
                    >
                        <Input
                            type="date"
                            value={form.data.expected_date}
                            onChange={(event) =>
                                form.setData(
                                    'expected_date',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>
            </Section>

            <Section
                title="Items to purchase"
                description="Prices and stock units come from the inventory item record. Allowed conversions are applied automatically."
                action={
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
                }
            >
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[850px] table-fixed text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <Th className="w-[34%]">Item</Th>
                                <Th className="w-[20%]">Unit</Th>
                                <Th className="w-[15%]">Quantity</Th>
                                {options.canViewCosts && (
                                    <>
                                        <Th className="w-[18%]">Unit price</Th>
                                        <Th className="w-[10%]">Total</Th>
                                    </>
                                )}
                                <Th className="w-12">
                                    <span className="sr-only">Actions</span>
                                </Th>
                            </tr>
                        </thead>
                        <tbody>
                            {form.data.lines.map((line, index) => {
                                const item = options.items.find(
                                    (row) => row.id === line.inventory_item_id,
                                );
                                return (
                                    <tr
                                        key={index}
                                        className="border-b last:border-0"
                                    >
                                        <Td>
                                            <SearchableSelect
                                                value={line.inventory_item_id}
                                                options={options.items
                                                    .filter(
                                                        (candidate) =>
                                                            candidate.id ===
                                                                line.inventory_item_id ||
                                                            !form.data.lines.some(
                                                                (current) =>
                                                                    current.inventory_item_id ===
                                                                    candidate.id,
                                                            ),
                                                    )
                                                    .map((row) => ({
                                                        value: row.id,
                                                        label: row.name,
                                                        description: row.code,
                                                    }))}
                                                onValueChange={(value) =>
                                                    selectItem(index, value)
                                                }
                                                placeholder="Select inventory item"
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.inventory_item_id`
                                                    ]
                                                }
                                            />
                                        </Td>
                                        <Td>
                                            <SearchableSelect
                                                value={line.unit_of_measure_id}
                                                options={allowedUnits(
                                                    item,
                                                    options,
                                                ).map((row) => ({
                                                    value: row.id,
                                                    label: row.name,
                                                    description:
                                                        row.symbol ?? row.code,
                                                }))}
                                                onValueChange={(value) =>
                                                    item &&
                                                    selectUnit(
                                                        index,
                                                        item,
                                                        value,
                                                    )
                                                }
                                                placeholder="Select unit"
                                                disabled={!item}
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.unit_of_measure_id`
                                                    ]
                                                }
                                            />
                                        </Td>
                                        <Td>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.0001"
                                                value={line.ordered_quantity}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        ordered_quantity:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.ordered_quantity`
                                                    ]
                                                }
                                            />
                                        </Td>
                                        {options.canViewCosts && (
                                            <>
                                                <Td>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                        value={line.unit_price}
                                                        onChange={(event) =>
                                                            updateLine(index, {
                                                                unit_price:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                        disabled={
                                                            !options.canOverridePrice
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            form.errors[
                                                                `lines.${index}.unit_price`
                                                            ]
                                                        }
                                                    />
                                                </Td>
                                                <Td className="font-medium">
                                                    {formatCurrencyAmount(
                                                        form.data.currency_code,
                                                        Number(
                                                            line.ordered_quantity ||
                                                                0,
                                                        ) *
                                                            Number(
                                                                line.unit_price ||
                                                                    0,
                                                            ),
                                                    )}
                                                </Td>
                                            </>
                                        )}
                                        <Td>
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
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Section>

            <Section
                title="Commercial terms"
                description="Optional tax, discount and supplier delivery terms."
            >
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {options.canViewCosts && (
                        <>
                            <Field label="Discount">
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.discount_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'discount_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Tax">
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.tax_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'tax_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </>
                    )}
                    <Field label="Delivery terms">
                        <Textarea
                            value={form.data.delivery_terms}
                            onChange={(event) =>
                                form.setData(
                                    'delivery_terms',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Payment terms">
                        <Textarea
                            value={form.data.payment_terms}
                            onChange={(event) =>
                                form.setData(
                                    'payment_terms',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <div className="md:col-span-2 xl:col-span-4">
                        <Field label="Notes">
                            <Textarea
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </Field>
                    </div>
                </div>
                {options.canViewCosts && (
                    <div className="mt-5 flex justify-end">
                        <div className="grid w-full max-w-sm gap-2 text-sm">
                            <Total
                                label="Subtotal"
                                value={subtotal}
                                currency={form.data.currency_code}
                            />
                            <Total
                                label="Discount"
                                value={Number(form.data.discount_amount || 0)}
                                currency={form.data.currency_code}
                            />
                            <Total
                                label="Tax"
                                value={Number(form.data.tax_amount || 0)}
                                currency={form.data.currency_code}
                            />
                            <Total
                                label="Order total"
                                value={total}
                                currency={form.data.currency_code}
                                strong
                            />
                        </div>
                    </div>
                )}
            </Section>

            <div className="flex justify-end border-t pt-5">
                <Button type="submit" disabled={form.processing}>
                    {purchaseOrder ? 'Save changes' : 'Save draft'}
                </Button>
            </div>
        </form>
    );
}

function recordedPrice(item: ProcurementItemOption, unitId: string): string {
    const basePrice = Number(item.default_unit_cost ?? 0);
    if (unitId === item.stock_unit_id) return String(basePrice);
    const conversion = item.conversions.find((row) => row.unit_id === unitId);
    return String(
        (basePrice * Number(conversion?.multiplier ?? 0)) /
            Number(conversion?.divisor ?? 1),
    );
}

function allowedUnits(
    item: ProcurementItemOption | undefined,
    options: ProcurementOptions,
) {
    if (!item) return [];
    const ids = new Set([
        item.stock_unit_id,
        ...item.conversions.map((row) => row.unit_id),
    ]);
    return options.units.filter((unit) => ids.has(unit.id));
}

function Section({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3 border-b pb-3">
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {action}
            </div>
            {children}
        </section>
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

function Total({
    label,
    value,
    currency,
    strong = false,
}: {
    label: string;
    value: number;
    currency: string;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex items-center justify-between gap-4 ${strong ? 'border-t pt-2 text-base font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span>{formatCurrencyAmount(currency, value)}</span>
        </div>
    );
}

function Th({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return <th className={`px-3 py-3 font-medium ${className}`}>{children}</th>;
}
function Td({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return <td className={`px-3 py-3 align-top ${className}`}>{children}</td>;
}
