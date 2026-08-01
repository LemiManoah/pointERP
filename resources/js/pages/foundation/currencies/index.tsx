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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CurrencyDialog } from './partials/currency-dialog';
import type { CurrencyFormData } from './partials/currency-form';

type Currency = {
    code: string;
    name: string;
    symbol: string | null;
    decimal_places: number;
    is_active: boolean;
};

type Props = {
    currencies: Currency[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/foundation/currencies' },
];

export default function CurrenciesIndex({ currencies }: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const debouncedSearch = useDebouncedValue(search);

    const filteredCurrencies = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return currencies.filter((currency) => {
            const matchesStatus =
                status === 'all' ||
                (status === 'active' && currency.is_active) ||
                (status === 'inactive' && !currency.is_active);
            const matchesSearch =
                !term ||
                [currency.code, currency.name, currency.symbol ?? '']
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [currencies, debouncedSearch, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currencies" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Currencies
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            ISO currency references available to tenant and
                            branch currency settings.
                        </p>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search currencies"
                                    className="w-full pl-9 sm:w-64"
                                />
                            </div>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-full sm:w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="lg:ml-auto">
                        <CurrencyDialog />
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Reference currencies</CardTitle>
                        <CardDescription>
                            Tenants cannot invent arbitrary currency codes
                            through ordinary UI.
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
                                            Name
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Symbol
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Decimals
                                        </th>
                                        <th className="py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCurrencies.map((currency) => (
                                        <tr
                                            key={currency.code}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {currency.code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {currency.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {currency.symbol ?? '-'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {currency.decimal_places}
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    variant={
                                                        currency.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {currency.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <CurrencyDialog
                                                        currency={
                                                            currency as CurrencyFormData
                                                        }
                                                    />
                                                    <Button
                                                        variant={
                                                            currency.is_active
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: currency.is_active
                                                                    ? 'Deactivate currency?'
                                                                    : 'Activate currency?',
                                                                description: `${currency.code} will ${currency.is_active ? 'no longer' : 'again'} be available in setup workflows.`,
                                                                confirmLabel:
                                                                    currency.is_active
                                                                        ? 'Deactivate'
                                                                        : 'Activate',
                                                                variant:
                                                                    currency.is_active
                                                                        ? 'destructive'
                                                                        : 'default',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/foundation/currencies/${currency.code}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {currency.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredCurrencies.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No currencies match the current
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
