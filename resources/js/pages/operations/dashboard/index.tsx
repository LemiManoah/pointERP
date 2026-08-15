import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Row = {
    id: string;
    project_name: string | null;
    site_name: string | null;
    site_manager: string | null;
    report_date: string;
    deadline_at: string | null;
    status: string;
    report_id: string | null;
    report_reference: string | null;
    report_status: string | null;
    submitted_at: string | null;
    notified_at: string | null;
    escalated_at: string | null;
    missing_evidence: boolean;
};
type Option = { id: string; name: string; project_id?: string };
type Summary = {
    expected: number;
    on_time: number;
    late: number;
    missing: number;
    excused: number;
    returned: number;
    pending: number;
    missing_evidence: number;
    compliance_percent: number;
};
type Filters = {
    from: string;
    to: string;
    project_id: string;
    site_id: string;
    status: string;
};
type Props = {
    rows: Row[];
    summary: Summary;
    filters: Filters;
    projects: Option[];
    sites: Option[];
    generatedAt: string;
    canExport: boolean;
    canExcuse: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Operations control', href: '/operations-dashboard' },
];

const attentionStatuses = ['missing', 'late'];

export default function OperationsDashboard({
    rows,
    summary,
    filters,
    projects,
    sites,
    generatedAt,
    canExport,
    canExcuse,
}: Props) {
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('attention');
    const [excuseRow, setExcuseRow] = useState<Row | null>(null);
    const [selectedFilters, setSelectedFilters] = useState(filters);
    const debouncedSearch = useDebouncedValue(search);
    const excuseForm = useForm({ reason: '' });
    const visibleRows = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();
        return rows.filter((row) => {
            const matchesTab =
                tab === 'attention'
                    ? attentionStatuses.includes(row.status) ||
                      row.report_status === 'returned' ||
                      row.missing_evidence
                    : tab === 'calendar'
                      ? ['expected', 'excused'].includes(row.status)
                      : ['submitted', 'late'].includes(row.status) &&
                        !row.missing_evidence;
            const matchesSearch =
                !term ||
                [
                    row.project_name ?? '',
                    row.site_name ?? '',
                    row.site_manager ?? '',
                    row.report_reference ?? '',
                    row.report_date,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);
            return matchesTab && matchesSearch;
        });
    }, [debouncedSearch, rows, tab]);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/operations-dashboard', selectedFilters, {
            preserveState: true,
            replace: true,
        });
    }

    function submitExcuse(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!excuseRow) return;
        excuseForm.post(`/expected-daily-site-reports/${excuseRow.id}/excuse`, {
            preserveScroll: true,
            onSuccess: () => {
                setExcuseRow(null);
                excuseForm.reset();
            },
        });
    }

    const exportQuery = new URLSearchParams(filters).toString();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Operations control" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Operations control
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Daily reporting compliance, overdue obligations
                                and evidence exceptions.
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Updated {generatedAt}
                            </p>
                        </div>
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search projects, sites or reports"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    {canExport && (
                        <Button variant="outline" asChild>
                            <a
                                href={`/operations-dashboard/export?${exportQuery}`}
                            >
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 border-y py-4 sm:grid-cols-2 xl:grid-cols-[10rem_10rem_1fr_1fr_12rem_auto]"
                >
                    <Input
                        type="date"
                        value={selectedFilters.from}
                        onChange={(event) =>
                            setSelectedFilters((current) => ({
                                ...current,
                                from: event.target.value,
                            }))
                        }
                        aria-label="From date"
                    />
                    <Input
                        type="date"
                        value={selectedFilters.to}
                        onChange={(event) =>
                            setSelectedFilters((current) => ({
                                ...current,
                                to: event.target.value,
                            }))
                        }
                        aria-label="To date"
                    />
                    <SearchableSelect
                        value={selectedFilters.project_id}
                        onValueChange={(value) =>
                            setSelectedFilters((current) => ({
                                ...current,
                                project_id: value,
                                site_id: '',
                            }))
                        }
                        options={[
                            { value: '', label: 'All projects' },
                            ...projects.map((item) => ({
                                value: item.id,
                                label: item.name,
                            })),
                        ]}
                    />
                    <SearchableSelect
                        value={selectedFilters.site_id}
                        onValueChange={(value) =>
                            setSelectedFilters((current) => ({
                                ...current,
                                site_id: value,
                            }))
                        }
                        options={[
                            { value: '', label: 'All sites' },
                            ...sites
                                .filter(
                                    (item) =>
                                        !selectedFilters.project_id ||
                                        item.project_id ===
                                            selectedFilters.project_id,
                                )
                                .map((item) => ({
                                    value: item.id,
                                    label: item.name,
                                })),
                        ]}
                    />
                    <SearchableSelect
                        value={selectedFilters.status}
                        onValueChange={(value) =>
                            setSelectedFilters((current) => ({
                                ...current,
                                status: value,
                            }))
                        }
                        options={[
                            { value: '', label: 'All statuses' },
                            ...[
                                'expected',
                                'submitted',
                                'late',
                                'missing',
                                'excused',
                            ].map((value) => ({
                                value,
                                label: value.replaceAll('_', ' '),
                            })),
                        ]}
                    />
                    <Button type="submit">Apply</Button>
                </form>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric
                        label="Compliance"
                        value={`${formatNumber(summary.compliance_percent)}%`}
                    />
                    <Metric
                        label="Expected reports"
                        value={formatNumber(summary.expected)}
                    />
                    <Metric
                        label="Missing / late"
                        value={formatNumber(summary.missing + summary.late)}
                    />
                    <Metric
                        label="Pending review"
                        value={formatNumber(summary.pending)}
                    />
                    <Metric
                        label="Returned"
                        value={formatNumber(summary.returned)}
                    />
                    <Metric
                        label="Missing evidence"
                        value={formatNumber(summary.missing_evidence)}
                    />
                    <Metric
                        label="On time"
                        value={formatNumber(summary.on_time)}
                    />
                    <Metric
                        label="Excused"
                        value={formatNumber(summary.excused)}
                    />
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="attention">
                                Needs attention
                            </TabsTrigger>
                            <TabsTrigger value="calendar">
                                Reporting calendar
                            </TabsTrigger>
                            <TabsTrigger value="completed">
                                Completed
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Project / site
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Report date
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Obligation
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            DSR
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Owner
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleRows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {row.project_name ??
                                                        'Project'}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {row.site_name ?? 'Site'}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>{row.report_date}</div>
                                                <div className="text-muted-foreground">
                                                    Due{' '}
                                                    {row.deadline_at ??
                                                        'Not set'}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        row.status === 'missing'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {row.status}
                                                </Badge>
                                                {row.escalated_at && (
                                                    <div className="mt-1 text-xs text-destructive">
                                                        Escalated
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>
                                                    {row.report_reference ??
                                                        'Not created'}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {row.report_status ?? '-'}
                                                </div>
                                                {row.missing_evidence && (
                                                    <div className="text-xs text-destructive">
                                                        Evidence missing
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {row.site_manager ??
                                                    'Unassigned'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    {row.report_id && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/daily-site-reports/${row.report_id}`}
                                                            >
                                                                <ExternalLink />
                                                                Open
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canExcuse &&
                                                        [
                                                            'expected',
                                                            'missing',
                                                        ].includes(
                                                            row.status,
                                                        ) && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setExcuseRow(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                Excuse
                                                            </Button>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {visibleRows.length === 0 && (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                No reporting obligations match this view.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={excuseRow !== null}
                onOpenChange={(open) => !open && setExcuseRow(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={submitExcuse} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                Excuse reporting obligation
                            </DialogTitle>
                            <DialogDescription>
                                This removes the date from compliance exceptions
                                but preserves who made the decision and why.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="excuse_reason">Reason</Label>
                            <Textarea
                                id="excuse_reason"
                                value={excuseForm.data.reason}
                                onChange={(event) =>
                                    excuseForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <p className="text-sm text-destructive">
                                {excuseForm.errors.reason}
                            </p>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setExcuseRow(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={excuseForm.processing}
                            >
                                Confirm excuse
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-l-2 pl-4">
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
            <div className="text-sm text-muted-foreground">{label}</div>
        </div>
    );
}
