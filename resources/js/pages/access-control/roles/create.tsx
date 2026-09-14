import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { RoleForm } from './partials/role-form';

type Props = {
    permissions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Access control', href: '/users' },
    { title: 'Roles', href: '/roles' },
    { title: 'New role', href: '/roles/create' },
];

export default function CreateRole({ permissions }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New role" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">New role</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Name the role and select the permissions its users should receive.
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit('/roles')}>
                        <ArrowLeft />
                        Back to roles
                    </Button>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Role details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RoleForm
                            permissions={permissions}
                            standalone
                            onCancel={() => router.visit('/roles')}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}