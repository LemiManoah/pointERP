import { Head, router } from '@inertiajs/react';
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
import {
    CustomerDialog,
    type Customer,
    type Option,
} from './partials/customer-dialog';

type Props = {
    customers: Customer[];
    branches: Option[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Companies', href: '/customers' },
];

export default function CustomersIndex({ customers, branches }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('active');
    const debouncedSearch = useDebouncedValue(search);
    const filteredCustomers = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return customers.filter(
            (customer) =>
                customer.status === status &&
                (!term ||
                    [
                        customer.name,
                        customer.code,
                        customer.type,
                        customer.branch_name ?? 'tenant-wide',
                        customer.email ?? '',
                        customer.phone ?? '',
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [customers, debouncedSearch, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Companies
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Clients, subcontractors and suppliers used by
                                project records.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search companies"
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <CustomerDialog branches={branches} />
                </div>

                <div className="flex justify-end">
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Company records</CardTitle>
                        <CardDescription>
                            Branch-specific companies are only visible inside
                            authorised branch scope.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Company
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Branch
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Contact
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCustomers.map((customer) => (
                                        <tr
                                            key={customer.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {customer.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {customer.code}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {customer.type}
                                                </Badge>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {customer.branch_name ??
                                                    'Tenant-wide'}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {customer.email ??
                                                    customer.phone ??
                                                    'None'}
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <CustomerDialog
                                                        customer={customer}
                                                        branches={branches}
                                                    />
                                                    <Button
                                                        variant={
                                                            customer.status ===
                                                            'active'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title:
                                                                    customer.status ===
                                                                    'active'
                                                                        ? 'Deactivate company?'
                                                                        : 'Activate company?',
                                                                description: `${customer.name} will move ${customer.status === 'active' ? 'out of' : 'back into'} active company lists.`,
                                                                confirmLabel:
                                                                    customer.status ===
                                                                    'active'
                                                                        ? 'Deactivate'
                                                                        : 'Activate',
                                                                variant:
                                                                    customer.status ===
                                                                    'active'
                                                                        ? 'destructive'
                                                                        : 'default',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/customers/${customer.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {customer.status ===
                                                        'active'
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredCustomers.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No companies match the current
                                                tab and search.
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
