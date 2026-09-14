import { Head, Link } from '@inertiajs/react';
import { Pie, PieChart } from 'recharts';
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
import type { BreadcrumbItem } from '@/types';

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
    currentUser: { name: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const dsrChartConfig = {
    draft: { label: 'Draft', color: '#94a3b8' },
    pending: { label: 'Pending approval', color: '#f59e0b' },
    returned: { label: 'Returned', color: '#f97316' },
    missing: { label: 'Missing', color: '#ef4444' },
    approved: { label: 'Approved', color: '#22c55e' },
} satisfies ChartConfig;

const equipmentChartConfig = {
    available: { label: 'Available', color: '#22c55e' },
    assigned: { label: 'Assigned', color: '#2563eb' },
    underMaintenance: { label: 'Under maintenance', color: '#f59e0b' },
    idle: { label: 'Idle', color: '#94a3b8' },
    outOfService: { label: 'Out of service', color: '#ef4444' },
    retired: { label: 'Retired', color: '#64748b' },
} satisfies ChartConfig;

export default function Dashboard({
    metrics,
    dailyReports,
    equipment,
    expiringDocuments,
    currentUser,
}: DashboardProps) {
    const dsrData = [
        { status: 'draft', value: dailyReports.draft },
        { status: 'pending', value: dailyReports.pending },
        { status: 'returned', value: dailyReports.returned },
        { status: 'missing', value: dailyReports.missing },
        { status: 'approved', value: dailyReports.approved },
    ]
        .filter((entry) => entry.value > 0)
        .map((entry) => ({ ...entry, fill: `var(--color-${entry.status})` }));
    const equipmentData = [
        { status: 'available', value: equipment.available },
        { status: 'assigned', value: equipment.assigned },
        { status: 'underMaintenance', value: equipment.underMaintenance },
        { status: 'idle', value: equipment.idle },
        { status: 'outOfService', value: equipment.outOfService },
        { status: 'retired', value: equipment.retired },
    ]
        .filter((entry) => entry.value > 0)
        .map((entry) => ({ ...entry, fill: `var(--color-${entry.status})` }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Welcome back, {currentUser.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Live operational information for your accessible branches.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard title="Active projects" value={metrics.projects} description={`${formatNumber(metrics.sites)} active sites`} href="/projects" />
                    <MetricCard title="DSRs awaiting approval" value={dailyReports.pending} description={`${formatNumber(dailyReports.approved)} approved, ${formatNumber(dailyReports.missing)} missing`} href="/daily-site-reports" />
                    <MetricCard title="Equipment available" value={equipment.available} description={`${formatNumber(equipment.underMaintenance)} under maintenance of ${formatNumber(equipment.total)}`} href="/equipment" />
                    <MetricCard title="Documents expiring" value={metrics.expiringDocuments} description={`${formatNumber(metrics.documents)} documents on file`} href="/documents" />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <LivePieCard
                        title="DSR workflow"
                        description="Current daily site report status from the database."
                        data={dsrData}
                        config={dsrChartConfig}
                        nameKey="status"
                        empty="No daily site report data yet."
                    />
                    <LivePieCard
                        title="Equipment status"
                        description="Current equipment register status from the database."
                        data={equipmentData}
                        config={equipmentChartConfig}
                        nameKey="status"
                        empty="No equipment data yet."
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Documents expiring soon</CardTitle>
                        <CardDescription>Certificates, permits and contracts expiring within 30 days.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {expiringDocuments.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">No documents expiring in the next 30 days.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b text-muted-foreground">
                                            <th className="py-3 pr-4 font-medium">Document</th>
                                            <th className="py-3 pr-4 font-medium">Type</th>
                                            <th className="py-3 pr-4 font-medium">Reference</th>
                                            <th className="py-3 pr-4 font-medium">Expires</th>
                                            <th className="py-3 text-right font-medium">Due</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expiringDocuments.map((document) => (
                                            <tr key={document.id} className="border-b last:border-0">
                                                <td className="py-3 pr-4"><Link href={`/documents/${document.id}`} className="font-medium hover:underline">{document.title}</Link></td>
                                                <td className="py-3 pr-4 text-muted-foreground">{document.type_name ?? 'Document'}</td>
                                                <td className="py-3 pr-4 text-muted-foreground">{document.reference ?? '-'}</td>
                                                <td className="py-3 pr-4">{document.expires_on ?? '-'}</td>
                                                <td className="py-3 text-right"><Badge variant={(document.days_left ?? 99) <= 7 ? 'destructive' : 'secondary'}>{document.days_left === null ? '-' : document.days_left < 0 ? `${Math.abs(document.days_left)} days overdue` : `${document.days_left} ${document.days_left === 1 ? 'day' : 'days'}`}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function LivePieCard({ title, description, data, config, nameKey, empty }: { title: string; description: string; data: { [key: string]: string | number }[]; config: ChartConfig; nameKey: string; empty: string }) {
    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader><CardTitle>{title}</CardTitle><CardDescription>{description}</CardDescription></CardHeader>
            <CardContent className="min-w-0 overflow-hidden">
                {data.length === 0 ? <p className="py-16 text-center text-sm text-muted-foreground">{empty}</p> : <ChartContainer config={config} className="mx-auto aspect-square max-h-[300px] w-full max-w-full [&_.recharts-sector]:transition-opacity [&_.recharts-sector:hover]:opacity-70">
                    <PieChart>
                        <ChartTooltip cursor={false} content={<ChartTooltipContent nameKey={nameKey} hideLabel />} />
                        <Pie data={data} dataKey="value" nameKey={nameKey} innerRadius={60} outerRadius={90} strokeWidth={4} />
                        <ChartLegend content={<ChartLegendContent nameKey={nameKey} className="flex-row flex-wrap justify-center gap-x-3 gap-y-2 px-1" />} />
                    </PieChart>
                </ChartContainer>}
            </CardContent>
        </Card>
    );
}

function MetricCard({ title, value, description, href }: { title: string; value: number | string; description: string; href: string }) {
    return <Link href={href}><Card className="h-full transition-colors hover:bg-muted/40"><CardHeader><CardDescription>{title}</CardDescription><CardTitle className="mt-2 text-3xl">{formatNumber(value)}</CardTitle></CardHeader><CardContent><p className="text-sm text-muted-foreground">{description}</p></CardContent></Card></Link>;
}