import { Head, router } from '@inertiajs/react';
import { ChevronDown, FilterX, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AuditActivity = {
    id: number;
    event: string | null;
    description: string;
    tenant_id: string | null;
    branch_id: string | null;
    branch_name: string | null;
    subject_type: string | null;
    subject_label: string;
    subject_id: string | number | null;
    actor_id: string | number | null;
    actor_name: string | null;
    actor_email: string | null;
    changes: Record<string, unknown>;
    reason: string | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
};

type BranchOption = {
    id: string;
    name: string;
    code: string;
};

type ActorOption = {
    id: string;
    name: string;
    email: string;
};

type SubjectTypeOption = {
    value: string;
    label: string;
};

type Filters = {
    search: string;
    event: string;
    branch_id: string;
    actor_id: string;
    subject_type: string;
};

type Props = {
    activities: AuditActivity[];
    filters: Filters;
    events: string[];
    branches: BranchOption[];
    actors: ActorOption[];
    subjectTypes: SubjectTypeOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit trail', href: '/audit-trail' },
];

function formatEvent(event: string | null) {
    return event?.replaceAll('.', ' ') ?? 'audit event';
}

function formatJson(value: unknown) {
    return JSON.stringify(value ?? {}, null, 2);
}

function applyFilters(filters: Filters) {
    router.get('/audit-trail', filters, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

export default function AuditTrailIndex({
    activities,
    filters,
    events,
    branches,
    actors,
    subjectTypes,
}: Props) {
    const [form, setForm] = useState(filters);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit trail" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Audit trail
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Security and compliance events for this tenant.
                        </p>
                    </div>
                    <Badge variant="secondary" className="w-fit">
                        Latest {activities.length}
                    </Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Narrow the audit trail by actor, branch, event,
                            record type, or keyword.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 lg:grid-cols-[minmax(16rem,1.4fr)_repeat(4,minmax(10rem,1fr))_auto_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters(form);
                            }}
                        >
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={form.search}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            search: event.target.value,
                                        })
                                    }
                                    placeholder="Search events"
                                    className="pl-9"
                                />
                            </div>
                            <NativeSelect
                                value={form.event}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        event: event.target.value,
                                    })
                                }
                                className="w-full"
                            >
                                <NativeSelectOption value="">
                                    All events
                                </NativeSelectOption>
                                {events.map((event) => (
                                    <NativeSelectOption
                                        key={event}
                                        value={event}
                                    >
                                        {formatEvent(event)}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                value={form.branch_id}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        branch_id: event.target.value,
                                    })
                                }
                                className="w-full"
                            >
                                <NativeSelectOption value="">
                                    All branches
                                </NativeSelectOption>
                                {branches.map((branch) => (
                                    <NativeSelectOption
                                        key={branch.id}
                                        value={branch.id}
                                    >
                                        {branch.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                value={form.actor_id}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        actor_id: event.target.value,
                                    })
                                }
                                className="w-full"
                            >
                                <NativeSelectOption value="">
                                    All actors
                                </NativeSelectOption>
                                {actors.map((actor) => (
                                    <NativeSelectOption
                                        key={actor.id}
                                        value={actor.id}
                                    >
                                        {actor.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                value={form.subject_type}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        subject_type: event.target.value,
                                    })
                                }
                                className="w-full"
                            >
                                <NativeSelectOption value="">
                                    All records
                                </NativeSelectOption>
                                {subjectTypes.map((subjectType) => (
                                    <NativeSelectOption
                                        key={subjectType.value}
                                        value={subjectType.value}
                                    >
                                        {subjectType.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <Button type="submit">Apply</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    const emptyFilters = {
                                        search: '',
                                        event: '',
                                        branch_id: '',
                                        actor_id: '',
                                        subject_type: '',
                                    };

                                    setForm(emptyFilters);
                                    applyFilters(emptyFilters);
                                }}
                            >
                                <FilterX />
                                Clear
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Events</CardTitle>
                        <CardDescription>
                            Audit entries are separate from ordinary business
                            activity feeds.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y">
                            {activities.map((activity) => (
                                <Collapsible key={activity.id}>
                                    <div className="grid gap-3 py-4 lg:grid-cols-[minmax(0,1fr)_11rem_9rem] lg:items-start">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge>
                                                    {formatEvent(
                                                        activity.event,
                                                    )}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {activity.subject_label}
                                                </Badge>
                                                {activity.branch_name && (
                                                    <Badge variant="outline">
                                                        {activity.branch_name}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="mt-2 font-medium">
                                                {activity.description}
                                            </div>
                                            <div className="mt-1 text-sm text-muted-foreground">
                                                {activity.actor_name ??
                                                    'System'}{' '}
                                                {activity.actor_email
                                                    ? `(${activity.actor_email})`
                                                    : ''}
                                            </div>
                                            <div className="mt-1 truncate text-xs text-muted-foreground">
                                                Record ID:{' '}
                                                {activity.subject_id ?? '-'}
                                            </div>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {activity.created_at ?? '-'}
                                        </div>
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="justify-self-start lg:justify-self-end"
                                            >
                                                Details
                                                <ChevronDown />
                                            </Button>
                                        </CollapsibleTrigger>
                                    </div>
                                    <CollapsibleContent>
                                        <div className="grid gap-4 pb-4 lg:grid-cols-2">
                                            <div className="rounded-md border bg-muted/30 p-3">
                                                <div className="text-xs font-medium text-muted-foreground uppercase">
                                                    Old values
                                                </div>
                                                <pre className="mt-2 max-h-72 overflow-auto text-xs leading-5">
                                                    {formatJson(
                                                        activity.changes['old'],
                                                    )}
                                                </pre>
                                            </div>
                                            <div className="rounded-md border bg-muted/30 p-3">
                                                <div className="text-xs font-medium text-muted-foreground uppercase">
                                                    New values
                                                </div>
                                                <pre className="mt-2 max-h-72 overflow-auto text-xs leading-5">
                                                    {formatJson(
                                                        activity.changes[
                                                            'attributes'
                                                        ],
                                                    )}
                                                </pre>
                                            </div>
                                            <div className="text-sm text-muted-foreground lg:col-span-2">
                                                <Separator className="mb-3" />
                                                <div className="grid gap-2 md:grid-cols-3">
                                                    <div>
                                                        IP:{' '}
                                                        {activity.ip_address ??
                                                            '-'}
                                                    </div>
                                                    <div>
                                                        Reason:{' '}
                                                        {activity.reason ?? '-'}
                                                    </div>
                                                    <div className="truncate">
                                                        User agent:{' '}
                                                        {activity.user_agent ??
                                                            '-'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            ))}
                            {activities.length === 0 && (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    No audit events match the current filters.
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
