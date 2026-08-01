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
import { StaffPositionDialog } from './partials/staff-position-dialog';
import type { StaffPosition } from './partials/staff-position-form';

type Props = {
    positions: StaffPosition[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Staff positions', href: '/resources/staff-positions' },
];

export default function StaffPositionsIndex({ positions }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);
    const filteredPositions = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return positions.filter(
            (position) =>
                !term ||
                [position.name, position.code]
                    .join(' ')
                    .toLowerCase()
                    .includes(term),
        );
    }, [debouncedSearch, positions]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Staff positions" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Staff positions
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Positions are assigned to staff before user
                                access is created.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search positions"
                                className="w-full pl-9 sm:w-64"
                            />
                        </div>
                    </div>
                    <StaffPositionDialog />
                </div>

                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/resources/staff">Staff</Link>
                    </Button>
                    <Button variant="secondary" asChild>
                        <Link href="/resources/staff-positions">Positions</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Positions</CardTitle>
                        <CardDescription>
                            Inactive positions stay on historic staff records.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Code
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Position
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Staff
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
                                    {filteredPositions.map((position) => (
                                        <tr
                                            key={position.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {position.code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {position.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {position.staff_count}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        position.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {position.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <StaffPositionDialog
                                                        position={position}
                                                    />
                                                    <Button
                                                        variant={
                                                            position.is_active
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: position.is_active
                                                                    ? 'Deactivate position?'
                                                                    : 'Activate position?',
                                                                description: `${position.name} will ${position.is_active ? 'no longer' : 'again'} be available for staff records.`,
                                                                confirmLabel:
                                                                    position.is_active
                                                                        ? 'Deactivate'
                                                                        : 'Activate',
                                                                variant:
                                                                    position.is_active
                                                                        ? 'destructive'
                                                                        : 'default',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/resources/staff-positions/${position.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {position.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
