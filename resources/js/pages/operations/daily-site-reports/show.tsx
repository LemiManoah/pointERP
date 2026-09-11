import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    LockKeyhole,
    Pencil,
    Plus,
    RotateCcw,
    Send,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
    type Option,
} from '../documents/partials/document-dialog';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';

type Line = Record<string, string | null>;

type ActivityOption = {
    id: string;
    project_id: string;
    site_id: string | null;
    label: string;
    boq_item_number: string | null;
    unit: string | null;
    rate_amount: string | null;
    currency_code: string | null;
};

type EquipmentOption = {
    id: string;
    branch_id: string;
    name: string;
    asset_code: string;
    category_name: string;
    current_site_id: string | null;
    current_meter_reading: string | null;
    meter_type: string;
};

type InventoryUnitOption = { id: string; name: string; symbol: string | null };
type InventoryItemOption = {
    id: string;
    name: string;
    code: string;
    stock_unit_id: string;
    stock_unit: string;
    tracking_type: string;
    store_ids: string[];
    units: InventoryUnitOption[];
    batches: Array<{
        id: string;
        batch_number: string;
        inventory_store_id: string | null;
    }>;
};
type InventoryStoreOption = {
    id: string;
    branch_id: string;
    name: string;
    branch_name: string;
    site_id: string | null;
    is_default_for_site: boolean;
};
type SelectOption = {
    value: string;
    label: string;
    description?: string;
    has_quantity?: boolean;
    unit?: string | null;
};
type ExpenseDraftOptions = {
    items: SelectOption[];
    companies: SelectOption[];
    staff: SelectOption[];
};
type DsrExpense = {
    id: string;
    expense_number: string;
    item: string;
    payee: string;
    amount: string;
    currency_code: string;
    status: string;
};
type MaterialUsage = {
    id: string;
    material_name: string;
    source: string;
    source_label: string;
    status: string;
    status_label: string;
    reported_quantity: string | null;
    reported_unit: string | null;
    stock_quantity: string | null;
    stock_unit: string | null;
    store_name: string | null;
    batch_number: string | null;
    external_reason: string | null;
    posted_at: string | null;
};

const numericLineFields = new Set([
    'quantity',
    'rate_amount',
    'amount',
    'headcount',
    'hours',
    'person_hours',
    'working_hours',
    'idle_hours',
    'opening_meter_reading',
    'closing_meter_reading',
    'fuel_quantity',
    'hours_lost',
    'previous_approved_quantity',
    'cumulative_to_date',
]);

const readOnlyLineFields = new Set([
    'previous_approved_quantity',
    'cumulative_to_date',
    'fleet_posting_status',
    'person_hours',
]);

const activitySnapshotFields = new Set([
    'boq_item_number',
    'description',
    'unit',
    'rate_amount',
]);

const equipmentSnapshotFields = new Set([
    'equipment_name',
    'equipment_identifier',
]);

const materialSnapshotFields = new Set(['material_name', 'unit']);

const controlledLineOptions: Record<string, string[]> = {
    side: ['Full width', 'LHS', 'RHS', 'Centreline'],
    status: ['working', 'idle', 'breakdown', 'off-hire'],
    fuel_type: ['Diesel', 'Petrol'],
    fuel_transaction_type: ['consumption', 'refuel', 'issue', 'return'],
    category: [
        'Petty cash',
        'Allowances',
        'Overheads',
        'Mobilisation',
        'Demobilisation',
        'Subcontract',
    ],
    delay_type: [
        'Weather',
        'Equipment breakdown',
        'Material shortage',
        'Labour shortage',
        'Client instruction',
        'Design or technical',
        'Access',
        'Safety',
        'Other',
    ],
};

type Report = {
    id: string;
    reference: string;
    project_name: string;
    site_name: string;
    site_id: string;
    project_id: string;
    branch_id: string;
    report_date: string;
    status: string;
    weather: string | null;
    site_conditions: string | null;
    work_summary: string | null;
    delay_summary: string | null;
    visitor_summary: string | null;
    hse_notes: string | null;
    environment_notes: string | null;
    social_notes: string | null;
    completion_percent: string | null;
    output_value: string | null;
    input_cost: string | null;
    profit_loss: string | null;
    return_reason: string | null;
    work_lines: Line[];
    labour_lines: Line[];
    equipment_lines: Line[];
    material_lines: Line[];
    other_cost_expenses: DsrExpense[];
    delay_lines: Line[];
    evidence_count: number;
};

type Review = {
    id: string;
    action: string;
    remarks: string | null;
    reviewed_by: string | null;
    created_at: string;
};

type Correction = {
    id: string;
    status: string;
    reason: string;
    requested_by: string | null;
    created_at: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    can_manage: boolean;
};

type CorrectionField =
    | 'weather'
    | 'site_conditions'
    | 'work_summary'
    | 'delay_summary'
    | 'visitor_summary'
    | 'hse_notes'
    | 'environment_notes'
    | 'social_notes'
    | 'completion_percent';

type EquipmentAdjustmentForm = {
    line_id: string;
    equipment_name: string;
    working_hours_delta: string;
    idle_hours_delta: string;
    fuel_quantity_delta: string;
    note: string;
};

type CorrectionChanges = Record<CorrectionField, string> & {
    equipment_adjustments: EquipmentAdjustmentForm[];
};

type CorrectionFormData = {
    reason: string;
    changes: CorrectionChanges;
};

const correctionFields: Array<{
    field: CorrectionField;
    label: string;
}> = [
    { field: 'weather', label: 'Weather' },
    { field: 'site_conditions', label: 'Site conditions' },
    { field: 'work_summary', label: 'Work summary' },
    { field: 'delay_summary', label: 'Delay summary' },
    { field: 'visitor_summary', label: 'Visitor summary' },
    { field: 'hse_notes', label: 'HSE notes' },
    { field: 'environment_notes', label: 'Environment notes' },
    { field: 'social_notes', label: 'Social notes' },
    { field: 'completion_percent', label: 'Completion percent' },
];

type Props = {
    report: Report;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        return: boolean;
        correct: boolean;
        createExpenseDraft: boolean;
        manageExpenseItems: boolean;
    };
    reviews: Review[];
    corrections: Correction[];
    canViewCosts: boolean;
    documents: LinkedDocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    canUploadDocuments: boolean;
    activities: ActivityOption[];
    equipmentOptions: EquipmentOption[];
    inventoryItems: InventoryItemOption[];
    inventoryStores: InventoryStoreOption[];
    materialUsage: MaterialUsage[];
    units: string[];
    labourSources: SelectOption[];
    subcontractors: SelectOption[];
    expenseDraftOptions: ExpenseDraftOptions;
};

type FormData = Record<string, string | Line[]> & {
    site_id: string;
    report_date: string;
    weather: string;
    site_conditions: string;
    work_summary: string;
    delay_summary: string;
    visitor_summary: string;
    hse_notes: string;
    environment_notes: string;
    social_notes: string;
    completion_percent: string;
    work_lines: Line[];
    labour_lines: Line[];
    equipment_lines: Line[];
    material_lines: Line[];
    delay_lines: Line[];
};

export default function DailySiteReportShow({
    report,
    can,
    reviews,
    corrections,
    canViewCosts,
    documents,
    documentTypes,
    documentBranches,
    documentLinkOptions,
    canUploadDocuments,
    activities,
    equipmentOptions,
    inventoryItems,
    inventoryStores,
    materialUsage,
    units,
    labourSources,
    subcontractors,
    expenseDraftOptions,
}: Props) {
    const confirm = useConfirmDialog();
    const [tab, setTab] = useState('summary');
    const form = useForm<FormData>({
        site_id: report.site_id,
        report_date: report.report_date,
        weather: report.weather ?? '',
        site_conditions: report.site_conditions ?? '',
        work_summary: report.work_summary ?? '',
        delay_summary: report.delay_summary ?? '',
        visitor_summary: report.visitor_summary ?? '',
        hse_notes: report.hse_notes ?? '',
        environment_notes: report.environment_notes ?? '',
        social_notes: report.social_notes ?? '',
        completion_percent: report.completion_percent ?? '',
        work_lines: report.work_lines.length > 0 ? report.work_lines : [],
        labour_lines: report.labour_lines.length > 0 ? report.labour_lines : [],
        equipment_lines:
            report.equipment_lines.length > 0 ? report.equipment_lines : [],
        material_lines:
            report.material_lines.length > 0 ? report.material_lines : [],
        delay_lines: report.delay_lines.length > 0 ? report.delay_lines : [],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Daily reports', href: '/daily-site-reports' },
        { title: report.reference, href: `/daily-site-reports/${report.id}` },
    ];

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            work_lines: cleanLines(data.work_lines),
            labour_lines: cleanLines(data.labour_lines),
            equipment_lines: cleanLines(data.equipment_lines),
            material_lines: cleanLines(data.material_lines),
            delay_lines: cleanLines(data.delay_lines),
        }));
        form.put(`/daily-site-reports/${report.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={report.reference} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="text-sm font-medium text-muted-foreground">
                            {report.site_name} · {report.project_name}
                        </div>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {report.reference}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {report.report_date} · {report.status}
                        </p>
                        {report.return_reason && (
                            <p className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                {report.return_reason}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.submit && (
                            <SubmitReportButton
                                report={report}
                                disabled={form.isDirty || form.processing}
                            />
                        )}
                        {can.return && <ReturnReportDialog report={report} />}
                        {can.correct && <CorrectionDialog report={report} />}
                        {can.approve && (
                            <Button
                                onClick={() =>
                                    confirm({
                                        title: 'Approve report?',
                                        description: `${report.reference} will be locked from direct editing.`,
                                        confirmLabel: 'Approve',
                                        onConfirm: () =>
                                            router.post(
                                                `/daily-site-reports/${report.id}/approve`,
                                            ),
                                    })
                                }
                            >
                                <CheckCircle2 />
                                Approve
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Metric label="Output value" value={report.output_value} />
                    {canViewCosts && (
                        <>
                            <Metric
                                label="Input cost"
                                value={report.input_cost}
                            />
                            <Metric
                                label="Profit/loss"
                                value={report.profit_loss}
                            />
                        </>
                    )}
                    <Metric
                        label="Evidence"
                        value={String(report.evidence_count)}
                    />
                </div>

                {can.submit &&
                    report.work_lines.length > 0 &&
                    report.evidence_count === 0 && (
                        <Card className="border-amber-200 bg-amber-50 text-amber-950">
                            <CardContent className="pt-6 text-sm">
                                Work activities have been entered without linked
                                evidence. Upload evidence or submit with an
                                override reason.
                            </CardContent>
                        </Card>
                    )}

                <form onSubmit={submit} className="grid gap-6">
                    {!can.update && (
                        <Alert>
                            <LockKeyhole />
                            <AlertTitle>
                                {[
                                    'submitted',
                                    'reviewed',
                                    'approved',
                                    'archived',
                                ].includes(report.status)
                                    ? 'This report is locked'
                                    : 'Read-only report'}
                            </AlertTitle>
                            <AlertDescription>
                                {['submitted', 'reviewed'].includes(
                                    report.status,
                                )
                                    ? 'The report is in review and cannot be edited unless it is returned.'
                                    : ['approved', 'archived'].includes(
                                            report.status,
                                        )
                                      ? 'Approved and archived reports can only be changed through the controlled correction workflow.'
                                      : 'You can view this report, but you do not have permission to edit it for this site.'}
                            </AlertDescription>
                        </Alert>
                    )}
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList className="h-auto flex-wrap justify-start">
                            <TabsTrigger value="summary">Summary</TabsTrigger>
                            <TabsTrigger value="work">
                                Work Activities
                            </TabsTrigger>
                            <TabsTrigger value="labour">Labour</TabsTrigger>
                            <TabsTrigger value="equipment">
                                Equipment
                            </TabsTrigger>
                            <TabsTrigger value="materials">
                                Material Usage
                            </TabsTrigger>
                            <TabsTrigger value="costs-delays">
                                Costs &amp; Delays
                            </TabsTrigger>
                            <TabsTrigger value="evidence">Evidence</TabsTrigger>
                            <TabsTrigger value="workflow">Workflow</TabsTrigger>
                        </TabsList>
                        <TabsContent value="summary" className="mt-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Daily summary</CardTitle>
                                    <CardDescription>
                                        Weather, site conditions, work, issues
                                        and compliance notes.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Field
                                            label="Weather"
                                            value={form.data.weather}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData('weather', value)
                                            }
                                        />
                                        <Field
                                            label="Site conditions"
                                            value={form.data.site_conditions}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'site_conditions',
                                                    value,
                                                )
                                            }
                                        />
                                        <Field
                                            label="Completion %"
                                            value={form.data.completion_percent}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'completion_percent',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <TextAreaField
                                        label="Work summary"
                                        value={form.data.work_summary}
                                        disabled={!can.update}
                                        onChange={(value) =>
                                            form.setData('work_summary', value)
                                        }
                                    />
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <TextAreaField
                                            label="Delays"
                                            value={form.data.delay_summary}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'delay_summary',
                                                    value,
                                                )
                                            }
                                        />
                                        <TextAreaField
                                            label="Visitors"
                                            value={form.data.visitor_summary}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'visitor_summary',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <TextAreaField
                                            label="HSE"
                                            value={form.data.hse_notes}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData('hse_notes', value)
                                            }
                                        />
                                        <TextAreaField
                                            label="Environment"
                                            value={form.data.environment_notes}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'environment_notes',
                                                    value,
                                                )
                                            }
                                        />
                                        <TextAreaField
                                            label="Social"
                                            value={form.data.social_notes}
                                            disabled={!can.update}
                                            onChange={(value) =>
                                                form.setData(
                                                    'social_notes',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="work" className="mt-6 grid gap-6">
                            <LineCard
                                title="Work Activities"
                                disabled={!can.update}
                                lines={form.data.work_lines}
                                fields={[
                                    'project_activity_id',
                                    'boq_item_number',
                                    'description',
                                    'chainage_from',
                                    'chainage_to',
                                    'side',
                                    'quantity',
                                    'unit',
                                    'previous_approved_quantity',
                                    'cumulative_to_date',
                                    ...(canViewCosts ? ['rate_amount'] : []),
                                ]}
                                activities={activities.filter(
                                    (activity) =>
                                        activity.project_id ===
                                            report.project_id &&
                                        (activity.site_id === null ||
                                            activity.site_id ===
                                                report.site_id),
                                )}
                                units={units}
                                onAdd={() =>
                                    form.setData('work_lines', [
                                        ...form.data.work_lines,
                                        emptyWorkLine(),
                                    ])
                                }
                                onChange={(lines) =>
                                    form.setData('work_lines', lines)
                                }
                            />
                        </TabsContent>
                        <TabsContent value="labour" className="mt-6 grid gap-6">
                            <LineCard
                                title="Labour"
                                disabled={!can.update}
                                lines={form.data.labour_lines}
                                fields={[
                                    'labour_source',
                                    'subcontractor_id',
                                    'trade_or_role',
                                    'headcount',
                                    'hours',
                                    'person_hours',
                                    ...(canViewCosts ? ['rate_amount'] : []),
                                ]}
                                labourSources={labourSources}
                                subcontractors={subcontractors}
                                onAdd={() =>
                                    form.setData('labour_lines', [
                                        ...form.data.labour_lines,
                                        emptyLabourLine(),
                                    ])
                                }
                                onChange={(lines) =>
                                    form.setData('labour_lines', lines)
                                }
                            />
                        </TabsContent>
                        <TabsContent
                            value="equipment"
                            className="mt-6 grid gap-6"
                        >
                            <LineCard
                                title="Equipment and fuel"
                                disabled={!can.update}
                                lines={form.data.equipment_lines}
                                fields={[
                                    'equipment_id',
                                    'equipment_name',
                                    'equipment_identifier',
                                    'status',
                                    'working_hours',
                                    'idle_hours',
                                    'opening_meter_reading',
                                    'closing_meter_reading',
                                    'fuel_type',
                                    'fuel_quantity',
                                    'fuel_transaction_type',
                                    'evidence_note',
                                    'fleet_posting_status',
                                    ...(canViewCosts ? ['rate_amount'] : []),
                                ]}
                                equipmentOptions={equipmentOptions.filter(
                                    (equipment) =>
                                        equipment.branch_id ===
                                            report.branch_id &&
                                        (equipment.current_site_id === null ||
                                            equipment.current_site_id ===
                                                report.site_id),
                                )}
                                onAdd={() =>
                                    form.setData('equipment_lines', [
                                        ...form.data.equipment_lines,
                                        emptyEquipmentLine(),
                                    ])
                                }
                                onChange={(lines) =>
                                    form.setData('equipment_lines', lines)
                                }
                            />
                        </TabsContent>
                        <TabsContent
                            value="materials"
                            className="mt-6 grid gap-6"
                        >
                            <LineCard
                                title="Material usage"
                                disabled={!can.update}
                                lines={form.data.material_lines}
                                fields={[
                                    'material_source',
                                    'inventory_item_id',
                                    'inventory_store_id',
                                    'inventory_batch_id',
                                    'unit_of_measure_id',
                                    'material_name',
                                    'quantity',
                                    'unit',
                                    'external_material_reason',
                                    'notes',
                                ]}
                                units={units}
                                inventoryItems={inventoryItems}
                                inventoryStores={inventoryStores.filter(
                                    (store) =>
                                        store.branch_id === report.branch_id,
                                )}
                                onAdd={() =>
                                    form.setData('material_lines', [
                                        ...form.data.material_lines,
                                        emptyMaterialLine(),
                                    ])
                                }
                                onChange={(lines) =>
                                    form.setData('material_lines', lines)
                                }
                            />
                            <MaterialUsageStatusCard lines={materialUsage} />
                        </TabsContent>
                        <TabsContent
                            value="costs-delays"
                            className="mt-6 grid gap-6"
                        >
                            {canViewCosts && (
                                <OtherCostsCard
                                    reportId={report.id}
                                    expenses={report.other_cost_expenses}
                                    options={expenseDraftOptions}
                                    canCreate={can.createExpenseDraft}
                                    canManageItems={can.manageExpenseItems}
                                />
                            )}
                            <LineCard
                                title="Delay details"
                                disabled={!can.update}
                                lines={form.data.delay_lines}
                                fields={[
                                    'delay_type',
                                    'description',
                                    'hours_lost',
                                ]}
                                onAdd={() =>
                                    form.setData('delay_lines', [
                                        ...form.data.delay_lines,
                                        emptyDelayLine(),
                                    ])
                                }
                                onChange={(lines) =>
                                    form.setData('delay_lines', lines)
                                }
                            />
                        </TabsContent>
                        <TabsContent value="evidence" className="mt-6">
                            <DocumentEvidenceTable
                                documents={documents}
                                emptyText="No documents linked to this report."
                                title="Linked evidence"
                                description="Drawings, sketches, permits, photos and other files tied to this daily report."
                                actions={
                                    canUploadDocuments && (
                                        <DocumentDialog
                                            documentTypes={documentTypes}
                                            branches={documentBranches}
                                            linkOptions={documentLinkOptions}
                                            defaultBranchId={report.branch_id}
                                            defaultLink={{
                                                type: 'daily_site_report',
                                                id: report.id,
                                            }}
                                            buttonLabel="Upload evidence"
                                        />
                                    )
                                }
                            />
                        </TabsContent>
                        <TabsContent value="workflow" className="mt-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Workflow trail</CardTitle>
                                    <CardDescription>
                                        Submit, return, approval and correction
                                        events for this report.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {[...corrections, ...reviews].length ===
                                    0 ? (
                                        <div className="text-sm text-muted-foreground">
                                            No workflow events recorded yet.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b text-left text-muted-foreground">
                                                        <th className="py-3 pr-4 font-medium">
                                                            Event
                                                        </th>
                                                        <th className="py-3 pr-4 font-medium">
                                                            Status
                                                        </th>
                                                        <th className="py-3 pr-4 font-medium">
                                                            Actor
                                                        </th>
                                                        <th className="py-3 pr-4 font-medium">
                                                            Details
                                                        </th>
                                                        <th className="py-3 pr-4 font-medium">
                                                            Date
                                                        </th>
                                                        <th className="py-3 text-right font-medium">
                                                            Actions
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {reviews.map((review) => (
                                                        <tr
                                                            key={review.id}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-3 pr-4 font-medium capitalize">
                                                                {review.action.replaceAll(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                            </td>
                                                            <td className="py-3 pr-4">
                                                                <Badge variant="outline">
                                                                    Recorded
                                                                </Badge>
                                                            </td>
                                                            <td className="py-3 pr-4">
                                                                {review.reviewed_by ??
                                                                    'Unknown user'}
                                                            </td>
                                                            <td className="min-w-64 py-3 pr-4 whitespace-normal">
                                                                {review.remarks ??
                                                                    '—'}
                                                            </td>
                                                            <td className="py-3 pr-4 whitespace-nowrap text-muted-foreground">
                                                                {
                                                                    review.created_at
                                                                }
                                                            </td>
                                                            <td className="py-3 text-right" />
                                                        </tr>
                                                    ))}
                                                    {corrections.map(
                                                        (correction) => (
                                                            <tr
                                                                key={
                                                                    correction.id
                                                                }
                                                                className="border-b last:border-0"
                                                            >
                                                                <td className="py-3 pr-4 font-medium">
                                                                    Correction
                                                                </td>
                                                                <td className="py-3 pr-4">
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="capitalize"
                                                                    >
                                                                        {correction.status.replaceAll(
                                                                            '_',
                                                                            ' ',
                                                                        )}
                                                                    </Badge>
                                                                </td>
                                                                <td className="py-3 pr-4">
                                                                    {correction.requested_by ??
                                                                        'Unknown user'}
                                                                </td>
                                                                <td className="min-w-80 py-3 pr-4 whitespace-normal">
                                                                    <div className="font-medium">
                                                                        {
                                                                            correction.reason
                                                                        }
                                                                    </div>
                                                                    {correction.new_values && (
                                                                        <div className="mt-2 grid gap-1 text-xs text-muted-foreground">
                                                                            {Object.entries(
                                                                                correction.new_values,
                                                                            ).map(
                                                                                ([
                                                                                    field,
                                                                                    value,
                                                                                ]) =>
                                                                                    field ===
                                                                                        'equipment_adjustments' &&
                                                                                    Array.isArray(
                                                                                        value,
                                                                                    ) ? (
                                                                                        <CorrectionAdjustmentSummary
                                                                                            key={
                                                                                                field
                                                                                            }
                                                                                            adjustments={
                                                                                                value
                                                                                            }
                                                                                        />
                                                                                    ) : (
                                                                                        <div
                                                                                            key={
                                                                                                field
                                                                                            }
                                                                                            className="flex justify-between gap-4"
                                                                                        >
                                                                                            <span className="capitalize">
                                                                                                {field.replaceAll(
                                                                                                    '_',
                                                                                                    ' ',
                                                                                                )}
                                                                                            </span>
                                                                                            <span className="text-right font-medium text-foreground">
                                                                                                {displayUnknown(
                                                                                                    value,
                                                                                                )}
                                                                                            </span>
                                                                                        </div>
                                                                                    ),
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </td>
                                                                <td className="py-3 pr-4 whitespace-nowrap text-muted-foreground">
                                                                    {
                                                                        correction.created_at
                                                                    }
                                                                </td>
                                                                <td className="py-3 text-right">
                                                                    {correction.can_manage && (
                                                                        <CorrectionActions
                                                                            reportId={
                                                                                report.id
                                                                            }
                                                                            correction={
                                                                                correction
                                                                            }
                                                                        />
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>

                    {can.update && (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                Save draft
                            </Button>
                        </div>
                    )}
                    <InputError message={form.errors.site_id} />
                    <InputError
                        message={
                            (form.errors as Record<string, string | undefined>)
                                .report
                        }
                    />
                </form>
            </div>
        </AppLayout>
    );
}

function CorrectionActions({
    reportId,
    correction,
}: {
    reportId: string;
    correction: Correction;
}) {
    const confirm = useConfirmDialog();
    const [rejectOpen, setRejectOpen] = useState(false);
    const rejectForm = useForm({ reason: '' });

    function reject(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        rejectForm.post(
            `/daily-site-reports/${reportId}/corrections/${correction.id}/reject`,
            {
                preserveScroll: true,
                onSuccess: () => setRejectOpen(false),
            },
        );
    }

    return (
        <div className="flex flex-wrap justify-end gap-2">
            <Button
                type="button"
                size="sm"
                onClick={() =>
                    confirm({
                        title: 'Approve correction?',
                        description:
                            'The proposed values will be applied to the approved report and recorded in the audit trail.',
                        confirmLabel: 'Approve correction',
                        onConfirm: () =>
                            router.post(
                                `/daily-site-reports/${reportId}/corrections/${correction.id}/approve`,
                                {},
                                { preserveScroll: true },
                            ),
                    })
                }
            >
                Approve
            </Button>
            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogTrigger asChild>
                    <Button type="button" size="sm" variant="outline">
                        Reject
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={reject} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>Reject correction</DialogTitle>
                            <DialogDescription>
                                Record why the proposed change should not be
                                applied.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor={`reject-${correction.id}`}>
                                Reason
                            </Label>
                            <Textarea
                                id={`reject-${correction.id}`}
                                value={rejectForm.data.reason}
                                onChange={(event) =>
                                    rejectForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={rejectForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRejectOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={rejectForm.processing}
                            >
                                Reject correction
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function SubmitReportButton({
    report,
    disabled,
}: {
    report: Report;
    disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ evidence_override_reason: string }>({
        evidence_override_reason: '',
    });
    const needsOverride =
        report.work_lines.length > 0 && report.evidence_count === 0;

    function submit() {
        form.post(`/daily-site-reports/${report.id}/submit`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    if (!needsOverride) {
        return (
            <Button
                variant="outline"
                onClick={submit}
                disabled={disabled}
                title={
                    disabled ? 'Save the draft before submitting.' : undefined
                }
            >
                <Send />
                Submit
            </Button>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    disabled={disabled}
                    title={
                        disabled
                            ? 'Save the draft before submitting.'
                            : undefined
                    }
                >
                    <Send />
                    Submit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Submit without evidence?</DialogTitle>
                    <DialogDescription>
                        This report has work activities but no linked evidence.
                        Record the reason before submitting.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="evidence_override_reason">
                        Override reason
                    </Label>
                    <Textarea
                        id="evidence_override_reason"
                        value={form.data.evidence_override_reason}
                        onChange={(event) =>
                            form.setData(
                                'evidence_override_reason',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={form.errors.evidence_override_reason}
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button type="button" onClick={submit}>
                        Submit report
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ReturnReportDialog({ report }: { report: Report }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ reason: string }>({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/daily-site-reports/${report.id}/return`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <RotateCcw />
                    Return
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Return report</DialogTitle>
                        <DialogDescription>
                            Tell the site team what must be corrected before
                            resubmission.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="reason">Reason</Label>
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Return report
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CorrectionDialog({ report }: { report: Report }) {
    const [open, setOpen] = useState(false);
    const form = useForm<CorrectionFormData>({
        reason: '',
        changes: {
            weather: report.weather ?? '',
            site_conditions: report.site_conditions ?? '',
            work_summary: report.work_summary ?? '',
            delay_summary: report.delay_summary ?? '',
            visitor_summary: report.visitor_summary ?? '',
            hse_notes: report.hse_notes ?? '',
            environment_notes: report.environment_notes ?? '',
            social_notes: report.social_notes ?? '',
            completion_percent: report.completion_percent ?? '',
            equipment_adjustments: report.equipment_lines
                .filter(
                    (line) =>
                        line.equipment_id &&
                        line.fleet_posting_status === 'posted',
                )
                .map((line) => ({
                    line_id: line.id ?? '',
                    equipment_name:
                        line.equipment_identifier ??
                        line.equipment_name ??
                        'Equipment',
                    working_hours_delta: '',
                    idle_hours_delta: '',
                    fuel_quantity_delta: '',
                    note: '',
                })),
        },
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/daily-site-reports/${report.id}/corrections`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Request correction</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-5xl">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Request correction</DialogTitle>
                        <DialogDescription>
                            Approved reports are locked. This records proposed
                            changes for controlled review.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="correction_reason">Reason</Label>
                        <Textarea
                            id="correction_reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {correctionFields.map(({ field, label }) => (
                            <div key={field} className="grid gap-2">
                                <Label>{label}</Label>
                                <Textarea
                                    value={form.data.changes[field]}
                                    onChange={(event) =>
                                        form.setData('changes', {
                                            ...form.data.changes,
                                            [field]: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div className="grid gap-3">
                        <div>
                            <Label>Fleet ledger adjustments</Label>
                            <p className="text-sm text-muted-foreground">
                                Enter only the difference. Use a negative value
                                to reduce the posted total. Meter changes use
                                the equipment meter-correction workflow.
                            </p>
                        </div>
                        {form.data.changes.equipment_adjustments.map(
                            (adjustment, index) => (
                                <div
                                    key={adjustment.line_id}
                                    className="grid gap-3 rounded-md border p-3"
                                >
                                    <div className="font-medium">
                                        {adjustment.equipment_name}
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <AdjustmentInput
                                            label="Working hours delta"
                                            value={
                                                adjustment.working_hours_delta
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'changes',
                                                    updateEquipmentAdjustment(
                                                        form.data.changes,
                                                        index,
                                                        'working_hours_delta',
                                                        value,
                                                    ),
                                                )
                                            }
                                        />
                                        <AdjustmentInput
                                            label="Idle hours delta"
                                            value={adjustment.idle_hours_delta}
                                            onChange={(value) =>
                                                form.setData(
                                                    'changes',
                                                    updateEquipmentAdjustment(
                                                        form.data.changes,
                                                        index,
                                                        'idle_hours_delta',
                                                        value,
                                                    ),
                                                )
                                            }
                                        />
                                        <AdjustmentInput
                                            label="Fuel litres delta"
                                            value={
                                                adjustment.fuel_quantity_delta
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'changes',
                                                    updateEquipmentAdjustment(
                                                        form.data.changes,
                                                        index,
                                                        'fuel_quantity_delta',
                                                        value,
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Line note</Label>
                                        <Input
                                            value={adjustment.note}
                                            onChange={(event) =>
                                                form.setData(
                                                    'changes',
                                                    updateEquipmentAdjustment(
                                                        form.data.changes,
                                                        index,
                                                        'note',
                                                        event.target.value,
                                                    ),
                                                )
                                            }
                                            placeholder="Optional equipment-specific explanation"
                                        />
                                    </div>
                                </div>
                            ),
                        )}
                        {form.data.changes.equipment_adjustments.length ===
                            0 && (
                            <div className="rounded-md border px-3 py-6 text-center text-sm text-muted-foreground">
                                This report has no posted linked equipment lines
                                available for fleet adjustment.
                            </div>
                        )}
                    </div>
                    <InputError message={form.errors.changes} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Record correction
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AdjustmentInput({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                type="number"
                step="0.0001"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder="0"
            />
        </div>
    );
}

function updateEquipmentAdjustment(
    changes: CorrectionChanges,
    index: number,
    field:
        | 'working_hours_delta'
        | 'idle_hours_delta'
        | 'fuel_quantity_delta'
        | 'note',
    value: string,
): CorrectionChanges {
    return {
        ...changes,
        equipment_adjustments: changes.equipment_adjustments.map(
            (adjustment, adjustmentIndex) =>
                adjustmentIndex === index
                    ? { ...adjustment, [field]: value }
                    : adjustment,
        ),
    };
}

function CorrectionAdjustmentSummary({
    adjustments,
}: {
    adjustments: unknown[];
}) {
    return (
        <div className="grid gap-1 border-t pt-2">
            <span className="font-medium text-foreground">
                Fleet ledger adjustments
            </span>
            {adjustments.filter(isRecord).map((adjustment, index) => (
                <div
                    key={`${displayUnknown(adjustment.line_id)}-${index}`}
                    className="grid gap-1"
                >
                    <div className="font-medium">
                        {displayUnknown(
                            adjustment.equipment_name ??
                                adjustment.line_id ??
                                'Equipment',
                        )}
                    </div>
                    <div className="flex flex-wrap gap-x-4">
                        <span>
                            Working: {signed(adjustment.working_hours_delta)} h
                        </span>
                        <span>
                            Idle: {signed(adjustment.idle_hours_delta)} h
                        </span>
                        <span>
                            Fuel: {signed(adjustment.fuel_quantity_delta)} L
                        </span>
                    </div>
                    {adjustment.note ? (
                        <div>{displayUnknown(adjustment.note)}</div>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function displayUnknown(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    ) {
        return String(value);
    }

    return JSON.stringify(value) ?? '';
}

function signed(value: unknown): string {
    const number = Number(value ?? 0);

    return `${number > 0 ? '+' : ''}${formatNumber(number)}`;
}

function Metric({ label, value }: { label: string; value: string | null }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-xl">{formatNumber(value)}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function Field({
    label,
    value,
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Input
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function TextAreaField({
    label,
    value,
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Textarea
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function MaterialUsageStatusCard({ lines }: { lines: MaterialUsage[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Material usage status</CardTitle>
                <CardDescription>
                    Site-store quantities are deducted once when this report is
                    approved. Materials supplied outside inventory do not change
                    stock.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        This report has no recorded material usage.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">
                                        Material
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Source
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Reported usage
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Store / batch
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-3 py-3 font-medium">
                                            {line.material_name}
                                        </td>
                                        <td className="px-3 py-3">
                                            {line.source_label}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums">
                                            {formatNumber(
                                                line.reported_quantity ?? 0,
                                            )}{' '}
                                            {line.reported_unit ?? ''}
                                            {line.stock_quantity !== null &&
                                                line.stock_unit !== null &&
                                                line.stock_unit !==
                                                    line.reported_unit && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatNumber(
                                                            line.stock_quantity,
                                                        )}{' '}
                                                        {line.stock_unit} in
                                                        stock units
                                                    </div>
                                                )}
                                        </td>
                                        <td className="px-3 py-3">
                                            {line.source === 'external' ? (
                                                <span className="text-muted-foreground">
                                                    Not from inventory
                                                </span>
                                            ) : (
                                                <>
                                                    <div>
                                                        {line.store_name ??
                                                            'Site stock source not selected'}
                                                    </div>
                                                    {line.batch_number && (
                                                        <div className="text-xs text-muted-foreground">
                                                            Batch{' '}
                                                            {line.batch_number}
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </td>
                                        <td className="px-3 py-3">
                                            <Badge variant="outline">
                                                {line.status_label}
                                            </Badge>
                                            {line.posted_at && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {line.posted_at}
                                                </div>
                                            )}
                                            {line.external_reason && (
                                                <div className="mt-1 max-w-72 text-xs text-muted-foreground">
                                                    {line.external_reason}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function CreateExpenseDraftDialog({
    reportId,
    options,
    canManageItems,
}: {
    reportId: string;
    options: ExpenseDraftOptions;
    canManageItems: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        expense_item_id: '',
        payee_type: 'company',
        customer_id: '',
        staff_id: '',
        payee_name: '',
        quantity: '1',
        unit_amount: '',
        description: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/daily-site-reports/${reportId}/expenses`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    Add other cost
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Add other cost</DialogTitle>
                        <DialogDescription>
                            This creates the expense draft immediately and links
                            it to this DSR. Approval and payment still happen in
                            Expenses.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>
                                Expense item{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            {canManageItems ? (
                                <a
                                    href="/expenses?tab=items"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="w-fit text-xs font-medium text-primary hover:underline"
                                >
                                    Expense item not listed? Add it in a new
                                    tab, then refresh this report
                                </a>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    If the item is not listed, ask an expense
                                    administrator to register it first.
                                </p>
                            )}
                            <SearchableSelect
                                value={form.data.expense_item_id}
                                onValueChange={(value) =>
                                    form.setData('expense_item_id', value)
                                }
                                options={options.items}
                                placeholder="Select expense item"
                                searchPlaceholder="Search expense items..."
                            />
                            <InputError message={form.errors.expense_item_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>
                                Payee type{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SearchableSelect
                                value={form.data.payee_type}
                                onValueChange={(value) =>
                                    form.setData({
                                        ...form.data,
                                        payee_type: value,
                                        customer_id: '',
                                        staff_id: '',
                                        payee_name: '',
                                    })
                                }
                                options={[
                                    { value: 'company', label: 'Company' },
                                    { value: 'staff', label: 'Staff' },
                                    { value: 'other', label: 'Other' },
                                ]}
                                placeholder="Select payee type"
                            />
                        </div>
                        {form.data.payee_type === 'company' && (
                            <div className="grid gap-2">
                                <Label>
                                    Company{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <SearchableSelect
                                    value={form.data.customer_id}
                                    onValueChange={(value) =>
                                        form.setData('customer_id', value)
                                    }
                                    options={options.companies}
                                    placeholder="Select company"
                                    searchPlaceholder="Search companies..."
                                />
                                <InputError message={form.errors.customer_id} />
                            </div>
                        )}
                        {form.data.payee_type === 'staff' && (
                            <div className="grid gap-2">
                                <Label>
                                    Staff member{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <SearchableSelect
                                    value={form.data.staff_id}
                                    onValueChange={(value) =>
                                        form.setData('staff_id', value)
                                    }
                                    options={options.staff}
                                    placeholder="Select staff member"
                                    searchPlaceholder="Search staff..."
                                />
                                <InputError message={form.errors.staff_id} />
                            </div>
                        )}
                        {form.data.payee_type === 'other' && (
                            <div className="grid gap-2">
                                <Label>
                                    Payee name{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    value={form.data.payee_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'payee_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.payee_name} />
                            </div>
                        )}
                        {(() => {
                            const selectedItem = options.items.find(
                                (i) => i.value === form.data.expense_item_id,
                            );
                            const itemHasQuantity =
                                selectedItem?.has_quantity ?? true;
                            return itemHasQuantity ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label>
                                            Quantity
                                            {selectedItem?.unit
                                                ? ` (${selectedItem.unit})`
                                                : ''}{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            value={form.data.quantity}
                                            onChange={(event) =>
                                                form.setData(
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.quantity}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>
                                            Unit amount{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            value={form.data.unit_amount}
                                            onChange={(event) =>
                                                form.setData(
                                                    'unit_amount',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.unit_amount}
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="grid gap-2">
                                    <Label>
                                        Amount{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0.0001"
                                        step="0.0001"
                                        value={form.data.unit_amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'unit_amount',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.unit_amount}
                                    />
                                </div>
                            );
                        })()}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>Description</Label>
                            <Textarea
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <InputError
                        message={
                            (form.errors as Record<string, string | undefined>)
                                .expense
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Create draft
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function OtherCostsCard({
    reportId,
    expenses,
    options,
    canCreate,
    canManageItems,
}: {
    reportId: string;
    expenses: DsrExpense[];
    options: ExpenseDraftOptions;
    canCreate: boolean;
    canManageItems: boolean;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>Other costs</CardTitle>
                    <CardDescription>
                        Expense drafts recorded from this daily report.
                    </CardDescription>
                </div>
                {canCreate && (
                    <CreateExpenseDraftDialog
                        reportId={reportId}
                        options={options}
                        canManageItems={canManageItems}
                    />
                )}
            </CardHeader>
            <CardContent>
                {expenses.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No other costs have been recorded.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-3 pr-4 font-medium">
                                        Expense
                                    </th>
                                    <th className="py-3 pr-4 font-medium">
                                        Payee
                                    </th>
                                    <th className="py-3 pr-4 text-right font-medium">
                                        Amount
                                    </th>
                                    <th className="py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.map((expense) => (
                                    <tr
                                        key={expense.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-3 pr-4">
                                            <a
                                                href={`/expenses/${expense.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {expense.item}
                                            </a>
                                            <div className="text-xs text-muted-foreground">
                                                {expense.expense_number}
                                            </div>
                                        </td>
                                        <td className="py-3 pr-4">
                                            {expense.payee}
                                        </td>
                                        <td className="py-3 pr-4 text-right tabular-nums">
                                            {expense.currency_code}{' '}
                                            {formatNumber(expense.amount)}
                                        </td>
                                        <td className="py-3">
                                            <Badge variant="outline">
                                                {expense.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function LineCard({
    title,
    description,
    disabled,
    lines,
    fields,
    onAdd,
    onChange,
    activities = [],
    equipmentOptions = [],
    inventoryItems = [],
    inventoryStores = [],
    units = [],
    labourSources = [],
    subcontractors = [],
}: {
    title: string;
    description?: string;
    disabled: boolean;
    lines: Line[];
    fields: string[];
    onAdd: () => void;
    onChange: (lines: Line[]) => void;
    activities?: ActivityOption[];
    equipmentOptions?: EquipmentOption[];
    inventoryItems?: InventoryItemOption[];
    inventoryStores?: InventoryStoreOption[];
    units?: string[];
    labourSources?: SelectOption[];
    subcontractors?: SelectOption[];
}) {
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);
    const activeIndex = editingIndex ?? 0;
    const currentLine = editingIndex === null ? null : lines[editingIndex];

    function openNewLine() {
        onAdd();
        setEditingIndex(lines.length);
        setAdding(true);
        setEditorOpen(true);
    }

    function openLine(index: number) {
        setEditingIndex(index);
        setAdding(false);
        setEditorOpen(true);
    }

    function closeEditor(save: boolean) {
        if (!save && adding && editingIndex !== null) {
            onChange(lines.filter((_, index) => index !== editingIndex));
        }
        setAdding(false);
        setEditorOpen(false);
    }
    function updateLine(index: number, field: string, value: string) {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, [field]: value } : line,
            ),
        );
    }

    function selectActivity(index: number, activityId: string) {
        const activity = activities.find((option) => option.id === activityId);

        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          project_activity_id: activityId,
                          boq_item_number: activity?.boq_item_number ?? '',
                          description: activity?.label ?? '',
                          unit: activity?.unit ?? '',
                          rate_amount:
                              activity?.rate_amount ?? line.rate_amount ?? '',
                          currency_code:
                              activity?.currency_code ??
                              line.currency_code ??
                              'UGX',
                      }
                    : line,
            ),
        );
    }

    function selectEquipment(index: number, equipmentId: string) {
        const equipment = equipmentOptions.find(
            (option) => option.id === equipmentId,
        );

        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          equipment_id: equipmentId,
                          equipment_name: equipment?.name ?? '',
                          equipment_identifier: equipment?.asset_code ?? '',
                          opening_meter_reading:
                              line.opening_meter_reading ||
                              equipment?.current_meter_reading ||
                              '',
                      }
                    : line,
            ),
        );
    }

    function selectMaterialSource(index: number, source: string) {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          material_source: source,
                          inventory_item_id:
                              source === 'external'
                                  ? ''
                                  : line.inventory_item_id,
                          inventory_store_id:
                              source === 'external'
                                  ? ''
                                  : line.inventory_store_id,
                          inventory_batch_id:
                              source === 'external'
                                  ? ''
                                  : line.inventory_batch_id,
                          unit_of_measure_id:
                              source === 'external'
                                  ? ''
                                  : line.unit_of_measure_id,
                          material_name:
                              source === 'external' ? '' : line.material_name,
                          unit: source === 'external' ? '' : line.unit,
                          external_material_reason:
                              source === 'external'
                                  ? line.external_material_reason
                                  : '',
                      }
                    : line,
            ),
        );
    }
    function selectInventoryItem(index: number, itemId: string) {
        const item = inventoryItems.find((option) => option.id === itemId);
        const currentStoreId = String(lines[index]?.inventory_store_id ?? '');
        const storeId = item?.store_ids.includes(currentStoreId)
            ? currentStoreId
            : (inventoryStores.find(
                  (store) =>
                      store.is_default_for_site &&
                      item?.store_ids.includes(store.id),
              )?.id ??
              inventoryStores.find((store) =>
                  item?.store_ids.includes(store.id),
              )?.id ??
              '');
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          inventory_item_id: itemId,
                          inventory_store_id: storeId,
                          inventory_batch_id: '',
                          unit_of_measure_id: item?.stock_unit_id ?? '',
                          material_name: item?.name ?? '',
                          unit: item?.stock_unit ?? '',
                      }
                    : line,
            ),
        );
    }

    function selectInventoryUnit(index: number, unitId: string) {
        const item = inventoryItems.find(
            (option) => option.id === lines[index]?.inventory_item_id,
        );
        const unit = item?.units.find((option) => option.id === unitId);
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          unit_of_measure_id: unitId,
                          unit: unit?.symbol ?? unit?.name ?? '',
                      }
                    : line,
            ),
        );
    }

    function selectSubcontractor(index: number, customerId: string) {
        const subcontractor = subcontractors.find(
            (option) => option.value === customerId,
        );
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          subcontractor_id: customerId,
                          subcontractor_name: subcontractor?.label ?? '',
                      }
                    : line,
            ),
        );
    }

    function selectLabourSource(index: number, source: string) {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          labour_source: source,
                          subcontractor_id:
                              source === 'subcontractor'
                                  ? line.subcontractor_id
                                  : '',
                          subcontractor_name:
                              source === 'subcontractor'
                                  ? line.subcontractor_name
                                  : '',
                      }
                    : line,
            ),
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {!disabled && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={openNewLine}
                    >
                        <Plus />
                        Add {lineSingularLabel(title)}
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No {title.toLowerCase()} recorded.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">
                                        Description
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Quantity / time
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Details
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line, index) => {
                                    const summary = lineTableSummary(
                                        title,
                                        line,
                                        inventoryStores,
                                    );

                                    return (
                                        <tr
                                            key={index}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-3 py-3">
                                                <div className="font-medium">
                                                    {summary.primary}
                                                </div>
                                                {summary.secondary && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {summary.secondary}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 tabular-nums">
                                                {summary.quantity}
                                            </td>
                                            <td className="px-3 py-3 text-muted-foreground">
                                                {summary.details}
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openLine(index)
                                                        }
                                                        title={`Edit ${lineSingularLabel(title)}`}
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                    {!disabled && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() =>
                                                                onChange(
                                                                    lines.filter(
                                                                        (
                                                                            _,
                                                                            lineIndex,
                                                                        ) =>
                                                                            lineIndex !==
                                                                            index,
                                                                    ),
                                                                )
                                                            }
                                                            title={`Remove ${lineSingularLabel(title)}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>

            <Dialog
                open={editorOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setEditorOpen(true);
                        return;
                    }

                    if (adding) {
                        closeEditor(false);
                        return;
                    }

                    setEditorOpen(false);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {adding ? 'Add' : 'Edit'} {lineSingularLabel(title)}
                        </DialogTitle>
                        <DialogDescription>
                            Record the details for this daily site report.
                        </DialogDescription>
                    </DialogHeader>
                    {currentLine &&
                        title === 'Material usage' &&
                        (currentLine.material_source ?? 'site_store') ===
                            'site_store' &&
                        currentLine.inventory_item_id &&
                        !currentLine.inventory_store_id && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Material is not available in this site stock
                                </AlertTitle>
                                <AlertDescription>
                                    Add or transfer this item to the site store
                                    before recording it as used. Otherwise
                                    choose Delivered directly outside inventory.
                                </AlertDescription>
                            </Alert>
                        )}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {currentLine &&
                            fields
                                .filter((field) =>
                                    lineFieldVisible(
                                        field,
                                        currentLine,
                                        title,
                                        inventoryItems,
                                        inventoryStores,
                                    ),
                                )
                                .map((field) => (
                                    <div key={field} className="grid gap-2">
                                        <Label
                                            required={lineFieldRequired(
                                                field,
                                                title,
                                                currentLine,
                                            )}
                                        >
                                            {lineFieldLabel(field, title)}
                                        </Label>
                                        {field === 'material_source' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ??
                                                        'site_store',
                                                )}
                                                onValueChange={(value) =>
                                                    selectMaterialSource(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: 'site_store',
                                                        label: 'From site stock',
                                                    },
                                                    {
                                                        value: 'external',
                                                        label: 'Delivered directly outside inventory',
                                                    },
                                                ]}
                                                placeholder="Select how the material was supplied"
                                                disabled={disabled}
                                            />
                                        ) : field === 'inventory_item_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectInventoryItem(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={inventoryItems.map(
                                                    (item) => ({
                                                        value: item.id,
                                                        label: item.name,
                                                        description: item.code,
                                                    }),
                                                )}
                                                placeholder="Select material item"
                                                searchPlaceholder="Search inventory..."
                                                disabled={disabled}
                                            />
                                        ) : field === 'labour_source' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectLabourSource(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={labourSources}
                                                placeholder="Select labour source"
                                                disabled={disabled}
                                            />
                                        ) : field === 'subcontractor_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectSubcontractor(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={subcontractors}
                                                placeholder="Select subcontractor"
                                                searchPlaceholder="Search subcontractors..."
                                                disabled={
                                                    disabled ||
                                                    currentLine.labour_source !==
                                                        'subcontractor'
                                                }
                                            />
                                        ) : field === 'inventory_store_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    updateLine(
                                                        activeIndex,
                                                        field,
                                                        value,
                                                    )
                                                }
                                                options={inventoryStores
                                                    .filter((store) => {
                                                        const item =
                                                            inventoryItems.find(
                                                                (option) =>
                                                                    option.id ===
                                                                    currentLine.inventory_item_id,
                                                            );
                                                        return (
                                                            item?.store_ids.includes(
                                                                store.id,
                                                            ) ?? false
                                                        );
                                                    })
                                                    .map((store) => ({
                                                        value: store.id,
                                                        label: store.name,
                                                        description:
                                                            store.branch_name,
                                                    }))}
                                                placeholder="Select source store"
                                                disabled={
                                                    disabled ||
                                                    !currentLine.inventory_item_id
                                                }
                                            />
                                        ) : field === 'inventory_batch_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    updateLine(
                                                        activeIndex,
                                                        field,
                                                        value,
                                                    )
                                                }
                                                options={(
                                                    inventoryItems.find(
                                                        (item) =>
                                                            item.id ===
                                                            currentLine.inventory_item_id,
                                                    )?.batches ?? []
                                                )
                                                    .filter(
                                                        (batch) =>
                                                            batch.inventory_store_id ===
                                                                null ||
                                                            batch.inventory_store_id ===
                                                                currentLine.inventory_store_id,
                                                    )
                                                    .map((batch) => ({
                                                        value: batch.id,
                                                        label: batch.batch_number,
                                                    }))}
                                                placeholder="Select batch"
                                                searchPlaceholder="Search batches..."
                                                disabled={
                                                    disabled ||
                                                    !currentLine.inventory_item_id
                                                }
                                            />
                                        ) : field === 'unit_of_measure_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectInventoryUnit(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={(
                                                    inventoryItems.find(
                                                        (item) =>
                                                            item.id ===
                                                            currentLine.inventory_item_id,
                                                    )?.units ?? []
                                                ).map((unit) => ({
                                                    value: unit.id,
                                                    label: unit.name,
                                                    description:
                                                        unit.symbol ??
                                                        undefined,
                                                }))}
                                                placeholder="Select unit"
                                                disabled={
                                                    disabled ||
                                                    !currentLine.inventory_item_id
                                                }
                                            />
                                        ) : field === 'project_activity_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectActivity(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={activities.map(
                                                    (activity) => ({
                                                        value: activity.id,
                                                        label: activity.label,
                                                        description: [
                                                            activity.unit,
                                                            activity.boq_item_number,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' / '),
                                                    }),
                                                )}
                                                placeholder="Select work activity"
                                                searchPlaceholder="Search work activities..."
                                                emptyMessage="No work activity is available for this site."
                                                disabled={disabled}
                                            />
                                        ) : field === 'equipment_id' ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    selectEquipment(
                                                        activeIndex,
                                                        value,
                                                    )
                                                }
                                                options={equipmentOptions.map(
                                                    (equipment) => ({
                                                        value: equipment.id,
                                                        label: `${equipment.asset_code} - ${equipment.name}`,
                                                        description: [
                                                            equipment.category_name,
                                                            equipment.current_site_id
                                                                ? 'Assigned to a site'
                                                                : 'Not site-assigned',
                                                        ].join(' / '),
                                                    }),
                                                )}
                                                placeholder="Select equipment"
                                                searchPlaceholder="Search asset code or name..."
                                                emptyMessage="No registered equipment is available for this branch."
                                                disabled={disabled}
                                            />
                                        ) : (field === 'unit' &&
                                              units.length > 0) ||
                                          controlledLineOptions[field] ? (
                                            <SearchableSelect
                                                value={String(
                                                    currentLine[field] ?? '',
                                                )}
                                                onValueChange={(value) =>
                                                    updateLine(
                                                        activeIndex,
                                                        field,
                                                        value,
                                                    )
                                                }
                                                options={lineFieldOptions(
                                                    field,
                                                    currentLine,
                                                    units,
                                                )}
                                                placeholder={`Select ${field.replaceAll('_', ' ')}`}
                                                searchPlaceholder={`Search ${field.replaceAll('_', ' ')}...`}
                                                disabled={lineFieldDisabled(
                                                    currentLine,
                                                    field,
                                                    disabled,
                                                )}
                                            />
                                        ) : (
                                            <Input
                                                value={lineValue(
                                                    currentLine,
                                                    field,
                                                    lineFieldDisabled(
                                                        currentLine,
                                                        field,
                                                        disabled,
                                                    ),
                                                )}
                                                disabled={lineFieldDisabled(
                                                    currentLine,
                                                    field,
                                                    disabled,
                                                )}
                                                onChange={(event) =>
                                                    updateLine(
                                                        activeIndex,
                                                        field,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </div>
                                ))}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                adding
                                    ? closeEditor(false)
                                    : setEditorOpen(false)
                            }
                        >
                            {adding ? 'Cancel' : 'Close'}
                        </Button>
                        {!disabled && (
                            <Button
                                type="button"
                                onClick={() => closeEditor(true)}
                            >
                                Done
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function lineSingularLabel(title: string): string {
    if (title === 'Work Activities') return 'work activity';
    if (title === 'Material usage') return 'material';
    if (title === 'Delay details') return 'delay';
    if (title === 'Equipment and fuel') return 'equipment record';

    return title.toLowerCase().replace(/s$/, '');
}

function lineTableSummary(
    title: string,
    line: Line,
    stores: InventoryStoreOption[],
) {
    const text = (
        value: string | null | undefined,
        fallback = 'Not recorded',
    ) => (value && value.trim() !== '' ? value : fallback);
    const quantity = (
        value: string | null | undefined,
        unit?: string | null,
    ) => (value ? `${formatNumber(value)}${unit ? ` ${unit}` : ''}` : '—');

    if (title === 'Work Activities') {
        return {
            primary: text(line.description, 'Work activity'),
            secondary: line.boq_item_number
                ? `Item ${line.boq_item_number}`
                : null,
            quantity: quantity(line.quantity, line.unit),
            details:
                [
                    line.chainage_from && `From ${line.chainage_from}`,
                    line.chainage_to && `to ${line.chainage_to}`,
                    line.side,
                ]
                    .filter(Boolean)
                    .join(' ') || '—',
        };
    }
    if (title === 'Labour') {
        return {
            primary: text(line.trade_or_role, 'Labour'),
            secondary:
                line.subcontractor_name ||
                line.labour_source?.replaceAll('_', ' ') ||
                null,
            quantity: line.headcount
                ? `${formatNumber(line.headcount)} people`
                : '—',
            details: line.person_hours
                ? `${formatNumber(line.person_hours)} person-hours`
                : line.hours
                  ? `${formatNumber(line.hours)} hours each`
                  : '—',
        };
    }
    if (title === 'Equipment and fuel') {
        return {
            primary: text(line.equipment_name, 'Equipment'),
            secondary: line.equipment_identifier || null,
            quantity: line.working_hours
                ? `${formatNumber(line.working_hours)} working hours`
                : '—',
            details:
                [
                    line.idle_hours &&
                        `${formatNumber(line.idle_hours)} idle hours`,
                    line.fuel_quantity &&
                        `${formatNumber(line.fuel_quantity)} ${line.fuel_type ?? ''}`,
                ]
                    .filter(Boolean)
                    .join(' / ') || text(line.status, '—'),
        };
    }
    if (title === 'Material usage') {
        const store = stores.find(
            (option) => option.id === line.inventory_store_id,
        );
        return {
            primary: text(line.material_name, 'Material'),
            secondary:
                line.material_source === 'external'
                    ? 'Delivered directly outside inventory'
                    : (store?.name ?? 'Site stock'),
            quantity: quantity(line.quantity, line.unit),
            details: line.external_material_reason || line.notes || '—',
        };
    }

    return {
        primary: text(line.delay_type, 'Delay'),
        secondary: line.description || null,
        quantity: line.hours_lost
            ? `${formatNumber(line.hours_lost)} hours lost`
            : '—',
        details: '—',
    };
}

function lineFieldOptions(field: string, line: Line, units: string[]) {
    const values =
        field === 'unit' ? units : (controlledLineOptions[field] ?? []);
    const current = String(line[field] ?? '');
    const options =
        current && !values.includes(current) ? [current, ...values] : values;

    return options.map((value) => ({ value, label: value }));
}

function lineFieldVisible(
    field: string,
    line: Line,
    section: string,
    inventoryItems: InventoryItemOption[],
    inventoryStores: InventoryStoreOption[],
): boolean {
    if (section !== 'Material usage') return true;

    const source = line.material_source ?? 'site_store';
    if (field === 'external_material_reason') return source === 'external';
    if (field === 'inventory_store_id')
        return source === 'site_store' && inventoryStores.length > 1;
    if (['inventory_item_id', 'unit_of_measure_id'].includes(field))
        return source === 'site_store';
    if (['material_name', 'unit'].includes(field)) return source === 'external';
    if (field === 'inventory_batch_id') {
        return (
            source === 'site_store' &&
            inventoryItems.find((item) => item.id === line.inventory_item_id)
                ?.tracking_type === 'batch'
        );
    }

    return true;
}
function lineFieldDisabled(
    line: Line,
    field: string,
    disabled: boolean,
): boolean {
    return (
        disabled ||
        readOnlyLineFields.has(field) ||
        (Boolean(line.project_activity_id) &&
            activitySnapshotFields.has(field)) ||
        (Boolean(line.equipment_id) && equipmentSnapshotFields.has(field)) ||
        (Boolean(line.inventory_item_id) && materialSnapshotFields.has(field))
    );
}

function lineValue(line: Line, field: string, disabled: boolean): string {
    if (field === 'person_hours') {
        const headcount = Number(line.headcount ?? 0);
        const hours = Number(line.hours ?? 0);

        return headcount > 0 && hours > 0
            ? formatNumber(headcount * hours)
            : '';
    }

    const value = line[field];

    if (disabled && numericLineFields.has(field)) {
        return value ? formatNumber(value) : '';
    }

    return String(value ?? '');
}

function lineFieldLabel(field: string, section: string): string {
    if (field === 'project_activity_id') return 'Work activity';
    if (field === 'material_source') return 'How was it supplied?';
    if (field === 'inventory_item_id') return 'Material item';
    if (field === 'inventory_store_id') return 'Source site store';
    if (field === 'inventory_batch_id') return 'Batch';
    if (field === 'unit_of_measure_id') return 'Unit';
    if (field === 'external_material_reason')
        return 'Why this is outside inventory';
    if (field === 'labour_source') return 'Labour source';
    if (field === 'subcontractor_id') return 'Subcontractor';
    if (field === 'hours') return 'Hours per worker';
    if (field === 'person_hours') return 'Total person-hours';
    if (field === 'rate_amount' && section === 'Labour') {
        return 'Rate per person-hour';
    }

    return field.replaceAll('_', ' ');
}

function lineFieldRequired(
    field: string,
    section: string,
    line: Line,
): boolean {
    if (field === 'description') {
        return section === 'Work Activities' || section === 'Delay details';
    }

    if (field === 'subcontractor_id') {
        return line.labour_source === 'subcontractor';
    }

    if (section === 'Material usage') {
        if (field === 'external_material_reason')
            return line.material_source === 'external';

        return [
            'material_source',
            'inventory_item_id',
            'inventory_store_id',
            'inventory_batch_id',
            'unit_of_measure_id',
            'material_name',
            'quantity',
            'unit',
        ].includes(field);
    }

    return ['trade_or_role', 'equipment_name', 'material_name'].includes(field);
}

function cleanLines(lines: Line[]): Line[] {
    const defaults = new Set([
        'currency_code',
        'status',
        'fuel_transaction_type',
        'fleet_posting_status',
        'labour_source',
        'material_source',
    ]);

    return lines.filter((line) =>
        Object.entries(line).some(
            ([key, value]) =>
                !defaults.has(key) && value !== null && value !== '',
        ),
    );
}

function emptyWorkLine(): Line {
    return {
        project_activity_id: '',
        boq_item_number: '',
        description: '',
        chainage_from: '',
        chainage_to: '',
        side: '',
        quantity: '',
        unit: '',
        rate_amount: '',
        currency_code: 'UGX',
    };
}

function emptyLabourLine(): Line {
    return {
        labour_source: 'internal',
        subcontractor_id: '',
        trade_or_role: '',
        subcontractor_name: '',
        headcount: '',
        hours: '',
        rate_amount: '',
        currency_code: 'UGX',
    };
}

function emptyEquipmentLine(): Line {
    return {
        equipment_id: '',
        equipment_name: '',
        equipment_identifier: '',
        status: 'working',
        working_hours: '',
        idle_hours: '',
        opening_meter_reading: '',
        closing_meter_reading: '',
        fuel_type: '',
        fuel_quantity: '',
        fuel_transaction_type: 'consumption',
        evidence_note: '',
        fleet_posting_status: 'unposted',
        rate_amount: '',
        currency_code: 'UGX',
    };
}

function emptyMaterialLine(): Line {
    return {
        material_source: 'site_store',
        inventory_item_id: '',
        inventory_store_id: '',
        inventory_batch_id: '',
        unit_of_measure_id: '',
        material_name: '',
        quantity: '',
        unit: '',
        external_material_reason: '',
        notes: '',
        rate_amount: '',
        currency_code: 'UGX',
    };
}

function emptyDelayLine(): Line {
    return {
        delay_type: '',
        description: '',
        hours_lost: '',
    };
}
