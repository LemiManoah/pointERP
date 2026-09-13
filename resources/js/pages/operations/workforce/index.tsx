import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { ClipboardCheck, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { StaffDeploymentDialog } from './partials/staff-deployment-dialog';
import {
    WorkforceTradeDialog,
    type Trade,
} from './partials/workforce-trade-dialog';

type WorkforceStaff = {
    id: string;
    name: string;
    staff_number: string;
    branch_id: string;
    branch_name: string;
    primary_trade_id: string | null;
    primary_trade_name: string | null;
};

type Project = {
    id: string;
    name: string;
    reference: string;
    branch_id: string;
    branch_name: string;
    sites: { id: string; name: string }[];
};

type Deployment = {
    id: string;
    staff_name: string;
    staff_number: string;
    project_name: string;
    project_reference: string;
    site_name: string | null;
    branch_name: string;
    trade_name: string;
    starts_on: string;
    ends_on: string | null;
    status: 'active' | 'ended';
    notes: string | null;
    assigned_by_name: string;
    ended_by_name: string | null;
};

type Props = {
    tab: 'deployments' | 'trades';
    trades: Trade[];
    staff: WorkforceStaff[];
    projects: Project[];
    deployments: Deployment[];
    tradeCategories: { value: string; label: string }[];
    can: {
        manageTrades: boolean;
        manageDeployments: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workforce', href: '/workforce' },
];

function displayDate(value: string) {
    return format(parseISO(value), 'dd MMM yyyy');
}

export default function WorkforceIndex({
    tab,
    trades,
    staff,
    projects,
    deployments,
    tradeCategories,
    can,
}: Props) {
    const confirm = useConfirmDialog();
    const [currentTab, setCurrentTab] = useState(tab);
    const [tradeStatus, setTradeStatus] = useState('active');
    const [deploymentStatus, setDeploymentStatus] = useState('active');
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);
    const term = debouncedSearch.trim().toLowerCase();

    const filteredTrades = useMemo(
        () =>
            trades.filter(
                (trade) =>
                    (trade.is_active ? 'active' : 'inactive') === tradeStatus &&
                    (!term ||
                        [trade.code, trade.name, trade.category_label]
                            .join(' ')
                            .toLowerCase()
                            .includes(term)),
            ),
        [term, tradeStatus, trades],
    );

    const filteredDeployments = useMemo(
        () =>
            deployments.filter(
                (deployment) =>
                    deployment.status === deploymentStatus &&
                    (!term ||
                        [
                            deployment.staff_name,
                            deployment.staff_number,
                            deployment.project_name,
                            deployment.project_reference,
                            deployment.site_name,
                            deployment.branch_name,
                            deployment.trade_name,
                        ]
                            .join(' ')
                            .toLowerCase()
                            .includes(term)),
            ),
        [deploymentStatus, deployments, term],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workforce" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Workforce setup
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Maintain site trades and a clear history of where
                            staff are deployed.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/workforce/attendance">
                            <ClipboardCheck />
                            Site attendance
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-sm">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={
                                currentTab === 'deployments'
                                    ? 'Search deployments'
                                    : 'Search trades'
                            }
                            className="pl-9"
                        />
                    </div>
                    {currentTab === 'deployments' && can.manageDeployments && (
                        <StaffDeploymentDialog
                            staff={staff}
                            projects={projects}
                            trades={trades}
                        />
                    )}
                    {currentTab === 'trades' && can.manageTrades && (
                        <WorkforceTradeDialog categories={tradeCategories} />
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs
                        value={currentTab}
                        onValueChange={(value) =>
                            setCurrentTab(value as 'deployments' | 'trades')
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="deployments">
                                Deployments
                            </TabsTrigger>
                            <TabsTrigger value="trades">Trades</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {currentTab === 'deployments' ? (
                        <Tabs
                            value={deploymentStatus}
                            onValueChange={setDeploymentStatus}
                        >
                            <TabsList>
                                <TabsTrigger value="active">Active</TabsTrigger>
                                <TabsTrigger value="ended">History</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    ) : (
                        <Tabs
                            value={tradeStatus}
                            onValueChange={setTradeStatus}
                        >
                            <TabsList>
                                <TabsTrigger value="active">Active</TabsTrigger>
                                <TabsTrigger value="inactive">
                                    Inactive
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    )}
                </div>

                {currentTab === 'deployments' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Staff deployments</CardTitle>
                            <CardDescription>
                                Reassignment preserves the previous deployment
                                in History.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Staff</TableHead>
                                        <TableHead>Project and site</TableHead>
                                        <TableHead>Trade</TableHead>
                                        <TableHead>Period</TableHead>
                                        <TableHead>Recorded by</TableHead>
                                        {deploymentStatus === 'active' &&
                                            can.manageDeployments && (
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredDeployments.map((deployment) => (
                                        <TableRow key={deployment.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {deployment.staff_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {deployment.staff_number} -{' '}
                                                    {deployment.branch_name}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {deployment.project_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {deployment.site_name ??
                                                        'Project level'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {deployment.trade_name}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {displayDate(
                                                        deployment.starts_on,
                                                    )}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {deployment.ends_on
                                                        ? 'Ended ' +
                                                          displayDate(
                                                              deployment.ends_on,
                                                          )
                                                        : 'Current'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {
                                                        deployment.assigned_by_name
                                                    }
                                                </div>
                                                {deployment.ended_by_name && (
                                                    <div className="text-xs text-muted-foreground">
                                                        Ended by{' '}
                                                        {
                                                            deployment.ended_by_name
                                                        }
                                                    </div>
                                                )}
                                            </TableCell>
                                            {deploymentStatus === 'active' &&
                                                can.manageDeployments && (
                                                    <TableCell>
                                                        <div className="flex justify-end">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    confirm({
                                                                        title: 'End deployment?',
                                                                        description:
                                                                            deployment.staff_name +
                                                                            ' will no longer appear as currently deployed to ' +
                                                                            deployment.project_name +
                                                                            '.',
                                                                        confirmLabel:
                                                                            'End deployment',
                                                                        onConfirm:
                                                                            () =>
                                                                                router.delete(
                                                                                    '/workforce/deployments/' +
                                                                                        deployment.id,
                                                                                    {
                                                                                        preserveScroll: true,
                                                                                    },
                                                                                ),
                                                                    })
                                                                }
                                                            >
                                                                End
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                )}
                                        </TableRow>
                                    ))}
                                    {filteredDeployments.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No deployments match the current
                                                view.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Construction trades</CardTitle>
                            <CardDescription>
                                Trades describe practical skills used for
                                staffing and site reporting.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Trade</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Status</TableHead>
                                        {can.manageTrades && (
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredTrades.map((trade) => (
                                        <TableRow key={trade.id}>
                                            <TableCell className="font-medium">
                                                {trade.name}
                                            </TableCell>
                                            <TableCell>{trade.code}</TableCell>
                                            <TableCell>
                                                {trade.category_label}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        trade.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {trade.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            {can.manageTrades && (
                                                <TableCell>
                                                    <div className="flex justify-end gap-2">
                                                        <WorkforceTradeDialog
                                                            trade={trade}
                                                            categories={
                                                                tradeCategories
                                                            }
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant={
                                                                trade.is_active
                                                                    ? 'destructive'
                                                                    : 'secondary'
                                                            }
                                                            onClick={() =>
                                                                confirm({
                                                                    title: trade.is_active
                                                                        ? 'Deactivate trade?'
                                                                        : 'Restore trade?',
                                                                    description:
                                                                        trade.is_active
                                                                            ? 'It will no longer be available for new staff and deployments.'
                                                                            : 'It will be available for new staff and deployments.',
                                                                    confirmLabel:
                                                                        trade.is_active
                                                                            ? 'Deactivate'
                                                                            : 'Restore',
                                                                    variant:
                                                                        trade.is_active
                                                                            ? 'destructive'
                                                                            : 'default',
                                                                    onConfirm:
                                                                        () =>
                                                                            router.delete(
                                                                                '/workforce/trades/' +
                                                                                    trade.id,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            ),
                                                                })
                                                            }
                                                        >
                                                            {trade.is_active
                                                                ? 'Deactivate'
                                                                : 'Restore'}
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                    {filteredTrades.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No trades match the current
                                                view.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
