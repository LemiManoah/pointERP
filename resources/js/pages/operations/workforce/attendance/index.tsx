import { Head, Link } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Register = {
    id: string;
    project_name: string;
    project_reference: string;
    site_name: string;
    attendance_date: string;
    shift_label: string;
    status: 'draft' | 'confirmed';
    status_label: string;
    line_count: number;
    headcount: number;
    person_hours: number;
    recorded_by_name: string;
};
type Props = { registers: Register[]; canCreate: boolean };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance', href: '/workforce/attendance' },
];

export default function AttendanceIndex({ registers, canCreate }: Props) {
    const [tab, setTab] = useState<'draft' | 'confirmed'>('draft');
    const [search, setSearch] = useState('');
    const term = useDebouncedValue(search).trim().toLowerCase();
    const rows = useMemo(
        () =>
            registers.filter(
                (register) =>
                    register.status === tab &&
                    (!term ||
                        [
                            register.project_name,
                            register.project_reference,
                            register.site_name,
                            register.recorded_by_name,
                        ]
                            .join(' ')
                            .toLowerCase()
                            .includes(term)),
            ),
        [registers, tab, term],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site attendance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Site attendance</h1>
                    <p className="text-sm text-muted-foreground">
                        Record who attended each site during the day or night
                        shift.
                    </p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:max-w-sm">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search project, site or recorder..."
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-1 flex-wrap items-center justify-end gap-3">
                        <Tabs
                            value={tab}
                            onValueChange={(value) =>
                                setTab(value as 'draft' | 'confirmed')
                            }
                        >
                            <TabsList>
                                <TabsTrigger value="draft">Drafts</TabsTrigger>
                                <TabsTrigger value="confirmed">
                                    Confirmed
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <Button asChild variant="outline">
                            <Link href="/workforce">Setup</Link>
                        </Button>
                        {canCreate && (
                            <Button asChild>
                                <Link href="/workforce/attendance/create">
                                    <Plus />
                                    New attendance
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Project / site</TableHead>
                                        <TableHead>Shift</TableHead>
                                        <TableHead className="text-right">
                                            People
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Person-hours
                                        </TableHead>
                                        <TableHead>Recorded by</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((register) => (
                                        <TableRow key={register.id}>
                                            <TableCell>
                                                <Link
                                                    href={
                                                        '/workforce/attendance/' +
                                                        register.id
                                                    }
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {format(
                                                        parseISO(
                                                            register.attendance_date,
                                                        ),
                                                        'dd MMM yyyy',
                                                    )}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {register.project_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {register.site_name} ·{' '}
                                                    {register.project_reference}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {register.shift_label}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {register.headcount.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {register.person_hours.toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                {register.recorded_by_name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        register.status ===
                                                        'confirmed'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {register.status_label}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {rows.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No {tab} attendance registers
                                                found.
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
