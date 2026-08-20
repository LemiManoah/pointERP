import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ClipboardCheck,
    FileText,
    FolderKanban,
    HardHat,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant } from '@/types';

type DashboardProps = {
    metrics: {
        projects: number;
        sites: number;
        documents: number;
        expiringDocuments: number;
    };
    dailyReports: {
        draft: number;
        pending: number;
        returned: number;
        missing: number;
        approved: number;
        outputValue: number | string;
        inputCost: number | string;
        profitLoss: number | string;
    };
    equipment: {
        total: number;
        available: number;
        assigned: number;
        underMaintenance: number;
        idle: number;
        outOfService: number;
        retired: number;
    };
    expiringDocuments: {
        id: string;
        title: string;
        reference: string | null;
        type_name: string | null;
        expires_on: string | null;
        days_left: number | null;
    }[];
    currentTenant: CurrentTenant | null;
    currentUser: { name: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Provisional figures until financials are posted from approved DSRs.
// Values are in UGX billions.
const monthlyTrend = [
    { month: 'Mar', output: 4.2, input: 3.1 },
    { month: 'Apr', output: 5.1, input: 3.6 },
    { month: 'May', output: 4.8, input: 3.9 },
    { month: 'Jun', output: 6.3, input: 4.2 },
    { month: 'Jul', output: 7.1, input: 4.8 },
    { month: 'Aug', output: 6.8, input: 5.0 },
];

// Percentage split of recorded DSR input cost.
const costBreakdown = [
    { name: 'labour', value: 32, fill: 'var(--color-labour)' },
    { name: 'materials', value: 28, fill: 'var(--color-materials)' },
    { name: 'equipment', value: 18, fill: 'var(--color-equipment)' },
    { name: 'fuel', value: 14, fill: 'var(--color-fuel)' },
    { name: 'subcontract', value: 8, fill: 'var(--color-subcontract)' },
];

// Provisional output value by BOQ work category, UGX billions.
const outputByCategory = [
    { category: 'Earthworks', value: 12.4 },
    { category: 'Base course', value: 9.8 },
    { category: 'Asphalt', value: 11.2 },
    { category: 'Drainage', value: 6.4 },
    { category: 'Structures', value: 4.1 },
    { category: 'Road furniture', value: 2.9 },
];

// Provisional DSR submission compliance per site.
const siteCompliance = [
    { site: 'Busunju Section', rate: 92 },
    { site: 'Kiboga–Hoima Section', rate: 78 },
    { site: 'Juba Main Site', rate: 85 },
];

const dsrChartConfig = {
    draft: { label: 'Draft', color: '#94a3b8' },
    pending: { label: 'Pending approval', color: '#f59e0b' },
    returned: { label: 'Returned', color: '#f97316' },
    missing: { label: 'Missing', color: '#ef4444' },
    approved: { label: 'Approved', color: '#22c55e' },
} satisfies ChartConfig;

const trendChartConfig = {
    output: { label: 'Output value', color: '#2563eb' },
    input: { label: 'Input cost', color: '#f97316' },
} satisfies ChartConfig;

const costChartConfig = {
    labour: { label: 'Labour', color: '#2563eb' },
    materials: { label: 'Materials', color: '#7c3aed' },
    equipment: { label: 'Equipment', color: '#0891b2' },
    fuel: { label: 'Fuel', color: '#f59e0b' },
    subcontract: { label: 'Subcontract', color: '#64748b' },
} satisfies ChartConfig;

const categoryChartConfig = {
    value: { label: 'Output value', color: '#16a34a' },
} satisfies ChartConfig;

const equipmentChartConfig = {
    available: { label: 'Available', color: '#22c55e' },
    assigned: { label: 'Assigned', color: '#2563eb' },
    underMaintenance: { label: 'Under maintenance', color: '#f59e0b' },
    idle: { label: 'Idle', color: '#94a3b8' },
    outOfService: { label: 'Out of service', color: '#ef4444' },
    retired: { label: 'Retired', color: '#64748b' },
} satisfies ChartConfig;

const fallbackDsrData = [
    { status: 'draft', value: 2 },
    { status: 'pending', value: 3 },
    { status: 'returned', value: 1 },
    { status: 'missing', value: 1 },
    { status: 'approved', value: 8 },
];

const fallbackEquipmentData = [
    { status: 'available', value: 4 },
    { status: 'assigned', value: 7 },
    { status: 'underMaintenance', value: 2 },
    { status: 'idle', value: 1 },
];

export default function Dashboard({
    metrics,
    dailyReports,
    equipment,
    expiringDocuments,
    currentTenant,
    currentUser,
}: DashboardProps) {
    const dsrData = [
        { status: 'draft', value: dailyReports.draft },
        { status: 'pending', value: dailyReports.pending },
        { status: 'returned', value: dailyReports.returned },
        { status: 'missing', value: dailyReports.missing },
        { status: 'approved', value: dailyReports.approved },
    ].filter((entry) => entry.value > 0);

    const equipmentData = [
        { status: 'available', value: equipment.available },
        { status: 'assigned', value: equipment.assigned },
        { status: 'underMaintenance', value: equipment.underMaintenance },
        { status: 'idle', value: equipment.idle },
        { status: 'outOfService', value: equipment.outOfService },
        { status: 'retired', value: equipment.retired },
    ].filter((entry) => entry.value > 0);

    const visibleDsrData = (dsrData.length > 0 ? dsrData : fallbackDsrData).map(
        (entry) => ({
            ...entry,
            fill: `var(--color-${entry.status})`,
        }),
    );
    const visibleEquipmentData = (
        equipmentData.length > 0 ? equipmentData : fallbackEquipmentData
    ).map((entry) => ({
        ...entry,
        fill: `var(--color-${entry.status})`,
    }));

    const currency = currentTenant?.default_currency_code ?? '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Welcome back, {currentUser.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Here is your operational overview for today.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title="Active projects"
                        value={metrics.projects}
                        icon={FolderKanban}
                        description={`${formatNumber(metrics.sites)} active sites`}
                        href="/projects"
                    />
                    <MetricCard
                        title="DSRs awaiting approval"
                        value={dailyReports.pending}
                        icon={ClipboardCheck}
                        description={`${formatNumber(dailyReports.approved)} approved, ${formatNumber(dailyReports.missing)} missing`}
                        href="/daily-site-reports"
                    />
                    <MetricCard
                        title="Equipment available"
                        value={equipment.available}
                        icon={HardHat}
                        description={`${formatNumber(equipment.underMaintenance)} under maintenance of ${formatNumber(equipment.total)}`}
                        href="/equipment"
                    />
                    <MetricCard
                        title="Documents expiring"
                        value={metrics.expiringDocuments}
                        icon={FileText}
                        description={`${formatNumber(metrics.documents)} documents on file`}
                        href="/documents"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>DSR workflow</CardTitle>
                            <CardDescription>
                                Current status of daily site reports this
                                period.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={dsrChartConfig}
                                className="mx-auto aspect-square max-h-[300px] w-full [&_.recharts-sector]:transition-opacity [&_.recharts-sector:hover]:opacity-70"
                            >
                                <PieChart>
                                    <ChartTooltip
                                        cursor={false}
                                        content={
                                            <ChartTooltipContent
                                                nameKey="status"
                                                hideLabel
                                            />
                                        }
                                    />
                                    <Pie
                                        data={visibleDsrData}
                                        dataKey="value"
                                        nameKey="status"
                                        innerRadius={60}
                                        outerRadius={80}
                                        strokeWidth={4}
                                    />
                                    <ChartLegend
                                        content={
                                            <ChartLegendContent
                                                nameKey="status"
                                                className="flex-wrap gap-2 [&>*]:basis-1/2 [&>*]:justify-center"
                                            />
                                        }
                                    />
                                </PieChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Output vs input cost</CardTitle>
                            <CardDescription>
                                Provisional monthly trend in UGX billions for
                                the last six months.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={trendChartConfig}
                                className="h-[280px] w-full"
                            >
                                <AreaChart
                                    data={monthlyTrend}
                                    margin={{ left: 4, right: 12, top: 8 }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="month"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        tickFormatter={(value) => `${value}B`}
                                    />
                                    <ChartTooltip
                                        cursor={false}
                                        content={
                                            <ChartTooltipContent
                                                formatter={(value) =>
                                                    `UGX ${formatNumber(
                                                        Number(value),
                                                    )}B`
                                                }
                                            />
                                        }
                                    />
                                    <Area
                                        dataKey="output"
                                        type="monotone"
                                        fill="var(--color-output)"
                                        fillOpacity={0.2}
                                        stroke="var(--color-output)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        dataKey="input"
                                        type="monotone"
                                        fill="var(--color-input)"
                                        fillOpacity={0.2}
                                        stroke="var(--color-input)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cost breakdown</CardTitle>
                            <CardDescription>
                                Share of recorded DSR input cost by category.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={costChartConfig}
                                className="mx-auto aspect-square max-h-[300px] w-full [&_.recharts-sector]:transition-opacity [&_.recharts-sector:hover]:opacity-70"
                            >
                                <PieChart>
                                    <ChartTooltip
                                        cursor={false}
                                        content={
                                            <ChartTooltipContent
                                                nameKey="name"
                                                hideLabel
                                                formatter={(value) =>
                                                    `${formatNumber(Number(value))}%`
                                                }
                                            />
                                        }
                                    />
                                    <Pie
                                        data={costBreakdown}
                                        dataKey="value"
                                        nameKey="name"
                                        innerRadius={55}
                                        outerRadius={80}
                                        strokeWidth={4}
                                    />
                                    <ChartLegend
                                        content={
                                            <ChartLegendContent
                                                nameKey="name"
                                                className="flex-wrap gap-2 [&>*]:basis-1/2 [&>*]:justify-center"
                                            />
                                        }
                                    />
                                </PieChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Output by work category</CardTitle>
                            <CardDescription>
                                Provisional output value by BOQ work category in
                                UGX billions.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={categoryChartConfig}
                                className="h-[240px] w-full"
                            >
                                <BarChart
                                    data={outputByCategory}
                                    margin={{ left: 4, right: 12, top: 8 }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="category"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        interval={0}
                                        angle={-25}
                                        textAnchor="end"
                                        height={60}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        tickFormatter={(value) => `${value}B`}
                                    />
                                    <ChartTooltip
                                        cursor={false}
                                        content={
                                            <ChartTooltipContent
                                                formatter={(value) =>
                                                    `UGX ${formatNumber(
                                                        Number(value),
                                                    )}B`
                                                }
                                            />
                                        }
                                    />
                                    <Bar
                                        dataKey="value"
                                        fill="var(--color-value)"
                                        radius={4}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Equipment status</CardTitle>
                            <CardDescription>
                                Fleet availability from the equipment register.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={equipmentChartConfig}
                                className="mx-auto aspect-square max-h-[300px] w-full [&_.recharts-sector]:transition-opacity [&_.recharts-sector:hover]:opacity-70"
                            >
                                <PieChart>
                                    <ChartTooltip
                                        cursor={false}
                                        content={
                                            <ChartTooltipContent
                                                nameKey="status"
                                                hideLabel
                                            />
                                        }
                                    />
                                    <Pie
                                        data={visibleEquipmentData}
                                        dataKey="value"
                                        nameKey="status"
                                        innerRadius={55}
                                        outerRadius={80}
                                        strokeWidth={4}
                                    />
                                    <ChartLegend
                                        content={
                                            <ChartLegendContent
                                                nameKey="status"
                                                className="flex-wrap gap-2 [&>*]:basis-1/2 [&>*]:justify-center"
                                            />
                                        }
                                    />
                                </PieChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Site DSR compliance</CardTitle>
                            <CardDescription>
                                Share of expected reports submitted on time.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {siteCompliance.map((item) => (
                                <div key={item.site} className="grid gap-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>{item.site}</span>
                                        <span className="font-medium">
                                            {item.rate}%
                                        </span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full rounded-full ${item.rate >= 90 ? 'bg-green-500' : item.rate >= 80 ? 'bg-amber-500' : 'bg-red-500'}`}
                                            style={{ width: `${item.rate}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Operational value to date</CardTitle>
                            <CardDescription>
                                Provisional totals recorded on approved DSRs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <MoneyRow
                                label="Output value"
                                value={dailyReports.outputValue}
                                currency={currency}
                            />
                            <MoneyRow
                                label="Input cost"
                                value={dailyReports.inputCost}
                                currency={currency}
                            />
                            <MoneyRow
                                label="Profit / loss"
                                value={dailyReports.profitLoss}
                                currency={currency}
                            />
                            <div className="mt-2 flex items-center gap-2 rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
                                <AlertTriangle className="size-4 shrink-0" />
                                Figures refresh as DSRs move through approval.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Documents expiring soon</CardTitle>
                        <CardDescription>
                            Certificates, permits and contracts expiring within
                            30 days.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {expiringDocuments.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b text-muted-foreground">
                                            <th className="py-3 pr-4 font-medium">
                                                Document
                                            </th>
                                            <th className="py-3 pr-4 font-medium">
                                                Type
                                            </th>
                                            <th className="py-3 pr-4 font-medium">
                                                Reference
                                            </th>
                                            <th className="py-3 pr-4 font-medium">
                                                Expires
                                            </th>
                                            <th className="py-3 text-right font-medium">
                                                Due
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expiringDocuments.map((document) => (
                                            <tr
                                                key={document.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-3 pr-4">
                                                    <Link
                                                        href={`/documents/${document.id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {document.title}
                                                    </Link>
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {document.type_name ??
                                                        'Document'}
                                                </td>
                                                <td className="py-3 pr-4 text-muted-foreground">
                                                    {document.reference ?? '-'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    {document.expires_on ?? '-'}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Badge
                                                        variant={
                                                            (document.days_left ??
                                                                99) <= 7
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {document.days_left ===
                                                        null
                                                            ? '-'
                                                            : document.days_left <
                                                                0
                                                              ? `${Math.abs(document.days_left)} days overdue`
                                                              : `${document.days_left} ${document.days_left === 1 ? 'day' : 'days'}`}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No documents expiring in the next 30 days.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function MetricCard({
    title,
    value,
    icon: Icon,
    description,
    href,
}: {
    title: string;
    value: number | string;
    icon: LucideIcon;
    description: string;
    href: string;
}) {
    return (
        <Link href={href}>
            <Card className="h-full transition-colors hover:bg-muted/40">
                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                    <div>
                        <CardDescription>{title}</CardDescription>
                        <CardTitle className="mt-2 text-3xl">
                            {formatNumber(value)}
                        </CardTitle>
                    </div>
                    <div className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function MoneyRow({
    label,
    value,
    currency,
}: {
    label: string;
    value: number | string;
    currency: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 border-b pb-2 last:border-b-0 last:pb-0">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-right font-medium">
                {currency ? `${currency} ` : ''}
                {formatNumber(value)}
            </span>
        </div>
    );
}
