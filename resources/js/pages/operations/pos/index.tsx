import { Head, Link, router, useForm } from '@inertiajs/react';
import { Minus, Plus, Search, ShoppingCart, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrencyAmount,
    formatDateTime,
    formatNumber,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string; description?: string };
type Unit = {
    id: string;
    label: string;
    symbol: string;
    price_id: string | null;
    price: string;
    multiplier: string;
    available: string;
};
type Item = {
    id: string;
    code: string;
    name: string;
    category: string;
    tracking_type: string;
    units: Unit[];
};
type Sale = {
    id: string;
    sale_number: string;
    customer: string;
    store: string;
    cashier: string;
    status: string;
    status_label: string;
    currency_code: string;
    total_amount: string;
    line_count: number;
    completed_at: string | null;
    payments: { method: string; amount: string }[];
};
type CartLine = {
    inventory_item_id: string;
    unit_of_measure_id: string;
    quantity: string;
    discount_amount: string;
};
type CartDetail = {
    line: CartLine;
    item: Item;
    unit: Unit;
    gross: number;
    total: number;
};
type Props = {
    branches: (Option & { currency_code: string })[];
    stores: Option[];
    priceLists: Option[];
    customers: Option[];
    paymentMethods: Option[];
    checkoutKey: string;
    items: Item[];
    sales: Sale[];
    selected: {
        branch_id: string;
        store_id: string | null;
        price_list_id: string | null;
        currency_code: string;
    };
    can: {
        changeBranch: boolean;
        changeStore: boolean;
        changePriceList: boolean;
        discount: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'POS', href: '/pos' }];

export default function PosIndex(props: Props) {
    const confirm = useConfirmDialog();
    const [tab, setTab] = useState('sale');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [customerId, setCustomerId] = useState('');
    const [method, setMethod] = useState('cash');
    const [reference, setReference] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);
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
    const categoryOptions = useMemo(
        () => [
            { value: 'all', label: 'All categories' },
            ...Array.from(new Set(props.items.map((item) => item.category)))
                .sort()
                .map((value) => ({ value, label: value })),
        ],
        [props.items],
    );
    const visibleItems = useMemo(
        () =>
            props.items.filter(
                (item) =>
                    (category === 'all' || item.category === category) &&
                    `${item.name} ${item.code} ${item.category}`
                        .toLowerCase()
                        .includes(search.toLowerCase()),
            ),
        [category, props.items, search],
    );
    const details: CartDetail[] = cart.map((line) => {
        const item = props.items.find(
            (row) => row.id === line.inventory_item_id,
        )!;
        const unit = item.units.find(
            (row) => row.id === line.unit_of_measure_id,
        )!;
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
    const total = Math.max(subtotal - discount, 0);
    const itemCount = details.reduce(
        (sum, row) => sum + Number(row.line.quantity || 0),
        0,
    );

    function scope(
        key: 'branch_id' | 'store_id' | 'price_list_id',
        value: string,
    ) {
        router.get(
            '/pos',
            {
                branch_id:
                    key === 'branch_id' ? value : props.selected.branch_id,
                store_id: key === 'store_id' ? value : props.selected.store_id,
                price_list_id:
                    key === 'price_list_id'
                        ? value
                        : props.selected.price_list_id,
            },
            { preserveScroll: true },
        );
    }
    function add(item: Item) {
        const unit = item.units[0];
        if (!unit) return;
        const existing = cart.findIndex(
            (line) =>
                line.inventory_item_id === item.id &&
                line.unit_of_measure_id === unit.id,
        );
        if (existing >= 0) {
            setCart(
                cart.map((line, index) =>
                    index === existing
                        ? {
                              ...line,
                              quantity: String(Number(line.quantity) + 1),
                          }
                        : line,
                ),
            );
            return;
        }
        setCart([
            ...cart,
            {
                inventory_item_id: item.id,
                unit_of_measure_id: unit.id,
                quantity: '1',
                discount_amount: '0',
            },
        ]);
    }
    function update(index: number, values: Partial<CartLine>) {
        setCart(
            cart.map((line, row) =>
                row === index ? { ...line, ...values } : line,
            ),
        );
    }
    function checkout() {
        if (cart.length === 0 || total <= 0) return;
        confirm({
            title: 'Complete this sale?',
            description: `${cart.length} item line${cart.length === 1 ? '' : 's'} will reduce stock and record ${formatCurrencyAmount(props.selected.currency_code, total)} as paid.`,
            confirmLabel: 'Complete sale',
            onConfirm: () => {
                form.transform((data) => ({
                    ...data,
                    customer_id: customerId,
                    lines: cart,
                    payments: [{ method, amount: total.toFixed(4), reference }],
                }));
                form.post('/pos', { preserveScroll: true });
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Point of sale" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Point of sale</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Sell available inventory and issue a receipt.
                    </p>
                </div>
                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="sale">New sale</TabsTrigger>
                        <TabsTrigger value="history">Sales history</TabsTrigger>
                    </TabsList>
                </Tabs>
                {tab === 'sale' ? (
                    <>
                        <div className="grid gap-3 md:grid-cols-3">
                            <Field label="Branch">
                                <SearchableSelect
                                    value={props.selected.branch_id}
                                    onValueChange={(value) =>
                                        scope('branch_id', value)
                                    }
                                    options={props.branches}
                                    disabled={!props.can.changeBranch}
                                />
                            </Field>
                            <Field label="Store">
                                <SearchableSelect
                                    value={props.selected.store_id ?? ''}
                                    onValueChange={(value) =>
                                        scope('store_id', value)
                                    }
                                    options={props.stores}
                                    disabled={!props.can.changeStore}
                                    placeholder="No active store"
                                />
                            </Field>
                            <Field label="Price list">
                                <SearchableSelect
                                    value={props.selected.price_list_id ?? ''}
                                    onValueChange={(value) =>
                                        scope('price_list_id', value)
                                    }
                                    options={props.priceLists}
                                    disabled={!props.can.changePriceList}
                                    placeholder="No active price list"
                                />
                            </Field>
                        </div>
                        <div className="flex justify-end">
                            <PosCartDrawer
                                currencyCode={props.selected.currency_code}
                                details={details}
                                itemCount={itemCount}
                                subtotal={subtotal}
                                discount={discount}
                                total={total}
                                canDiscount={props.can.discount}
                                customers={props.customers}
                                paymentMethods={props.paymentMethods}
                                customerId={customerId}
                                method={method}
                                reference={reference}
                                processing={form.processing}
                                error={
                                    form.errors.lines ??
                                    form.errors.payments ??
                                    form.errors.inventory_store_id
                                }
                                checkoutDisabled={
                                    cart.length === 0 ||
                                    total <= 0 ||
                                    !props.selected.store_id ||
                                    !props.selected.price_list_id ||
                                    (method !== 'cash' &&
                                        reference.trim() === '')
                                }
                                onCustomerChange={setCustomerId}
                                onMethodChange={setMethod}
                                onReferenceChange={setReference}
                                onUpdate={update}
                                onRemove={(index) =>
                                    setCart(
                                        cart.filter((_, row) => row !== index),
                                    )
                                }
                                onCheckout={checkout}
                            />
                        </div>
                        <Card className="min-w-0">
                            <CardHeader>
                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <div className="relative flex-1">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            className="pl-9"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Search sellable items..."
                                        />
                                    </div>
                                    <div className="sm:w-56">
                                        <SearchableSelect
                                            value={category}
                                            onValueChange={setCategory}
                                            options={categoryOptions}
                                        />
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {visibleItems.map((item) => {
                                        const unit = item.units[0];
                                        return (
                                            <button
                                                type="button"
                                                key={item.id}
                                                onClick={() => add(item)}
                                                disabled={
                                                    Number(unit.available) <= 0
                                                }
                                                className="min-w-0 rounded-md border bg-background p-3 text-left transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <div
                                                    className="truncate font-medium"
                                                    title={item.name}
                                                >
                                                    {item.name}
                                                </div>
                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {item.code} ·{' '}
                                                    {item.category}
                                                </div>
                                                <div className="mt-3 flex items-end justify-between gap-2">
                                                    <span className="font-semibold text-primary">
                                                        {formatCurrencyAmount(
                                                            props.selected
                                                                .currency_code,
                                                            unit.price,
                                                        )}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {formatNumber(
                                                            unit.available,
                                                        )}{' '}
                                                        {unit.symbol}
                                                    </span>
                                                </div>
                                            </button>
                                        );
                                    })}
                                    {visibleItems.length === 0 && (
                                        <div className="col-span-full py-12 text-center text-sm text-muted-foreground">
                                            No priced sellable stock is
                                            available for this store.
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardContent className="pt-6">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Receipt</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Store</TableHead>
                                        <TableHead>Cashier</TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                        <TableHead>Payment</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.sales.map((sale) => (
                                        <TableRow key={sale.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/pos/${sale.id}`}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {sale.sale_number}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {sale.completed_at
                                                        ? formatDateTime(
                                                              sale.completed_at,
                                                          )
                                                        : 'Not completed'}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                className="max-w-52 truncate"
                                                title={sale.customer}
                                            >
                                                {sale.customer}
                                            </TableCell>
                                            <TableCell>{sale.store}</TableCell>
                                            <TableCell>
                                                {sale.cashier}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatCurrencyAmount(
                                                    sale.currency_code,
                                                    sale.total_amount,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {sale.payments
                                                    .map(
                                                        (payment) =>
                                                            payment.method,
                                                    )
                                                    .join(', ') || 'Restricted'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge>
                                                    {sale.status_label}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {props.sales.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No sales recorded.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function PosCartDrawer({
    currencyCode,
    details,
    itemCount,
    subtotal,
    discount,
    total,
    canDiscount,
    customers,
    paymentMethods,
    customerId,
    method,
    reference,
    processing,
    error,
    checkoutDisabled,
    onCustomerChange,
    onMethodChange,
    onReferenceChange,
    onUpdate,
    onRemove,
    onCheckout,
}: {
    currencyCode: string;
    details: CartDetail[];
    itemCount: number;
    subtotal: number;
    discount: number;
    total: number;
    canDiscount: boolean;
    customers: Option[];
    paymentMethods: Option[];
    customerId: string;
    method: string;
    reference: string;
    processing: boolean;
    error?: string;
    checkoutDisabled: boolean;
    onCustomerChange: (value: string) => void;
    onMethodChange: (value: string) => void;
    onReferenceChange: (value: string) => void;
    onUpdate: (index: number, values: Partial<CartLine>) => void;
    onRemove: (index: number) => void;
    onCheckout: () => void;
}) {
    return (
        <Drawer direction="right">
            <DrawerTrigger asChild>
                <Button type="button" size="lg" className="min-w-44 gap-2">
                    <ShoppingCart className="size-4" />
                    <span>Cart</span>
                    <Badge
                        variant="secondary"
                        className="bg-primary-foreground/15 text-primary-foreground"
                    >
                        {formatNumber(itemCount)}
                    </Badge>
                    <span className="ml-auto tabular-nums">
                        {formatCurrencyAmount(currencyCode, total)}
                    </span>
                </Button>
            </DrawerTrigger>
            <DrawerContent className="h-full w-full data-[vaul-drawer-direction=right]:w-full data-[vaul-drawer-direction=right]:sm:max-w-xl">
                <DrawerHeader className="shrink-0 border-b px-4 py-4 sm:px-6">
                    <DrawerTitle className="flex items-center gap-2 text-lg">
                        Cart
                        <Badge variant="secondary">
                            {formatNumber(itemCount)}
                        </Badge>
                    </DrawerTitle>
                    <DrawerDescription>
                        Review quantities and collect payment.
                    </DrawerDescription>
                </DrawerHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-4 sm:px-6">
                    <div className="divide-y">
                        {details.map(
                            ({ line, item, unit, total: lineTotal }, index) => (
                                <div
                                    key={`${line.inventory_item_id}-${index}`}
                                    className="grid min-w-0 gap-3 py-4"
                                >
                                    <div className="flex min-w-0 items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div
                                                className="line-clamp-2 leading-snug font-medium"
                                                title={item.name}
                                            >
                                                {item.name}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {item.code} ·{' '}
                                                {formatCurrencyAmount(
                                                    currencyCode,
                                                    unit.price,
                                                )}{' '}
                                                / {unit.symbol}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {formatNumber(unit.available)}{' '}
                                                {unit.symbol} available
                                            </div>
                                        </div>
                                        <div className="shrink-0 text-right font-semibold tabular-nums">
                                            {formatCurrencyAmount(
                                                currencyCode,
                                                lineTotal,
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-end justify-between gap-3">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 px-0 text-destructive hover:bg-transparent hover:text-destructive/80"
                                            onClick={() => onRemove(index)}
                                        >
                                            <Trash2 className="size-4" />
                                            Remove
                                        </Button>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-9"
                                                onClick={() =>
                                                    onUpdate(index, {
                                                        quantity: String(
                                                            Math.max(
                                                                Number(
                                                                    line.quantity,
                                                                ) - 1,
                                                                0.0001,
                                                            ),
                                                        ),
                                                    })
                                                }
                                                aria-label={`Reduce ${item.name} quantity`}
                                            >
                                                <Minus className="size-4" />
                                            </Button>
                                            <Input
                                                className="h-9 w-20 text-center tabular-nums"
                                                type="number"
                                                min="0.0001"
                                                step="0.0001"
                                                max={unit.available}
                                                value={line.quantity}
                                                onChange={(event) =>
                                                    onUpdate(index, {
                                                        quantity:
                                                            event.target.value,
                                                    })
                                                }
                                                aria-label={`${item.name} quantity`}
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                className="size-9"
                                                onClick={() =>
                                                    onUpdate(index, {
                                                        quantity: String(
                                                            Math.min(
                                                                Number(
                                                                    line.quantity,
                                                                ) + 1,
                                                                Number(
                                                                    unit.available,
                                                                ),
                                                            ),
                                                        ),
                                                    })
                                                }
                                                aria-label={`Increase ${item.name} quantity`}
                                            >
                                                <Plus className="size-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    {(item.units.length > 1 || canDiscount) && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {item.units.length > 1 && (
                                                <Field label="Selling unit">
                                                    <SearchableSelect
                                                        value={
                                                            line.unit_of_measure_id
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            onUpdate(index, {
                                                                unit_of_measure_id:
                                                                    value,
                                                                quantity: '1',
                                                            })
                                                        }
                                                        options={item.units.map(
                                                            (row) => ({
                                                                value: row.id,
                                                                label: `${row.label} · ${formatCurrencyAmount(currencyCode, row.price)}`,
                                                            }),
                                                        )}
                                                    />
                                                </Field>
                                            )}
                                            {canDiscount && (
                                                <Field label="Discount">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={
                                                            line.discount_amount
                                                        }
                                                        onChange={(event) =>
                                                            onUpdate(index, {
                                                                discount_amount:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ),
                        )}
                    </div>
                    {details.length === 0 && (
                        <div className="grid min-h-64 place-items-center text-center text-sm text-muted-foreground">
                            <div>
                                <ShoppingCart className="mx-auto mb-3 size-8 opacity-40" />
                                Select an item to begin the sale.
                            </div>
                        </div>
                    )}
                </div>

                <div className="max-h-[48vh] shrink-0 overflow-y-auto border-t bg-muted/20 p-4 sm:p-6">
                    <div className="grid gap-4">
                        <div className="space-y-1 text-sm">
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
                        </div>
                        <Field label="Customer">
                            <SearchableSelect
                                value={customerId}
                                onValueChange={onCustomerChange}
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
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Payment method">
                                <SearchableSelect
                                    value={method}
                                    onValueChange={onMethodChange}
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
                                        onReferenceChange(event.target.value)
                                    }
                                    placeholder={
                                        method === 'cash'
                                            ? 'Optional'
                                            : 'Required'
                                    }
                                />
                            </Field>
                        </div>
                        <InputError message={error} />
                        <Button
                            type="button"
                            size="lg"
                            disabled={processing || checkoutDisabled}
                            onClick={onCheckout}
                        >
                            Complete sale ·{' '}
                            {formatCurrencyAmount(currencyCode, total)}
                        </Button>
                    </div>
                </div>
            </DrawerContent>
        </Drawer>
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
