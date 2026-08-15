import { Head, router } from '@inertiajs/react';
import { CalendarDays, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    CalendarDialog,
    type CalendarRow,
} from './partials/calendar-dialog';
import { CalendarExceptionsDialog } from './partials/calendar-exceptions-dialog';

type Option = { id: string; name: string; project_id?: string };
type Props = {
    calendars: CalendarRow[];
    projects: Option[];
    sites: Option[];
    timezones: Array<{ value: string; label: string }>;
    canManage: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reporting calendars', href: '/reporting-calendars' },
];

const dayLabels: Record<number, string> = {
    1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun',
};

export default function ReportingCalendarsIndex({
    calendars,
    projects,
    sites,
    timezones,
    canManage,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('active');
    const debouncedSearch = useDebouncedValue(search);
    const filteredCalendars = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return calendars.filter((calendar) => {
            const matchesState =
                tab === 'active' ? calendar.is_active : !calendar.is_active;
            const matchesSearch =
                !term ||
                [calendar.name, calendar.scope, calendar.timezone]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesState && matchesSearch;
        });
    }, [calendars, debouncedSearch, tab]);

    async function deactivate(calendar: CalendarRow) {
        const accepted = await confirm({
            title: `Deactivate ${calendar.name}?`,
            description:
                'The next applicable active calendar will control future reporting obligations.',
            confirmLabel: 'Deactivate',
            variant: 'destructive',
        });

        if (accepted) {
            router.delete(`/reporting-calendars/${calendar.id}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reporting calendars" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Reporting calendars
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Define when each site owes a daily report and when missed reporting escalates.
                            </p>
                        </div>
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search calendars"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    {canManage && (
                        <CalendarDialog
                            projects={projects}
                            sites={sites}
                            timezones={timezones}
                        />
                    )}
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">Calendar</th>
                                        <th className="py-3 pr-4 font-medium">Reporting days</th>
                                        <th className="py-3 pr-4 font-medium">Deadline</th>
                                        <th className="py-3 pr-4 font-medium">Escalation</th>
                                        <th className="py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCalendars.map((calendar) => (
                                        <tr key={calendar.id} className="border-b last:border-0">
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">{calendar.name}</div>
                                                <div className="text-muted-foreground">{calendar.scope}</div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>{calendar.working_days.map((day) => dayLabels[day]).join(', ')}</div>
                                                <div className="text-muted-foreground">
                                                    {calendar.exceptions.length} dated exception{calendar.exceptions.length === 1 ? '' : 's'}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>{calendar.reporting_deadline}</div>
                                                <div className="text-muted-foreground">{calendar.timezone}</div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {calendar.missing_escalation_days} consecutive misses
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    {canManage && (
                                                        <>
                                                            {calendar.is_active && <CalendarExceptionsDialog calendar={calendar} />}
                                                            <CalendarDialog calendar={calendar} projects={projects} sites={sites} timezones={timezones} />
                                                            {calendar.is_active && <Button variant="destructive" size="sm" onClick={() => void deactivate(calendar)}>
                                                                Deactivate
                                                            </Button>}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {filteredCalendars.length === 0 && (
                            <div className="grid place-items-center gap-2 py-12 text-center">
                                <CalendarDays className="size-8 text-muted-foreground" />
                                <p className="font-medium">No calendars found</p>
                                <p className="text-sm text-muted-foreground">
                                    {tab === 'active' ? 'Create a tenant, project or site reporting calendar.' : 'Deactivated calendars appear here.'}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
