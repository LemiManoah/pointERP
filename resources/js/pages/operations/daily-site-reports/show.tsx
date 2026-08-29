import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, RotateCcw, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
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
};
type MaterialReconciliation = {
    id: string;
    material_name: string;
    inventory_item_id: string | null;
    inventory_store_id: string | null;
    status: string;
    reported_quantity: string | null;
    reported_unit: string | null;
    stock_quantity: string | null;
    stock_unit: string | null;
    allocated_quantity: string;
    outstanding_quantity: string;
    external_reason: string | null;
    allocations: Array<{
        id: string;
        type: string;
        quantity: string;
        reason: string;
    }>;
    candidate_issues: Array<{
        id: string;
        quantity: string;
        store_name: string;
        posted_at: string;
        posted_by: string;
    }>;
    can_manage: boolean;
    can_direct_issue: boolean;
    can_mark_external: boolean;
};

const numericLineFields = new Set([
    'quantity',
    'rate_amount',
    'amount',
    'headcount',
    'hours',
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
    material_type: ['used', 'delivered', 'wasted', 'rejected'],
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
    cost_lines: Line[];
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
        viewMaterialReconciliation: boolean;
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
    materialReconciliations: MaterialReconciliation[];
    units: string[];
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
    cost_lines: Line[];
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
    materialReconciliations,
    units,
}: Props) {
    const confirm = useConfirmDialog();
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
        work_lines:
            report.work_lines.length > 0
                ? report.work_lines
                : [emptyWorkLine()],
        labour_lines:
            report.labour_lines.length > 0
                ? report.labour_lines
                : [emptyLabourLine()],
        equipment_lines:
            report.equipment_lines.length > 0
                ? report.equipment_lines
                : [emptyEquipmentLine()],
        material_lines:
            report.material_lines.length > 0
                ? report.material_lines
                : [emptyMaterialLine()],
        cost_lines:
            report.cost_lines.length > 0
                ? report.cost_lines
                : [emptyCostLine()],
        delay_lines:
            report.delay_lines.length > 0
                ? report.delay_lines
                : [emptyDelayLine()],
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
            cost_lines: cleanLines(data.cost_lines),
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
                        {can.submit && <SubmitReportButton report={report} />}
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
                    <Metric label="Output" value={report.output_value} />
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
                                Work quantities have been entered without linked
                                evidence. Upload evidence or submit with an
                                override reason.
                            </CardContent>
                        </Card>
                    )}

                <Card>
                    <CardHeader>
                        <CardTitle>Workflow trail</CardTitle>
                        <CardDescription>
                            Submit, return, approval and correction events for
                            this report.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {[...corrections, ...reviews].length === 0 && (
                            <div className="text-sm text-muted-foreground">
                                No workflow events recorded yet.
                            </div>
                        )}
                        {reviews.map((review) => (
                            <div
                                key={review.id}
                                className="rounded-md border px-3 py-2 text-sm"
                            >
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium">
                                        {review.action}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {review.created_at}
                                    </span>
                                </div>
                                <div className="mt-1 text-muted-foreground">
                                    {review.reviewed_by ?? 'Unknown user'}
                                </div>
                                {review.remarks && (
                                    <div className="mt-2">{review.remarks}</div>
                                )}
                            </div>
                        ))}
                        {corrections.map((correction) => (
                            <div
                                key={correction.id}
                                className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-950"
                            >
                                <div className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium">
                                        Correction {correction.status}
                                    </span>
                                    <span>{correction.created_at}</span>
                                </div>
                                <div className="mt-1">
                                    {correction.requested_by ?? 'Unknown user'}
                                </div>
                                <div className="mt-2">{correction.reason}</div>
                                {correction.new_values && (
                                    <div className="mt-2 grid gap-1 rounded border border-blue-200 bg-white/60 p-2">
                                        {Object.entries(
                                            correction.new_values,
                                        ).map(([field, value]) =>
                                            field === 'equipment_adjustments' &&
                                            Array.isArray(value) ? (
                                                <CorrectionAdjustmentSummary
                                                    key={field}
                                                    adjustments={value}
                                                />
                                            ) : (
                                                <div
                                                    key={field}
                                                    className="flex justify-between gap-4"
                                                >
                                                    <span className="text-blue-700">
                                                        {field.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </span>
                                                    <span className="text-right font-medium">
                                                        {displayUnknown(value)}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                                {correction.can_manage && (
                                    <CorrectionActions
                                        reportId={report.id}
                                        correction={correction}
                                    />
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

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

                <form onSubmit={submit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily summary</CardTitle>
                            <CardDescription>
                                Weather, site conditions, work, issues and
                                compliance notes.
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
                                        form.setData('site_conditions', value)
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
                                        form.setData('delay_summary', value)
                                    }
                                />
                                <TextAreaField
                                    label="Visitors"
                                    value={form.data.visitor_summary}
                                    disabled={!can.update}
                                    onChange={(value) =>
                                        form.setData('visitor_summary', value)
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
                                        form.setData('environment_notes', value)
                                    }
                                />
                                <TextAreaField
                                    label="Social"
                                    value={form.data.social_notes}
                                    disabled={!can.update}
                                    onChange={(value) =>
                                        form.setData('social_notes', value)
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <LineCard
                        title="Work quantities"
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
                                activity.project_id === report.project_id &&
                                (activity.site_id === null ||
                                    activity.site_id === report.site_id),
                        )}
                        units={units}
                        onAdd={() =>
                            form.setData('work_lines', [
                                ...form.data.work_lines,
                                emptyWorkLine(),
                            ])
                        }
                        onChange={(lines) => form.setData('work_lines', lines)}
                    />
                    <LineCard
                        title="Labour"
                        disabled={!can.update}
                        lines={form.data.labour_lines}
                        fields={[
                            'trade_or_role',
                            'subcontractor_name',
                            'headcount',
                            'hours',
                            ...(canViewCosts ? ['rate_amount'] : []),
                        ]}
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
                                equipment.branch_id === report.branch_id &&
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
                    <LineCard
                        title="Materials"
                        disabled={!can.update}
                        lines={form.data.material_lines}
                        fields={[
                            'inventory_item_id',
                            'inventory_store_id',
                            'unit_of_measure_id',
                            'material_name',
                            'material_type',
                            'quantity',
                            'unit',
                            'delivery_reference',
                            ...(canViewCosts ? ['rate_amount'] : []),
                        ]}
                        units={units}
                        inventoryItems={inventoryItems}
                        inventoryStores={inventoryStores.filter(
                            (store) => store.branch_id === report.branch_id,
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
                    {can.viewMaterialReconciliation && (
                        <MaterialReconciliationCard
                            lines={materialReconciliations}
                            stores={inventoryStores.filter(
                                (store) => store.branch_id === report.branch_id,
                            )}
                            items={inventoryItems}
                        />
                    )}
                    {canViewCosts && (
                        <LineCard
                            title="Other costs"
                            disabled={!can.update}
                            lines={form.data.cost_lines}
                            fields={[
                                'category',
                                'description',
                                'quantity',
                                'unit',
                                'rate_amount',
                            ]}
                            units={units}
                            onAdd={() =>
                                form.setData('cost_lines', [
                                    ...form.data.cost_lines,
                                    emptyCostLine(),
                                ])
                            }
                            onChange={(lines) =>
                                form.setData('cost_lines', lines)
                            }
                        />
                    )}
                    <LineCard
                        title="Delay details"
                        disabled={!can.update}
                        lines={form.data.delay_lines}
                        fields={['delay_type', 'description', 'hours_lost']}
                        onAdd={() =>
                            form.setData('delay_lines', [
                                ...form.data.delay_lines,
                                emptyDelayLine(),
                            ])
                        }
                        onChange={(lines) => form.setData('delay_lines', lines)}
                    />

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
        <div className="mt-3 flex flex-wrap justify-end gap-2">
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

function SubmitReportButton({ report }: { report: Report }) {
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
            <Button variant="outline" onClick={submit}>
                <Send />
                Submit
            </Button>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Send />
                    Submit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Submit without evidence?</DialogTitle>
                    <DialogDescription>
                        This report has work quantities but no linked evidence.
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
        <div className="grid gap-2 border-t border-blue-200 pt-2">
            <span className="font-medium text-blue-700">
                Fleet ledger adjustments
            </span>
            {adjustments.filter(isRecord).map((adjustment, index) => (
                <div
                    key={`${displayUnknown(adjustment.line_id)}-${index}`}
                    className="grid gap-1 rounded border border-blue-100 bg-white px-2 py-1"
                >
                    <div className="font-medium">
                        {displayUnknown(
                            adjustment.equipment_name ??
                                adjustment.line_id ??
                                'Equipment',
                        )}
                    </div>
                    <div className="flex flex-wrap gap-x-4 text-blue-800">
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

function MaterialReconciliationCard({
    lines,
    stores,
    items,
}: {
    lines: MaterialReconciliation[];
    stores: InventoryStoreOption[];
    items: InventoryItemOption[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Material reconciliation</CardTitle>
                <CardDescription>
                    Match approved material usage to stock issues. Allocating an
                    existing issue does not deduct stock again.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {lines.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        This report has no material lines to reconcile.
                    </p>
                ) : (
                    lines.map((line) => (
                        <div
                            key={line.id}
                            className="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,.7fr))_auto] lg:items-center"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {line.material_name}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Reported{' '}
                                    {formatNumber(line.reported_quantity ?? 0)}{' '}
                                    {line.reported_unit ?? ''}
                                </div>
                            </div>
                            <QuantitySummary
                                label="Stock quantity"
                                value={line.stock_quantity}
                                unit={line.stock_unit}
                            />
                            <QuantitySummary
                                label="Allocated"
                                value={line.allocated_quantity}
                                unit={line.stock_unit}
                            />
                            <QuantitySummary
                                label="Outstanding"
                                value={line.outstanding_quantity}
                                unit={line.stock_unit}
                            />
                            <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                                <Badge variant="outline">
                                    {line.status.replaceAll('_', ' ')}
                                </Badge>
                                {Number(line.outstanding_quantity) > 0 &&
                                    line.inventory_item_id !== null &&
                                    line.can_manage && (
                                        <AllocationDialog line={line} />
                                    )}
                                {Number(line.outstanding_quantity) > 0 &&
                                    line.inventory_item_id !== null &&
                                    line.can_direct_issue && (
                                        <DirectIssueDialog
                                            line={line}
                                            stores={stores}
                                            items={items}
                                        />
                                    )}
                                {Number(line.outstanding_quantity) > 0 &&
                                    line.can_mark_external &&
                                    line.allocations.length === 0 && (
                                        <ExternalMaterialDialog line={line} />
                                    )}
                            </div>
                            {line.external_reason && (
                                <p className="text-sm text-muted-foreground lg:col-span-5">
                                    External material: {line.external_reason}
                                </p>
                            )}
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function QuantitySummary({
    label,
    value,
    unit,
}: {
    label: string;
    value: string | null;
    unit: string | null;
}) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium tabular-nums">
                {value === null ? 'Not linked' : formatNumber(value)}{' '}
                {unit ?? ''}
            </div>
        </div>
    );
}

function AllocationDialog({ line }: { line: MaterialReconciliation }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_stock_movement_id: '',
        quantity: line.outstanding_quantity,
        reason: '',
    });

    function submit() {
        form.post(`/dsr-material-lines/${line.id}/allocate`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    Match issue
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Match an existing stock issue</DialogTitle>
                    <DialogDescription>
                        Use stock already issued to this project and site. This
                        creates a link only and does not reduce stock again.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Stock issue</Label>
                        <SearchableSelect
                            value={form.data.inventory_stock_movement_id}
                            onValueChange={(value) =>
                                form.setData(
                                    'inventory_stock_movement_id',
                                    value,
                                )
                            }
                            options={line.candidate_issues.map((issue) => ({
                                value: issue.id,
                                label: `${formatNumber(issue.quantity)} ${line.stock_unit ?? ''} from ${issue.store_name}`,
                                description: `${issue.posted_at} by ${issue.posted_by}`,
                            }))}
                            placeholder="Select an existing issue"
                            emptyMessage="No matching stock issues were found."
                        />
                        <InputError
                            message={form.errors.inventory_stock_movement_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Quantity to match</Label>
                        <Input
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                        />
                        <InputError message={form.errors.quantity} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Reason</Label>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Explain why this issue belongs to the report line"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Match issue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DirectIssueDialog({
    line,
    stores,
    items,
}: {
    line: MaterialReconciliation;
    stores: InventoryStoreOption[];
    items: InventoryItemOption[];
}) {
    const [open, setOpen] = useState(false);
    const item = items.find((option) => option.id === line.inventory_item_id);
    const allowedStores = stores.filter(
        (store) => item?.store_ids.includes(store.id) ?? false,
    );
    const defaultStore = allowedStores.some(
        (store) => store.id === line.inventory_store_id,
    )
        ? (line.inventory_store_id ?? '')
        : (allowedStores[0]?.id ?? '');
    const form = useForm({
        inventory_store_id: defaultStore,
        inventory_batch_id: '',
        quantity: line.outstanding_quantity,
        reason: '',
    });
    const batches =
        item?.batches.filter(
            (batch) =>
                batch.inventory_store_id === null ||
                batch.inventory_store_id === form.data.inventory_store_id,
        ) ?? [];

    function submit() {
        form.post(`/dsr-material-lines/${line.id}/direct-issue`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm">
                    Issue balance
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Issue outstanding material</DialogTitle>
                    <DialogDescription>
                        This posts a real stock issue for the unmatched
                        quantity.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>Store</Label>
                        <SearchableSelect
                            value={form.data.inventory_store_id}
                            onValueChange={(value) =>
                                form.setData({
                                    ...form.data,
                                    inventory_store_id: value,
                                    inventory_batch_id: '',
                                })
                            }
                            options={allowedStores.map((store) => ({
                                value: store.id,
                                label: store.name,
                                description: store.branch_name,
                            }))}
                            placeholder="Select store"
                        />
                        <InputError message={form.errors.inventory_store_id} />
                    </div>
                    {item?.tracking_type === 'batch' && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>Batch</Label>
                            <SearchableSelect
                                value={form.data.inventory_batch_id}
                                onValueChange={(value) =>
                                    form.setData('inventory_batch_id', value)
                                }
                                options={batches.map((batch) => ({
                                    value: batch.id,
                                    label: batch.batch_number,
                                }))}
                                placeholder="Select batch"
                            />
                            <InputError
                                message={form.errors.inventory_batch_id}
                            />
                        </div>
                    )}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>Quantity ({line.stock_unit})</Label>
                        <Input
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                        />
                        <InputError message={form.errors.quantity} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>Reason</Label>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Explain the direct issue"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Issue material
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ExternalMaterialDialog({ line }: { line: MaterialReconciliation }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit() {
        form.post(`/dsr-material-lines/${line.id}/external`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    External
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Mark as external material</DialogTitle>
                    <DialogDescription>
                        Use this only when the material did not come from
                        company stock. No stock movement will be created.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label>Reason and source</Label>
                    <Textarea
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        placeholder="For example, supplied directly by the subcontractor"
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
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Mark external
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
}) {
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

    function selectInventoryItem(index: number, itemId: string) {
        const item = inventoryItems.find((option) => option.id === itemId);
        const storeId = item?.store_ids.includes(
            String(lines[index]?.inventory_store_id ?? ''),
        )
            ? String(lines[index]?.inventory_store_id ?? '')
            : (inventoryStores.find((store) =>
                  item?.store_ids.includes(store.id),
              )?.id ?? '');
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index
                    ? {
                          ...line,
                          inventory_item_id: itemId,
                          inventory_store_id: storeId,
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

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {!disabled && (
                    <Button type="button" variant="outline" onClick={onAdd}>
                        Add line
                    </Button>
                )}
            </CardHeader>
            <CardContent className="grid gap-4">
                {lines.map((line, index) => (
                    <div
                        key={index}
                        className="grid gap-3 rounded-md border p-3 md:grid-cols-4"
                    >
                        {fields.map((field) => (
                            <div key={field} className="grid gap-2">
                                <Label>
                                    {field === 'project_activity_id'
                                        ? 'Work item'
                                        : field.replaceAll('_', ' ')}
                                </Label>
                                {field === 'inventory_item_id' ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            selectInventoryItem(index, value)
                                        }
                                        options={inventoryItems.map((item) => ({
                                            value: item.id,
                                            label: item.name,
                                            description: item.code,
                                        }))}
                                        placeholder="Select inventory item"
                                        searchPlaceholder="Search inventory..."
                                        disabled={disabled}
                                    />
                                ) : field === 'inventory_store_id' ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            updateLine(index, field, value)
                                        }
                                        options={inventoryStores
                                            .filter((store) => {
                                                const item =
                                                    inventoryItems.find(
                                                        (option) =>
                                                            option.id ===
                                                            line.inventory_item_id,
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
                                                description: store.branch_name,
                                            }))}
                                        placeholder="Select source store"
                                        disabled={
                                            disabled || !line.inventory_item_id
                                        }
                                    />
                                ) : field === 'unit_of_measure_id' ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            selectInventoryUnit(index, value)
                                        }
                                        options={(
                                            inventoryItems.find(
                                                (item) =>
                                                    item.id ===
                                                    line.inventory_item_id,
                                            )?.units ?? []
                                        ).map((unit) => ({
                                            value: unit.id,
                                            label: unit.name,
                                            description:
                                                unit.symbol ?? undefined,
                                        }))}
                                        placeholder="Select unit"
                                        disabled={
                                            disabled || !line.inventory_item_id
                                        }
                                    />
                                ) : field === 'project_activity_id' ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            selectActivity(index, value)
                                        }
                                        options={activities.map((activity) => ({
                                            value: activity.id,
                                            label: activity.label,
                                            description: [
                                                activity.unit,
                                                activity.boq_item_number,
                                            ]
                                                .filter(Boolean)
                                                .join(' / '),
                                        }))}
                                        placeholder="Select work item"
                                        searchPlaceholder="Search work items..."
                                        emptyMessage="No work item is available for this site."
                                        disabled={disabled}
                                    />
                                ) : field === 'equipment_id' ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            selectEquipment(index, value)
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
                                ) : (field === 'unit' && units.length > 0) ||
                                  controlledLineOptions[field] ? (
                                    <SearchableSelect
                                        value={String(line[field] ?? '')}
                                        onValueChange={(value) =>
                                            updateLine(index, field, value)
                                        }
                                        options={lineFieldOptions(
                                            field,
                                            line,
                                            units,
                                        )}
                                        placeholder={`Select ${field.replaceAll('_', ' ')}`}
                                        searchPlaceholder={`Search ${field.replaceAll('_', ' ')}...`}
                                        disabled={lineFieldDisabled(
                                            line,
                                            field,
                                            disabled,
                                        )}
                                    />
                                ) : (
                                    <Input
                                        value={lineValue(
                                            line,
                                            field,
                                            lineFieldDisabled(
                                                line,
                                                field,
                                                disabled,
                                            ),
                                        )}
                                        disabled={lineFieldDisabled(
                                            line,
                                            field,
                                            disabled,
                                        )}
                                        onChange={(event) =>
                                            updateLine(
                                                index,
                                                field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function lineFieldOptions(field: string, line: Line, units: string[]) {
    const values =
        field === 'unit' ? units : (controlledLineOptions[field] ?? []);
    const current = String(line[field] ?? '');
    const options =
        current && !values.includes(current) ? [current, ...values] : values;

    return options.map((value) => ({ value, label: value }));
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
    const value = line[field];

    if (disabled && numericLineFields.has(field)) {
        return value ? formatNumber(value) : '';
    }

    return String(value ?? '');
}

function cleanLines(lines: Line[]): Line[] {
    return lines.filter((line) =>
        Object.values(line).some((value) => value !== null && value !== ''),
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
        inventory_item_id: '',
        inventory_store_id: '',
        unit_of_measure_id: '',
        material_name: '',
        material_type: '',
        quantity: '',
        unit: '',
        rate_amount: '',
        delivery_reference: '',
        currency_code: 'UGX',
    };
}

function emptyCostLine(): Line {
    return {
        category: '',
        description: '',
        quantity: '',
        unit: '',
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
