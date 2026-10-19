import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
import { compactMoney, exactMoney } from '@/lib/money-display';
import { formatNumber } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as projectShow } from '@/routes/projects';
import { projectBudgets } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

type Row = {
    id: string;
    reference: string;
    name: string;
    branch: string;
    status: string;
    budget: string | null;
    spent: string | null;
    remaining: string | null;
};
type Report = {
    currency: string;
    total_budget: string;
    total_spent: string | null;
    total_remaining: string | null;
    project_count: number;
    budgeted_count: number;
    unbudgeted_count: number;
    uncomparable_count: number;
    rows: Row[];
};

export default function ProjectBudgets({
    report,
    currencies,
}: {
    report: Report;
    currencies: string[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard.url() },
        { title: 'Project budgets', href: projectBudgets.url() },
    ];
    const amount = (value: string | null) =>
        value === null
            ? '—'
            : `${report.currency} ${exactMoney(value)}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project budgets" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Project budgets</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Budgets and recorded costs for projects in the selected branch and currency.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={dashboard.url()}><ArrowLeft />Dashboard</Link>
                    </Button>
                </div>

                {currencies.length > 1 && (
                    <div className="flex flex-wrap gap-2" aria-label="Report currency">
                        {currencies.map((currency) => (
                            <Button key={currency} asChild size="sm" variant={currency === report.currency ? 'default' : 'outline'}>
                                <Link href={projectBudgets.url({ query: { currency } })}>{currency}</Link>
                            </Button>
                        ))}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary title="Total budgets" value={report.total_budget} currency={report.currency} />
                    <Summary title="Incurred project cost" value={report.total_spent} currency={report.currency} />
                    <Summary title="Remaining budget" value={report.total_remaining} currency={report.currency} />
                    <Summary title="Projects" value={formatNumber(report.project_count)} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Project breakdown</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {report.budgeted_count} budgeted · {report.unbudgeted_count} without budgets
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project</TableHead>
                                        <TableHead>Branch</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Budget</TableHead>
                                        <TableHead className="text-right">Incurred cost</TableHead>
                                        <TableHead className="text-right">Remaining</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Link className="font-medium text-primary hover:underline" href={projectShow.url(row.id)}>{row.name}</Link>
                                                <div className="text-xs text-muted-foreground">{row.reference}</div>
                                            </TableCell>
                                            <TableCell>{row.branch}</TableCell>
                                            <TableCell className="capitalize">{row.status.replaceAll('_', ' ')}</TableCell>
                                            <TableCell className="text-right tabular-nums">{amount(row.budget)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{amount(row.spent)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{amount(row.remaining)}</TableCell>
                                        </TableRow>
                                    ))}
                                    {report.rows.length === 0 && (
                                        <TableRow><TableCell colSpan={6} className="py-10 text-center text-muted-foreground">No projects in this currency.</TableCell></TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <p className="mt-4 text-xs text-muted-foreground">
                            Incurred cost includes approved site-report input costs and approved project expenses. Purchase orders are commitments, and payments are cash outflows; neither is added here. Projects whose budget currency differs from their branch cost currency show no cost or remaining figure.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Summary({ title, value, currency }: { title: string; value: string | null; currency?: string }) {
    const exact = currency && value !== null ? `${currency} ${exactMoney(value)}` : undefined;
    return (
        <Card>
            <CardContent className="space-y-2 py-5">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="break-words text-xl font-semibold tabular-nums" title={exact} aria-label={exact}>
                    {currency ? (value === null ? '—' : `${currency} ${compactMoney(value)}`) : value}
                </p>
            </CardContent>
        </Card>
    );
}
