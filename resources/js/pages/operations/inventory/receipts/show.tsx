import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type ReceiptLine = {
    id: string;
    item_name: string;
    item_code: string;
    quantity: string;
    accepted_quantity: string;
    rejected_quantity: string;
    rejection_reason: string | null;
    unit: string;
    batch_number: string | null;
    expires_on: string | null;
    unit_cost: string | null;
    line_total: string | null;
};
type Receipt = {
    id: string;
    reference: string;
    supplier_reference: string | null;
    received_on: string;
    recorded_at: string;
    currency_code: string | null;
    total_amount: string | null;
    inspection_status: string;
    notes: string | null;
    company_name: string;
    branch_name: string;
    store_name: string;
    supplier_name: string;
    receiver_name: string;
    verifier_name: string;
    purchase_order: { id: string; order_number: string };
    lines: ReceiptLine[];
};
type Props = { receipt: Receipt; canViewCosts: boolean };

export default function GoodsReceiptShow({ receipt, canViewCosts }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'PO receipts', href: '/inventory/receipts' },
        { title: receipt.reference, href: `/inventory/receipts/${receipt.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={receipt.reference} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 print:block print:p-0">
                <div className="flex flex-wrap items-start justify-between gap-4 print:hidden">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {receipt.reference}
                            </h1>
                            <Badge variant="outline">
                                {label(receipt.inspection_status)}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Purchase-order goods receipt
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/inventory/receipts">
                                <ArrowLeft />
                                Back
                            </Link>
                        </Button>
                        <Button onClick={() => window.print()}>
                            <Printer />
                            Print receipt
                        </Button>
                    </div>
                </div>

                <article className="mx-auto w-full max-w-6xl space-y-6 bg-background print:max-w-none print:text-black">
                    <header className="border-b pb-5">
                        <div className="flex items-start justify-between gap-8">
                            <div>
                                <h2 className="text-xl font-semibold">
                                    {receipt.company_name}
                                </h2>
                                <p className="mt-1 text-sm">
                                    {receipt.branch_name}
                                </p>
                            </div>
                            <div className="text-right">
                                <div className="text-xs font-medium text-muted-foreground uppercase print:text-black">
                                    Goods receipt note
                                </div>
                                <div className="mt-1 text-lg font-semibold">
                                    {receipt.reference}
                                </div>
                                <div className="mt-1 text-sm">
                                    Received {receipt.received_on}
                                </div>
                            </div>
                        </div>
                    </header>

                    <section className="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="Supplier"
                            value={receipt.supplier_name}
                        />
                        <Detail
                            label="Receiving store"
                            value={receipt.store_name}
                        />
                        <Detail
                            label="Purchase order"
                            value={
                                <Link
                                    className="font-medium text-primary underline print:text-black"
                                    href={`/inventory/purchase-orders/${receipt.purchase_order.id}`}
                                >
                                    {receipt.purchase_order.order_number}
                                </Link>
                            }
                        />
                        <Detail
                            label="Delivery note / invoice"
                            value={receipt.supplier_reference ?? 'Not provided'}
                        />
                        <Detail label="Recorded" value={receipt.recorded_at} />
                        <Detail
                            label="Received by"
                            value={receipt.receiver_name}
                        />
                        <Detail
                            label="Verified by"
                            value={receipt.verifier_name}
                        />
                        <Detail
                            label="Inspection"
                            value={label(receipt.inspection_status)}
                        />
                    </section>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[850px] border-collapse text-sm print:min-w-0">
                            <thead>
                                <tr className="border-y text-left">
                                    <Th>Item</Th>
                                    <Th>Delivered</Th>
                                    <Th>Accepted</Th>
                                    <Th>Rejected</Th>
                                    <Th>Batch / expiry</Th>
                                    <Th>Rejection reason</Th>
                                    {canViewCosts && (
                                        <>
                                            <Th>Unit cost</Th>
                                            <Th>Total</Th>
                                        </>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {receipt.lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b align-top"
                                    >
                                        <Td>
                                            <span className="font-medium">
                                                {line.item_name}
                                            </span>
                                            <div className="text-muted-foreground print:text-black">
                                                {line.item_code}
                                            </div>
                                        </Td>
                                        <Td>
                                            {formatNumber(line.quantity)}{' '}
                                            {line.unit}
                                        </Td>
                                        <Td>
                                            {formatNumber(
                                                line.accepted_quantity,
                                            )}{' '}
                                            {line.unit}
                                        </Td>
                                        <Td>
                                            {formatNumber(
                                                line.rejected_quantity,
                                            )}{' '}
                                            {line.unit}
                                        </Td>
                                        <Td>
                                            {line.batch_number ?? 'N/A'}
                                            {line.expires_on && (
                                                <div className="text-muted-foreground print:text-black">
                                                    Expires {line.expires_on}
                                                </div>
                                            )}
                                        </Td>
                                        <Td>
                                            {line.rejection_reason ?? 'N/A'}
                                        </Td>
                                        {canViewCosts && (
                                            <>
                                                <Td>
                                                    {formatCurrencyAmount(
                                                        receipt.currency_code,
                                                        line.unit_cost,
                                                    )}
                                                </Td>
                                                <Td>
                                                    {formatCurrencyAmount(
                                                        receipt.currency_code,
                                                        line.line_total,
                                                    )}
                                                </Td>
                                            </>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            {canViewCosts && (
                                <tfoot>
                                    <tr className="border-t-2">
                                        <td
                                            colSpan={7}
                                            className="px-3 py-4 text-right font-semibold"
                                        >
                                            Accepted receipt value
                                        </td>
                                        <td className="px-3 py-4 font-semibold">
                                            {formatCurrencyAmount(
                                                receipt.currency_code,
                                                receipt.total_amount,
                                            )}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>

                    {receipt.notes && (
                        <section className="border-t pt-4">
                            <div className="text-xs font-medium text-muted-foreground uppercase print:text-black">
                                Notes
                            </div>
                            <p className="mt-2 text-sm whitespace-pre-wrap">
                                {receipt.notes}
                            </p>
                        </section>
                    )}
                    <footer className="grid gap-8 border-t pt-10 sm:grid-cols-2">
                        <Signature
                            label="Received by"
                            name={receipt.receiver_name}
                        />
                        <Signature
                            label="Verified by"
                            name={receipt.verifier_name}
                        />
                    </footer>
                </article>
            </div>
        </AppLayout>
    );
}

function Detail({
    label: heading,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground uppercase print:text-black">
                {heading}
            </div>
            <div className="mt-1 text-sm">{value}</div>
        </div>
    );
}
function Signature({ label: heading, name }: { label: string; name: string }) {
    return (
        <div>
            <div className="border-t pt-2 text-sm">{name}</div>
            <div className="text-xs text-muted-foreground print:text-black">
                {heading} / signature
            </div>
        </div>
    );
}
function Th({ children }: { children: ReactNode }) {
    return <th className="px-3 py-3 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="px-3 py-4">{children}</td>;
}
const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
