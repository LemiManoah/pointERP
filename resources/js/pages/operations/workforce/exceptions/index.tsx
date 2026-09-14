import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DatePicker } from '@/components/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ExceptionRow = {
    id: string;
    type: string;
    type_label: string;
    severity: 'warning' | 'critical';
    project_name: string;
    project_reference: string;
    site_name: string;
    date: string;
    summary: string;
    attended_hours: number | null;
    reported_hours: number | null;
    variance_hours: number | null;
    action_url: string;
};

type ProjectOption = {
    id: string;
    name: string;
    reference: string;
    sites: { id: string; name: string }[];
};

type Filters = {
    project_id: string | null;
    site_id: string | null;
    date_from: string;
    date_to: string;
};

type Props = {
    exceptions: ExceptionRow[];
    summary: {
        total: number;
        unconfirmed: number;
        missing_attendance: number;
        labour_variance: number;
    };
    projects: ProjectOption[];
    filters: Filters;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance', href: '/workforce/attendance' },
    { title: 'Exceptions', href: '/workforce/exceptions' },
];

function hours(value: number | null) {
    return value === null
        ? '—'
        : value.toLocaleString(undefined, { maximumFractionDigits: 2 });
}

export default function WorkforceExceptionsIndex(props: Props) {
    const [filters, setFilters] = useState(props.filters);
    const sites = useMemo(
        () =>
            props.projects
                .filter(
                    (project) =>
                        !filters.project_id ||
                        project.id === filters.project_id,
                )
                .flatMap((project) =>
                    project.sites.map((site) => ({
                        value: site.id,
                        label: site.name,
                        description: project.name + ' · ' + project.reference,
                    })),
                ),
        [filters.project_id, props.projects],
    );

    function applyFilters() {
        router.get(
            '/workforce/exceptions',
            {
                project_id: filters.project_id || undefined,
                site_id: filters.site_id || undefined,
                date_from: filters.date_from,
                date_to: filters.date_to,
            },
            { preserveState: true, replace: true },
        );
    }

    function resetFilters() {
        router.get('/workforce/exceptions');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workforce exceptions" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Workforce exceptions
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Attendance and DSR labour records that need review.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/workforce/attendance">Attendance</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/workforce">Setup</Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="grid min-w-0 gap-2">
                            <Label>Project</Label>
                            <SearchableSelect
                                value={filters.project_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        project_id: value || null,
                                        site_id: null,
                                    })
                                }
                                options={props.projects.map((project) => ({
                                    value: project.id,
                                    label: project.name,
                                    description: project.reference,
                                }))}
                                placeholder="All accessible projects"
                                searchPlaceholder="Search projects..."
                            />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label>Site</Label>
                            <SearchableSelect
                                value={filters.site_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        site_id: value || null,
                                    })
                                }
                                options={sites}
                                placeholder="All accessible sites"
                                searchPlaceholder="Search sites..."
                            />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label>From</Label>
                            <DatePicker
                                value={filters.date_from}
                                onChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        date_from: value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label>To</Label>
                            <DatePicker
                                value={filters.date_to}
                                onChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        date_to: value,
                                    })
                                }
                            />
                        </div>
                        <div className="flex gap-2 sm:col-span-2 xl:col-span-4 xl:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                <RotateCcw />
                                Reset
                            </Button>
                            <Button type="button" onClick={applyFilters}>
                                Apply filters
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid overflow-hidden rounded-md border bg-card sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Open exceptions', props.summary.total],
                        ['Attendance attention', props.summary.unconfirmed],
                        [
                            'Missing attendance',
                            props.summary.missing_attendance,
                        ],
                        ['Labour variances', props.summary.labour_variance],
                    ].map(([label, value], index) => (
                        <div
                            key={String(label)}
                            className={
                                'px-4 py-3 ' +
                                (index > 0
                                    ? 'border-t sm:border-t-0 sm:border-l'
                                    : '')
                            }
                        >
                            <div className="text-xs text-muted-foreground">
                                {label}
                            </div>
                            <div className="mt-1 text-xl font-semibold">
                                {Number(value).toLocaleString()}
                            </div>
                        </div>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Exception</TableHead>
                                        <TableHead>Project / site</TableHead>
                                        <TableHead className="text-right">
                                            Attended
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Reported
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Variance
                                        </TableHead>
                                        <TableHead>Details</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.exceptions.map((exception) => (
                                        <TableRow key={exception.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {format(
                                                    parseISO(exception.date),
                                                    'dd MMM yyyy',
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        exception.severity ===
                                                        'critical'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {exception.type_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {exception.project_name}
                                                </div>
                                                <div className="max-w-64 truncate text-xs text-muted-foreground">
                                                    {exception.site_name} ·{' '}
                                                    {
                                                        exception.project_reference
                                                    }
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {hours(
                                                    exception.attended_hours,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {hours(
                                                    exception.reported_hours,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {hours(
                                                    exception.variance_hours,
                                                )}
                                            </TableCell>
                                            <TableCell className="min-w-72">
                                                <div className="text-sm">
                                                    {exception.summary}
                                                </div>
                                                <Link
                                                    href={exception.action_url}
                                                    className="mt-1 inline-block text-sm font-medium text-primary hover:underline"
                                                >
                                                    Review record
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {props.exceptions.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No workforce exceptions match
                                                these filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
