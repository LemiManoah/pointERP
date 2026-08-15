import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

type NotificationSummary = {
    unread_count: number;
    latest: Array<{
        id: string;
        title: string;
        message: string;
        severity: string;
        action_url: string | null;
        created_at: string;
    }>;
};

const severityClass: Record<string, string> = {
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    critical: 'bg-red-500',
    info: 'bg-blue-500',
};

export function NotificationBell() {
    const { auth, notificationSummary } = usePage<{
        auth: Auth;
        notificationSummary: NotificationSummary;
    }>().props;

    if (!auth.user.permissions?.includes('notifications.view')) {
        return null;
    }

    function openNotification(id: string, actionUrl: string | null) {
        router.patch(
            `/notifications/${id}`,
            { read: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (actionUrl) {
                        router.visit(actionUrl);
                    }
                },
            },
        );
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Notifications"
                    className="relative"
                >
                    <Bell />
                    {notificationSummary.unread_count > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-4 text-white">
                            {Math.min(notificationSummary.unread_count, 99)}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0 sm:w-96">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div>
                        <div className="font-medium">Notifications</div>
                        <div className="text-xs text-muted-foreground">
                            {notificationSummary.unread_count} unread
                        </div>
                    </div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/notifications">View all</Link>
                    </Button>
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {notificationSummary.latest.map((notification) => (
                        <button
                            key={notification.id}
                            type="button"
                            className="flex w-full gap-3 border-b px-4 py-3 text-left last:border-b-0 hover:bg-muted/50"
                            onClick={() =>
                                openNotification(
                                    notification.id,
                                    notification.action_url,
                                )
                            }
                        >
                            <span
                                className={cn(
                                    'mt-1.5 size-2 shrink-0 rounded-full',
                                    severityClass[notification.severity] ??
                                        severityClass.info,
                                )}
                            />
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-medium">
                                    {notification.title}
                                </span>
                                <span className="mt-1 line-clamp-2 block text-xs text-muted-foreground">
                                    {notification.message}
                                </span>
                            </span>
                        </button>
                    ))}
                    {notificationSummary.latest.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                            You have no unread notifications.
                        </div>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
