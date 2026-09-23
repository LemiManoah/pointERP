import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Filters = { period: string; from: string; to: string; currency: string };
type Metric = {
    id: string;
    title: string;
    amount: string;
    subtitle?: string | null;
    count: number;
    description: string;
    href: string;
    snapshot: boolean;
};
type PaymentTotal = { amount: string; count: number };
type Method = {
    method: string;
    label: string;
    received?: string;
    paid?: string;
    receivedCount?: number;
    paidCount?: number;
};
type CashFlow = {
    received?: PaymentTotal;
    paid?: PaymentTotal;
    net?: string;
    interval: 'day' | 'week' | 'month';
    series: { label: string; received?: string; paid?: string }[];
    methods: Method[];
};
type Props = {
    filters: Filters;
    currencies: string[];
    cards: Metric[];
    cashFlow?: CashFlow;
    workQueues?: {
        id: string;
        title: string;
        count: number;
        description: string;
        href: string;
    }[];
    operationalCards?: {
        subtitle?: string | null;
        nearExpiry?: number;
        expired?: number;
        id: string;
        title: string;
        count: number;
        description: string;
        href: string;
    }[];

};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
];
const periods = [
    { value: 'today', label: 'Today' },
    { value: 'last7', label: 'Last 7 days' },
    { value: 'month', label: 'This month' },
    { value: 'quarter', label: 'This quarter' },
    { value: 'range', label: 'Custom range' },
];
const chartConfig = {
    received: { label: 'Money in', color: '#059669' },
    paid: { label: 'Money out', color: '#7c3aed' },
} satisfies ChartConfig;
const money = (amount: string | number) =>
    formatNumber(amount, { maximumFractionDigits: 2 });
const shortMoney = (amount: number) =>
    formatNumber(amount, { notation: 'compact', maximumFractionDigits: 1 });

export default function Dashboard({
    filters,
    currencies,
    cards,
    cashFlow,
    operationalCards = [],
    workQueues = [],
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Dashboard
                    </h1>
                    {cards.length > 0 && (
                        <DashboardFilters
                            key={JSON.stringify(filters)}
                            filters={filters}
                            currencies={currencies}
                        />
                    )}
                </div>
                {cards.length === 0 &&
                operationalCards.length === 0 &&
                workQueues.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No dashboard cards available</CardTitle>
                            <CardDescription>
                                Your permissions do not include any dashboard
                                cards yet.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <TooltipProvider delayDuration={200}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                {cards.map((card) => (
                                    <StatCard key={card.id} id={card.id} title={card.title}
                                        value={money(card.amount)} currency={filters.currency}
                                        href={card.href} description={card.description}
                                        subtitle={card.subtitle ?? (formatNumber(card.count) + ' ' + (card.id === 'sales' || card.id === 'receivables' ? (card.count === 1 ? 'sale' : 'sales') : (card.count === 1 ? 'expense' : 'expenses')))}
                                    />
                                ))}
                                {operationalCards.map((card) => (
                                    <StatCard key={card.id} id={card.id} title={card.title}
                                        value={formatNumber(card.count)} href={card.href}
                                        description={card.description} subtitle={card.subtitle}
                                        nearExpiry={card.nearExpiry} expired={card.expired}
                                    />
                                ))}
                            </div>
                        </TooltipProvider>
                        {cashFlow && (
                            <div className="grid min-w-0 gap-6 xl:grid-cols-5">
                                <MoneyMovement
                                    flow={cashFlow}
                                    currency={filters.currency}
                                />
                                <PaymentMethods
                                    flow={cashFlow}
                                    currency={filters.currency}
                                />
                            </div>
                        )}
                        {workQueues.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Work queues
                                    </CardTitle>
                                    <CardDescription>
                                        Current work in your accessible branches
                                        · across all dates
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-x-8 md:grid-cols-2">
                                    {workQueues.map((queue) => (
                                        <Link
                                            key={queue.id}
                                            href={queue.href}
                                            className="flex items-center justify-between gap-4 border-b py-4 hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {queue.title}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {queue.description}
                                                </p>
                                            </div>
                                            <span className="text-2xl font-semibold tabular-nums">
                                                {formatNumber(queue.count)}
                                            </span>
                                        </Link>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function StatCard({ id, title, value, currency, href, description, subtitle, nearExpiry, expired }: {
    id: string;
    title: string;
    value: string;
    currency?: string;
    href: string;
    description: string;
    subtitle?: string | null;
    nearExpiry?: number;
    expired?: number;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link href={href} className="min-w-0 rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
                    <Card className="h-44 gap-0 py-5 transition-colors hover:bg-muted/30">
                        <CardContent className="flex h-full min-w-0 flex-col justify-between px-5">
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
                                {currency ? <span className="text-xs text-muted-foreground">{currency}</span> : <ArrowUpRight className="size-4 text-muted-foreground" aria-hidden="true" />}
                            </div>
                            <p data-testid={'metric-' + id} className="text-2xl font-semibold tracking-tight break-words tabular-nums">{value}</p>
                            {nearExpiry !== undefined && expired !== undefined ? (
                                <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums">
                                    <span className="text-amber-700 dark:text-amber-400">{formatNumber(nearExpiry)} near expiry</span>
                                    <span className="text-destructive">{formatNumber(expired)} expired</span>
                                </div>
                            ) : <p className="min-h-4 text-xs text-muted-foreground">{subtitle}</p>}
                        </CardContent>
                    </Card>
                </Link>
            </TooltipTrigger>
            <TooltipContent className="max-w-72" side="bottom">{description}</TooltipContent>
        </Tooltip>
    );
}

function DashboardFilters({
    filters,
    currencies,
}: {
    filters: Filters;
    currencies: string[];
}) {
    const [period, setPeriod] = useState(filters.period);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props;
    const visit = (next: Filters) => {
        setBusy(true);
        router.get(dashboard.url(), next, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };
    return (
        <div className="flex max-w-full flex-col items-start gap-3 sm:items-end">
            <div className="flex max-w-full flex-wrap items-center gap-2">
                {currencies.length > 1 && (
                    <Select
                        value={filters.currency}
                        disabled={busy}
                        onValueChange={(currency) =>
                            visit({ ...filters, currency })
                        }
                    >
                        <SelectTrigger aria-label="Currency">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {currencies.map((currency) => (
                                <SelectItem key={currency} value={currency}>
                                    {currency}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <Select
                    value={period}
                    disabled={busy}
                    onValueChange={(value) => {
                        setPeriod(value);
                        if (value !== 'range')
                            visit({ ...filters, period: value });
                    }}
                >
                    <SelectTrigger
                        aria-label="Reporting period"
                        className="w-44"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {periods.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            {period === 'range' && (
                <form
                    className="flex max-w-full flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ ...filters, period, from, to });
                    }}
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="dashboard-from">From</Label>
                        <Input
                            id="dashboard-from"
                            type="date"
                            required
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            disabled={busy}
                            className="w-40"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="dashboard-to">To</Label>
                        <Input
                            id="dashboard-to"
                            type="date"
                            required
                            min={from}
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            disabled={busy}
                            className="w-40"
                        />
                    </div>
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Updating…' : 'Apply'}
                    </Button>
                </form>
            )}
            {Object.entries(errors ?? {})
                .filter(([key]) =>
                    ['period', 'from', 'to', 'currency'].includes(key),
                )
                .map(([key, error]) => (
                    <p
                        key={key}
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {error}
                    </p>
                ))}
        </div>
    );
}

function MoneyMovement({
    flow,
    currency,
}: {
    flow: CashFlow;
    currency: string;
}) {
    const hasActivity =
        (flow.received?.count ?? 0) + (flow.paid?.count ?? 0) > 0;
    const data = flow.series.map((point) => ({
        ...point,
        ...(point.received !== undefined
            ? { received: Number(point.received) }
            : {}),
        ...(point.paid !== undefined ? { paid: Number(point.paid) } : {}),
    }));
    return (
        <Card className="min-w-0 overflow-hidden xl:col-span-3">
            <CardHeader>
                <CardTitle className="text-base">
                    {flow.received && flow.paid
                        ? 'Money in & out'
                        : flow.received
                          ? 'Money received'
                          : 'Money paid'}
                </CardTitle>
                <CardDescription>
                    Customer payments received and expense payments made ·{' '}
                    {currency}
                </CardDescription>
                <div className="mt-4 flex flex-wrap gap-x-10 gap-y-4">
                    {flow.received && (
                        <MovementTotal
                            label="Money in"
                            total={flow.received}
                            direction="in"
                        />
                    )}
                    {flow.paid && (
                        <MovementTotal
                            label="Money out"
                            total={flow.paid}
                            direction="out"
                        />
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {hasActivity ? (
                    <ChartContainer
                        config={chartConfig}
                        className="aspect-auto h-64 w-full"
                    >
                        <BarChart
                            accessibilityLayer
                            data={data}
                            margin={{ top: 12, right: 8, left: 0, bottom: 0 }}
                        >
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                                minTickGap={28}
                                tickMargin={10}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickFormatter={shortMoney}
                                width={56}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        formatter={(value, name) => (
                                            <div className="flex min-w-36 justify-between gap-4">
                                                <span>
                                                    {name === 'received'
                                                        ? 'Money in'
                                                        : 'Money out'}
                                                </span>
                                                <span className="font-medium tabular-nums">
                                                    {currency}{' '}
                                                    {money(Number(value))}
                                                </span>
                                            </div>
                                        )}
                                    />
                                }
                            />
                            {flow.received && (
                                <Bar
                                    dataKey="received"
                                    fill="var(--color-received)"
                                    radius={[3, 3, 0, 0]}
                                    maxBarSize={28}
                                />
                            )}
                            {flow.paid && (
                                <Bar
                                    dataKey="paid"
                                    fill="var(--color-paid)"
                                    radius={[3, 3, 0, 0]}
                                    maxBarSize={28}
                                />
                            )}
                        </BarChart>
                    </ChartContainer>
                ) : (
                    <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
                        No recorded payments in this period.
                    </div>
                )}
                <div className="mt-5 flex flex-wrap items-center justify-between gap-2 border-t pt-4 text-xs text-muted-foreground">
                    {flow.net !== undefined && (
                        <p>
                            Net movement{' '}
                            <span className="ml-2 font-semibold text-foreground tabular-nums">
                                {currency} {money(flow.net)}
                            </span>
                        </p>
                    )}
                    <p>Recorded payments only, not a cash or bank balance.</p>
                </div>
            </CardContent>
        </Card>
    );
}

function MovementTotal({
    label,
    total,
    direction,
}: {
    label: string;
    total: PaymentTotal;
    direction: 'in' | 'out';
}) {
    return (
        <div>
            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {direction === 'in' ? (
                    <ArrowDownLeft className="size-4 text-emerald-600" />
                ) : (
                    <ArrowUpRight className="size-4 text-violet-600" />
                )}
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">
                {money(total.amount)}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                {formatNumber(total.count)}{' '}
                {total.count === 1 ? 'payment' : 'payments'}
            </p>
        </div>
    );
}

function PaymentMethods({
    flow,
    currency,
}: {
    flow: CashFlow;
    currency: string;
}) {
    const [selected, setSelected] = useState<'received' | 'paid'>('received');
    const direction = flow[selected]
        ? selected
        : flow.received
          ? 'received'
          : 'paid';
    const colors = [
        '#059669',
        '#2563eb',
        '#7c3aed',
        '#d97706',
        '#db2777',
        '#0891b2',
    ];
    const data = flow.methods
        .map((method, index) => ({
            name: method.label,
            value: Number(method[direction] ?? 0),
            count:
                method[
                    direction === 'received' ? 'receivedCount' : 'paidCount'
                ] ?? 0,
            fill: colors[index % colors.length],
        }))
        .filter((method) => method.value > 0);
    const total = data.reduce((sum, method) => sum + method.value, 0);
    return (
        <Card className="min-w-0 overflow-hidden xl:col-span-2">
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle className="text-base">Payment methods</CardTitle>
                    {flow.received && flow.paid && (
                        <Select
                            value={direction}
                            onValueChange={(value) =>
                                setSelected(value as 'received' | 'paid')
                            }
                        >
                            <SelectTrigger
                                aria-label="Payment direction"
                                className="w-32"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="received">
                                    Money in
                                </SelectItem>
                                <SelectItem value="paid">Money out</SelectItem>
                            </SelectContent>
                        </Select>
                    )}
                </div>
                <CardDescription>
                    {direction === 'received'
                        ? 'Customer receipts'
                        : 'Expense payments'}{' '}
                    · share by amount · {currency}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {data.length === 0 ? (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        No{' '}
                        {direction === 'received'
                            ? 'customer receipts'
                            : 'expense payments'}{' '}
                        in this period.
                    </p>
                ) : (
                    <>
                        <ChartContainer
                            config={{ value: { label: 'Amount' } }}
                            className="mx-auto aspect-square h-52"
                        >
                            <PieChart accessibilityLayer>
                                <ChartTooltip
                                    content={
                                        <ChartTooltipContent
                                            hideLabel
                                            formatter={(value, name) => (
                                                <span>
                                                    {name}: {currency}{' '}
                                                    {money(Number(value))}
                                                </span>
                                            )}
                                        />
                                    }
                                />
                                <Pie
                                    data={data}
                                    dataKey="value"
                                    nameKey="name"
                                    outerRadius={88}
                                    strokeWidth={2}
                                    isAnimationActive={false}
                                />
                            </PieChart>
                        </ChartContainer>
                        <div className="space-y-3">
                            {data.map((method) => (
                                <div
                                    key={method.name}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{ background: method.fill }}
                                        />
                                        <div>
                                            <p className="font-medium">
                                                {method.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatNumber(method.count)}{' '}
                                                payments ·{' '}
                                                {formatNumber(
                                                    (method.value / total) *
                                                        100,
                                                    {
                                                        maximumFractionDigits: 1,
                                                    },
                                                )}
                                                %
                                            </p>
                                        </div>
                                    </div>
                                    <span className="font-medium tabular-nums">
                                        {money(method.value)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
