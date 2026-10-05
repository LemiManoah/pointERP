import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Plus, Search } from 'lucide-react';
import { SearchableSelect } from '@/components/searchable-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { show as showReport } from '@/actions/App/Http/Controllers/Operations/DailySiteReportController';
import {
    item as showItem,
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
    unit_of_measure_id: string;
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
export type BoqProps = {
    itemId?: string;
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
    activityTemplates: { id: string; name: string; code: string | null; category: string; unit: string; unit_of_measure_id: string }[];
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
        viewActivityLibrary: boolean;
        createActivity: boolean;
        createEstimate: boolean;
        viewCosts: boolean;
    };
};
const number = (value: string | number) =>
    Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 });

export function BoqPanel({
    itemId,
    activityTemplates,
    project,
    performance,
    items,
    activities,
    measurements,
    reports,
    revisions,
    sites,
    can,
}: BoqProps) {
    const [search, setSearch] = useState('');
    const [libraryId, setLibraryId] = useState('');
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
        setLibraryId('');
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
    const currentRow = performance?.work_items.find((row) => row.boq_item_id === itemId);
    const currentItem = items.find((item) => item.id === itemId);
    const children = activities.filter((activity) => activity.boq_item_id === itemId);
    const evidence = measurements.filter((entry) => entry.boq_item_id === itemId);
    const measured = currentRow?.item_type === 'measured';
    const currency = performance?.baseline.currency_code ?? '';
    const money = (value: string | null) => value === null ? 'Unpriced' : `${currency} ${number(value)}`;
    const libraryOptions = activityTemplates.filter((template) => form.data.progress_method === 'supporting' || template.unit_of_measure_id === selected?.unit_of_measure_id);
    return (
        <>
            <div className="flex flex-col gap-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{itemId ? currentItem?.name ?? 'BOQ item' : 'BOQ'}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {itemId ? `${project.name} · BOQ item ${currentRow?.boq_reference ?? '—'}` : 'Approved BOQ items and revisions.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {itemId && <Button variant="outline" asChild><Link href={showProject(project.id, {query: {tab: 'boq'}})}><ArrowLeft />Back to BOQ</Link></Button>}
                        {itemId && measured && currentItem && can.createActivity && <Button onClick={() => addActivity(currentItem)}><Plus />Add activity</Button>}
                        {!itemId && can.createEstimate && <>
                            <Button variant="outline" asChild><Link href={importBoq(project.id)}>Import Excel BOQ</Link></Button>
                            <Button asChild><Link href={createEstimate(project.id)}><Plus />{performance ? 'Create revision' : 'Create BOQ'}</Link></Button>
                        </>}
                    </div>
                </div>
                {!itemId ? <>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input className="pl-9" aria-label="Search BOQ items" placeholder="Search BOQ items, references or bills..." value={search} onChange={(event) => setSearch(event.target.value)} />
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{rows.length} BOQ items</span>
                            <Badge variant="secondary">{performance ? `Approved revision ${performance.baseline.version_number}` : 'Awaiting approval'}</Badge>
                            {can.viewCosts && performance?.pricing && <Badge variant="outline">{performance.pricing.status.replaceAll('_', ' ')}</Badge>}
                        </div>
                    </div>
                    <Card><CardContent className="pt-6">
                        <Table>
                            <TableHeader><TableRow>
                                <TableHead>BOQ item</TableHead><TableHead>Unit</TableHead>
                                <TableHead className="text-right">Quantity</TableHead>
                                <TableHead className="text-right">Approved output</TableHead>
                                <TableHead className="text-right">Remaining</TableHead>
                                <TableHead className="text-right">Completion</TableHead>
                                {can.viewCosts && <TableHead className="text-right">Amount ({currency})</TableHead>}
                            </TableRow></TableHeader>
                            <TableBody>
                                {rows.map((row) => <TableRow key={row.id}>
                                    <TableCell className="min-w-64 max-w-96 whitespace-normal">
                                        <Link className="font-medium text-primary hover:underline" href={showItem({project: project.id, item: row.boq_item_id})}>{row.boq_reference ? `${row.boq_reference} · ` : ''}{row.name}</Link>
                                        <div className="text-xs text-muted-foreground">{[row.bill, row.section, row.element].filter(Boolean).join(' / ')}</div>
                                        {Number(row.overrun_quantity) > 0 && <div className="text-xs text-amber-700">Over baseline by {number(row.overrun_quantity)} {row.unit}</div>}
                                    </TableCell>
                                    <TableCell>{row.unit}</TableCell>
                                    <TableCell className="text-right tabular-nums">{number(row.planned_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{row.item_type === 'measured' ? number(row.approved_progress) : '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{row.item_type === 'measured' ? number(row.remaining_quantity) : 'Allowance'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{row.item_type === 'measured' ? `${number(row.completion_percent)}%` : '—'}</TableCell>
                                    {can.viewCosts && <TableCell className="text-right tabular-nums">{row.baseline_revenue === null ? 'Unpriced' : number(row.baseline_revenue)}</TableCell>}
                                </TableRow>)}
                                {rows.length === 0 && <EmptyRow columns={can.viewCosts ? 7 : 6} text={performance ? 'No matching BOQ items.' : 'Create or import a BOQ, then approve it to display the schedule.'} />}
                            </TableBody>
                        </Table>
                    </CardContent></Card>
                    <div className="grid gap-5 xl:grid-cols-2">
                        <Card><CardHeader><CardTitle>BOQ revisions</CardTitle></CardHeader><CardContent>
                            <Table><TableHeader><TableRow><TableHead>Revision</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                                <TableBody>{revisions.map((revision) => <TableRow key={revision.id}>
                                    <TableCell className="whitespace-normal"><Link className="font-medium text-primary hover:underline" href={showEstimate(revision.id)}>V{revision.version_number} · {revision.title}</Link></TableCell>
                                    <TableCell><Badge variant={revision.is_baseline ? 'default' : 'secondary'}>{revision.is_baseline ? 'Current baseline' : revision.status}</Badge></TableCell>
                                </TableRow>)}{revisions.length === 0 && <EmptyRow columns={2} text="No BOQ revisions yet." />}</TableBody>
                            </Table>
                        </CardContent></Card>
                        <Card><CardHeader><CardTitle>Site reports</CardTitle></CardHeader><CardContent>
                            <Table><TableHeader><TableRow><TableHead>Report</TableHead><TableHead>Date</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                                <TableBody>{reports.map((report) => <TableRow key={report.id}>
                                    <TableCell><Link className="font-medium text-primary hover:underline" href={showReport(report.id)}>{report.reference}</Link></TableCell><TableCell>{report.date}</TableCell><TableCell><Badge variant="secondary">{report.status}</Badge></TableCell>
                                </TableRow>)}{reports.length === 0 && <EmptyRow columns={3} text="No site reports available." />}</TableBody>
                            </Table>
                        </CardContent></Card>
                    </div>
                </> : currentRow && currentItem && <>
                    <Card><CardHeader><CardTitle>BOQ item details</CardTitle></CardHeader><CardContent className="space-y-5">
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Detail label="Reference" value={currentRow.boq_reference ?? '—'} />
                            <Detail label="Bill" value={currentRow.bill ?? '—'} />
                            <Detail label="Section" value={currentRow.section ?? '—'} />
                            <Detail label="Element" value={currentRow.element ?? '—'} />
                            <Detail label="Item type" value={currentRow.item_type.replaceAll('_', ' ')} />
                            <Detail label="Unit" value={currentRow.unit} />
                            <Detail label="Site" value={sites.find((site) => site.id === currentItem.site_id)?.name ?? 'Project-wide'} />
                            <Detail label="Baseline" value={`Revision ${performance?.baseline.version_number}`} />
                        </dl>
                        {currentItem.description && <div><p className="text-sm text-muted-foreground">Description</p><p className="mt-1 text-sm whitespace-pre-line">{currentItem.description}</p></div>}
                        {currentItem.source_document && <p className="text-xs text-muted-foreground">Source: {currentItem.source_document} · {currentItem.source_sheet} · row {currentItem.source_row}</p>}
                    </CardContent></Card>
                    <Card><CardHeader><CardTitle>Quantity and progress</CardTitle></CardHeader><CardContent>
                        <Table><TableHeader><TableRow><TableHead className="text-right">BOQ quantity</TableHead><TableHead className="text-right">Approved output</TableHead><TableHead className="text-right">Remaining</TableHead><TableHead className="text-right">Over baseline</TableHead><TableHead className="text-right">Completion</TableHead>{can.viewCosts && <><TableHead className="text-right">BOQ amount</TableHead><TableHead className="text-right">Earned output</TableHead></>}</TableRow></TableHeader>
                            <TableBody><TableRow>
                                <TableCell className="text-right tabular-nums">{number(currentRow.planned_quantity)} {currentRow.unit}</TableCell>
                                <TableCell className="text-right tabular-nums">{measured ? `${number(currentRow.approved_progress)} ${currentRow.unit}` : 'Not measured'}</TableCell>
                                <TableCell className="text-right tabular-nums">{measured ? `${number(currentRow.remaining_quantity)} ${currentRow.unit}` : 'Allowance'}</TableCell>
                                <TableCell className="text-right tabular-nums">{measured ? `${number(currentRow.overrun_quantity)} ${currentRow.unit}` : '—'}</TableCell>
                                <TableCell className="text-right tabular-nums">{measured ? `${number(currentRow.completion_percent)}%` : '—'}</TableCell>
                                {can.viewCosts && <><TableCell className="text-right tabular-nums">{money(currentRow.baseline_revenue)}</TableCell><TableCell className="text-right tabular-nums">{currentRow.earned_output === null ? 'Not available' : money(currentRow.earned_output)}</TableCell></>}
                            </TableRow></TableBody>
                        </Table>
                    </CardContent></Card>
                    <Card><CardHeader><CardTitle>Work activities</CardTitle></CardHeader><CardContent>
                        <Table><TableHeader><TableRow><TableHead>Activity</TableHead><TableHead>Contribution</TableHead><TableHead>Unit</TableHead><TableHead className="text-right">Approved output</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                            <TableBody>{children.map((activity) => <TableRow key={activity.id}>
                                <TableCell className="font-medium whitespace-normal">{activity.name}</TableCell><TableCell><Badge variant="outline">{activity.progress_method === 'supporting' ? 'Supporting' : 'Measured output'}</Badge></TableCell><TableCell>{activity.unit}</TableCell><TableCell className="text-right tabular-nums">{activity.progress_method === 'supporting' ? 'Does not count' : number(activity.approved_quantity)}</TableCell><TableCell><Badge variant="secondary">{activity.status}</Badge></TableCell>
                            </TableRow>)}{children.length === 0 && <EmptyRow columns={5} text={measured ? 'No activities linked to this BOQ item yet.' : 'This allowance has no measured work activities.'} />}</TableBody>
                        </Table>
                    </CardContent></Card>
                    <Card><CardHeader><CardTitle>Approved measurements</CardTitle></CardHeader><CardContent>
                        <Table><TableHeader><TableRow><TableHead>Date</TableHead><TableHead>Activity / description</TableHead><TableHead className="text-right">Quantity</TableHead><TableHead>Evidence</TableHead></TableRow></TableHeader>
                            <TableBody>{evidence.map((entry) => <TableRow key={entry.id}>
                                <TableCell>{entry.date}</TableCell><TableCell className="min-w-56 whitespace-normal"><div className="font-medium">{children.find((activity) => activity.id === entry.activity_id)?.name ?? 'BOQ item measurement'}</div><div className="text-xs text-muted-foreground">{entry.description}</div></TableCell><TableCell className="text-right tabular-nums">{number(entry.quantity)} {entry.unit}</TableCell><TableCell>{entry.report_id ? <Link className="font-medium text-primary hover:underline" href={showReport(entry.report_id)}>Open report</Link> : entry.legacy ? <Badge variant="outline">Legacy balance · review required</Badge> : 'Approved record'}</TableCell>
                            </TableRow>)}{evidence.length === 0 && <EmptyRow columns={4} text="No approved measurements for this BOQ item yet." />}</TableBody>
                        </Table>
                    </CardContent></Card>
                </>}
            </div>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add activity to BOQ item</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        BOQ item: {selected?.name}. Its approved quantity and rate stay unchanged.
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
                        {can.viewActivityLibrary && <div className="space-y-2">
                            <Label>Work activity library</Label>
                            <SearchableSelect
                                value={libraryId}
                                placeholder="Select a library activity or enter your own"
                                searchPlaceholder="Search name, code or category..."
                                emptyMessage="No matching library activities for this unit."
                                options={[{value: '', label: 'Custom activity'}, ...libraryOptions.map((template) => ({value: template.id, label: `${template.code ? `${template.code} · ` : ''}${template.name}`, description: `${template.category} · ${template.unit}`}))]}
                                onValueChange={(value) => {
                                    setLibraryId(value);
                                    const template = libraryOptions.find((template) => template.id === value);
                                    if (template) form.setData({...form.data, name: template.name, unit: form.data.progress_method === 'measured' ? selected?.unit ?? template.unit : template.unit});
                                }}
                            />
                            <p className="text-xs text-muted-foreground">Copies the activity name and unit. Measured activities must use the BOQ item's unit. Library prices and resource estimates are not copied.</p>
                        </div>}
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
                                Contribution to BOQ item
                            </Label>
                            <NativeSelect
                                id="progress-method"
                                value={form.data.progress_method}
                                onChange={(event) => {
                                    setLibraryId('');
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
        </>
    );
}

export default function BoqItem(props: BoqProps) {
    const item = props.items.find((item) => item.id === props.itemId);
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: props.project.reference,
                    href: showProject.url(props.project.id),
                },
                {
                    title: 'BOQ',
                    href: showProject.url(props.project.id, {
                        query: { tab: 'boq' },
                    }),
                },
                {
                    title: item?.name ?? 'Item details',
                    href: showItem.url({
                        project: props.project.id,
                        item: props.itemId!,
                    }),
                },
            ]}
        >
            <Head
                title={`${item?.name ?? 'BOQ item'} · ${props.project.name}`}
            />
            <div className="p-4 md:p-6">
                <BoqPanel {...props} />
            </div>
        </AppLayout>
    );
}

function EmptyRow({columns, text}: {columns: number; text: string}) {
    return <TableRow><TableCell colSpan={columns} className="h-24 text-center text-muted-foreground">{text}</TableCell></TableRow>;
}

function Detail({label, value}: {label: string; value: string}) {
    return <div><dt className="text-sm text-muted-foreground">{label}</dt><dd className="mt-1 text-sm font-medium">{value}</dd></div>;
}
