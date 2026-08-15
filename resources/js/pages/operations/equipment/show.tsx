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
import { EquipmentDialog } from './partials/equipment-dialog';
import type {
    BranchOption,
    EquipmentCategory,
    EquipmentLocation,
    EquipmentRecord,
    Option,
    OwnerOption,
    ProjectOption,
    SiteOption,
} from './types';

type Props = {
    equipment: EquipmentRecord;
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    branches: BranchOption[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    projects: ProjectOption[];
    sites: SiteOption[];
    owners: OwnerOption[];
    currencies: Option[];
    can: { update: boolean; retire: boolean; uploadDocuments: boolean; viewCosts: boolean };
};

export default function EquipmentShow(props: Props) {
    const { equipment, documents, documentTypes, documentBranches, documentLinkOptions, branches, categories, locations, owners, currencies, can } = props;
    const [tab, setTab] = useState('overview');
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Equipment', href: '/equipment' }, { title: equipment.asset_code, href: `/equipment/${equipment.id}` }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={equipment.asset_code} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div><div className="flex flex-wrap items-center gap-2"><h1 className="text-2xl font-semibold">{equipment.name}</h1><Badge variant={equipment.is_active ? 'secondary' : 'destructive'}>{title(equipment.current_status)}</Badge></div><p className="mt-1 text-sm text-muted-foreground">{equipment.asset_code} · {equipment.category_name} · {equipment.branch_name}</p></div>
                    <div className="flex justify-end gap-2">
                        {can.update && <EquipmentDialog equipment={equipment} branches={branches} categories={categories} locations={locations} owners={owners} currencies={currencies} canViewCosts={can.viewCosts} />}
                        {can.retire && <Button variant={equipment.is_active ? 'destructive' : 'secondary'} onClick={() => confirm({ title: equipment.is_active ? 'Retire equipment?' : 'Restore equipment?', description: `${equipment.asset_code} will move to the ${equipment.is_active ? 'inactive' : 'active'} register.`, confirmLabel: equipment.is_active ? 'Retire' : 'Restore', variant: equipment.is_active ? 'destructive' : 'default', onConfirm: () => router.delete(`/equipment/${equipment.id}`) })}>{equipment.is_active ? 'Retire' : 'Restore'}</Button>}
                    </div>
                </div>

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList><TabsTrigger value="overview">Overview</TabsTrigger><TabsTrigger value="documents">Documents</TabsTrigger></TabsList>
                    <TabsContent value="overview" className="mt-6 grid gap-6">
                        <Card><CardHeader><CardTitle>Current state</CardTitle></CardHeader><CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><Value label="Status" value={title(equipment.current_status)} /><Value label="Location" value={equipment.current_location_name ?? equipment.default_location_name ?? 'Not set'} /><Value label="Project / site" value={equipment.current_site_name ?? equipment.current_project_name ?? 'Unassigned'} /><Value label="Custodian" value={equipment.current_custodian_name ?? 'Unassigned'} /><Value label="Current meter" value={equipment.current_meter_reading ? formatNumber(equipment.current_meter_reading) : 'No reading'} /><Value label="Meter type" value={title(equipment.meter_type)} /><Value label="Opening reading" value={equipment.starting_meter_reading ? formatNumber(equipment.starting_meter_reading) : 'None'} /><Value label="Opening date" value={equipment.starting_meter_date ?? 'Not set'} /></CardContent></Card>
                        <Card><CardHeader><CardTitle>Asset identity</CardTitle></CardHeader><CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><Value label="Make / model" value={[equipment.make, equipment.model].filter(Boolean).join(' ') || 'Not set'} /><Value label="Model year" value={equipment.model_year?.toString() ?? 'Not set'} /><Value label="Serial number" value={equipment.serial_number ?? 'Not set'} /><Value label="Registration" value={equipment.registration_number ?? 'Not set'} /><Value label="Chassis / VIN" value={equipment.chassis_number ?? 'Not set'} /><Value label="Ownership" value={title(equipment.ownership_type)} /><Value label="Owner" value={equipment.owner_name ?? 'Tenant'} /><Value label="Capacity" value={equipment.capacity_value ? `${formatNumber(equipment.capacity_value)} ${equipment.capacity_unit ?? ''}`.trim() : 'Not set'} /></CardContent></Card>
                        <Card><CardHeader><CardTitle>Fuel baseline</CardTitle></CardHeader><CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><Value label="Efficiency basis" value={equipment.fuel_efficiency_basis ? title(equipment.fuel_efficiency_basis) : 'Not set'} /><Value label="Expected consumption" value={equipment.expected_fuel_efficiency ? formatNumber(equipment.expected_fuel_efficiency) : 'Not set'} /><Value label="Tolerance" value={equipment.fuel_tolerance_percent ? `${formatNumber(equipment.fuel_tolerance_percent)}%` : 'Not set'} /><Value label="Tank capacity" value={equipment.tank_capacity ? `${formatNumber(equipment.tank_capacity)} L` : 'Not set'} /></CardContent></Card>
                        {can.viewCosts && <Card><CardHeader><CardTitle>Commercial details</CardTitle></CardHeader><CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><Value label="Acquired on" value={equipment.acquired_on ?? 'Not set'} /><Value label="Acquisition cost" value={formatCurrencyAmount(equipment.acquisition_currency_code, equipment.acquisition_amount)} /><Value label="Hire rate" value={formatCurrencyAmount(equipment.acquisition_currency_code, equipment.hire_rate)} /><Value label="Rate basis" value={equipment.hire_rate_basis ? title(equipment.hire_rate_basis) : 'Not set'} /></CardContent></Card>}
                        {equipment.condition_summary && <Card><CardHeader><CardTitle>Condition</CardTitle></CardHeader><CardContent className="whitespace-pre-wrap text-sm">{equipment.condition_summary}</CardContent></Card>}
                    </TabsContent>
                    <TabsContent value="documents" className="mt-6">
                        <DocumentEvidenceTable documents={documents} emptyText="No controlled documents are linked to this asset." actions={can.uploadDocuments && <DocumentDialog documentTypes={documentTypes} branches={documentBranches} linkOptions={documentLinkOptions} defaultBranchId={equipment.branch_id} defaultLink={{ type: 'equipment', id: equipment.id }} buttonLabel="Upload document" />} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function Value({ label, value }: { label: string; value: string }) { return <div><div className="text-xs font-medium text-muted-foreground uppercase">{label}</div><div className="mt-1 text-sm font-medium">{value}</div></div>; }
function title(value: string) { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
