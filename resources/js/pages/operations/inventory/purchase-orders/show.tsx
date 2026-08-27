import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatDate, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Line = {
    id: string;
    inventory_item_id: string;
    unit_of_measure_id: string;
    item_code_snapshot: string | null;
    item_name_snapshot: string;
    unit_symbol_snapshot: string | null;
    ordered_quantity: string;
    accepted_quantity: string;
    rejected_quantity: string;
    outstanding_quantity: string;
    unit_price: string | null;
    line_amount: string | null;
    price_source: string;
};
type Order = {
    id: string;
    branch_id: string;
    inventory_store_id: string;
    supplier_id: string;
    order_number: string;
    supplier_name_snapshot: string;
    order_date: string;
    expected_date: string | null;
    currency_code: string;
    status: string;
    branch_name: string;
    store_name: string;
    approved_by: string | null;
    subtotal: string | null;
    discount_amount: string | null;
    tax_amount: string | null;
    total_amount: string | null;
    delivery_terms: string | null;
    payment_terms: string | null;
    notes: string | null;
    decision_reason: string | null;
    lines: Line[];
    receipts: {
        id: string;
        reference: string;
        received_on: string;
        inspection_status: string;
    }[];
};
type Props = {
    purchaseOrder: Order;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        cancel: boolean;
        close: boolean;
        receive: boolean;
        viewCosts: boolean;
    };
};
export default function PurchaseOrderShow({ purchaseOrder: po, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Purchase orders', href: '/inventory/purchase-orders' },
        { title: po.order_number, href: `/inventory/purchase-orders/${po.id}` },
    ];
    const reason = (message: string) => window.prompt(message)?.trim();
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={po.order_number} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {po.order_number}
                            </h1>
                            <Badge>{label(po.status)}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {po.supplier_name_snapshot} · {po.store_name},{' '}
                            {po.branch_name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/inventory/purchase-orders/${po.id}/edit`}
                                >
                                    Edit draft
                                </Link>
                            </Button>
                        )}
                        {can.submit && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/inventory/purchase-orders/${po.id}/submit`,
                                    )
                                }
                            >
                                Submit
                            </Button>
                        )}
                        {can.approve && (
                            <>
                                <Button
                                    onClick={() =>
                                        router.post(
                                            `/inventory/purchase-orders/${po.id}/review`,
                                            { decision: 'approve' },
                                        )
                                    }
                                >
                                    Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        const value = reason(
                                            'Why is this PO being returned?',
                                        );
                                        if (value)
                                            router.post(
                                                `/inventory/purchase-orders/${po.id}/review`,
                                                {
                                                    decision: 'return',
                                                    reason: value,
                                                },
                                            );
                                    }}
                                >
                                    Return
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        const value = reason(
                                            'Why is this PO being rejected?',
                                        );
                                        if (value)
                                            router.post(
                                                `/inventory/purchase-orders/${po.id}/review`,
                                                {
                                                    decision: 'reject',
                                                    reason: value,
                                                },
                                            );
                                    }}
                                >
                                    Reject
                                </Button>
                            </>
                        )}
                        {can.receive &&
                            ['approved', 'partially_received'].includes(
                                po.status,
                            ) && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/inventory/receipts?purchase_order_id=${po.id}`}
                                    >
                                        Receive delivery
                                    </Link>
                                </Button>
                            )}
                        {can.close && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const value = reason(
                                        'Why is this PO being closed before full receipt?',
                                    );
                                    if (value)
                                        router.post(
                                            `/inventory/purchase-orders/${po.id}/close`,
                                            { reason: value },
                                        );
                                }}
                            >
                                Close
                            </Button>
                        )}
                        {can.cancel && (
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    const value = reason(
                                        'Why is this PO being cancelled?',
                                    );
                                    if (value)
                                        router.delete(
                                            `/inventory/purchase-orders/${po.id}`,
                                            { data: { reason: value } },
                                        );
                                }}
                            >
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Supplier"
                        value={po.supplier_name_snapshot}
                    />
                    <Summary
                        label="Order / delivery"
                        value={`${po.order_date} / ${po.expected_date ?? 'Not specified'}`}
                    />
                    <Summary label="Receiving store" value={po.store_name} />
                    {can.viewCosts && (
                        <Summary
                            label="Order total"
                            value={formatCurrencyAmount(
                                po.currency_code,
                                po.total_amount,
                            )}
                        />
                    )}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Order lines</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Item</Th>
                                        <Th>Ordered</Th>
                                        <Th>Accepted</Th>
                                        <Th>Rejected</Th>
                                        <Th>Outstanding</Th>
                                        {can.viewCosts && (
                                            <>
                                                <Th>Unit price</Th>
                                                <Th>Amount</Th>
                                            </>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {po.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                {line.item_name_snapshot}
                                                <div className="text-muted-foreground">
                                                    {line.item_code_snapshot}
                                                </div>
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.ordered_quantity,
                                                )}{' '}
                                                {line.unit_symbol_snapshot}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.accepted_quantity,
                                                )}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.rejected_quantity,
                                                )}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.outstanding_quantity,
                                                )}
                                            </Td>
                                            {can.viewCosts && (
                                                <>
                                                    <Td>
                                                        {formatCurrencyAmount(
                                                            po.currency_code,
                                                            line.unit_price,
                                                        )}
                                                    </Td>
                                                    <Td>
                                                        {formatCurrencyAmount(
                                                            po.currency_code,
                                                            line.line_amount,
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
                {po.receipts.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Goods receipts
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {po.receipts.map((receipt) => (
                                <div
                                    key={receipt.id}
                                    className="flex justify-between border-b py-3 last:border-0"
                                >
                                    <span>{receipt.reference}</span>
                                    <span className="text-muted-foreground">
                                        {formatDate(receipt.received_on)} ·{' '}
                                        {label(receipt.inspection_status)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
function Summary({ label, value }: { label: string; value: ReactNode }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="text-sm text-muted-foreground">{label}</div>
                <div className="mt-2 font-medium">{value}</div>
            </CardContent>
        </Card>
    );
}
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function Th({ children }: { children: ReactNode }) {
    return <th className="px-3 py-3 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="px-3 py-4 align-top">{children}</td>;
}
