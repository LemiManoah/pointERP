import { Head } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Building2,
    CheckCircle2,
    Database,
    Globe2,
    LockKeyhole,
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
        phase: string;
    };
    currentTenant: CurrentTenant | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const foundationItems = [
    {
        title: 'Tenant isolation',
        description:
            'Authenticated users resolve their tenant from server-side identity.',
        icon: LockKeyhole,
    },
    {
        title: 'Reference data',
        description:
            'Countries and currencies are global, active/inactive controlled lists.',
        icon: Database,
    },
    {
        title: 'UUID access model',
        description:
            'Users, tenants, roles and permissions are prepared for UUID ownership.',
        icon: CheckCircle2,
    },
];

export default function Dashboard({ metrics, currentTenant }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
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
                            Foundation workspace
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            The ERP foundation now has tenant ownership, ISO
                            reference data, UUID permissions, and a fail-closed
                            tenant context for later operational modules.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        title="Tenants"
                        value={metrics.tenants}
                        icon={Building2}
                        description="Active SaaS boundaries"
                    />
                    <MetricCard
                        title="Countries"
                        value={metrics.countries}
                        icon={Globe2}
                        description="Pilot reference countries"
                    />
                    <MetricCard
                        title="Currencies"
                        value={metrics.currencies}
                        icon={BadgeDollarSign}
                        description="Enabled ISO currencies"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Phase 1A readiness</CardTitle>
                            <CardDescription>
                                These pieces are intentionally narrow and will
                                support branch access next.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {foundationItems.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <div
                                        key={item.title}
                                        className="flex gap-3 rounded-md border p-3"
                                    >
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <Icon className="size-4" />
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium">
                                                {item.title}
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Current tenant</CardTitle>
                            <CardDescription>
                                Resolved from the authenticated user.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {currentTenant ? (
                                <dl className="grid gap-3 text-sm">
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
                                        value={
                                            currentTenant.default_currency_code
                                        }
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
            </div>
        </AppLayout>
    );
}

function MetricCard({
    title,
    value,
    icon: Icon,
    description,
}: {
    title: string;
    value: number;
    icon: LucideIcon;
    description: string;
}) {
    return (
        <Card>
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
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

function TenantFact({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b pb-2 last:border-b-0 last:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}
