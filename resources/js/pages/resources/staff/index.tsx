import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
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
import {
    Tabs,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { StaffDialog } from './partials/staff-dialog';
import type { Option, Staff } from './partials/staff-form';

type Props = {
    staff: Staff[];
    branches: Option[];
    positions: Option[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Staff', href: '/resources/staff' },
];

export default function StaffIndex({ staff, branches, positions }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('active');
    const debouncedSearch = useDebouncedValue(search);
    const filteredStaff = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return staff.filter(
            (staffMember) =>
                staffMember.status === status &&
                (!term ||
                    [
                        staffMember.staff_number,
                        staffMember.name,
                        staffMember.email,
                        staffMember.branch_name,
                        staffMember.position_name,
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [debouncedSearch, staff, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Staff" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Staff
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Staff records are linked to branches and
                                positions before ERP user access is created.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search staff"
                                className="w-full pl-9 sm:w-64"
                            />
                        </div>
                    </div>
                    <StaffDialog branches={branches} positions={positions} />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-2">
                        <Button variant="secondary" asChild>
                            <Link href="/resources/staff">Staff</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/resources/staff-positions">
                                Positions
                            </Link>
                        </Button>
                    </div>
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Staff records</CardTitle>
                        <CardDescription>
                            User accounts are created by selecting staff from
                            this list.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Staff
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Branch
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Position
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            User
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredStaff.map((staffMember) => (
                                        <tr
                                            key={staffMember.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {staffMember.staff_number}
                                                </div>
                                                <div>{staffMember.name}</div>
                                                <div className="text-muted-foreground">
                                                    {staffMember.email}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {staffMember.branch_name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {staffMember.position_name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        staffMember.status ===
                                                        'active'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {staffMember.status ===
                                                    'active'
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {staffMember.has_user
                                                    ? 'Yes'
                                                    : 'No'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <StaffDialog
                                                        staff={staffMember}
                                                        branches={branches}
                                                        positions={positions}
                                                    />
                                                    <Button
                                                        variant={
                                                            staffMember.status ===
                                                            'active'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title:
                                                                    staffMember.status ===
                                                                    'active'
                                                                        ? 'Deactivate staff member?'
                                                                        : 'Activate staff member?',
                                                                description: `${staffMember.name} will ${staffMember.status === 'active' ? 'no longer' : 'again'} be available for user account assignment.`,
                                                                confirmLabel:
                                                                    staffMember.status ===
                                                                    'active'
                                                                        ? 'Deactivate'
                                                                        : 'Activate',
                                                                variant:
                                                                    staffMember.status ===
                                                                    'active'
                                                                        ? 'destructive'
                                                                        : 'default',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/resources/staff/${staffMember.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {staffMember.status ===
                                                        'active'
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredStaff.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No staff records match the
                                                current tab and search.
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
