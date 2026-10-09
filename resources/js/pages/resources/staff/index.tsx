import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { StaffSectionNav } from '@/components/staff-section-nav';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import * as staffRoutes from '@/routes/resources/staff';
import * as workerRoutes from '@/routes/workforce/workers';
import type { BreadcrumbItem } from '@/types';
import { StaffDialog } from './partials/staff-dialog';
import type { Option, Staff } from './partials/staff-form';

type Props = {
    directory: 'company_staff' | 'workforce';
    can: { companyStaff: boolean; workers: boolean; reclassify: boolean };
    staff: Staff[];
    branches: Option[];
    positions: Option[];
    trades: Option[];
    employmentTypes: Option[];
};

export default function StaffIndex({
    directory,
    can,
    staff,
    branches,
    positions,
    trades,
    employmentTypes,
}: Props) {
    const isWorker = directory === 'workforce';
    const title = 'Staff';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title, href: '/staff' },
    ];
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
                        staffMember.phone,
                        staffMember.branch_name,
                        staffMember.position_name,
                        staffMember.primary_trade_name,
                        staffMember.employment_type_label,
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [debouncedSearch, staff, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {isWorker
                                    ? 'Project Workforce'
                                    : 'Company Staff'}
                            </h1>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={
                                    isWorker ? 'Search workers' : 'Search staff'
                                }
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <StaffDialog
                        directory={directory}
                        branches={branches}
                        positions={positions}
                        trades={trades}
                        employmentTypes={employmentTypes}
                    />
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <StaffSectionNav
                        active={isWorker ? 'workforce' : 'company'}
                    />
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {isWorker ? 'Worker records' : 'Staff records'}
                        </CardTitle>
                        <CardDescription>
                            A staff record does not automatically create an ERP
                            login account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Staff</TableHead>
                                    <TableHead>Branch</TableHead>
                                    <TableHead>Position and trade</TableHead>
                                    <TableHead>Employment</TableHead>
                                    <TableHead>ERP account</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredStaff.map((staffMember) => (
                                    <TableRow key={staffMember.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {staffMember.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {staffMember.staff_number}
                                                {staffMember.email && (
                                                    <> · {staffMember.email}</>
                                                )}
                                                {staffMember.phone && (
                                                    <> · {staffMember.phone}</>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {staffMember.branch_name}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                {staffMember.position_name ??
                                                    '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {staffMember.primary_trade_name ??
                                                    'No primary trade'}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {
                                                    staffMember.employment_type_label
                                                }
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {staffMember.has_user
                                                ? 'Enabled'
                                                : 'Not created'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <StaffDialog
                                                    canReclassify={
                                                        can.reclassify
                                                    }
                                                    directory={directory}
                                                    staff={staffMember}
                                                    branches={branches}
                                                    positions={positions}
                                                    trades={trades}
                                                    employmentTypes={
                                                        employmentTypes
                                                    }
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
                                                            description:
                                                                staffMember.name +
                                                                (staffMember.status ===
                                                                'active'
                                                                    ? ' will be deactivated. Any active deployments will be ended today.'
                                                                    : ' will be reactivated and available for new assignments.'),
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
                                                                    isWorker
                                                                        ? workerRoutes.destroy.url(
                                                                              staffMember.id,
                                                                          )
                                                                        : staffRoutes.destroy.url(
                                                                              staffMember.id,
                                                                          ),
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
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {filteredStaff.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No records match the current tab and
                                            search.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
