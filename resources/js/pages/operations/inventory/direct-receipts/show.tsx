import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Receipt = {
    reference: string;
    source_reference: string | null;
    received_on: string;
    reason: string;
    branch: string;
    store: string;
    source_company: string | null;
    received_by: string;
    lines: Array<{
        id: string;
        item_name: string;
        item_code: string;
        quantity: string;
        unit: string;
        stock_quantity: string;
        stock_unit: string;
        batch_number: string | null;
        expires_on: string | null;
    }>;
};

export default function DirectReceiptShow({ receipt }: { receipt: Receipt }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Stock movements', href: '/inventory/stock-movements' },
        { title: receipt.reference, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={receipt.reference} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {receipt.reference}
                            </h1>
                            <Badge variant="outline">Stock added</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {receipt.store} · {receipt.received_on}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/inventory/stock-movements">
                            <ArrowLeft />
                            Stock movements
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="grid gap-6 pt-6">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Detail label="Branch" value={receipt.branch} />
                            <Detail label="Store" value={receipt.store} />
                            <Detail
                                label="Source company"
                                value={receipt.source_company ?? 'Not provided'}
                            />
                            <Detail
                                label="Source reference"
                                value={
                                    receipt.source_reference ?? 'Not provided'
                                }
                            />
                            <Detail
                                label="Recorded by"
                                value={receipt.received_by}
                            />
                            <div className="sm:col-span-2 lg:col-span-3">
                                <Detail label="Reason" value={receipt.reason} />
                            </div>
                        </div>

                        <div className="overflow-x-auto border-t pt-5">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Item</Th>
                                        <Th>Received</Th>
                                        <Th>Stock quantity</Th>
                                        <Th>Batch</Th>
                                        <Th>Expiry</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {receipt.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                {line.item_name}
                                                <div className="text-muted-foreground">
                                                    {line.item_code}
                                                </div>
                                            </Td>
                                            <Td>
                                                {formatNumber(line.quantity)}{' '}
                                                {line.unit}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.stock_quantity,
                                                )}{' '}
                                                {line.stock_unit}
                                            </Td>
                                            <Td>{line.batch_number ?? '—'}</Td>
                                            <Td>{line.expires_on ?? '—'}</Td>
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

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-sm">{value}</div>
        </div>
    );
}

function Th({ children }: { children: ReactNode }) {
    return <th className="py-3 pr-4 font-medium">{children}</th>;
}

function Td({ children }: { children: ReactNode }) {
    return <td className="py-3 pr-4 align-top">{children}</td>;
}
