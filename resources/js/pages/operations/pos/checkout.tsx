import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import { index, store } from '@/routes/pos';
import type { CartLine, Props } from './index';

type CartDetail = {
    line: CartLine;
    item: Props['items'][number];
    unit: Props['items'][number]['units'][number];
    gross: number;
    total: number;
};

export default function PosCheckout(props: Omit<Props, 'sales'>) {
    const confirm = useConfirmDialog();
    const [customerId, setCustomerId] = useState('');
    const [method, setMethod] = useState('cash');
    const [reference, setReference] = useState('');
    const [paymentAmount, setPaymentAmount] = useState<string | null>(null);
    const [cart] = useState<CartLine[]>(props.draft?.lines ?? []);
    const form = useForm({
        checkout_key: props.checkoutKey,
        branch_id: props.selected.branch_id,
        inventory_store_id: props.selected.store_id ?? '',
        inventory_price_tier_id: props.selected.price_list_id ?? '',
        customer_id: '',
        notes: '',
        lines: [] as CartLine[],
        payments: [] as { method: string; amount: string; reference: string }[],
    });
    const details: CartDetail[] = cart.flatMap((line) => {
        const item = props.items.find(
            (row) => row.id === line.inventory_item_id,
        );
        if (!item) return [];
        const unit = item.units.find(
            (row) => row.id === line.unit_of_measure_id,
        );
        if (!unit) return [];
        const gross = Number(line.quantity || 0) * Number(unit.price);
        return {
            line,
            item,
            unit,
            gross,
            total: Math.max(gross - Number(line.discount_amount || 0), 0),
        };
    });
    const subtotal = details.reduce((sum, row) => sum + row.gross, 0);
    const discount = details.reduce(
        (sum, row) => sum + Number(row.line.discount_amount || 0),
        0,
    );
    const total = Number(Math.max(subtotal - discount, 0).toFixed(4));
    const enteredPaymentAmount =
        paymentAmount === null ? total : Number(paymentAmount || 0);
    const paidAmount = Number.isFinite(enteredPaymentAmount)
        ? enteredPaymentAmount
        : 0;
    const balanceDue = Number(Math.max(total - paidAmount, 0).toFixed(4));

    const currencyCode = props.selected.currency_code;
    const customers = props.customers;
    const paymentMethods = props.paymentMethods;
    const canSellOnCredit = props.can.sellOnCredit;
    const editUrl = index.url({ query: { cart: props.checkoutKey } });
    const unavailable = details.length !== cart.length;
    const invalidLines = details.some(
        ({ line, unit, gross }) =>
            !Number.isFinite(Number(line.quantity)) ||
            Number(line.quantity) <= 0 ||
            Number(line.quantity) > Number(unit.available) ||
            !Number.isFinite(Number(line.discount_amount)) ||
            Number(line.discount_amount) < 0 ||
            Number(line.discount_amount) > gross ||
            (!props.can.discount && Number(line.discount_amount) > 0),
    );
    const disabled =
        form.processing ||
        unavailable ||
        invalidLines ||
        cart.length === 0 ||
        total <= 0 ||
        !Number.isFinite(enteredPaymentAmount) ||
        paidAmount < 0 ||
        paidAmount > total ||
        (paidAmount > 0 && method !== 'cash' && reference.trim() === '') ||
        (balanceDue > 0 && (!canSellOnCredit || !customerId));
    function checkout() {
        if (cart.length === 0 || total <= 0) return;
        confirm({
            title: 'Complete this sale?',
            description: `${cart.length} item line${cart.length === 1 ? '' : 's'} will reduce stock. ${formatCurrencyAmount(props.selected.currency_code, paidAmount)} will be collected now${balanceDue > 0 ? ` and ${formatCurrencyAmount(props.selected.currency_code, balanceDue)} will remain due` : ''}.`,
            confirmLabel: 'Complete sale',
            onConfirm: () => {
                form.transform((data) => ({
                    ...data,
                    customer_id: customerId,
                    lines: cart,
                    payments:
                        paidAmount > 0
                            ? [
                                  {
                                      method,
                                      amount: paidAmount.toFixed(4),
                                      reference,
                                  },
                              ]
                            : [],
                }));
                form.post(store.url(), { preserveScroll: true });
            },
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'POS', href: index.url() },
                { title: 'Checkout', href: '#' },
            ]}
        >
            <Head title="Checkout" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Checkout</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Review the sale, select a customer, and collect
                            payment.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={editUrl}>Edit cart</Link>
                    </Button>
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="font-semibold">
                            Order summary
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(unavailable || invalidLines) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    Some items are unavailable, quantities
                                    exceed stock, or discounts need updating.
                                    Edit the cart before completing this sale.
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {
                                    props.branches.find(
                                        (branch) =>
                                            branch.value ===
                                            props.selected.branch_id,
                                    )?.label
                                }{' '}
                                ·{' '}
                                {
                                    props.stores.find(
                                        (location) =>
                                            location.value ===
                                            props.selected.store_id,
                                    )?.label
                                }{' '}
                                ·{' '}
                                {
                                    props.priceLists.find(
                                        (list) =>
                                            list.value ===
                                            props.selected.price_list_id,
                                    )?.label
                                }
                            </p>
                            <div className="divide-y">
                                {details.map(
                                    (
                                        { item, unit, line, total: lineTotal },
                                        index,
                                    ) => (
                                        <div
                                            key={index}
                                            className="flex justify-between gap-4 py-3"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {item.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatNumber(
                                                        line.quantity,
                                                    )}{' '}
                                                    {unit.symbol} ×{' '}
                                                    {formatCurrencyAmount(
                                                        currencyCode,
                                                        unit.price,
                                                    )}
                                                </p>
                                            </div>
                                            <span className="text-sm tabular-nums">
                                                {formatCurrencyAmount(
                                                    currencyCode,
                                                    lineTotal,
                                                )}
                                            </span>
                                        </div>
                                    ),
                                )}
                            </div>
                            <Total
                                label="Subtotal"
                                value={formatCurrencyAmount(
                                    currencyCode,
                                    subtotal,
                                )}
                            />
                            <Total
                                label="Discount"
                                value={formatCurrencyAmount(
                                    currencyCode,
                                    discount,
                                )}
                            />
                            <Total
                                label="Amount due"
                                value={formatCurrencyAmount(
                                    currencyCode,
                                    total,
                                )}
                                strong
                            />
                            <p className="text-xs text-muted-foreground">
                                Prices and available stock are refreshed at
                                checkout. Stock is deducted only when the sale
                                is completed.
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="font-semibold">
                            Customer & payment
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (!disabled) checkout();
                                }}
                            >
                                <Field label="Customer">
                                    <SearchableSelect
                                        value={customerId}
                                        onValueChange={setCustomerId}
                                        options={[
                                            {
                                                value: '',
                                                label: 'Walk-in customer',
                                            },
                                            ...customers,
                                        ]}
                                        placeholder="Walk-in customer"
                                    />
                                </Field>
                                <Field label="Amount paid" required>
                                    <Input
                                        type="number"
                                        min="0"
                                        max={total}
                                        step="0.0001"
                                        value={
                                            paymentAmount ??
                                            (total > 0 ? total.toFixed(4) : '')
                                        }
                                        onChange={(event) =>
                                            setPaymentAmount(event.target.value)
                                        }
                                    />
                                    {balanceDue > 0 && (
                                        <p
                                            className={`text-xs ${canSellOnCredit ? 'text-muted-foreground' : 'text-destructive'}`}
                                        >
                                            {canSellOnCredit
                                                ? `The remaining ${formatCurrencyAmount(currencyCode, balanceDue)} will be recorded as customer credit.`
                                                : 'You do not have permission to leave a customer balance.'}
                                        </p>
                                    )}
                                </Field>
                                {paidAmount > 0 && (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Payment method">
                                            <SearchableSelect
                                                value={method}
                                                onValueChange={setMethod}
                                                options={paymentMethods}
                                            />
                                        </Field>
                                        <Field
                                            label="Payment reference"
                                            required={method !== 'cash'}
                                        >
                                            <Input
                                                value={reference}
                                                onChange={(event) =>
                                                    setReference(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={
                                                    method === 'cash'
                                                        ? 'Optional'
                                                        : 'Required'
                                                }
                                            />
                                        </Field>
                                    </div>
                                )}
                                <div className="space-y-1 border-t pt-3 text-sm">
                                    <Total
                                        label="Paid now"
                                        value={formatCurrencyAmount(
                                            currencyCode,
                                            paidAmount,
                                        )}
                                    />
                                    <Total
                                        label="Balance due"
                                        value={formatCurrencyAmount(
                                            currencyCode,
                                            balanceDue,
                                        )}
                                        strong={balanceDue > 0}
                                    />
                                </div>

                                {balanceDue > 0 && !customerId && (
                                    <p className="text-xs text-destructive">
                                        Select a customer to record the
                                        remaining balance.
                                    </p>
                                )}
                                {Object.entries(form.errors).map(
                                    ([field, message]) => (
                                        <p
                                            key={field}
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {message}
                                        </p>
                                    ),
                                )}
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={disabled}
                                >
                                    {form.processing
                                        ? 'Completing sale…'
                                        : 'Complete sale'}{' '}
                                    ·{' '}
                                    {formatCurrencyAmount(currencyCode, total)}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    children,
    required = false,
}: {
    label: string;
    children: ReactNode;
    required?: boolean;
}) {
    return (
        <div className="grid min-w-0 gap-1.5">
            <Label>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children}
        </div>
    );
}
function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-4 ${strong ? 'pt-2 text-base font-semibold' : ''}`}
        >
            <span>{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}
