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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { UserDialog } from './partials/user-dialog';
import type {
    AccessUser,
    BranchOption,
    StaffOption,
} from './partials/user-form';

type Props = {
    users: AccessUser[];
    roles: string[];
    permissions: string[];
    staff: StaffOption[];
    branches: BranchOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Access control', href: '/users' },
    { title: 'Users', href: '/users' },
];

export default function UsersIndex({
    users,
    roles,
    permissions,
    staff,
    branches,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('active');
    const debouncedSearch = useDebouncedValue(search);

    const filteredUsers = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return users.filter((user) => {
            const matchesStatus =
                (status === 'active' && user.is_active) ||
                (status === 'inactive' && !user.is_active);
            const matchesSearch =
                !term ||
                [
                    user.name,
                    user.email,
                    user.staff_number ?? '',
                    user.branch_name ?? '',
                    user.position_name ?? '',
                    ...user.branch_ids.map(
                        (branchId) =>
                            branches.find((branch) => branch.id === branchId)
                                ?.name ?? '',
                    ),
                    ...user.roles,
                    ...user.permissions,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [branches, debouncedSearch, status, users]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Users
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Admin-created user accounts for the current ERP
                                tenant.
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
                                    placeholder="Search users"
                                    className="w-full pl-9 sm:w-64"
                                />
                            </div>
                        </div>
                    </div>
                    <div className="lg:ml-auto">
                        <UserDialog
                            roles={roles}
                            permissions={permissions}
                            staff={staff}
                            branches={branches}
                        />
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-2">
                        <Button variant="secondary" asChild>
                            <Link href="/users">Users</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/roles">Roles</Link>
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
                        <CardTitle>User accounts</CardTitle>
                        <CardDescription>
                            Deactivation keeps audit history while blocking
                            ordinary access.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            User
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Roles
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Staff
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Branch access
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Last login
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredUsers.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {user.name}
                                                </div>
                                                <div className="text-muted-foreground">{`${user.branch_name ?? '-'} - ${user.position_name ?? '-'}`}</div>
                                                <div className="hidden">
                                                    {user.email}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant="secondary"
                                                        >
                                                            {role}
                                                        </Badge>
                                                    ))}
                                                    {user.is_director && (
                                                        <Badge>Director</Badge>
                                                    )}
                                                    {user.permissions.map(
                                                        (permission) => (
                                                            <Badge
                                                                key={permission}
                                                                variant="outline"
                                                            >
                                                                {permission}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {user.staff_number ?? '-'}
                                                </div>
                                                <div className="text-muted-foreground">{`${user.branch_name ?? '-'} - ${user.position_name ?? '-'}`}</div>
                                                <div className="hidden">
                                                    {user.branch_name ?? '-'} -
                                                    {user.position_name ?? '-'}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {user.branch_ids.map(
                                                        (branchId) => {
                                                            const branch =
                                                                branches.find(
                                                                    (item) =>
                                                                        item.id ===
                                                                        branchId,
                                                                );

                                                            return (
                                                                <Badge
                                                                    key={
                                                                        branchId
                                                                    }
                                                                    variant={
                                                                        user.default_branch_id ===
                                                                        branchId
                                                                            ? 'default'
                                                                            : 'secondary'
                                                                    }
                                                                >
                                                                    {branch
                                                                        ? `${branch.name} (${branch.code})`
                                                                        : branchId}
                                                                </Badge>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {user.last_login_at ?? '-'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        user.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {user.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <UserDialog
                                                        user={user}
                                                        roles={roles}
                                                        permissions={
                                                            permissions
                                                        }
                                                        staff={staff}
                                                        branches={branches}
                                                    />
                                                    <Button
                                                        variant={
                                                            user.is_active
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: user.is_active
                                                                    ? 'Deactivate user?'
                                                                    : 'Activate user?',
                                                                description: `${user.name} will ${user.is_active ? 'not be able to sign in' : 'be able to sign in again'}.`,
                                                                confirmLabel:
                                                                    user.is_active
                                                                        ? 'Deactivate'
                                                                        : 'Activate',
                                                                variant:
                                                                    user.is_active
                                                                        ? 'destructive'
                                                                        : 'default',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/users/${user.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {user.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredUsers.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No users match the current
                                                filters.
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
