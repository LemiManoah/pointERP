import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

const sections = [
    { id: 'company', label: 'Company Staff', href: '/staff' },
    { id: 'workforce', label: 'Project Workforce', href: '/workforce/workers' },
    { id: 'positions', label: 'Positions', href: '/staff-positions' },
    { id: 'deployments', label: 'Deployments', href: '/workforce' },
    { id: 'attendance', label: 'Attendance', href: '/workforce/attendance' },
    { id: 'trades', label: 'Trades', href: '/workforce?tab=trades' },
] as const;

export type StaffSection = (typeof sections)[number]['id'];

export function StaffSectionNav({ active }: { active: StaffSection }) {
    const { auth } = usePage<{ auth: { user: { permissions?: string[] } } }>().props;
    const permissions = auth.user.permissions ?? [];
    const permissionBySection: Record<StaffSection, string> = {
        company: 'resources.staff.manage',
        workforce: 'workforce.deployments.manage',
        positions: 'resources.staff.manage',
        deployments: 'workforce.deployments.manage',
        attendance: 'workforce.view',
        trades: 'workforce.deployments.manage',
    };
    const visibleSections = sections.filter((section) =>
        permissions.includes(permissionBySection[section.id]),
    );

    return (
        <nav aria-label="Staff sections" className="flex gap-2 overflow-x-auto pb-1">
            {visibleSections.map((section) => (
                <Button
                    key={section.id}
                    asChild
                    size="sm"
                    variant={active === section.id ? 'secondary' : 'ghost'}
                    aria-current={active === section.id ? 'page' : undefined}
                >
                    <Link href={section.href}>{section.label}</Link>
                </Button>
            ))}
        </nav>
    );
}
