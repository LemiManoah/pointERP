import { Head, Link, router, useForm } from '@inertiajs/react';
import { Bell, CheckCheck, Search, Settings } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type NotificationRow = {
    id: string;
    category: string;
    severity: string;
    title: string;
    message: string;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
};

type Preference = {
    email_enabled: boolean;
    muted_email_categories: string[];
    digest_frequency: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifications', href: '/notifications' },
];

const categories = [
    'dsr_assignment',
    'dsr_expected',
    'dsr_submitted',
    'dsr_returned',
    'dsr_approved',
    'dsr_correction',
    'dsr_missing',
    'dsr_escalation',
    'document_expiry',
    'approval_pending',
];

export default function NotificationsIndex({
    notifications,
    preference,
}: {
    notifications: NotificationRow[];
    preference: Preference;
}) {
    const [tab, setTab] = useState('unread');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const debouncedSearch = useDebouncedValue(search);

    const filtered = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return notifications.filter((notification) => {
            const matchesTab = tab === 'all' || notification.read_at === null;
            const matchesCategory =
                category === 'all' || notification.category === category;
            const matchesSearch =
                !term ||
                `${notification.title} ${notification.message} ${notification.category}`
                    .toLowerCase()
                    .includes(term);

            return matchesTab && matchesCategory && matchesSearch;
        });
    }, [category, debouncedSearch, notifications, tab]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Notifications
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Assignments, approvals, exceptions and document
                                reminders linked to their source records.
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search notifications"
                                    className="w-full pl-9 sm:w-72"
                                />
                            </div>
                            <SearchableSelect
                                value={category}
                                onValueChange={setCategory}
                                options={[
                                    { value: 'all', label: 'All categories' },
                                    ...categories.map((value) => ({
                                        value,
                                        label: value.replaceAll('_', ' '),
                                    })),
                                ]}
                                className="sm:w-56"
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    '/notifications/read-all',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck />
                            Mark all read
                        </Button>
                        <PreferenceDialog preference={preference} />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="unread">Unread</TabsTrigger>
                            <TabsTrigger value="all">All</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <div className="overflow-hidden rounded-md border">
                    {filtered.map((notification) => (
                        <div
                            key={notification.id}
                            className={cn(
                                'flex flex-col gap-3 border-b p-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between',
                                notification.read_at === null && 'bg-blue-50/50',
                            )}
                        >
                            <div className="flex min-w-0 gap-3">
                                <Bell className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {notification.title}
                                        </span>
                                        <Badge variant="outline">
                                            {notification.severity}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {notification.category.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {notification.message}
                                    </p>
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        {notification.created_at}
                                    </div>
                                </div>
                            </div>
                            <div className="flex shrink-0 justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.patch(
                                            `/notifications/${notification.id}`,
                                            {
                                                read:
                                                    notification.read_at ===
                                                    null,
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Mark{' '}
                                    {notification.read_at === null
                                        ? 'read'
                                        : 'unread'}
                                </Button>
                                {notification.action_url && (
                                    <Button size="sm" asChild>
                                        <Link href={notification.action_url}>
                                            Open
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                    {filtered.length === 0 && (
                        <div className="p-12 text-center text-sm text-muted-foreground">
                            No notifications match this view.
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function PreferenceDialog({ preference }: { preference: Preference }) {
    const [open, setOpen] = useState(false);
    const form = useForm<Preference>({ ...preference });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put('/notification-preferences', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    function toggleCategory(category: string, checked: boolean) {
        form.setData(
            'muted_email_categories',
            checked
                ? [...form.data.muted_email_categories, category]
                : form.data.muted_email_categories.filter(
                      (value) => value !== category,
                  ),
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Settings />
                    Preferences
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Notification preferences</DialogTitle>
                        <DialogDescription>
                            In-app alerts remain enabled. Critical escalation
                            email cannot be muted.
                        </DialogDescription>
                    </DialogHeader>
                    <label className="flex items-center gap-3 rounded-md border p-3">
                        <Checkbox
                            checked={form.data.email_enabled}
                            onCheckedChange={(checked) =>
                                form.setData('email_enabled', checked === true)
                            }
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                Email notifications
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Requires mail delivery to be enabled by the
                                system administrator.
                            </span>
                        </span>
                    </label>
                    <div className="grid gap-2">
                        <Label>Delivery frequency</Label>
                        <SearchableSelect
                            value={form.data.digest_frequency}
                            onValueChange={(value) =>
                                form.setData('digest_frequency', value)
                            }
                            options={[
                                { value: 'immediate', label: 'Immediate' },
                                { value: 'daily', label: 'Daily digest' },
                                { value: 'weekly', label: 'Weekly digest' },
                            ]}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Muted non-critical email categories</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {categories.map((category) => (
                                <label
                                    key={category}
                                    className="flex items-center gap-2 rounded-md border p-3 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.muted_email_categories.includes(
                                            category,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleCategory(
                                                category,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {category.replaceAll('_', ' ')}
                                </label>
                            ))}
                        </div>
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
                            Save preferences
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
