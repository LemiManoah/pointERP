import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    ClipboardCheck,
    FileText,
    FolderKanban,
    Gauge,
    Globe2,
    HardHat,
    Package,
    RefreshCcw,
    ScrollText,
    Settings2,
    ShieldCheck,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { Auth, CurrentTenant } from '@/types';

type SidebarLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    status?: 'ready' | 'next' | 'later';
    permission?: string;
};

type SidebarGroupItem = {
    title: string;
    items: SidebarLink[];
};

const groups: SidebarGroupItem[] = [
    {
        title: 'Foundation',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: Gauge,
                status: 'ready',
            },
            {
                title: 'Countries',
                href: '/countries',
                icon: Globe2,
                status: 'ready',
                permission: 'foundation.countries.manage',
            },
            {
                title: 'Currencies',
                href: '/currencies',
                icon: BadgeDollarSign,
                status: 'ready',
                permission: 'foundation.currencies.manage',
            },
            {
                title: 'Currency settings',
                href: '/currency-settings',
                icon: Settings2,
                status: 'ready',
                permission: 'currency-settings.manage',
            },
            {
                title: 'Exchange rates',
                href: '/exchange-rates',
                icon: RefreshCcw,
                status: 'ready',
                permission: 'exchange-rates.view',
            },
            {
                title: 'Access control',
                href: '/users',
                icon: ShieldCheck,
                status: 'ready',
                permission: 'access-control.users.manage',
            },
            {
                title: 'Audit trail',
                href: '/audit-trail',
                icon: ScrollText,
                status: 'ready',
                permission: 'audit-trail.view',
            },
        ],
    },
    {
        title: 'Operations',
        items: [
            {
                title: 'Projects & sites',
                href: '/projects',
                icon: FolderKanban,
                status: 'ready',
                permission: 'projects.view',
            },
            {
                title: 'Companies',
                href: '/customers',
                icon: Users,
                status: 'ready',
                permission: 'customers.view',
            },
            {
                title: 'Contracts',
                href: '/contracts',
                icon: FileText,
                status: 'ready',
                permission: 'contracts.view',
            },
            {
                title: 'Daily reports',
                href: '#',
                icon: ClipboardCheck,
                status: 'later',
            },
            { title: 'Documents', href: '#', icon: FileText, status: 'later' },
        ],
    },
    {
        title: 'Resources',
        items: [
            {
                title: 'Staff',
                href: '/staff',
                icon: Users,
                status: 'ready',
                permission: 'resources.staff.manage',
            },
            { title: 'Equipment', href: '#', icon: HardHat, status: 'later' },
            { title: 'Inventory', href: '#', icon: Warehouse, status: 'later' },
            { title: 'Procurement', href: '#', icon: Package, status: 'later' },
        ],
    },
];

function StatusLabel({ status }: { status?: SidebarLink['status'] }) {
    if (status === 'next') {
        return (
            <span className="ml-auto text-[10px] text-sidebar-foreground/50 uppercase">
                Next
            </span>
        );
    }

    if (status === 'later') {
        return (
            <span className="ml-auto text-[10px] text-sidebar-foreground/40 uppercase">
                Later
            </span>
        );
    }

    return null;
}

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { auth, currentTenant } = usePage<{
        auth: Auth;
        currentTenant: CurrentTenant | null;
    }>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const permissions = auth.user.permissions ?? [];
    const can = (permission?: string) =>
        !permission || permissions.includes(permission);

    return (
        <Sidebar collapsible="icon" variant="inset" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="mx-2 rounded-md border border-sidebar-border/70 px-3 py-2 group-data-[collapsible=icon]:hidden">
                    <div className="truncate text-sm font-medium">
                        {currentTenant?.name ?? 'No tenant selected'}
                    </div>
                    <div className="mt-1 flex items-center gap-2 text-xs text-sidebar-foreground/60">
                        <span>{currentTenant?.code ?? 'UNSCOPED'}</span>
                        <span className="size-1 rounded-full bg-sidebar-border" />
                        <span>
                            {currentTenant?.default_currency_code ?? '---'}
                        </span>
                    </div>
                </div>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <Collapsible
                        key={group.title}
                        defaultOpen
                        className="group/collapsible"
                    >
                        <SidebarGroup>
                            <SidebarGroupLabel asChild>
                                <CollapsibleTrigger>
                                    {group.title}
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarGroupContent>
                                    <SidebarMenu>
                                        {group.items
                                            .filter((item) =>
                                                can(item.permission),
                                            )
                                            .map((item) => {
                                                const Icon = item.icon;
                                                const disabled =
                                                    item.href === '#';

                                                return (
                                                    <SidebarMenuItem
                                                        key={item.title}
                                                    >
                                                        <SidebarMenuButton
                                                            asChild={!disabled}
                                                            disabled={disabled}
                                                            isActive={
                                                                !disabled &&
                                                                isCurrentUrl(
                                                                    item.href,
                                                                )
                                                            }
                                                            tooltip={{
                                                                children:
                                                                    item.title,
                                                            }}
                                                        >
                                                            {disabled ? (
                                                                <>
                                                                    <Icon />
                                                                    <span>
                                                                        {
                                                                            item.title
                                                                        }
                                                                    </span>
                                                                    <StatusLabel
                                                                        status={
                                                                            item.status
                                                                        }
                                                                    />
                                                                </>
                                                            ) : (
                                                                <Link
                                                                    href={
                                                                        item.href
                                                                    }
                                                                    prefetch
                                                                >
                                                                    <Icon />
                                                                    <span>
                                                                        {
                                                                            item.title
                                                                        }
                                                                    </span>
                                                                    <StatusLabel
                                                                        status={
                                                                            item.status
                                                                        }
                                                                    />
                                                                </Link>
                                                            )}
                                                        </SidebarMenuButton>
                                                    </SidebarMenuItem>
                                                );
                                            })}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
