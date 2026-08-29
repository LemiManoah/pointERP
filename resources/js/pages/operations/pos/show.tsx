import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrencyAmount,
    formatDateTime,
    formatNumber,
} from '@/lib/utils';
import { RecordPaymentDialog } from '@/pages/operations/pos/partials/record-payment-dialog';
import type { BreadcrumbItem } from '@/types';

type Sale = {
    id: string;
    sale_number: string;
    status: string;
    status_label: string;
    branch: string;
    store: string;
    customer: string;
    cashier: string;
    currency_code: string;
    subtotal: string;
    discount_total: string;
    total_amount: string;
    amount_paid: string;
    balance_due: string;
    payment_status: string;
    payment_status_label: string;
    notes: string | null;
    completed_at: string | null;
    lines: {
        id: string;
        code: string;
        name: string;
        quantity: string;
        unit: string;
        unit_price: string;
        discount: string;
        total: string;
        batches: string;
    }[];
    payments: {
        number: string;
        method: string;
        amount: string;
        reference: string | null;
        recorded_at: string;
    }[];
};

type Option = { value: string; label: string };

export default function PosShow({
    sale,
    can,
    paymentMethods,
}: {
    sale: Sale;
    can: { recordPayment: boolean };
    paymentMethods: Option[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'POS', href: '/pos' },
        { title: sale.sale_number, href: `/pos/${sale.id}` },
    ];
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={sale.sale_number} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3 print:hidden">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href="/pos">
                                <ArrowLeft />
                                Back to POS
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold">
                            Sales receipt
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.recordPayment && (
                            <RecordPaymentDialog
                                saleId={sale.id}
                                currencyCode={sale.currency_code}
                                balanceDue={sale.balance_due}
                                paymentMethods={paymentMethods}
                            />
                        )}
                        <Button onClick={() => window.print()}>
                            <Printer />
                            Print receipt
                        </Button>
                    </div>
                </div>
                <Card className="mx-auto w-full max-w-4xl">
                    <CardHeader className="border-b">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <CardTitle>{sale.branch}</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {sale.store}
                                </p>
                            </div>
                            <div className="text-right">
                                <div className="font-semibold">
                                    {sale.sale_number}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {sale.completed_at
                                        ? formatDateTime(sale.completed_at)
                                        : 'Not completed'}
                                </div>
                                <Badge className="mt-2">
                                    {sale.status_label}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 pt-6">
                        <div className="grid gap-4 text-sm sm:grid-cols-2">
                            <Info label="Customer" value={sale.customer} />
                            <Info label="Cashier" value={sale.cashier} />
                            <Info
                                label="Payment status"
                                value={sale.payment_status_label}
                            />
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Quantity</TableHead>
                                    <TableHead className="text-right">
                                        Unit price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Discount
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sale.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {line.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {line.code}
                                                {line.batches
                                                    ? ` · Batch ${line.batches}`
                                                    : ''}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {formatNumber(line.quantity)}{' '}
                                            {line.unit}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatCurrencyAmount(
                                                sale.currency_code,
                                                line.unit_price,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatCurrencyAmount(
                                                sale.currency_code,
                                                line.discount,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatCurrencyAmount(
                                                sale.currency_code,
                                                line.total,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <div className="ml-auto max-w-sm space-y-2">
                            <Row
                                label="Subtotal"
                                value={formatCurrencyAmount(
                                    sale.currency_code,
                                    sale.subtotal,
                                )}
                            />
                            <Row
                                label="Discount"
                                value={formatCurrencyAmount(
                                    sale.currency_code,
                                    sale.discount_total,
                                )}
                            />
                            <Row
                                label="Sale total"
                                value={formatCurrencyAmount(
                                    sale.currency_code,
                                    sale.total_amount,
                                )}
                                strong
                            />
                            <Row
                                label="Amount paid"
                                value={formatCurrencyAmount(
                                    sale.currency_code,
                                    sale.amount_paid,
                                )}
                            />
                            <Row
                                label="Balance due"
                                value={formatCurrencyAmount(
                                    sale.currency_code,
                                    sale.balance_due,
                                )}
                                strong={Number(sale.balance_due) > 0}
                            />
                        </div>
                        {sale.payments.length > 0 && (
                            <div>
                                <h2 className="mb-2 font-semibold">Payments</h2>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Method</TableHead>
                                            <TableHead>Reference</TableHead>
                                            <TableHead>Recorded</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {sale.payments.map((payment) => (
                                            <TableRow key={payment.number}>
                                                <TableCell>
                                                    {payment.method}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.reference ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDateTime(
                                                        payment.recorded_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrencyAmount(
                                                        sale.currency_code,
                                                        payment.amount,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                        {sale.notes && (
                            <div className="rounded-md border p-3 text-sm">
                                <span className="font-medium">Notes:</span>{' '}
                                {sale.notes}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}
function Row({
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
            className={`flex justify-between gap-4 ${strong ? 'border-t pt-2 text-lg font-semibold' : 'text-sm'}`}
        >
            <span>{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}
