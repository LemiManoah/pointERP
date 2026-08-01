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
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { RoleDialog } from './partials/role-dialog';
import type { AccessRole } from './partials/role-form';

type Props = {
    roles: AccessRole[];
    permissions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Access control', href: '/access-control/users' },
    { title: 'Roles', href: '/access-control/roles' },
];

export default function RolesIndex({ roles, permissions }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);

    const filteredRoles = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return roles.filter(
            (role) =>
                !term ||
                [role.name, ...role.permissions]
                    .join(' ')
                    .toLowerCase()
                    .includes(term),
        );
    }, [debouncedSearch, roles]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Roles
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Role templates that bundle access permissions
                                for ERP users.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search roles"
                                className="w-full pl-9 sm:w-64"
                            />
                        </div>
                    </div>
                    <div className="lg:ml-auto">
                        <RoleDialog permissions={permissions} />
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/access-control/users">Users</Link>
                    </Button>
                    <Button variant="secondary" asChild>
                        <Link href="/access-control/roles">Roles</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles and permissions</CardTitle>
                        <CardDescription>
                            Assigned roles cannot be deleted until users are
                            moved to another role.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Role
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Users
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Permissions
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredRoles.map((role) => (
                                        <tr
                                            key={role.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {role.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {role.users_count}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {role.permissions.map(
                                                        (permission) => (
                                                            <Badge
                                                                key={permission}
                                                                variant="secondary"
                                                            >
                                                                {permission}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <RoleDialog
                                                        role={role}
                                                        permissions={
                                                            permissions
                                                        }
                                                    />
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={
                                                            role.users_count > 0
                                                        }
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Delete role?',
                                                                description: `${role.name} will be removed from access control. This cannot be undone.`,
                                                                confirmLabel:
                                                                    'Delete',
                                                                variant:
                                                                    'destructive',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/access-control/roles/${role.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredRoles.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No roles match the current
                                                search.
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
