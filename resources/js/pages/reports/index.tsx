import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

type ReportLink = { title: string; description: string; href: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: reportsIndex.url() },
];

export default function ReportsIndex({ reports }: { reports: ReportLink[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <h1 className="text-2xl font-semibold">Reports</h1>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {reports.map((report) => (
                        <Card key={report.title}>
                            <CardContent className="p-0">
                                <Link href={report.href} className="flex h-full items-start justify-between gap-4 rounded-xl p-5 hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                                    <div>
                                        <h2 className="font-semibold">{report.title}</h2>
                                        <p className="mt-2 text-sm text-muted-foreground">{report.description}</p>
                                    </div>
                                    <ArrowUpRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
