import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { show as showReport } from '@/actions/App/Http/Controllers/Operations/DailySiteReportController';
import {
    show,
    storeActivity,
} from '@/actions/App/Http/Controllers/Operations/ProjectBoqController';
import { show as showProject } from '@/actions/App/Http/Controllers/Operations/ProjectController';
import {
    create as createEstimate,
    show as showEstimate,
} from '@/actions/App/Http/Controllers/Operations/ProjectEstimateController';
import { index as importBoq } from '@/actions/App/Http/Controllers/Operations/ProjectEstimateImportController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';

type Item = {
    id: string;
    name: string;
    description: string | null;
    site_id: string | null;
    item_type: string;
    unit: string;
    source_document: string | null;
    source_sheet: string | null;
    source_row: number | null;
};
type Row = {
    id: string;
    boq_item_id: string;
    boq_reference: string | null;
    bill: string | null;
    section: string | null;
    element: string | null;
    item_type: string;
    name: string;
    unit: string;
    planned_quantity: string;
    approved_progress: string;
    remaining_quantity: string;
    overrun_quantity: string;
    completion_percent: string;
    baseline_revenue: string | null;
    earned_output: string | null;
};
type Props = {
    project: { id: string; name: string; reference: string };
    performance: null | {
        baseline: { version_number: number; currency_code: string };
        pricing: null | {
            status: string;
            priced_items: number;
            total_items: number;
        };
        work_items: Row[];
    };
    items: Item[];
    activities: {
        id: string;
        boq_item_id: string;
        name: string;
        unit: string;
        progress_method: string;
        approved_quantity: string;
        status: string;
    }[];
    measurements: {
        id: string;
        boq_item_id: string;
        activity_id: string;
        quantity: string;
        unit: string;
        date: string;
        description: string;
        report_id: string | null;
        legacy: boolean;
    }[];
    reports: { id: string; reference: string; date: string; status: string }[];
    revisions: {
        id: string;
        title: string;
        version_number: number;
        status: string;
        is_baseline: boolean;
    }[];
    sites: { id: string; name: string }[];
    can: {
        createActivity: boolean;
        createEstimate: boolean;
        viewCosts: boolean;
    };
};
const number = (value: string | number) =>
    Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });

export default function Boq({
    project,
    performance,
    items,
    activities,
    measurements,
    reports,
    revisions,
    sites,
    can,
}: Props) {
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<Item | null>(null);
    const form = useForm({
        project_id: project.id,
        boq_item_id: '',
        name: '',
        site_id: '',
        unit: '',
        progress_method: 'measured',
        status: 'active',
    });
    const rows = (performance?.work_items ?? []).filter((row) =>
        [row.name, row.boq_reference, row.bill, row.section, row.element]
            .join(' ')
            .toLowerCase()
            .includes(search.toLowerCase()),
    );
    function addActivity(item: Item) {
        setSelected(item);
        form.setData({
            project_id: project.id,
            boq_item_id: item.id,
            name: '',
            site_id: item.site_id ?? '',
            unit: item.unit,
            progress_method: 'measured',
            status: 'active',
        });
        form.clearErrors();
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: project.reference, href: showProject.url(project.id) },
                { title: 'BoQ and progress', href: show.url(project.id) },
            ]}
        >
            <Head title={`BoQ · ${project.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {project.name}
                        </p>
                        <h1 className="text-2xl font-semibold">
                            BoQ and progress
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            The approved schedule defines the scope. Activities
                            organise delivery. Only approved measured output
                            reduces the balance.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={showProject(project.id)}>
                                Project overview
                            </Link>
                        </Button>
                        {can.createEstimate && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={importBoq(project.id)}>
                                        Import Excel BoQ
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href={createEstimate(project.id)}>
                                        {performance
                                            ? 'Create revision'
                                            : 'Create BoQ'}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
                {project.reference === 'BOQ-DEMO-COE' && (
                    <Card className="border-blue-300 bg-blue-50/50 dark:bg-blue-950/20">
                        <CardHeader>
                            <CardTitle>
                                Walkthrough: Centre of Excellence
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>
                                This is a demonstration, not the live contract.
                                The excavation quantity and rate come from the
                                client’s workbook; site measurements are
                                fictional.
                            </p>
                            <p>
                                <strong>Start with item B:</strong> 1,648 m³
                                planned. Two approved reports contribute 100 +
                                150 = 250 m³, leaving 1,398 m³. Setting-out
                                supports the work and adds no excavation
                                quantity. A draft report is waiting and
                                contributes nothing yet.
                            </p>
                            <p>
                                Expand the item to see its activities and
                                evidence. Use “Add activity” for another area or
                                task. Use “Create revision” to change the plan
                                while preserving approved progress.
                            </p>
                        </CardContent>
                    </Card>
                )}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Current baseline
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xl font-semibold">
                            {performance
                                ? `Revision ${performance.baseline.version_number}`
                                : 'Awaiting approval'}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Schedule items
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xl font-semibold">
                            {items.length}
                            <p className="mt-1 text-xs font-normal text-muted-foreground">
                                Different measurement units are never added
                                together.
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Pricing
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-xl font-semibold">
                            {can.viewCosts
                                ? (performance?.pricing?.status.replaceAll(
                                      '_',
                                      ' ',
                                  ) ?? 'No baseline')
                                : 'Restricted'}
                            <p className="mt-1 text-xs font-normal text-muted-foreground">
                                A blank rate remains unpriced. Allowances have
                                no physical progress.
                            </p>
                        </CardContent>
                    </Card>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">
                        Approved BoQ schedule
                    </h2>
                    <Input
                        className="max-w-sm"
                        aria-label="Search BoQ"
                        placeholder="Search description, reference or bill…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                {!performance && (
                    <Card>
                        <CardContent className="p-6">
                            Import or enter the schedule, review it, then
                            approve the draft to establish the baseline.
                        </CardContent>
                    </Card>
                )}
                {performance && rows.length === 0 && (
                    <p className="text-muted-foreground">
                        No matching BoQ items.
                    </p>
                )}
                <div className="space-y-3">
                    {rows.map((row) => {
                        const item = items.find(
                            (item) => item.id === row.boq_item_id,
                        );
                        const children = activities.filter(
                            (activity) =>
                                activity.boq_item_id === row.boq_item_id,
                        );
                        const evidence = measurements.filter(
                            (entry) => entry.boq_item_id === row.boq_item_id,
                        );
                        const measured = row.item_type === 'measured';
                        return (
                            <details
                                key={row.id}
                                className="rounded-xl border bg-card"
                                open={search ? true : undefined}
                            >
                                <summary className="cursor-pointer p-4 marker:text-muted-foreground">
                                    <span className="ml-2 font-semibold">
                                        {row.boq_reference ?? '—'} · {row.name}
                                    </span>
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        {[row.bill, row.section, row.element]
                                            .filter(Boolean)
                                            .join(' / ')}
                                    </div>
                                    <div className="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                                        <span>
                                            Baseline{' '}
                                            <strong className="block">
                                                {number(row.planned_quantity)}{' '}
                                                {row.unit}
                                            </strong>
                                        </span>
                                        <span>
                                            Approved{' '}
                                            <strong className="block">
                                                {measured
                                                    ? `${number(row.approved_progress)} ${row.unit}`
                                                    : 'Not measured'}
                                            </strong>
                                        </span>
                                        <span>
                                            Remaining{' '}
                                            <strong className="block">
                                                {measured
                                                    ? `${number(row.remaining_quantity)} ${row.unit}`
                                                    : 'Allowance'}
                                            </strong>
                                        </span>
                                        <span>
                                            Completion{' '}
                                            <strong className="block">
                                                {measured
                                                    ? `${number(row.completion_percent)}%`
                                                    : 'Not applicable'}
                                            </strong>
                                        </span>
                                    </div>
                                    {Number(row.overrun_quantity) > 0 && (
                                        <p className="mt-2 text-sm text-amber-700">
                                            Exceeds baseline by{' '}
                                            {number(row.overrun_quantity)}{' '}
                                            {row.unit}; review the scope
                                            revision.
                                        </p>
                                    )}
                                </summary>
                                <div className="space-y-5 border-t p-4">
                                    {item?.description && (
                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                            {item.description}
                                        </p>
                                    )}
                                    {item?.source_document && (
                                        <p className="text-xs text-muted-foreground">
                                            Source: {item.source_document} ·{' '}
                                            {item.source_sheet} · row{' '}
                                            {item.source_row}
                                        </p>
                                    )}
                                    {can.viewCosts && performance && (
                                        <p className="text-sm">
                                            Baseline value:{' '}
                                            {row.baseline_revenue === null
                                                ? 'Unpriced'
                                                : `${performance.baseline.currency_code} ${number(row.baseline_revenue)}`}{' '}
                                            · Earned measured output:{' '}
                                            {row.earned_output === null
                                                ? 'Not available'
                                                : `${performance.baseline.currency_code} ${number(row.earned_output)}`}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-between gap-3">
                                        <h3 className="font-semibold">
                                            Execution activities
                                        </h3>
                                        {measured &&
                                            item &&
                                            can.createActivity && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        addActivity(item)
                                                    }
                                                >
                                                    Add activity
                                                </Button>
                                            )}
                                    </div>
                                    {children.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No physical activities for this
                                            allowance.
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {children.map((activity) => (
                                                <div
                                                    key={activity.id}
                                                    className="flex flex-wrap justify-between gap-2 rounded-lg bg-muted/40 p-3 text-sm"
                                                >
                                                    <span>
                                                        {activity.name}{' '}
                                                        {activity.status !==
                                                            'active' && (
                                                            <Badge variant="secondary">
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </span>
                                                    <span>
                                                        {activity.progress_method ===
                                                        'supporting' ? (
                                                            <Badge variant="secondary">
                                                                Supporting · no
                                                                BoQ output
                                                            </Badge>
                                                        ) : (
                                                            `Measured output · ${number(activity.approved_quantity)} ${activity.unit} approved`
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <h3 className="font-semibold">
                                        Approved measurement history
                                    </h3>
                                    {evidence.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No approved measurements yet.
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead>
                                                    <tr className="border-b">
                                                        <th className="py-2">
                                                            Date
                                                        </th>
                                                        <th>Description</th>
                                                        <th className="text-right">
                                                            Quantity
                                                        </th>
                                                        <th className="pl-4">
                                                            Evidence
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {evidence.map((entry) => (
                                                        <tr
                                                            key={entry.id}
                                                            className="border-b last:border-0"
                                                        >
                                                            <td className="py-3 pr-4 whitespace-nowrap">
                                                                {entry.date}
                                                            </td>
                                                            <td className="min-w-48 pr-4">
                                                                {
                                                                    entry.description
                                                                }
                                                            </td>
                                                            <td className="text-right whitespace-nowrap">
                                                                {number(
                                                                    entry.quantity,
                                                                )}{' '}
                                                                {entry.unit}
                                                            </td>
                                                            <td className="pl-4 whitespace-nowrap">
                                                                {entry.report_id ? (
                                                                    <Link
                                                                        className="text-primary underline"
                                                                        href={showReport(
                                                                            entry.report_id,
                                                                        )}
                                                                    >
                                                                        Open
                                                                        report
                                                                    </Link>
                                                                ) : entry.legacy ? (
                                                                    'Legacy balance · review required'
                                                                ) : (
                                                                    'Approved record'
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </details>
                        );
                    })}
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Revision history</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {revisions.map((revision) => (
                                <div
                                    key={revision.id}
                                    className="flex justify-between gap-2 text-sm"
                                >
                                    <Link
                                        className="underline"
                                        href={showEstimate(revision.id)}
                                    >
                                        V{revision.version_number} ·{' '}
                                        {revision.title}
                                    </Link>
                                    <Badge
                                        variant={
                                            revision.is_baseline
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {revision.is_baseline
                                            ? 'Baseline'
                                            : revision.status}
                                    </Badge>
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground">
                                Draft revisions do not change the approved
                                quantities or scope until approval.
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Site reports</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {reports.map((report) => (
                                <div
                                    key={report.id}
                                    className="flex justify-between gap-2 text-sm"
                                >
                                    <Link
                                        className="underline"
                                        href={showReport(report.id)}
                                    >
                                        {report.date} · {report.reference}
                                    </Link>
                                    <Badge variant="secondary">
                                        {report.status}
                                    </Badge>
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground">
                                Draft and submitted reports contribute no
                                approved progress.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add execution activity</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Linked to {selected?.name}. The BoQ quantity stays on
                        the parent item.
                    </p>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeActivity.url(project.id), {
                                preserveScroll: true,
                                onSuccess: () => setSelected(null),
                            });
                        }}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="activity-name">Activity name</Label>
                            <Input
                                id="activity-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                                maxLength={220}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="progress-method">
                                Contribution to BoQ
                            </Label>
                            <NativeSelect
                                id="progress-method"
                                value={form.data.progress_method}
                                onChange={(event) => {
                                    form.setData(
                                        'progress_method',
                                        event.target.value,
                                    );
                                    form.setData(
                                        'unit',
                                        event.target.value === 'measured'
                                            ? (selected?.unit ?? '')
                                            : 'day',
                                    );
                                }}
                            >
                                <NativeSelectOption value="measured">
                                    Measured output — adds approved quantity
                                </NativeSelectOption>
                                <NativeSelectOption value="supporting">
                                    Supporting task — does not add quantity
                                </NativeSelectOption>
                            </NativeSelect>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Use measured output for distinct completed
                            quantities. Do not report the same physical work
                            under multiple activities.
                        </p>
                        <div className="space-y-2">
                            <Label htmlFor="activity-site">Site</Label>
                            <NativeSelect
                                id="activity-site"
                                value={form.data.site_id}
                                disabled={Boolean(selected?.site_id)}
                                onChange={(event) =>
                                    form.setData('site_id', event.target.value)
                                }
                            >
                                <NativeSelectOption value="">
                                    Project-wide
                                </NativeSelectOption>
                                {sites.map((site) => (
                                    <NativeSelectOption
                                        key={site.id}
                                        value={site.id}
                                    >
                                        {site.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="activity-unit">
                                Reporting unit
                            </Label>
                            <Input
                                id="activity-unit"
                                value={form.data.unit}
                                disabled={
                                    form.data.progress_method === 'measured'
                                }
                                onChange={(event) =>
                                    form.setData('unit', event.target.value)
                                }
                                required
                            />
                        </div>
                        {Object.entries(form.errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                        <Button type="submit" disabled={form.processing}>
                            Save activity
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
