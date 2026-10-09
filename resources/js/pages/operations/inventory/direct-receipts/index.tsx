import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index as receiptsIndex, show as receiptShow, create as addStock } from '@/routes/inventory/direct-receipts';

type Receipt = {
    id: string;
    reference: string;
    received_on: string;
    reason: string;
    store: string;
    branch: string;
    received_by: string;
    lines_count: number;
};

type Props = {
    receipts: {
        data: Receipt[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function StockReceipts({ receipts }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Stock receipts', href: receiptsIndex.url() }]}>
            <Head title="Stock receipts" />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Stock receipts</h1>
                    <Button asChild><Link href={addStock.url()}>Add stock</Link></Button>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        {receipts.data.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">No stock receipts recorded yet.</p>
                        ) : (
                            <div className="divide-y">
                                {receipts.data.map((receipt) => (
                                    <div key={receipt.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div className="min-w-0">
                                            <Link href={receiptShow.url(receipt.id)} className="font-medium hover:underline">{receipt.reference}</Link>
                                            <p className="text-sm text-muted-foreground">{receipt.store} · {receipt.branch} · {receipt.received_on}</p>
                                            <p className="text-sm text-muted-foreground">{receipt.reason} · {receipt.lines_count} items · {receipt.received_by}</p>
                                        </div>
                                        <Button variant="outline" size="sm" asChild><Link href={receiptShow.url(receipt.id)}>View</Link></Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
                {(receipts.prev_page_url || receipts.next_page_url) && (
                    <div className="flex justify-end gap-2">
                        {receipts.prev_page_url && <Button variant="outline" asChild><Link href={receipts.prev_page_url}>Previous</Link></Button>}
                        {receipts.next_page_url && <Button variant="outline" asChild><Link href={receipts.next_page_url}>Next</Link></Button>}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
