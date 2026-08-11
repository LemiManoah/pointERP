import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    ContractDialog,
    type Contract,
    type Option,
} from './partials/contract-dialog';

type Props = {
    contracts: Contract[];
    branches: Option[];
    customers: Option[];
    currencies: Option[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contracts', href: '/contracts' },
];

export default function ContractsIndex({
    contracts,
    branches,
    customers,
    currencies,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('active');
    const debouncedSearch = useDebouncedValue(search);
    const filteredContracts = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();
        const activeStatuses = ['draft', 'active', 'completed'];

        return contracts.filter(
            (contract) =>
                (status === 'active'
                    ? activeStatuses.includes(contract.status)
                    : ['closed', 'archived'].includes(contract.status)) &&
                (!term ||
                    [
                        contract.reference,
                        contract.title,
                        contract.customer_name,
                        contract.branch_name,
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [contracts, debouncedSearch, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contracts" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Contracts
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Commercial baselines for projects and later IPC
                                workflows.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search contracts"
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <ContractDialog
                        branches={branches}
                        customers={customers}
                        currencies={currencies}
                    />
                </div>

                <div className="flex justify-end">
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">
                                Closed/archive
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Contract
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Company
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Value
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
                                    {filteredContracts.map((contract) => (
                                        <tr
                                            key={contract.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {contract.reference}
                                                </div>
                                                <div>{contract.title}</div>
                                                <div className="text-muted-foreground">
                                                    {contract.branch_name}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {contract.customer_name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {formatCurrencyAmount(
                                                    contract.currency_code,
                                                    contract.contract_value,
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {contract.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <ContractDialog
                                                        contract={contract}
                                                        branches={branches}
                                                        customers={customers}
                                                        currencies={currencies}
                                                    />
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Change contract archive status?',
                                                                description: `${contract.reference} will move between active and archive contract lists.`,
                                                                confirmLabel:
                                                                    'Continue',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/contracts/${contract.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        Archive
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredContracts.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No contracts match the current
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
