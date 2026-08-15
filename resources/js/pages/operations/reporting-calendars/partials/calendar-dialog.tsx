import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Switch } from '@/components/ui/switch';

export type CalendarRow = {
    id: string;
    name: string;
    project_id: string | null;
    project_name: string | null;
    site_id: string | null;
    site_name: string | null;
    scope: string;
    timezone: string;
    reporting_deadline: string;
    working_days: number[];
    missing_escalation_days: number;
    is_active: boolean;
    exceptions: CalendarException[];
};

export type CalendarException = {
    id: string;
    exception_date: string;
    type: string;
    name: string;
    reason: string | null;
};

type Option = { id: string; name: string; project_id?: string };
type Timezone = { value: string; label: string };

const weekdays = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

export function CalendarDialog({
    calendar,
    projects,
    sites,
    timezones,
}: {
    calendar?: CalendarRow;
    projects: Option[];
    sites: Option[];
    timezones: Timezone[];
}) {
    const [open, setOpen] = useState(false);
    const scope = calendar?.site_id
        ? `site:${calendar.site_id}`
        : calendar?.project_id
          ? `project:${calendar.project_id}`
          : 'tenant';
    const initialData = {
        name: calendar?.name ?? '',
        project_id: calendar?.project_id ?? '',
        site_id: calendar?.site_id ?? '',
        timezone: calendar?.timezone ?? 'Africa/Kampala',
        reporting_deadline: calendar?.reporting_deadline ?? '18:00',
        working_days: calendar?.working_days ?? [1, 2, 3, 4, 5, 6],
        missing_escalation_days:
            calendar?.missing_escalation_days ?? 2,
        is_active: calendar?.is_active ?? true,
    };
    const form = useForm(initialData);
    const [selectedScope, setSelectedScope] = useState(scope);

    function changeOpen(nextOpen: boolean) {
        setOpen(nextOpen);

        if (nextOpen) {
            setSelectedScope(scope);
            form.setData(initialData);
            form.clearErrors();
        }
    }

    function changeScope(value: string) {
        setSelectedScope(value);

        if (value === 'tenant') {
            form.setData({
                ...form.data,
                project_id: '',
                site_id: '',
            });
        } else if (value.startsWith('project:')) {
            form.setData({
                ...form.data,
                project_id: value.replace('project:', ''),
                site_id: '',
            });
        } else {
            const siteId = value.replace('site:', '');
            const site = sites.find((option) => option.id === siteId);
            form.setData({
                ...form.data,
                project_id: site?.project_id ?? '',
                site_id: siteId,
            });
        }
    }

    function toggleDay(day: number, checked: boolean) {
        form.setData(
            'working_days',
            checked
                ? [...form.data.working_days, day].sort()
                : form.data.working_days.filter((value) => value !== day),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (calendar) {
            form.put(`/reporting-calendars/${calendar.id}`, options);
        } else {
            form.post('/reporting-calendars', options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                {calendar ? (
                    <Button variant="outline" size="sm">
                        Edit
                    </Button>
                ) : (
                    <Button>
                        <Plus />
                        New calendar
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {calendar
                                ? 'Edit reporting calendar'
                                : 'New reporting calendar'}
                        </DialogTitle>
                        <DialogDescription>
                            Site rules override project rules, which override
                            the tenant default.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="calendar_name">Name</Label>
                            <Input
                                id="calendar_name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Scope</Label>
                            <SearchableSelect
                                value={selectedScope}
                                onValueChange={changeScope}
                                options={[
                                    {
                                        value: 'tenant',
                                        label: 'Tenant default',
                                    },
                                    ...projects.map((project) => ({
                                        value: `project:${project.id}`,
                                        label: `Project: ${project.name}`,
                                    })),
                                    ...sites.map((site) => ({
                                        value: `site:${site.id}`,
                                        label: `Site: ${site.name}`,
                                    })),
                                ]}
                                searchPlaceholder="Search scopes..."
                            />
                            <InputError
                                message={
                                    (form.errors as Record<string, string>)
                                        .scope
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Timezone</Label>
                            <SearchableSelect
                                value={form.data.timezone}
                                onValueChange={(value) =>
                                    form.setData('timezone', value)
                                }
                                options={timezones}
                            />
                            <InputError message={form.errors.timezone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reporting_deadline">
                                Reporting deadline
                            </Label>
                            <Input
                                id="reporting_deadline"
                                type="time"
                                value={form.data.reporting_deadline}
                                onChange={(event) =>
                                    form.setData(
                                        'reporting_deadline',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.reporting_deadline}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Expected reporting days</Label>
                        <div className="grid grid-cols-4 gap-2 sm:grid-cols-7">
                            {weekdays.map((day) => (
                                <label
                                    key={day.value}
                                    className="flex items-center gap-2 rounded-md border p-3 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.working_days.includes(
                                            day.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleDay(
                                                day.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {day.label}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.working_days} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="missing_escalation_days">
                                Escalate after consecutive misses
                            </Label>
                            <Input
                                id="missing_escalation_days"
                                type="number"
                                min={1}
                                max={30}
                                value={form.data.missing_escalation_days}
                                onChange={(event) =>
                                    form.setData(
                                        'missing_escalation_days',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <label className="flex items-center justify-between gap-4 rounded-md border p-3">
                            <span>
                                <span className="block text-sm font-medium">
                                    Active
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Only active calendars are resolved.
                                </span>
                            </span>
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </label>
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
                            Save calendar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
