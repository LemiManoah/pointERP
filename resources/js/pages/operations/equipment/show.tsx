import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
} from '../documents/partials/document-dialog';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';
import {
    EquipmentAssignmentDialog,
    EquipmentReturnDialog,
} from './partials/equipment-assignment-dialog';
import { EquipmentDialog } from './partials/equipment-dialog';
import {
    EquipmentFuelDialog,
    FuelApproveButton,
    FuelReversalDialog,
} from './partials/equipment-fuel-dialogs';
import { EquipmentLocationConfirmationDialog } from './partials/equipment-location-confirmation-dialog';
import {
    MaintenanceApproveButton,
    MaintenanceCancelDialog,
    MaintenanceCompleteDialog,
    MaintenanceScheduleDialog,
    MaintenanceStartDialog,
    MaintenanceWorkOrderDialog,
} from './partials/equipment-maintenance-dialogs';
import {
    EquipmentTransferRequestDialog,
    TransferApproveButton,
    TransferDispatchDialog,
    TransferReceiptDialog,
} from './partials/equipment-transfer-dialogs';
import { MeterCorrectionReviewDialog } from './partials/meter-correction-review-dialog';
import {
    MeterCorrectionDialog,
    MeterReadingDialog,
} from './partials/meter-reading-dialog';
import type {
    BranchOption,
    EquipmentCategory,
    EquipmentAssignment,
    EquipmentFuelTransaction,
    EquipmentLocation,
    EquipmentLocationConfirmation,
    EquipmentMaintenanceSchedule,
    EquipmentMaintenanceWorkOrder,
    EquipmentMeterReading,
    EquipmentRecord,
    EquipmentTransfer,
    Option,
    OwnerOption,
    ProjectOption,
    SiteOption,
    StaffOption,
} from './types';

type Props = {
    activeTab: string;
    equipment: EquipmentRecord;
    meterReadings: EquipmentMeterReading[];
    assignments: EquipmentAssignment[];
    transfers: EquipmentTransfer[];
    locationConfirmations: EquipmentLocationConfirmation[];
    fuelTransactions: EquipmentFuelTransaction[];
    maintenanceSchedules: EquipmentMaintenanceSchedule[];
    maintenanceWorkOrders: EquipmentMaintenanceWorkOrder[];
    maintenanceUsers: Option[];
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    branches: BranchOption[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    projects: ProjectOption[];
    sites: SiteOption[];
    staff: StaffOption[];
    owners: OwnerOption[];
    currencies: Option[];
    can: {
        update: boolean;
        retire: boolean;
        uploadDocuments: boolean;
        viewCosts: boolean;
        recordReading: boolean;
        assign: boolean;
        requestTransfer: boolean;
        confirmLocation: boolean;
        recordFuel: boolean;
        manageMaintenance: boolean;
        requestMaintenance: boolean;
    };
};

export default function EquipmentShow(props: Props) {
    const {
        equipment,
        meterReadings,
        assignments,
        transfers,
        locationConfirmations,
        fuelTransactions,
        maintenanceSchedules,
        maintenanceWorkOrders,
        maintenanceUsers,
        documents,
        documentTypes,
        documentBranches,
        documentLinkOptions,
        branches,
        categories,
        locations,
        projects,
        sites,
        staff,
        owners,
        currencies,
        can,
    } = props;
    const [tab, setTab] = useState(
        [
            'overview',
            'assignments',
            'transfers',
            'locations',
            'fuel',
            'maintenance',
            'readings',
            'documents',
        ].includes(props.activeTab)
            ? props.activeTab
            : 'overview',
    );
    const confirm = useConfirmDialog();
    const [maintenanceScheduleStatus, setMaintenanceScheduleStatus] =
        useState('active');
    const activeAssignment = assignments.find(
        (assignment) => assignment.status === 'active',
    );
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Equipment', href: '/equipment' },
        { title: equipment.asset_code, href: `/equipment/${equipment.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={equipment.asset_code} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {equipment.name}
                            </h1>
                            <Badge
                                variant={
                                    equipment.is_active
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {title(equipment.current_status)}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {equipment.asset_code} · {equipment.category_name} ·{' '}
                            {equipment.branch_name}
                        </p>
                    </div>
                    <div className="flex justify-end gap-2">
                        {can.assign && (
                            <EquipmentAssignmentDialog
                                equipment={equipment}
                                projects={projects}
                                sites={sites}
                                locations={locations}
                                staff={staff}
                            />
                        )}
                        {activeAssignment?.can_return && (
                            <EquipmentReturnDialog
                                equipment={equipment}
                                assignment={activeAssignment}
                                locations={locations}
                            />
                        )}
                        {can.requestTransfer && (
                            <EquipmentTransferRequestDialog
                                equipment={equipment}
                                branches={branches}
                                locations={locations}
                            />
                        )}
                        {can.confirmLocation && (
                            <EquipmentLocationConfirmationDialog
                                equipment={equipment}
                                locations={locations}
                            />
                        )}
                        {can.update && (
                            <EquipmentDialog
                                equipment={equipment}
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
                                variant={
                                    equipment.is_active
                                        ? 'destructive'
                                        : 'secondary'
                                }
                                onClick={() =>
                                    confirm({
                                        title: equipment.is_active
                                            ? 'Retire equipment?'
                                            : 'Restore equipment?',
                                        description: `${equipment.asset_code} will move to the ${equipment.is_active ? 'inactive' : 'active'} register.`,
                                        confirmLabel: equipment.is_active
                                            ? 'Retire'
                                            : 'Restore',
                                        variant: equipment.is_active
                                            ? 'destructive'
                                            : 'default',
                                        onConfirm: () =>
                                            router.delete(
                                                `/equipment/${equipment.id}`,
                                            ),
                                    })
                                }
                            >
                                {equipment.is_active ? 'Retire' : 'Restore'}
                            </Button>
                        )}
                    </div>
                </div>

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="assignments">
                            Assignments
                        </TabsTrigger>
                        <TabsTrigger value="transfers">Transfers</TabsTrigger>
                        <TabsTrigger value="locations">Locations</TabsTrigger>
                        <TabsTrigger value="fuel">Fuel</TabsTrigger>
                        <TabsTrigger value="maintenance">
                            Maintenance
                        </TabsTrigger>
                        <TabsTrigger value="readings">Readings</TabsTrigger>
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                    </TabsList>
                    <TabsContent value="overview" className="mt-6 grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Current state</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <Value
                                    label="Status"
                                    value={title(equipment.current_status)}
                                />
                                <Value
                                    label="Location"
                                    value={
                                        equipment.current_location_name ??
                                        equipment.default_location_name ??
                                        'Not set'
                                    }
                                />
                                <Value
                                    label="Project / site"
                                    value={
                                        equipment.current_site_name ??
                                        equipment.current_project_name ??
                                        'Unassigned'
                                    }
                                />
                                <Value
                                    label="Custodian"
                                    value={
                                        equipment.current_custodian_name ??
                                        'Unassigned'
                                    }
                                />
                                <Value
                                    label="Current meter"
                                    value={
                                        equipment.current_meter_reading
                                            ? formatNumber(
                                                  equipment.current_meter_reading,
                                              )
                                            : 'No reading'
                                    }
                                />
                                <Value
                                    label="Meter type"
                                    value={title(equipment.meter_type)}
                                />
                                <Value
                                    label="Opening reading"
                                    value={
                                        equipment.starting_meter_reading
                                            ? formatNumber(
                                                  equipment.starting_meter_reading,
                                              )
                                            : 'None'
                                    }
                                />
                                <Value
                                    label="Opening date"
                                    value={
                                        equipment.starting_meter_date ??
                                        'Not set'
                                    }
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Asset identity</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <Value
                                    label="Make / model"
                                    value={
                                        [equipment.make, equipment.model]
                                            .filter(Boolean)
                                            .join(' ') || 'Not set'
                                    }
                                />
                                <Value
                                    label="Model year"
                                    value={
                                        equipment.model_year?.toString() ??
                                        'Not set'
                                    }
                                />
                                <Value
                                    label="Serial number"
                                    value={equipment.serial_number ?? 'Not set'}
                                />
                                <Value
                                    label="Registration"
                                    value={
                                        equipment.registration_number ??
                                        'Not set'
                                    }
                                />
                                <Value
                                    label="Chassis / VIN"
                                    value={
                                        equipment.chassis_number ?? 'Not set'
                                    }
                                />
                                <Value
                                    label="Ownership"
                                    value={title(equipment.ownership_type)}
                                />
                                <Value
                                    label="Owner"
                                    value={equipment.owner_name ?? 'Tenant'}
                                />
                                <Value
                                    label="Capacity"
                                    value={
                                        equipment.capacity_value
                                            ? `${formatNumber(equipment.capacity_value)} ${equipment.capacity_unit ?? ''}`.trim()
                                            : 'Not set'
                                    }
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Fuel baseline</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <Value
                                    label="Efficiency basis"
                                    value={
                                        equipment.fuel_efficiency_basis
                                            ? title(
                                                  equipment.fuel_efficiency_basis,
                                              )
                                            : 'Not set'
                                    }
                                />
                                <Value
                                    label="Expected consumption"
                                    value={
                                        equipment.expected_fuel_efficiency
                                            ? formatNumber(
                                                  equipment.expected_fuel_efficiency,
                                              )
                                            : 'Not set'
                                    }
                                />
                                <Value
                                    label="Tolerance"
                                    value={
                                        equipment.fuel_tolerance_percent
                                            ? `${formatNumber(equipment.fuel_tolerance_percent)}%`
                                            : 'Not set'
                                    }
                                />
                                <Value
                                    label="Tank capacity"
                                    value={
                                        equipment.tank_capacity
                                            ? `${formatNumber(equipment.tank_capacity)} L`
                                            : 'Not set'
                                    }
                                />
                            </CardContent>
                        </Card>
                        {can.viewCosts && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Commercial details</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <Value
                                        label="Acquired on"
                                        value={
                                            equipment.acquired_on ?? 'Not set'
                                        }
                                    />
                                    <Value
                                        label="Acquisition cost"
                                        value={formatCurrencyAmount(
                                            equipment.acquisition_currency_code,
                                            equipment.acquisition_amount,
                                        )}
                                    />
                                    <Value
                                        label="Hire rate"
                                        value={formatCurrencyAmount(
                                            equipment.acquisition_currency_code,
                                            equipment.hire_rate,
                                        )}
                                    />
                                    <Value
                                        label="Rate basis"
                                        value={
                                            equipment.hire_rate_basis
                                                ? title(
                                                      equipment.hire_rate_basis,
                                                  )
                                                : 'Not set'
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}
                        {equipment.condition_summary && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Condition</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm whitespace-pre-wrap">
                                    {equipment.condition_summary}
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                    <TabsContent value="assignments" className="mt-6">
                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Custody history</CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Handover, custodian and return evidence
                                        for this asset.
                                    </p>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <AssignmentTable
                                    equipment={equipment}
                                    assignments={assignments}
                                    locations={locations}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="transfers" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Transfer history</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Controlled approval, dispatch and receipt
                                    between operational locations.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <TransferTable
                                    equipment={equipment}
                                    transfers={transfers}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="locations" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Location confirmations</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Physical observations supporting the last
                                    known location.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <LocationConfirmationTable
                                    confirmations={locationConfirmations}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="fuel" className="mt-6">
                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Fuel ledger</CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Fuel issues, posting controls and
                                        additive reversals for this asset.
                                    </p>
                                </div>
                                {can.recordFuel && (
                                    <EquipmentFuelDialog
                                        equipment={equipment}
                                        staff={staff}
                                        providers={owners}
                                        currencies={currencies}
                                        canViewCosts={can.viewCosts}
                                    />
                                )}
                            </CardHeader>
                            <CardContent>
                                <FuelTransactionTable
                                    transactions={fuelTransactions}
                                    canViewCosts={can.viewCosts}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent
                        value="maintenance"
                        className="mt-6 grid gap-6"
                    >
                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Maintenance schedules</CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Date and meter-based service
                                        requirements with due-state warnings.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {can.manageMaintenance && (
                                        <MaintenanceScheduleDialog
                                            equipment={equipment}
                                            users={maintenanceUsers}
                                        />
                                    )}
                                    <Tabs
                                        value={maintenanceScheduleStatus}
                                        onValueChange={
                                            setMaintenanceScheduleStatus
                                        }
                                    >
                                        <TabsList>
                                            <TabsTrigger value="active">
                                                Active
                                            </TabsTrigger>
                                            <TabsTrigger value="inactive">
                                                Inactive
                                            </TabsTrigger>
                                        </TabsList>
                                    </Tabs>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <MaintenanceScheduleTable
                                    schedules={maintenanceSchedules.filter(
                                        (schedule) =>
                                            schedule.is_active ===
                                            (maintenanceScheduleStatus ===
                                                'active'),
                                    )}
                                    equipment={equipment}
                                    users={maintenanceUsers}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>
                                        Maintenance work orders
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Approved workshop activity, downtime,
                                        parts, meter evidence and final cost.
                                    </p>
                                </div>
                                {can.requestMaintenance && (
                                    <MaintenanceWorkOrderDialog
                                        equipment={equipment}
                                        schedules={maintenanceSchedules}
                                        providers={owners}
                                    />
                                )}
                            </CardHeader>
                            <CardContent>
                                <MaintenanceWorkOrderTable
                                    workOrders={maintenanceWorkOrders}
                                    equipment={equipment}
                                    currencies={currencies}
                                    canViewCosts={can.viewCosts}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="readings" className="mt-6">
                        <Card>
                            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Meter ledger</CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Accepted observations and additive
                                        corrections for this asset.
                                    </p>
                                </div>
                                {can.recordReading &&
                                    equipment.is_active &&
                                    equipment.meter_type !== 'none' && (
                                        <MeterReadingDialog
                                            equipment={equipment}
                                        />
                                    )}
                            </CardHeader>
                            <CardContent>
                                <MeterReadingTable readings={meterReadings} />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="documents" className="mt-6">
                        <DocumentEvidenceTable
                            documents={documents}
                            emptyText="No controlled documents are linked to this asset."
                            actions={
                                can.uploadDocuments && (
                                    <DocumentDialog
                                        documentTypes={documentTypes}
                                        branches={documentBranches}
                                        linkOptions={documentLinkOptions}
                                        defaultBranchId={equipment.branch_id}
                                        defaultLink={{
                                            type: 'equipment',
                                            id: equipment.id,
                                        }}
                                        buttonLabel="Upload document"
                                    />
                                )
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function TransferTable({
    equipment,
    transfers,
}: {
    equipment: EquipmentRecord;
    transfers: EquipmentTransfer[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Requested</th>
                        <th className="py-3 pr-4 font-medium">Movement</th>
                        <th className="py-3 pr-4 font-medium">Reason</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {transfers.map((transfer) => (
                        <tr
                            key={transfer.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                {transfer.requested_at}
                                <div className="text-muted-foreground">
                                    {transfer.requested_by}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {transfer.source_location_name}
                                <div className="text-muted-foreground">
                                    to {transfer.destination_location_name} ·{' '}
                                    {transfer.destination_branch_name}
                                </div>
                            </td>
                            <td className="max-w-72 py-3 pr-4">
                                {transfer.reason}
                            </td>
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        transfer.status === 'dispatched'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {title(transfer.status)}
                                </Badge>
                                {transfer.transport_reference && (
                                    <div className="mt-1 text-muted-foreground">
                                        {transfer.transport_reference}
                                    </div>
                                )}
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    {transfer.can_approve && (
                                        <TransferApproveButton
                                            transfer={transfer}
                                        />
                                    )}
                                    {transfer.can_dispatch && (
                                        <TransferDispatchDialog
                                            equipment={equipment}
                                            transfer={transfer}
                                        />
                                    )}
                                    {transfer.can_receive && (
                                        <TransferReceiptDialog
                                            equipment={equipment}
                                            transfer={transfer}
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {transfers.length === 0 && (
                        <tr>
                            <td
                                colSpan={5}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No transfers recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function LocationConfirmationTable({
    confirmations,
}: {
    confirmations: EquipmentLocationConfirmation[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Observed</th>
                        <th className="py-3 pr-4 font-medium">Location</th>
                        <th className="py-3 pr-4 font-medium">
                            Status observed
                        </th>
                        <th className="py-3 font-medium">Evidence note</th>
                    </tr>
                </thead>
                <tbody>
                    {confirmations.map((confirmation) => (
                        <tr
                            key={confirmation.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                {confirmation.observed_at}
                                <div className="text-muted-foreground">
                                    {confirmation.confirmed_by}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {confirmation.location_name}
                            </td>
                            <td className="py-3 pr-4">
                                {confirmation.observed_status
                                    ? title(confirmation.observed_status)
                                    : 'Not recorded'}
                            </td>
                            <td className="max-w-96 py-3">
                                {confirmation.condition_observation ??
                                    confirmation.note ??
                                    'None'}
                            </td>
                        </tr>
                    ))}
                    {confirmations.length === 0 && (
                        <tr>
                            <td
                                colSpan={4}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No manual location confirmations recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function AssignmentTable({
    equipment,
    assignments,
    locations,
}: {
    equipment: EquipmentRecord;
    assignments: EquipmentAssignment[];
    locations: EquipmentLocation[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Handover</th>
                        <th className="py-3 pr-4 font-medium">Destination</th>
                        <th className="py-3 pr-4 font-medium">Custodian</th>
                        <th className="py-3 pr-4 font-medium">Meter</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {assignments.map((assignment) => (
                        <tr
                            key={assignment.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                {assignment.assigned_at}
                                <div className="text-muted-foreground">
                                    by {assignment.handed_over_by}
                                </div>
                                {assignment.expected_return_at && (
                                    <div className="text-muted-foreground">
                                        Due {assignment.expected_return_at}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                {assignment.site_name}
                                <div className="text-muted-foreground">
                                    {assignment.project_name} ·{' '}
                                    {assignment.location_name}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {assignment.custodian_name ?? 'Not recorded'}
                                {assignment.custodian_employer && (
                                    <div className="text-muted-foreground">
                                        {assignment.custodian_employer}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                {assignment.handover_meter_reading
                                    ? formatNumber(
                                          assignment.handover_meter_reading,
                                      )
                                    : 'None'}
                                {assignment.return_meter_reading && (
                                    <div className="text-muted-foreground">
                                        Return{' '}
                                        {formatNumber(
                                            assignment.return_meter_reading,
                                        )}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        assignment.status === 'active'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {title(assignment.status)}
                                </Badge>
                                {assignment.returned_at && (
                                    <div className="mt-1 text-muted-foreground">
                                        {assignment.returned_at}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 text-right">
                                {assignment.can_return && (
                                    <EquipmentReturnDialog
                                        equipment={equipment}
                                        assignment={assignment}
                                        locations={locations}
                                    />
                                )}
                            </td>
                        </tr>
                    ))}
                    {assignments.length === 0 && (
                        <tr>
                            <td
                                colSpan={6}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No assignment history recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function MeterReadingTable({
    readings,
}: {
    readings: EquipmentMeterReading[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Observed</th>
                        <th className="py-3 pr-4 font-medium">Event</th>
                        <th className="py-3 pr-4 font-medium">Reading</th>
                        <th className="py-3 pr-4 font-medium">Usage</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 pr-4 font-medium">Evidence</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {readings.map((reading) => (
                        <tr
                            key={reading.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                {reading.read_at}
                                <div className="text-muted-foreground">
                                    {reading.recorded_by ?? 'System'}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {title(reading.event_type)}
                                {reading.corrects_reading_id && (
                                    <div className="text-muted-foreground">
                                        Corrects{' '}
                                        {formatNumber(reading.corrected_value)}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4 font-medium">
                                {formatNumber(reading.reading_value)}
                                {reading.previous_reading !== null && (
                                    <div className="font-normal text-muted-foreground">
                                        Previous{' '}
                                        {formatNumber(reading.previous_reading)}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                {reading.usage === null
                                    ? 'Opening'
                                    : formatNumber(reading.usage)}
                            </td>
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        reading.status === 'rejected'
                                            ? 'destructive'
                                            : reading.status === 'accepted'
                                              ? 'secondary'
                                              : 'outline'
                                    }
                                >
                                    {title(reading.status)}
                                </Badge>
                            </td>
                            <td className="max-w-64 py-3 pr-4 text-muted-foreground">
                                {reading.reason ??
                                    reading.evidence_note ??
                                    reading.decision_note ??
                                    'None'}
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    {reading.can_correct && (
                                        <MeterCorrectionDialog
                                            reading={reading}
                                        />
                                    )}
                                    {reading.can_approve && (
                                        <>
                                            <MeterCorrectionReviewDialog
                                                reading={reading}
                                                action="approve"
                                            />
                                            <MeterCorrectionReviewDialog
                                                reading={reading}
                                                action="reject"
                                            />
                                        </>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {readings.length === 0 && (
                        <tr>
                            <td
                                colSpan={7}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No meter readings recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function MaintenanceScheduleTable({
    schedules,
    equipment,
    users,
}: {
    schedules: EquipmentMaintenanceSchedule[];
    equipment: EquipmentRecord;
    users: Option[];
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Schedule</th>
                        <th className="py-3 pr-4 font-medium">
                            Basis / interval
                        </th>
                        <th className="py-3 pr-4 font-medium">Last service</th>
                        <th className="py-3 pr-4 font-medium">Next due</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {schedules.map((schedule) => (
                        <tr
                            key={schedule.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                <div className="font-medium">
                                    {schedule.name}
                                </div>
                                <div className="text-muted-foreground">
                                    {title(schedule.maintenance_type)}
                                </div>
                                <div className="text-muted-foreground">
                                    {schedule.responsible_user_name ??
                                        'No responsible user'}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {title(schedule.basis)}
                                <div className="text-muted-foreground">
                                    {schedule.interval_days
                                        ? `${formatNumber(schedule.interval_days)} days`
                                        : ''}
                                    {schedule.interval_days &&
                                    schedule.interval_meter_units
                                        ? ' / '
                                        : ''}
                                    {schedule.interval_meter_units
                                        ? `${formatNumber(schedule.interval_meter_units)} meter units`
                                        : ''}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {schedule.last_service_date ?? 'No date'}
                                <div className="text-muted-foreground">
                                    {schedule.last_service_reading
                                        ? formatNumber(
                                              schedule.last_service_reading,
                                          )
                                        : 'No reading'}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {schedule.next_due_date ?? 'No date trigger'}
                                <div className="text-muted-foreground">
                                    {schedule.next_due_reading
                                        ? formatNumber(
                                              schedule.next_due_reading,
                                          )
                                        : 'No meter trigger'}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        schedule.due_status === 'overdue'
                                            ? 'destructive'
                                            : schedule.due_status === 'due_soon'
                                              ? 'outline'
                                              : 'secondary'
                                    }
                                >
                                    {title(schedule.due_status)}
                                </Badge>
                            </td>
                            <td className="py-3 text-right">
                                {schedule.can_update && (
                                    <MaintenanceScheduleDialog
                                        equipment={equipment}
                                        users={users}
                                        schedule={schedule}
                                    />
                                )}
                            </td>
                        </tr>
                    ))}
                    {schedules.length === 0 && (
                        <tr>
                            <td
                                colSpan={6}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No maintenance schedules configured.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function MaintenanceWorkOrderTable({
    workOrders,
    equipment,
    currencies,
    canViewCosts,
}: {
    workOrders: EquipmentMaintenanceWorkOrder[];
    equipment: EquipmentRecord;
    currencies: Option[];
    canViewCosts: boolean;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Work order</th>
                        <th className="py-3 pr-4 font-medium">
                            Timing / provider
                        </th>
                        <th className="py-3 pr-4 font-medium">
                            Meter / downtime
                        </th>
                        {canViewCosts && (
                            <th className="py-3 pr-4 font-medium">Cost</th>
                        )}
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {workOrders.map((workOrder) => (
                        <tr
                            key={workOrder.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="max-w-80 py-3 pr-4">
                                <div className="font-medium">
                                    {workOrder.reference}
                                </div>
                                <div>
                                    {title(workOrder.maintenance_type)} ·{' '}
                                    {title(workOrder.priority)}
                                </div>
                                <div className="mt-1 text-muted-foreground">
                                    {workOrder.description}
                                </div>
                                {workOrder.work_performed && (
                                    <div className="mt-2">
                                        <span className="font-medium">
                                            Work:
                                        </span>{' '}
                                        {workOrder.work_performed}
                                    </div>
                                )}
                                {workOrder.cancellation_reason && (
                                    <div className="mt-2 text-destructive">
                                        {workOrder.cancellation_reason}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                Reported {workOrder.reported_at}
                                <div className="text-muted-foreground">
                                    {workOrder.actual_start_at
                                        ? `Started ${workOrder.actual_start_at}`
                                        : workOrder.planned_start_at
                                          ? `Planned ${workOrder.planned_start_at}`
                                          : 'Not scheduled'}
                                </div>
                                <div className="text-muted-foreground">
                                    {workOrder.provider_name ??
                                        'Internal workshop'}
                                </div>
                            </td>
                            <td className="py-3 pr-4">
                                {workOrder.opening_meter_reading
                                    ? `${formatNumber(workOrder.opening_meter_reading)} to ${formatNumber(workOrder.closing_meter_reading)}`
                                    : 'No meter evidence'}
                                <div className="text-muted-foreground">
                                    {workOrder.downtime_hours
                                        ? `${formatNumber(workOrder.downtime_hours)} downtime hours`
                                        : 'Downtime not recorded'}
                                </div>
                                {workOrder.next_service_date && (
                                    <div className="text-muted-foreground">
                                        Next {workOrder.next_service_date}
                                    </div>
                                )}
                            </td>
                            {canViewCosts && (
                                <td className="py-3 pr-4">
                                    {formatCurrencyAmount(
                                        workOrder.currency_code,
                                        workOrder.total_cost,
                                    )}
                                    <div className="text-muted-foreground">
                                        Parts{' '}
                                        {formatCurrencyAmount(
                                            workOrder.currency_code,
                                            workOrder.parts_cost,
                                        )}
                                    </div>
                                </td>
                            )}
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        workOrder.status === 'cancelled'
                                            ? 'destructive'
                                            : workOrder.status === 'completed'
                                              ? 'secondary'
                                              : 'outline'
                                    }
                                >
                                    {title(workOrder.status)}
                                </Badge>
                                <div className="mt-1 text-muted-foreground">
                                    by {workOrder.requested_by}
                                </div>
                                {workOrder.approved_by && (
                                    <div className="text-muted-foreground">
                                        Approved by {workOrder.approved_by}
                                    </div>
                                )}
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    {workOrder.can_approve && (
                                        <MaintenanceApproveButton
                                            workOrder={workOrder}
                                        />
                                    )}
                                    {workOrder.can_start && (
                                        <MaintenanceStartDialog
                                            workOrder={workOrder}
                                            equipment={equipment}
                                        />
                                    )}
                                    {workOrder.can_complete && (
                                        <MaintenanceCompleteDialog
                                            workOrder={workOrder}
                                            equipment={equipment}
                                            currencies={currencies}
                                            canViewCosts={canViewCosts}
                                        />
                                    )}
                                    {workOrder.can_cancel && (
                                        <MaintenanceCancelDialog
                                            workOrder={workOrder}
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {workOrders.length === 0 && (
                        <tr>
                            <td
                                colSpan={canViewCosts ? 6 : 5}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No maintenance work orders recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function FuelTransactionTable({
    transactions,
    canViewCosts,
}: {
    transactions: EquipmentFuelTransaction[];
    canViewCosts: boolean;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Date / source</th>
                        <th className="py-3 pr-4 font-medium">Transaction</th>
                        <th className="py-3 pr-4 font-medium">Quantity</th>
                        <th className="py-3 pr-4 font-medium">Meter / tank</th>
                        {canViewCosts && (
                            <th className="py-3 pr-4 font-medium">Cost</th>
                        )}
                        <th className="py-3 pr-4 font-medium">Control</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {transactions.map((transaction) => (
                        <tr
                            key={transaction.id}
                            className="border-b align-top last:border-0"
                        >
                            <td className="py-3 pr-4">
                                {transaction.transacted_at}
                                <div className="text-muted-foreground">
                                    {transaction.source_name ??
                                        title(transaction.source_type)}
                                </div>
                                {transaction.voucher_reference && (
                                    <div className="text-muted-foreground">
                                        Ref {transaction.voucher_reference}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                {title(transaction.transaction_type)}
                                <div className="text-muted-foreground">
                                    {title(transaction.fuel_type)}
                                </div>
                            </td>
                            <td className="py-3 pr-4 font-medium">
                                {formatNumber(transaction.quantity)}{' '}
                                {transaction.unit}
                                {transaction.receiver_name && (
                                    <div className="font-normal text-muted-foreground">
                                        Received by {transaction.receiver_name}
                                    </div>
                                )}
                            </td>
                            <td className="py-3 pr-4">
                                {transaction.meter_reading === null
                                    ? 'No meter'
                                    : formatNumber(transaction.meter_reading)}
                                {(transaction.tank_level_before !== null ||
                                    transaction.tank_level_after !== null) && (
                                    <div className="text-muted-foreground">
                                        Tank{' '}
                                        {formatNumber(
                                            transaction.tank_level_before,
                                        )}{' '}
                                        to{' '}
                                        {formatNumber(
                                            transaction.tank_level_after,
                                        )}{' '}
                                        L
                                    </div>
                                )}
                                {transaction.is_full_tank && (
                                    <div className="text-muted-foreground">
                                        Full tank
                                    </div>
                                )}
                            </td>
                            {canViewCosts && (
                                <td className="py-3 pr-4">
                                    {formatCurrencyAmount(
                                        transaction.currency_code,
                                        transaction.total_cost,
                                    )}
                                    {transaction.unit_cost !== null && (
                                        <div className="text-muted-foreground">
                                            {formatCurrencyAmount(
                                                transaction.currency_code,
                                                transaction.unit_cost,
                                            )}{' '}
                                            / L
                                        </div>
                                    )}
                                </td>
                            )}
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        transaction.status === 'reversed'
                                            ? 'destructive'
                                            : transaction.status === 'posted'
                                              ? 'secondary'
                                              : 'outline'
                                    }
                                >
                                    {title(transaction.status)}
                                </Badge>
                                <div className="mt-1 text-muted-foreground">
                                    by {transaction.submitted_by}
                                </div>
                                {transaction.approved_by && (
                                    <div className="text-muted-foreground">
                                        Posted by {transaction.approved_by}
                                    </div>
                                )}
                                {transaction.exception_status !==
                                    'not_evaluated' && (
                                    <Badge
                                        className="mt-2"
                                        variant={
                                            transaction.exception_status ===
                                            'review_required'
                                                ? 'destructive'
                                                : transaction.exception_status ===
                                                    'within_tolerance'
                                                  ? 'secondary'
                                                  : 'outline'
                                        }
                                    >
                                        {title(transaction.exception_status)}
                                    </Badge>
                                )}
                                {transaction.exception_reason && (
                                    <div className="mt-1 max-w-72 text-muted-foreground">
                                        {transaction.exception_reason}
                                    </div>
                                )}
                                {transaction.reversal_reason && (
                                    <div className="mt-1 max-w-56 text-muted-foreground">
                                        {transaction.reversal_reason}
                                    </div>
                                )}
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    {transaction.can_approve && (
                                        <FuelApproveButton
                                            transaction={transaction}
                                        />
                                    )}
                                    {transaction.can_reverse && (
                                        <FuelReversalDialog
                                            transaction={transaction}
                                        />
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {transactions.length === 0 && (
                        <tr>
                            <td
                                colSpan={canViewCosts ? 7 : 6}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No fuel transactions recorded.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function Value({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}
function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
