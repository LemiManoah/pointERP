import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, RotateCcw, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Line = Record<string, string | null>;

type Report = {
    id: string;
    reference: string;
    project_name: string;
    site_name: string;
    site_id: string;
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
};

type Props = {
    report: Report;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        return: boolean;
    };
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

export default function DailySiteReportShow({ report, can }: Props) {
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
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/daily-site-reports/${report.id}/submit`,
                                    )
                                }
                            >
                                <Send />
                                Submit
                            </Button>
                        )}
                        {can.return && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const reason = window.prompt(
                                        'Why is this DSR being returned?',
                                    );

                                    if (reason) {
                                        router.post(
                                            `/daily-site-reports/${report.id}/return`,
                                            { reason },
                                        );
                                    }
                                }}
                            >
                                <RotateCcw />
                                Return
                            </Button>
                        )}
                        {can.approve && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/daily-site-reports/${report.id}/approve`,
                                    )
                                }
                            >
                                <CheckCircle2 />
                                Approve
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="Output" value={report.output_value} />
                    <Metric label="Input cost" value={report.input_cost} />
                    <Metric label="Profit/loss" value={report.profit_loss} />
                </div>

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
                        description="BOQ/activity progress by chainage and side."
                        disabled={!can.update}
                        lines={form.data.work_lines}
                        fields={[
                            'boq_item_number',
                            'description',
                            'chainage_from',
                            'chainage_to',
                            'side',
                            'quantity',
                            'unit',
                            'rate_amount',
                        ]}
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
                        description="Trades, subcontractors, headcount and hours."
                        disabled={!can.update}
                        lines={form.data.labour_lines}
                        fields={[
                            'trade_or_role',
                            'subcontractor_name',
                            'headcount',
                            'hours',
                            'rate_amount',
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
                        description="Plant identifiers, hours and fuel usage."
                        disabled={!can.update}
                        lines={form.data.equipment_lines}
                        fields={[
                            'equipment_name',
                            'equipment_identifier',
                            'status',
                            'working_hours',
                            'idle_hours',
                            'fuel_type',
                            'fuel_quantity',
                            'rate_amount',
                        ]}
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
                        description="Materials used or delivered for the day."
                        disabled={!can.update}
                        lines={form.data.material_lines}
                        fields={[
                            'material_name',
                            'material_type',
                            'quantity',
                            'unit',
                            'rate_amount',
                            'delivery_reference',
                        ]}
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
                    <LineCard
                        title="Other costs"
                        description="Petty cash, allowances, overheads and mobilisation."
                        disabled={!can.update}
                        lines={form.data.cost_lines}
                        fields={[
                            'category',
                            'description',
                            'quantity',
                            'unit',
                            'rate_amount',
                        ]}
                        onAdd={() =>
                            form.setData('cost_lines', [
                                ...form.data.cost_lines,
                                emptyCostLine(),
                            ])
                        }
                        onChange={(lines) => form.setData('cost_lines', lines)}
                    />
                    <LineCard
                        title="Delay details"
                        description="Causes, time lost and action taken."
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
                </form>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | null }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-xl">{value ?? '0.0000'}</CardTitle>
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

function LineCard({
    title,
    description,
    disabled,
    lines,
    fields,
    onAdd,
    onChange,
}: {
    title: string;
    description: string;
    disabled: boolean;
    lines: Line[];
    fields: string[];
    onAdd: () => void;
    onChange: (lines: Line[]) => void;
}) {
    function updateLine(index: number, field: string, value: string) {
        onChange(
            lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, [field]: value } : line,
            ),
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
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
                                <Label>{field.replaceAll('_', ' ')}</Label>
                                <Input
                                    value={String(line[field] ?? '')}
                                    disabled={disabled}
                                    onChange={(event) =>
                                        updateLine(
                                            index,
                                            field,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function emptyWorkLine(): Line {
    return {
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
        equipment_name: '',
        equipment_identifier: '',
        status: 'working',
        working_hours: '',
        idle_hours: '',
        fuel_type: '',
        fuel_quantity: '',
        rate_amount: '',
        currency_code: 'UGX',
    };
}

function emptyMaterialLine(): Line {
    return {
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
