import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { compactMoney, exactMoney } from '@/lib/money-display';
import { index as reportsIndex, ownerOverview } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

type Period = 'today' | 'last7' | 'month' | 'quarter' | 'range';
type SourceRow = { id: string; date: string; reference: string; description: string; amount: string; href: string };
type Source = { count: number; rows: SourceRow[] };
type Report = {
    filters: { period: Period; from: string; to: string; currency: string };
    currencies: string[];
    branch: string;
    generatedAt: string;
    summary: {
        approvedExpenses: string;
        moneyIn: string;
        moneyOut: string;
        activeProjectBudgets: string;
        incurredProjectCost: string | null;
        activeProjectCount: number;
        uncomparableProjectCount: number;
    };
    exceptions: { id: string; title: string; count: number; href: string }[];
    sources: { expenses: Source; receipts: Source; payments: Source };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: reportsIndex.url() },
    { title: 'Owner overview', href: ownerOverview.url() },
];

export default function OwnerOverview({ report }: { report: Report }) {
    const [period, setPeriod] = useState<Period>(report.filters.period);
    const [from, setFrom] = useState(report.filters.from);
    const [to, setTo] = useState(report.filters.to);
    const [currency, setCurrency] = useState(report.filters.currency);

    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(ownerOverview.url(), {
            period,
            currency,
            ...(period === 'range' ? { from, to } : {}),
        }, { preserveState: false });
    }

    const amount = (value: string | null) => value === null ? '—' : `${report.filters.currency} ${compactMoney(value)}`;
    const exact = (value: string | null) => value === null ? undefined : `${report.filters.currency} ${exactMoney(value)}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Owner overview" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Owner overview</h1>
                    <p className="mt-1 text-sm text-muted-foreground">{report.branch} · Updated {new Date(report.generatedAt).toLocaleString()}</p>
                </div>

                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <div className="grid min-w-36 gap-1.5">
                        <Label htmlFor="overview-period">Period</Label>
                        <NativeSelect id="overview-period" value={period} onChange={(event) => setPeriod(event.target.value as Period)}>
                            <NativeSelectOption value="today">Today</NativeSelectOption>
                            <NativeSelectOption value="last7">Last 7 days</NativeSelectOption>
                            <NativeSelectOption value="month">This month</NativeSelectOption>
                            <NativeSelectOption value="quarter">This quarter</NativeSelectOption>
                            <NativeSelectOption value="range">Custom range</NativeSelectOption>
                        </NativeSelect>
                    </div>
                    {period === 'range' && <>
                        <div className="grid gap-1.5"><Label htmlFor="overview-from">From</Label><Input id="overview-from" type="date" value={from} onChange={(event) => setFrom(event.target.value)} required /></div>
                        <div className="grid gap-1.5"><Label htmlFor="overview-to">To</Label><Input id="overview-to" type="date" value={to} onChange={(event) => setTo(event.target.value)} required /></div>
                    </>}
                    <div className="grid min-w-28 gap-1.5">
                        <Label htmlFor="overview-currency">Currency</Label>
                        <NativeSelect id="overview-currency" value={currency} onChange={(event) => setCurrency(event.target.value)}>
                            {report.currencies.map((code) => <NativeSelectOption key={code} value={code}>{code}</NativeSelectOption>)}
                        </NativeSelect>
                    </div>
                    <Button type="submit">Apply</Button>
                </form>

                <p className="text-sm text-muted-foreground">
                    Activity from {report.filters.from} to {report.filters.to}. Each amount uses {report.filters.currency}; other currencies are shown separately with the currency filter.
                </p>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Metric title="Approved expenses" value={amount(report.summary.approvedExpenses)} exact={exact(report.summary.approvedExpenses)} detail="Expense date in selected period" />
                    <Metric title="Money in" value={amount(report.summary.moneyIn)} exact={exact(report.summary.moneyIn)} detail="Recorded POS payments in selected period" />
                    <Metric title="Money out" value={amount(report.summary.moneyOut)} exact={exact(report.summary.moneyOut)} detail="Recorded expense payments in selected period" />
                    <Metric title="Active project budgets" value={amount(report.summary.activeProjectBudgets)} exact={exact(report.summary.activeProjectBudgets)} detail={`${report.summary.activeProjectCount} active projects · current budgets`} />
                    <Metric title="Incurred project cost" value={amount(report.summary.incurredProjectCost)} exact={exact(report.summary.incurredProjectCost)} detail={report.summary.uncomparableProjectCount ? `${report.summary.uncomparableProjectCount} projects need currency review` : 'Approved costs · current project snapshot'} />
                    <Metric title="Exception categories" value={String(report.exceptions.length)} detail="Current work queues and stock alerts" />
                </div>

                <p className="text-xs text-muted-foreground">
                    Project budgets and incurred cost are current snapshots, independent of the period filter. Incurred cost includes approved site-report input costs and approved project expenses for budgeted active projects. Money in/out records POS and expense payments; it is not a bank balance or full cash-flow statement.
                </p>

                <Card>
                    <CardHeader><CardTitle className="text-base">Current exceptions</CardTitle></CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {report.exceptions.length ? report.exceptions.map((row) => <Button key={row.id} asChild size="sm" variant="outline"><Link href={row.href}>{row.title}: {row.count}</Link></Button>) : <span className="text-sm text-muted-foreground">No visible exceptions.</span>}
                    </CardContent>
                </Card>

                <SourceTable title="Approved expenses" source={report.sources.expenses} currency={report.filters.currency} empty="No approved expenses dated in this period." />
                <SourceTable title="POS receipts" source={report.sources.receipts} currency={report.filters.currency} empty="No recorded POS payments in this period." />
                <SourceTable title="Expense payments" source={report.sources.payments} currency={report.filters.currency} empty="No recorded expense payments in this period." />
            </div>
        </AppLayout>
    );
}

function Metric({ title, value, exact, detail }: { title: string; value: string; exact?: string; detail: string }) {
    return <Card><CardContent className="space-y-2 py-4">
        <p className="text-sm text-muted-foreground">{title}</p>
        <p className="break-words text-xl font-semibold tabular-nums" title={exact} aria-label={exact}>{value}</p>
        <p className="text-xs text-muted-foreground">{detail}</p>
    </CardContent></Card>;
}

function SourceTable({ title, source, currency, empty }: { title: string; source: Source; currency: string; empty: string }) {
    return <Card>
        <CardHeader><CardTitle className="text-base">{title}</CardTitle><p className="text-sm text-muted-foreground">{source.count} records · latest {source.rows.length} shown</p></CardHeader>
        <CardContent><div className="overflow-x-auto"><Table>
            <TableHeader><TableRow><TableHead>Date</TableHead><TableHead>Reference</TableHead><TableHead>Description</TableHead><TableHead className="text-right">Amount</TableHead></TableRow></TableHeader>
            <TableBody>{source.rows.map((row) => <TableRow key={row.id}>
                <TableCell>{row.date}</TableCell>
                <TableCell><Link className="font-medium text-primary hover:underline" href={row.href}>{row.reference}</Link></TableCell>
                <TableCell>{row.description}</TableCell>
                <TableCell className="text-right tabular-nums">{currency} {exactMoney(row.amount)}</TableCell>
            </TableRow>)}
                {!source.rows.length && <TableRow><TableCell colSpan={4} className="py-8 text-center text-muted-foreground">{empty}</TableCell></TableRow>}
            </TableBody>
        </Table></div></CardContent>
    </Card>;
}
