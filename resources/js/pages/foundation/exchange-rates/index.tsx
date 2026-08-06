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
import { ExchangeRateDialog } from './partials/exchange-rate-dialog';
import type {
    BranchOption,
    CurrencyOption,
    ExchangeRate,
} from './partials/exchange-rate-form';

type Props = {
    exchangeRates: ExchangeRate[];
    branches: BranchOption[];
    currencies: CurrencyOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exchange rates', href: '/exchange-rates' },
];

export default function ExchangeRatesIndex({
    exchangeRates,
    branches,
    currencies,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('draft');
    const debouncedSearch = useDebouncedValue(search);

    const filteredRates = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return exchangeRates.filter((rate) => {
            const matchesStatus = rate.status === status;
            const matchesSearch =
                !term ||
                [
                    rate.branch_name ?? 'tenant-wide',
                    rate.from_currency_code,
                    rate.to_currency_code,
                    rate.rate,
                    rate.effective_date,
                    rate.status,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [debouncedSearch, exchangeRates, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exchange rates" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Exchange rates
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Manual dated rates. Direction is always 1 FROM =
                                RATE TO.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search rates"
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-3 lg:ml-auto lg:items-end">
                        <Tabs value={status} onValueChange={setStatus}>
                            <TabsList>
                                <TabsTrigger value="draft">Draft</TabsTrigger>
                                <TabsTrigger value="approved">
                                    Approved
                                </TabsTrigger>
                                <TabsTrigger value="superseded">
                                    Superseded
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <ExchangeRateDialog
                            branches={branches}
                            currencies={currencies}
                        />
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Rates</CardTitle>
                        <CardDescription>
                            Approved rates are preserved; new periods supersede
                            earlier approved rates.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Scope
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Direction
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Effective
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
                                    {filteredRates.map((rate) => (
                                        <tr
                                            key={rate.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                {rate.branch_name ??
                                                    'Tenant-wide'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                1 {rate.from_currency_code} ={' '}
                                                {rate.rate}{' '}
                                                {rate.to_currency_code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {rate.effective_date}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge
                                                    variant={
                                                        rate.status ===
                                                        'approved'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {rate.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <ExchangeRateDialog
                                                        exchangeRate={rate}
                                                        branches={branches}
                                                        currencies={currencies}
                                                    />
                                                    {rate.status ===
                                                        'draft' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="secondary"
                                                                onClick={() =>
                                                                    confirm({
                                                                        title: 'Approve exchange rate?',
                                                                        description:
                                                                            'This will lock the draft and supersede older approved rates for the same scope and pair.',
                                                                        confirmLabel:
                                                                            'Approve',
                                                                        onConfirm:
                                                                            () =>
                                                                                router.post(
                                                                                    `/exchange-rates/${rate.id}/approve`,
                                                                                    {},
                                                                                    {
                                                                                        preserveScroll: true,
                                                                                    },
                                                                                ),
                                                                    })
                                                                }
                                                            >
                                                                Approve
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    confirm({
                                                                        title: 'Delete draft rate?',
                                                                        description:
                                                                            'Only the draft will be removed. Approved history is never deleted here.',
                                                                        confirmLabel:
                                                                            'Delete',
                                                                        variant:
                                                                            'destructive',
                                                                        onConfirm:
                                                                            () =>
                                                                                router.delete(
                                                                                    `/exchange-rates/${rate.id}`,
                                                                                    {
                                                                                        preserveScroll: true,
                                                                                    },
                                                                                ),
                                                                    })
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredRates.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No exchange rates match the
                                                current filters.
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
