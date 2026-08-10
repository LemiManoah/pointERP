import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, Plus, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Report = {
    id: string;
    reference: string;
    project_name: string;
    site_name: string;
    report_date: string;
    status: string;
    output_value: string | null;
    input_cost: string | null;
    profit_loss: string | null;
    submitted_by: string | null;
    approved_by: string | null;
};

type SiteOption = {
    id: string;
    name: string;
    project_id: string;
};

type Props = {
    reports: Report[];
    sites: SiteOption[];
};

type FormData = Record<string, string> & {
    site_id: string;
    report_date: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Daily reports', href: '/daily-site-reports' },
];

export default function DailySiteReportsIndex({ reports, sites }: Props) {
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('open');
    const debouncedSearch = useDebouncedValue(search);
    const form = useForm<FormData>({
        site_id: sites[0]?.id ?? '',
        report_date: new Date().toISOString().slice(0, 10),
    });

    const filteredReports = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();
        const openStatuses = [
            'draft',
            'submitted',
            'reviewed',
            'returned',
            'missing',
        ];

        return reports.filter((report) => {
            const matchesTab =
                tab === 'open'
                    ? openStatuses.includes(report.status)
                    : !openStatuses.includes(report.status);
            const matchesSearch =
                !term ||
                [
                    report.reference,
                    report.project_name,
                    report.site_name,
                    report.report_date,
                    report.status,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesTab && matchesSearch;
        });
    }, [debouncedSearch, reports, tab]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/daily-site-reports', {
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daily reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Daily site reports
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Field records for site progress, resources,
                                issues and approvals.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search reports"
                                className="w-full pl-9 sm:w-80"
                            />
                        </div>
                    </div>

                    <form
                        onSubmit={submit}
                        className="grid gap-3 rounded-md border p-3 sm:min-w-[30rem] sm:grid-cols-[1fr_10rem_auto]"
                    >
                        <div className="grid gap-2">
                            <Label>Site</Label>
                            <SearchableSelect
                                value={form.data.site_id}
                                onValueChange={(value) =>
                                    form.setData('site_id', value)
                                }
                                options={sites.map((site) => ({
                                    value: site.id,
                                    label: site.name,
                                }))}
                                placeholder="Select site"
                                searchPlaceholder="Search sites..."
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="report_date">Date</Label>
                            <Input
                                id="report_date"
                                type="date"
                                value={form.data.report_date}
                                onChange={(event) =>
                                    form.setData(
                                        'report_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" disabled={form.processing}>
                                <Plus />
                                New DSR
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="open">Open</TabsTrigger>
                            <TabsTrigger value="closed">Closed</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Reports</CardTitle>
                        <CardDescription>
                            Open reports include drafts, submitted, returned and
                            missing records.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Report
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Site
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Value
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredReports.map((report) => (
                                        <tr
                                            key={report.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {report.reference}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {report.report_date}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>{report.site_name}</div>
                                                <div className="text-muted-foreground">
                                                    {report.project_name}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {report.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {report.output_value ??
                                                    '0.0000'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/daily-site-reports/${report.id}`}
                                                        >
                                                            Open
                                                        </Link>
                                                    </Button>
                                                    {report.status !==
                                                        'approved' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.delete(
                                                                    `/daily-site-reports/${report.id}`,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Archive
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredReports.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                <ClipboardCheck className="mx-auto mb-3 size-8 opacity-50" />
                                                No reports match this view.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
