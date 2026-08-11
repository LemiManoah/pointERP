import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgeDollarSign,
    Building2,
    ClipboardCheck,
    FileText,
    FolderKanban,
    GitBranch,
    ShieldCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem, CurrentTenant } from '@/types';

type DashboardProps = {
    metrics: {
        tenants: number;
        countries: number;
        currencies: number;
        branches: number;
        users: number;
        roles: number;
        customers: number;
        contracts: number;
        projects: number;
        sites: number;
        documents: number;
        expiringDocuments: number;
        phase: string;
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
    currentTenant: CurrentTenant | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({
    metrics,
    dailyReports,
    currentTenant,
}: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{metrics.phase}</Badge>
                            <Badge
                                variant={
                                    currentTenant ? 'default' : 'destructive'
                                }
                            >
                                {currentTenant ? 'Tenant scoped' : 'No tenant'}
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Operations command centre
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            A working view of company setup, active projects,
                            daily reporting exceptions and document control.
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
                        title="Pending DSRs"
                        value={dailyReports.pending}
                        icon={ClipboardCheck}
                        description={`${formatNumber(dailyReports.missing)} missing, ${formatNumber(dailyReports.returned)} returned`}
                        href="/daily-site-reports"
                    />
                    <MetricCard
                        title="Documents"
                        value={metrics.documents}
                        icon={FileText}
                        description={`${formatNumber(metrics.expiringDocuments)} expiring soon`}
                        href="/documents"
                    />
                    <MetricCard
                        title="Active users"
                        value={metrics.users}
                        icon={Users}
                        description={`${formatNumber(metrics.roles)} role templates`}
                        href="/users"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily site reporting</CardTitle>
                            <CardDescription>
                                The DSR workflow is the operational source of
                                truth for work, evidence, delays and approvals.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <StatusTile
                                label="Draft"
                                value={dailyReports.draft}
                            />
                            <StatusTile
                                label="Pending"
                                value={dailyReports.pending}
                            />
                            <StatusTile
                                label="Returned"
                                value={dailyReports.returned}
                            />
                            <StatusTile
                                label="Missing"
                                value={dailyReports.missing}
                                tone="warning"
                            />
                            <StatusTile
                                label="Approved"
                                value={dailyReports.approved}
                                tone="success"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Operational value</CardTitle>
                            <CardDescription>
                                Provisional figures from daily site reports.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <MoneyRow
                                label="Output value"
                                value={dailyReports.outputValue}
                            />
                            <MoneyRow
                                label="Input cost"
                                value={dailyReports.inputCost}
                            />
                            <MoneyRow
                                label="Profit/loss"
                                value={dailyReports.profitLoss}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Company setup</CardTitle>
                            <CardDescription>
                                Client structure currently available in the ERP.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <SetupRow
                                icon={Building2}
                                label="Tenants"
                                value={metrics.tenants}
                            />
                            <SetupRow
                                icon={GitBranch}
                                label="Active branches"
                                value={metrics.branches}
                            />
                            <SetupRow
                                icon={BadgeDollarSign}
                                label="Currencies"
                                value={metrics.currencies}
                            />
                            <SetupRow
                                icon={ShieldCheck}
                                label="Countries"
                                value={metrics.countries}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Commercial spine</CardTitle>
                            <CardDescription>
                                Early contract/project data available for
                                onboarding and demos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <SetupRow
                                icon={Users}
                                label="Customers and subcontractors"
                                value={metrics.customers}
                            />
                            <SetupRow
                                icon={FileText}
                                label="Active contracts"
                                value={metrics.contracts}
                            />
                            <SetupRow
                                icon={FolderKanban}
                                label="Active projects"
                                value={metrics.projects}
                            />
                            <SetupRow
                                icon={ClipboardCheck}
                                label="Active sites"
                                value={metrics.sites}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Onboarding checklist</CardTitle>
                            <CardDescription>
                                Recommended flow for a new construction client.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <ChecklistItem text="Create tenant and branches in the manager app." />
                            <ChecklistItem text="Create staff positions, staff, users and roles." />
                            <ChecklistItem text="Create customers, contracts, projects and sites." />
                            <ChecklistItem text="Upload project documents and DSR evidence types." />
                            <ChecklistItem text="Train site teams to submit DSRs daily." />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Current tenant</CardTitle>
                        <CardDescription>
                            Resolved from the authenticated user.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {currentTenant ? (
                            <dl className="grid gap-3 text-sm md:grid-cols-5">
                                <TenantFact
                                    label="Name"
                                    value={currentTenant.name}
                                />
                                <TenantFact
                                    label="Code"
                                    value={currentTenant.code}
                                />
                                <TenantFact
                                    label="Base currency"
                                    value={currentTenant.default_currency_code}
                                />
                                <TenantFact
                                    label="Timezone"
                                    value={currentTenant.timezone}
                                />
                                <TenantFact
                                    label="Status"
                                    value={currentTenant.status}
                                />
                            </dl>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No tenant is attached to this user yet.
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

function StatusTile({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: number | string;
    tone?: 'default' | 'success' | 'warning';
}) {
    const toneClass =
        tone === 'success'
            ? 'border-green-200 bg-green-50 text-green-950'
            : tone === 'warning'
              ? 'border-amber-200 bg-amber-50 text-amber-950'
              : 'border-border';

    return (
        <div className={`rounded-md border px-3 py-2 ${toneClass}`}>
            <div className="text-xs opacity-75">{label}</div>
            <div className="mt-1 text-xl font-semibold">
                {formatNumber(value)}
            </div>
        </div>
    );
}

function MoneyRow({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b pb-2 last:border-b-0 last:pb-0">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-right font-medium">
                {formatNumber(value)}
            </span>
        </div>
    );
}

function SetupRow({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-md border px-3 py-2">
            <div className="flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" />
                <span className="text-sm">{label}</span>
            </div>
            <span className="font-medium">{formatNumber(value)}</span>
        </div>
    );
}

function ChecklistItem({ text }: { text: string }) {
    return (
        <div className="flex gap-2 rounded-md border px-3 py-2">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span>{text}</span>
        </div>
    );
}

function TenantFact({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b pb-2 last:border-b-0 last:pb-0 md:block md:border-b-0 md:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium md:mt-1 md:text-left">
                {value}
            </dd>
        </div>
    );
}
