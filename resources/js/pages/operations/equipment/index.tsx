import { Head, Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { EquipmentCategoryDialog } from './partials/equipment-category-dialog';
import { EquipmentDialog } from './partials/equipment-dialog';
import {
    FuelApproveButton,
    FuelReversalDialog,
} from './partials/equipment-fuel-dialogs';
import { EquipmentLocationDialog } from './partials/equipment-location-dialog';
import type {
    BranchOption,
    EquipmentCategory,
    EquipmentFuelTransaction,
    EquipmentLocation,
    EquipmentMaintenancePortfolioSchedule,
    EquipmentMaintenancePortfolioWorkOrder,
    EquipmentRecord,
    Option,
    OwnerOption,
    ProjectOption,
    SiteOption,
    StaffOption,
} from './types';

type Props = {
    activeTab: string;
    equipment: EquipmentRecord[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    staff: StaffOption[];
    owners: OwnerOption[];
    currencies: Option[];
    fuelTransactions: EquipmentFuelTransaction[];
    maintenanceSchedules: EquipmentMaintenancePortfolioSchedule[];
    maintenanceWorkOrders: EquipmentMaintenancePortfolioWorkOrder[];
    can: {
        create: boolean;
        update: boolean;
        retire: boolean;
        manageCategories: boolean;
        manageLocations: boolean;
        viewCosts: boolean;
        viewFuelDashboard: boolean;
        viewMaintenanceDashboard: boolean;
        exportFuel: boolean;
        exportMaintenance: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Equipment', href: '/equipment' },
];

export default function EquipmentIndex(props: Props) {
    const {
        equipment,
        categories,
        locations,
        branches,
        projects,
        sites,
        owners,
        currencies,
        fuelTransactions,
        maintenanceSchedules,
        maintenanceWorkOrders,
        can,
    } = props;
    const confirm = useConfirmDialog();
    const [tab, setTab] = useState(
        ['register', 'categories', 'locations', 'fuel', 'maintenance'].includes(
            props.activeTab,
        )
            ? props.activeTab
            : 'register',
    );
    const [status, setStatus] = useState('active');
    const [fuelStatus, setFuelStatus] = useState('all');
    const [maintenanceStatus, setMaintenanceStatus] = useState('all');
    const [maintenanceDueStatus, setMaintenanceDueStatus] = useState('all');
    const [maintenancePriority, setMaintenancePriority] = useState('all');
    const [search, setSearch] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [branchId, setBranchId] = useState('all');
    const [projectId, setProjectId] = useState('all');
    const [siteId, setSiteId] = useState('all');
    const [equipmentId, setEquipmentId] = useState('all');
    const [transactionType, setTransactionType] = useState('all');
    const [sourceType, setSourceType] = useState('all');
    const [exceptionStatus, setExceptionStatus] = useState('all');
    const debouncedSearch = useDebouncedValue(search);
    const term = debouncedSearch.trim().toLowerCase();
    const rows = useMemo(() => {
        const source =
            tab === 'register'
                ? equipment
                : tab === 'categories'
                  ? categories
                  : locations;
        return source.filter(
            (record) =>
                record.is_active === (status === 'active') &&
                (!term ||
                    Object.values(record)
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [categories, equipment, locations, status, tab, term]);
    const filteredProjects = projects.filter(
        (project) => branchId === 'all' || project.branch_id === branchId,
    );
    const filteredSites = sites.filter(
        (site) =>
            (branchId === 'all' || site.branch_id === branchId) &&
            (projectId === 'all' || site.project_id === projectId),
    );
    const fuelRows = useMemo(
        () =>
            fuelTransactions.filter((transaction) => {
                const transactionDate = transaction.transacted_at.slice(0, 10);

                return (
                    (fuelStatus === 'all' ||
                        transaction.status === fuelStatus) &&
                    (!dateFrom || transactionDate >= dateFrom) &&
                    (!dateTo || transactionDate <= dateTo) &&
                    (branchId === 'all' ||
                        transaction.branch_id === branchId) &&
                    (projectId === 'all' ||
                        transaction.project_id === projectId) &&
                    (siteId === 'all' || transaction.site_id === siteId) &&
                    (equipmentId === 'all' ||
                        transaction.equipment_id === equipmentId) &&
                    (transactionType === 'all' ||
                        transaction.transaction_type === transactionType) &&
                    (sourceType === 'all' ||
                        transaction.source_type === sourceType) &&
                    (exceptionStatus === 'all' ||
                        transaction.exception_status === exceptionStatus) &&
                    (!term ||
                        Object.values(transaction)
                            .join(' ')
                            .toLowerCase()
                            .includes(term))
                );
            }),
        [
            branchId,
            dateFrom,
            dateTo,
            equipmentId,
            exceptionStatus,
            fuelStatus,
            fuelTransactions,
            projectId,
            siteId,
            sourceType,
            term,
            transactionType,
        ],
    );
    const fuelSummary = useMemo(() => summarizeFuel(fuelRows), [fuelRows]);
    const fuelExportUrl = useMemo(() => {
        const parameters = new URLSearchParams();
        const filters: Record<string, string> = {
            search: debouncedSearch.trim(),
            from: dateFrom,
            to: dateTo,
            branch_id: branchId,
            project_id: projectId,
            site_id: siteId,
            equipment_id: equipmentId,
            transaction_type: transactionType,
            source_type: sourceType,
            exception_status: exceptionStatus,
            status: fuelStatus,
        };

        Object.entries(filters).forEach(([key, value]) => {
            if (value && value !== 'all') parameters.set(key, value);
        });

        return `/equipment-fuel/export?${parameters.toString()}`;
    }, [
        branchId,
        dateFrom,
        dateTo,
        debouncedSearch,
        equipmentId,
        exceptionStatus,
        fuelStatus,
        projectId,
        siteId,
        sourceType,
        transactionType,
    ]);
    const maintenanceScheduleRows = useMemo(
        () =>
            maintenanceSchedules.filter(
                (schedule) =>
                    (branchId === 'all' || schedule.branch_id === branchId) &&
                    (projectId === 'all' ||
                        schedule.project_id === projectId) &&
                    (siteId === 'all' || schedule.site_id === siteId) &&
                    (equipmentId === 'all' ||
                        schedule.equipment_id === equipmentId) &&
                    (maintenanceDueStatus === 'all' ||
                        schedule.due_status === maintenanceDueStatus) &&
                    (!term ||
                        Object.values(schedule)
                            .join(' ')
                            .toLowerCase()
                            .includes(term)),
            ),
        [
            branchId,
            equipmentId,
            maintenanceDueStatus,
            maintenanceSchedules,
            projectId,
            siteId,
            term,
        ],
    );
    const maintenanceWorkOrderRows = useMemo(
        () =>
            maintenanceWorkOrders.filter((workOrder) => {
                const reportedDate = workOrder.reported_at.slice(0, 10);
                return (
                    (maintenanceStatus === 'all' ||
                        workOrder.status === maintenanceStatus) &&
                    (maintenancePriority === 'all' ||
                        workOrder.priority === maintenancePriority) &&
                    (!dateFrom || reportedDate >= dateFrom) &&
                    (!dateTo || reportedDate <= dateTo) &&
                    (branchId === 'all' || workOrder.branch_id === branchId) &&
                    (projectId === 'all' ||
                        workOrder.project_id === projectId) &&
                    (siteId === 'all' || workOrder.site_id === siteId) &&
                    (equipmentId === 'all' ||
                        workOrder.equipment_id === equipmentId) &&
                    (!term ||
                        Object.values(workOrder)
                            .join(' ')
                            .toLowerCase()
                            .includes(term))
                );
            }),
        [
            branchId,
            dateFrom,
            dateTo,
            equipmentId,
            maintenancePriority,
            maintenanceStatus,
            maintenanceWorkOrders,
            projectId,
            siteId,
            term,
        ],
    );
    const maintenanceSummary = useMemo(
        () =>
            summarizeMaintenance(
                maintenanceScheduleRows,
                maintenanceWorkOrderRows,
            ),
        [maintenanceScheduleRows, maintenanceWorkOrderRows],
    );
    const maintenanceExportUrl = useMemo(() => {
        const parameters = new URLSearchParams();
        const filters: Record<string, string> = {
            search: debouncedSearch.trim(),
            from: dateFrom,
            to: dateTo,
            branch_id: branchId,
            project_id: projectId,
            site_id: siteId,
            equipment_id: equipmentId,
            due_status: maintenanceDueStatus,
            status: maintenanceStatus,
            priority: maintenancePriority,
        };
        Object.entries(filters).forEach(([key, value]) => {
            if (value && value !== 'all') parameters.set(key, value);
        });
        return `/equipment-maintenance/export?${parameters.toString()}`;
    }, [
        branchId,
        dateFrom,
        dateTo,
        debouncedSearch,
        equipmentId,
        maintenanceDueStatus,
        maintenancePriority,
        maintenanceStatus,
        projectId,
        siteId,
    ]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipment" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Equipment
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Fleet register, deployment, fuel control and
                                operational locations.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={`Search ${tab}`}
                                className="w-full pl-9 sm:w-80"
                            />
                        </div>
                        {tab === 'fuel' && (
                            <FuelFilters
                                {...{
                                    dateFrom,
                                    setDateFrom,
                                    dateTo,
                                    setDateTo,
                                    branchId,
                                    setBranchId,
                                    projectId,
                                    setProjectId,
                                    siteId,
                                    setSiteId,
                                    equipmentId,
                                    setEquipmentId,
                                    transactionType,
                                    setTransactionType,
                                    sourceType,
                                    setSourceType,
                                    exceptionStatus,
                                    setExceptionStatus,
                                    branches,
                                    projects: filteredProjects,
                                    sites: filteredSites,
                                    equipment,
                                }}
                            />
                        )}
                        {tab === 'maintenance' && (
                            <MaintenanceFilters
                                {...{
                                    dateFrom,
                                    setDateFrom,
                                    dateTo,
                                    setDateTo,
                                    branchId,
                                    setBranchId,
                                    projectId,
                                    setProjectId,
                                    siteId,
                                    setSiteId,
                                    equipmentId,
                                    setEquipmentId,
                                    maintenanceDueStatus,
                                    setMaintenanceDueStatus,
                                    maintenancePriority,
                                    setMaintenancePriority,
                                    branches,
                                    projects: filteredProjects,
                                    sites: filteredSites,
                                    equipment,
                                }}
                            />
                        )}
                    </div>
                    {tab === 'register' && can.create && (
                        <EquipmentDialog
                            branches={branches}
                            categories={categories}
                            locations={locations}
                            owners={owners}
                            currencies={currencies}
                            canViewCosts={can.viewCosts}
                        />
                    )}
                    {tab === 'categories' && can.manageCategories && (
                        <EquipmentCategoryDialog />
                    )}
                    {tab === 'locations' && can.manageLocations && (
                        <EquipmentLocationDialog
                            branches={branches}
                            projects={projects}
                            sites={sites}
                        />
                    )}
                    {tab === 'fuel' && can.exportFuel && (
                        <Button asChild>
                            <a href={fuelExportUrl}>
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    )}
                    {tab === 'maintenance' && can.exportMaintenance && (
                        <Button asChild>
                            <a href={maintenanceExportUrl}>
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="register">Register</TabsTrigger>
                            <TabsTrigger value="categories">
                                Categories
                            </TabsTrigger>
                            <TabsTrigger value="locations">
                                Locations
                            </TabsTrigger>
                            <TabsTrigger value="fuel">Fuel</TabsTrigger>
                            <TabsTrigger value="maintenance">
                                Maintenance
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <Tabs
                        value={
                            tab === 'fuel'
                                ? fuelStatus
                                : tab === 'maintenance'
                                  ? maintenanceStatus
                                  : status
                        }
                        onValueChange={
                            tab === 'fuel'
                                ? setFuelStatus
                                : tab === 'maintenance'
                                  ? setMaintenanceStatus
                                  : setStatus
                        }
                    >
                        <TabsList>
                            {tab === 'fuel' ? (
                                <>
                                    <TabsTrigger value="all">All</TabsTrigger>
                                    <TabsTrigger value="submitted">
                                        Submitted
                                    </TabsTrigger>
                                    <TabsTrigger value="posted">
                                        Posted
                                    </TabsTrigger>
                                    <TabsTrigger value="reversed">
                                        Reversed
                                    </TabsTrigger>
                                </>
                            ) : tab === 'maintenance' ? (
                                <>
                                    <TabsTrigger value="all">All</TabsTrigger>
                                    <TabsTrigger value="planned">
                                        Planned
                                    </TabsTrigger>
                                    <TabsTrigger value="approved">
                                        Approved
                                    </TabsTrigger>
                                    <TabsTrigger value="in_progress">
                                        In progress
                                    </TabsTrigger>
                                    <TabsTrigger value="completed">
                                        Completed
                                    </TabsTrigger>
                                    <TabsTrigger value="cancelled">
                                        Cancelled
                                    </TabsTrigger>
                                </>
                            ) : (
                                <>
                                    <TabsTrigger value="active">
                                        Active
                                    </TabsTrigger>
                                    <TabsTrigger value="inactive">
                                        Inactive
                                    </TabsTrigger>
                                </>
                            )}
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {tab === 'register'
                                ? 'Asset register'
                                : tab === 'categories'
                                  ? 'Equipment categories'
                                  : tab === 'locations'
                                    ? 'Operational locations'
                                    : tab === 'fuel'
                                      ? 'Fuel ledger'
                                      : 'Maintenance portfolio'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tab === 'register' && (
                            <RegisterTable
                                rows={rows as EquipmentRecord[]}
                                {...{
                                    branches,
                                    categories,
                                    locations,
                                    owners,
                                    currencies,
                                    can,
                                    confirm,
                                }}
                            />
                        )}
                        {tab === 'categories' && (
                            <CategoryTable
                                rows={rows as EquipmentCategory[]}
                                canManage={can.manageCategories}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'locations' && (
                            <LocationTable
                                rows={rows as EquipmentLocation[]}
                                canManage={can.manageLocations}
                                branches={branches}
                                projects={projects}
                                sites={sites}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'fuel' && (
                            <div className="grid gap-5">
                                {can.viewFuelDashboard && (
                                    <FuelSummary summary={fuelSummary} />
                                )}
                                <FuelTable
                                    rows={fuelRows}
                                    canViewCosts={can.viewCosts}
                                />
                            </div>
                        )}
                        {tab === 'maintenance' && (
                            <div className="grid gap-6">
                                {can.viewMaintenanceDashboard && (
                                    <MaintenanceSummary
                                        summary={maintenanceSummary}
                                        canViewCosts={can.viewCosts}
                                    />
                                )}
                                <MaintenanceSchedulePortfolioTable
                                    rows={maintenanceScheduleRows}
                                />
                                <MaintenanceWorkOrderPortfolioTable
                                    rows={maintenanceWorkOrderRows}
                                    canViewCosts={can.viewCosts}
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function RegisterTable({
    rows,
    branches,
    categories,
    locations,
    owners,
    currencies,
    can,
    confirm,
}: {
    rows: EquipmentRecord[];
    branches: BranchOption[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    owners: OwnerOption[];
    currencies: Option[];
    can: Props['can'];
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table
            headers={[
                'Asset',
                'Category',
                'Ownership',
                'Location / custodian',
                'Meter',
                'Status',
                '',
            ]}
        >
            {rows.map((asset) => (
                <tr key={asset.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <Link
                            href={`/equipment/${asset.id}`}
                            className="font-medium hover:underline"
                        >
                            {asset.asset_code}
                        </Link>
                        <div className="text-muted-foreground">
                            {asset.name}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.category_name}
                        <div className="text-muted-foreground">
                            {asset.branch_name}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">
                            {title(asset.ownership_type)}
                        </Badge>
                        <div className="mt-1 text-muted-foreground">
                            {asset.owner_name ?? 'Tenant asset'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.current_location_name ??
                            asset.default_location_name ??
                            'Not set'}
                        <div className="text-muted-foreground">
                            {asset.current_custodian_name ?? 'No custodian'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.current_meter_reading
                            ? formatNumber(asset.current_meter_reading)
                            : 'None'}
                        <div className="text-muted-foreground">
                            {title(asset.meter_type)}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge
                            variant={
                                asset.current_status === 'out_of_service' ||
                                asset.current_status === 'retired'
                                    ? 'destructive'
                                    : 'secondary'
                            }
                        >
                            {title(asset.current_status)}
                        </Badge>
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {can.update && (
                                <EquipmentDialog
                                    equipment={asset}
                                    branches={branches}
                                    categories={categories}
                                    locations={locations}
                                    owners={owners}
                                    currencies={currencies}
                                    canViewCosts={can.viewCosts}
                                />
                            )}
                            {can.retire && (
                                <Button
                                    size="sm"
                                    variant={
                                        asset.is_active
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: asset.is_active
                                                ? 'Retire equipment?'
                                                : 'Restore equipment?',
                                            description: `${asset.asset_code} will move to the ${asset.is_active ? 'inactive' : 'active'} register.`,
                                            confirmLabel: asset.is_active
                                                ? 'Retire'
                                                : 'Restore',
                                            variant: asset.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/equipment/${asset.id}`,
                                                    { preserveScroll: true },
                                                ),
                                        })
                                    }
                                >
                                    {asset.is_active ? 'Retire' : 'Restore'}
                                </Button>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={7} />}
        </Table>
    );
}

type FuelSummaryData = {
    transactions: number;
    assets: number;
    awaitingReview: number;
    exceptions: number;
    quantities: Array<{ fuel: string; quantity: number; unit: string }>;
    costs: Array<{ currency: string; amount: number }>;
};

function summarizeFuel(rows: EquipmentFuelTransaction[]): FuelSummaryData {
    const quantities = new Map<string, { quantity: number; unit: string }>();
    const costs = new Map<string, number>();

    rows.forEach((row) => {
        const quantity = quantities.get(row.fuel_type) ?? {
            quantity: 0,
            unit: row.unit,
        };
        quantity.quantity += Number(row.quantity);
        quantities.set(row.fuel_type, quantity);

        if (row.currency_code && row.total_cost) {
            costs.set(
                row.currency_code,
                (costs.get(row.currency_code) ?? 0) + Number(row.total_cost),
            );
        }
    });

    return {
        transactions: rows.length,
        assets: new Set(rows.map((row) => row.equipment_id)).size,
        awaitingReview: rows.filter((row) => row.status === 'submitted').length,
        exceptions: rows.filter((row) =>
            ['review_required', 'insufficient_evidence'].includes(
                row.exception_status,
            ),
        ).length,
        quantities: [...quantities].map(([fuel, value]) => ({
            fuel,
            ...value,
        })),
        costs: [...costs].map(([currency, amount]) => ({ currency, amount })),
    };
}

function FuelSummary({ summary }: { summary: FuelSummaryData }) {
    const quantityText = summary.quantities.length
        ? summary.quantities
              .map(
                  (item) =>
                      `${title(item.fuel)} ${formatNumber(item.quantity)} ${item.unit}`,
              )
              .join(' / ')
        : 'No fuel recorded';
    const costText = summary.costs.length
        ? summary.costs
              .map((item) => formatCurrencyAmount(item.currency, item.amount))
              .join(' / ')
        : 'No visible cost';

    return (
        <div className="grid gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-2 xl:grid-cols-6">
            <Metric
                label="Transactions"
                value={formatNumber(summary.transactions)}
            />
            <Metric
                label="Assets fuelled"
                value={formatNumber(summary.assets)}
            />
            <Metric
                label="Awaiting review"
                value={formatNumber(summary.awaitingReview)}
            />
            <Metric
                label="Exceptions"
                value={formatNumber(summary.exceptions)}
            />
            <Metric label="Fuel quantity" value={quantityText} />
            <Metric label="Visible cost" value={costText} />
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 bg-background p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold break-words">{value}</div>
        </div>
    );
}

function FuelFilters({
    dateFrom,
    setDateFrom,
    dateTo,
    setDateTo,
    branchId,
    setBranchId,
    projectId,
    setProjectId,
    siteId,
    setSiteId,
    equipmentId,
    setEquipmentId,
    transactionType,
    setTransactionType,
    sourceType,
    setSourceType,
    exceptionStatus,
    setExceptionStatus,
    branches,
    projects,
    sites,
    equipment,
}: {
    dateFrom: string;
    setDateFrom: (value: string) => void;
    dateTo: string;
    setDateTo: (value: string) => void;
    branchId: string;
    setBranchId: (value: string) => void;
    projectId: string;
    setProjectId: (value: string) => void;
    siteId: string;
    setSiteId: (value: string) => void;
    equipmentId: string;
    setEquipmentId: (value: string) => void;
    transactionType: string;
    setTransactionType: (value: string) => void;
    sourceType: string;
    setSourceType: (value: string) => void;
    exceptionStatus: string;
    setExceptionStatus: (value: string) => void;
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    equipment: EquipmentRecord[];
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <Input
                type="date"
                value={dateFrom}
                onChange={(event) => setDateFrom(event.target.value)}
                aria-label="From date"
            />
            <Input
                type="date"
                value={dateTo}
                onChange={(event) => setDateTo(event.target.value)}
                aria-label="To date"
            />
            <FilterSelect
                value={branchId}
                onChange={(value) => {
                    setBranchId(value);
                    setProjectId('all');
                    setSiteId('all');
                }}
                label="All branches"
                options={branches.map((branch) => ({
                    value: branch.id,
                    label: branch.name,
                }))}
            />
            <FilterSelect
                value={projectId}
                onChange={(value) => {
                    setProjectId(value);
                    setSiteId('all');
                }}
                label="All projects"
                options={projects.map((project) => ({
                    value: project.id,
                    label: project.name,
                }))}
            />
            <FilterSelect
                value={siteId}
                onChange={setSiteId}
                label="All sites"
                options={sites.map((site) => ({
                    value: site.id,
                    label: site.name,
                }))}
            />
            <FilterSelect
                value={equipmentId}
                onChange={setEquipmentId}
                label="All equipment"
                options={equipment.map((asset) => ({
                    value: asset.id,
                    label: asset.asset_code,
                    description: asset.name,
                }))}
            />
            <FilterSelect
                value={transactionType}
                onChange={setTransactionType}
                label="All transaction types"
                options={[
                    'issue',
                    'refuel',
                    'consumption',
                    'return',
                    'adjustment',
                ].map(option)}
            />
            <FilterSelect
                value={sourceType}
                onChange={setSourceType}
                label="All sources"
                options={[
                    'supplier',
                    'store',
                    'site_stock',
                    'mobile_bowser',
                    'other',
                ].map(option)}
            />
            <FilterSelect
                value={exceptionStatus}
                onChange={setExceptionStatus}
                label="All exception states"
                options={[
                    'not_evaluated',
                    'within_tolerance',
                    'review_required',
                    'insufficient_evidence',
                ].map(option)}
            />
        </div>
    );
}

function FilterSelect({
    value,
    onChange,
    label,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    label: string;
    options: Array<{ value: string; label: string; description?: string }>;
}) {
    return (
        <SearchableSelect
            value={value}
            onValueChange={onChange}
            options={[{ value: 'all', label }, ...options]}
            placeholder={label}
        />
    );
}

function FuelTable({
    rows,
    canViewCosts,
}: {
    rows: EquipmentFuelTransaction[];
    canViewCosts: boolean;
}) {
    const headers = [
        'Date / asset',
        'Branch / project',
        'Transaction',
        'Quantity / meter',
        ...(canViewCosts ? ['Cost'] : []),
        'Control status',
        '',
    ];

    return (
        <Table headers={headers}>
            {rows.map((transaction) => (
                <tr key={transaction.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div>{transaction.transacted_at.slice(0, 10)}</div>
                        <Link
                            href={`/equipment/${transaction.equipment_id}?tab=fuel`}
                            className="font-medium hover:underline"
                        >
                            {transaction.equipment_code}
                        </Link>
                        <div className="text-muted-foreground">
                            {transaction.equipment_name}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {transaction.branch_name}
                        <div className="text-muted-foreground">
                            {transaction.site_name ??
                                transaction.project_name ??
                                'Branch operation'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">
                            {title(transaction.transaction_type)}
                        </Badge>
                        <div className="mt-1 text-muted-foreground">
                            {title(transaction.source_type)}
                            {transaction.source_name
                                ? ` / ${transaction.source_name}`
                                : ''}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {formatNumber(transaction.quantity)} {transaction.unit}
                        <div className="text-muted-foreground">
                            {transaction.meter_reading
                                ? `Meter ${formatNumber(transaction.meter_reading)}`
                                : title(transaction.fuel_type)}
                        </div>
                    </td>
                    {canViewCosts && (
                        <td className="py-3 pr-4">
                            {formatCurrencyAmount(
                                transaction.currency_code,
                                transaction.total_cost,
                            )}
                        </td>
                    )}
                    <td className="py-3 pr-4">
                        <Badge
                            variant={
                                transaction.exception_status ===
                                'within_tolerance'
                                    ? 'secondary'
                                    : transaction.exception_status ===
                                        'not_evaluated'
                                      ? 'outline'
                                      : 'destructive'
                            }
                        >
                            {title(transaction.exception_status)}
                        </Badge>
                        <div className="mt-1 text-muted-foreground">
                            {title(transaction.status)}
                        </div>
                        {transaction.exception_reason && (
                            <div className="mt-1 max-w-64 text-xs text-muted-foreground">
                                {transaction.exception_reason}
                            </div>
                        )}
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {transaction.can_approve && (
                                <FuelApproveButton transaction={transaction} />
                            )}
                            {transaction.can_reverse && (
                                <FuelReversalDialog transaction={transaction} />
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={headers.length} />}
        </Table>
    );
}

type MaintenanceSummaryData = {
    dueSoon: number;
    overdue: number;
    planned: number;
    inProgress: number;
    completed: number;
    downtime: number;
    costs: Array<{ currency: string; amount: number }>;
};

function summarizeMaintenance(
    schedules: EquipmentMaintenancePortfolioSchedule[],
    workOrders: EquipmentMaintenancePortfolioWorkOrder[],
): MaintenanceSummaryData {
    const costs = new Map<string, number>();
    workOrders.forEach((row) => {
        if (row.currency_code && row.total_cost)
            costs.set(
                row.currency_code,
                (costs.get(row.currency_code) ?? 0) + Number(row.total_cost),
            );
    });
    return {
        dueSoon: schedules.filter((row) => row.due_status === 'due_soon')
            .length,
        overdue: schedules.filter((row) => row.due_status === 'overdue').length,
        planned: workOrders.filter((row) =>
            ['planned', 'approved'].includes(row.status),
        ).length,
        inProgress: workOrders.filter((row) => row.status === 'in_progress')
            .length,
        completed: workOrders.filter((row) => row.status === 'completed')
            .length,
        downtime: workOrders.reduce(
            (total, row) => total + Number(row.downtime_hours ?? 0),
            0,
        ),
        costs: [...costs].map(([currency, amount]) => ({ currency, amount })),
    };
}

function MaintenanceSummary({
    summary,
    canViewCosts,
}: {
    summary: MaintenanceSummaryData;
    canViewCosts: boolean;
}) {
    const costText = summary.costs.length
        ? summary.costs
              .map((row) => formatCurrencyAmount(row.currency, row.amount))
              .join(' / ')
        : 'No visible cost';
    return (
        <div className="grid gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-2 xl:grid-cols-7">
            <Metric label="Due soon" value={formatNumber(summary.dueSoon)} />
            <Metric label="Overdue" value={formatNumber(summary.overdue)} />
            <Metric label="Planned" value={formatNumber(summary.planned)} />
            <Metric
                label="In progress"
                value={formatNumber(summary.inProgress)}
            />
            <Metric label="Completed" value={formatNumber(summary.completed)} />
            <Metric
                label="Downtime"
                value={`${formatNumber(summary.downtime)} hours`}
            />
            {canViewCosts && (
                <Metric label="Maintenance cost" value={costText} />
            )}
        </div>
    );
}

function MaintenanceFilters({
    dateFrom,
    setDateFrom,
    dateTo,
    setDateTo,
    branchId,
    setBranchId,
    projectId,
    setProjectId,
    siteId,
    setSiteId,
    equipmentId,
    setEquipmentId,
    maintenanceDueStatus,
    setMaintenanceDueStatus,
    maintenancePriority,
    setMaintenancePriority,
    branches,
    projects,
    sites,
    equipment,
}: {
    dateFrom: string;
    setDateFrom: (value: string) => void;
    dateTo: string;
    setDateTo: (value: string) => void;
    branchId: string;
    setBranchId: (value: string) => void;
    projectId: string;
    setProjectId: (value: string) => void;
    siteId: string;
    setSiteId: (value: string) => void;
    equipmentId: string;
    setEquipmentId: (value: string) => void;
    maintenanceDueStatus: string;
    setMaintenanceDueStatus: (value: string) => void;
    maintenancePriority: string;
    setMaintenancePriority: (value: string) => void;
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    equipment: EquipmentRecord[];
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <Input
                type="date"
                value={dateFrom}
                onChange={(event) => setDateFrom(event.target.value)}
                aria-label="From date"
            />
            <Input
                type="date"
                value={dateTo}
                onChange={(event) => setDateTo(event.target.value)}
                aria-label="To date"
            />
            <FilterSelect
                value={branchId}
                onChange={(value) => {
                    setBranchId(value);
                    setProjectId('all');
                    setSiteId('all');
                }}
                label="All branches"
                options={branches.map((branch) => ({
                    value: branch.id,
                    label: branch.name,
                }))}
            />
            <FilterSelect
                value={projectId}
                onChange={(value) => {
                    setProjectId(value);
                    setSiteId('all');
                }}
                label="All projects"
                options={projects.map((project) => ({
                    value: project.id,
                    label: project.name,
                }))}
            />
            <FilterSelect
                value={siteId}
                onChange={setSiteId}
                label="All sites"
                options={sites.map((site) => ({
                    value: site.id,
                    label: site.name,
                }))}
            />
            <FilterSelect
                value={equipmentId}
                onChange={setEquipmentId}
                label="All equipment"
                options={equipment.map((asset) => ({
                    value: asset.id,
                    label: asset.asset_code,
                    description: asset.name,
                }))}
            />
            <FilterSelect
                value={maintenanceDueStatus}
                onChange={setMaintenanceDueStatus}
                label="All due states"
                options={['current', 'due_soon', 'overdue'].map(option)}
            />
            <FilterSelect
                value={maintenancePriority}
                onChange={setMaintenancePriority}
                label="All priorities"
                options={['low', 'normal', 'high', 'critical'].map(option)}
            />
        </div>
    );
}

function MaintenanceSchedulePortfolioTable({
    rows,
}: {
    rows: EquipmentMaintenancePortfolioSchedule[];
}) {
    return (
        <section className="grid gap-3">
            <div>
                <h3 className="font-semibold">Service schedule exceptions</h3>
                <p className="text-sm text-muted-foreground">
                    Active schedules ordered by urgency.
                </p>
            </div>
            <Table
                headers={[
                    'Asset',
                    'Schedule',
                    'Scope',
                    'Due threshold',
                    'Status',
                ]}
            >
                {rows.map((row) => (
                    <tr key={row.id} className="border-b last:border-0">
                        <td className="py-3 pr-4">
                            <Link
                                href={`/equipment/${row.equipment_id}?tab=maintenance`}
                                className="font-medium hover:underline"
                            >
                                {row.equipment_code}
                            </Link>
                            <div className="text-muted-foreground">
                                {row.equipment_name}
                            </div>
                        </td>
                        <td className="py-3 pr-4">
                            {row.name}
                            <div className="text-muted-foreground">
                                {title(row.maintenance_type)} /{' '}
                                {title(row.basis)}
                            </div>
                        </td>
                        <td className="py-3 pr-4">
                            {row.branch_name}
                            <div className="text-muted-foreground">
                                {row.site_name ??
                                    row.project_name ??
                                    'Branch operation'}
                            </div>
                        </td>
                        <td className="py-3 pr-4">
                            {row.next_due_date ?? 'No date trigger'}
                            <div className="text-muted-foreground">
                                {row.next_due_reading
                                    ? `${formatNumber(row.next_due_reading)} meter units`
                                    : 'No meter trigger'}
                            </div>
                        </td>
                        <td className="py-3">
                            <Badge
                                variant={
                                    row.due_status === 'overdue'
                                        ? 'destructive'
                                        : row.due_status === 'due_soon'
                                          ? 'outline'
                                          : 'secondary'
                                }
                            >
                                {title(row.due_status)}
                            </Badge>
                        </td>
                    </tr>
                ))}
                {rows.length === 0 && <Empty colSpan={5} />}
            </Table>
        </section>
    );
}

function MaintenanceWorkOrderPortfolioTable({
    rows,
    canViewCosts,
}: {
    rows: EquipmentMaintenancePortfolioWorkOrder[];
    canViewCosts: boolean;
}) {
    const headers = [
        'Work order / asset',
        'Scope',
        'Type / priority',
        'Timing',
        ...(canViewCosts ? ['Cost'] : []),
        'Status / evidence',
    ];
    return (
        <section className="grid gap-3">
            <div>
                <h3 className="font-semibold">Work-order ledger</h3>
                <p className="text-sm text-muted-foreground">
                    Planned work, workshop progress and completed maintenance
                    history.
                </p>
            </div>
            <Table headers={headers}>
                {rows.map((row) => (
                    <tr
                        key={row.id}
                        className="border-b align-top last:border-0"
                    >
                        <td className="py-3 pr-4">
                            <div className="font-medium">{row.reference}</div>
                            <Link
                                href={`/equipment/${row.equipment_id}?tab=maintenance`}
                                className="hover:underline"
                            >
                                {row.equipment_code}
                            </Link>
                            <div className="text-muted-foreground">
                                {row.equipment_name}
                            </div>
                        </td>
                        <td className="py-3 pr-4">{row.branch_name}</td>
                        <td className="py-3 pr-4">
                            {title(row.maintenance_type)}
                            <div className="text-muted-foreground">
                                {title(row.priority)}
                            </div>
                            <div className="max-w-72 text-muted-foreground">
                                {row.description}
                            </div>
                        </td>
                        <td className="py-3 pr-4">
                            {row.reported_at}
                            <div className="text-muted-foreground">
                                {row.completed_at
                                    ? `Completed ${row.completed_at}`
                                    : row.actual_start_at
                                      ? `Started ${row.actual_start_at}`
                                      : row.planned_start_at
                                        ? `Planned ${row.planned_start_at}`
                                        : 'Start not set'}
                            </div>
                            <div className="text-muted-foreground">
                                {row.downtime_hours
                                    ? `${formatNumber(row.downtime_hours)} downtime hours`
                                    : ''}
                            </div>
                        </td>
                        {canViewCosts && (
                            <td className="py-3 pr-4">
                                {formatCurrencyAmount(
                                    row.currency_code,
                                    row.total_cost,
                                )}
                            </td>
                        )}
                        <td className="py-3">
                            <Badge
                                variant={
                                    row.status === 'cancelled'
                                        ? 'destructive'
                                        : row.status === 'completed'
                                          ? 'secondary'
                                          : 'outline'
                                }
                            >
                                {title(row.status)}
                            </Badge>
                            <div className="mt-1 text-muted-foreground">
                                {formatNumber(row.document_count)} document(s)
                            </div>
                        </td>
                    </tr>
                ))}
                {rows.length === 0 && <Empty colSpan={headers.length} />}
            </Table>
        </section>
    );
}

function option(value: string) {
    return { value, label: title(value) };
}

function CategoryTable({
    rows,
    canManage,
    confirm,
}: {
    rows: EquipmentCategory[];
    canManage: boolean;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Category', 'Meter', 'Capacity', 'Fuel control', '']}>
            {rows.map((category) => (
                <tr key={category.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{category.name}</div>
                        <div className="text-muted-foreground">
                            {category.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {title(category.default_meter_type)}
                    </td>
                    <td className="py-3 pr-4">
                        {category.default_capacity_unit ?? 'Not set'}
                    </td>
                    <td className="py-3 pr-4">
                        {category.fuel_efficiency_basis
                            ? `${formatNumber(category.expected_fuel_efficiency)} ${title(category.fuel_efficiency_basis)}`
                            : 'Not set'}
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {canManage && (
                                <>
                                    <EquipmentCategoryDialog
                                        category={category}
                                    />
                                    <Button
                                        size="sm"
                                        variant={
                                            category.is_active
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        onClick={() =>
                                            confirm({
                                                title: category.is_active
                                                    ? 'Deactivate category?'
                                                    : 'Activate category?',
                                                description: `${category.name} will move to the ${category.is_active ? 'inactive' : 'active'} list.`,
                                                confirmLabel: category.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                                variant: category.is_active
                                                    ? 'destructive'
                                                    : 'default',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/equipment-categories/${category.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {category.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                </>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={5} />}
        </Table>
    );
}

function LocationTable({
    rows,
    canManage,
    branches,
    projects,
    sites,
    confirm,
}: {
    rows: EquipmentLocation[];
    canManage: boolean;
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Location', 'Type', 'Branch', 'Project / site', '']}>
            {rows.map((location) => (
                <tr key={location.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{location.name}</div>
                        <div className="text-muted-foreground">
                            {location.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">{title(location.type)}</Badge>
                    </td>
                    <td className="py-3 pr-4">{location.branch_name}</td>
                    <td className="py-3 pr-4">
                        {location.project_name ?? 'Not project-linked'}
                        <div className="text-muted-foreground">
                            {location.site_name ?? location.address ?? ''}
                        </div>
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {canManage && (
                                <>
                                    <EquipmentLocationDialog
                                        location={location}
                                        branches={branches}
                                        projects={projects}
                                        sites={sites}
                                    />
                                    <Button
                                        size="sm"
                                        variant={
                                            location.is_active
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        onClick={() =>
                                            confirm({
                                                title: location.is_active
                                                    ? 'Deactivate location?'
                                                    : 'Activate location?',
                                                description: `${location.name} will move to the ${location.is_active ? 'inactive' : 'active'} list.`,
                                                confirmLabel: location.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                                variant: location.is_active
                                                    ? 'destructive'
                                                    : 'default',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/equipment-locations/${location.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {location.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                </>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={5} />}
        </Table>
    );
}

function Table({
    headers,
    children,
}: {
    headers: string[];
    children: React.ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header, index) => (
                            <th
                                key={index}
                                className={`py-3 font-medium ${index === headers.length - 1 ? 'text-right' : 'pr-4'}`}
                            >
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}
function Empty({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="py-10 text-center text-muted-foreground"
            >
                No records match the current tab and search.
            </td>
        </tr>
    );
}
function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
