import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Currency = {
    code: string;
    name: string;
    symbol: string | null;
    tenant_enabled: boolean;
    tenant_default: boolean;
};

type BranchCurrency = {
    id: string;
    currency_code: string;
    currency_name: string | null;
    is_enabled: boolean;
    is_default_transaction_currency: boolean;
    can_receive: boolean;
    can_pay: boolean;
};

type Branch = {
    id: string;
    name: string;
    code: string;
    default_currency_code: string;
    currencies: BranchCurrency[];
};

type Props = {
    tenant: {
        id: string;
        name: string;
        default_currency_code: string;
        multi_currency_enabled: boolean;
    };
    currencies: Currency[];
    branches: Branch[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currency settings', href: '/currency-settings' },
];

export default function CurrencySettingsIndex({
    tenant,
    currencies,
    branches,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('enabled');
    const [branchId, setBranchId] = useState(branches[0]?.id ?? '');
    const [currencyCode, setCurrencyCode] = useState(
        currencies.find((currency) => currency.tenant_enabled)?.code ?? '',
    );
    const debouncedSearch = useDebouncedValue(search);

    const filteredCurrencies = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return currencies.filter((currency) => {
            const matchesStatus =
                (status === 'enabled' && currency.tenant_enabled) ||
                (status === 'disabled' && !currency.tenant_enabled);
            const matchesSearch =
                !term ||
                [currency.code, currency.name, currency.symbol ?? '']
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [currencies, debouncedSearch, status]);

    const tenantEnabledCurrencies = currencies.filter(
        (currency) => currency.tenant_enabled,
    );

    function saveBranchCurrency(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const form = new FormData(event.currentTarget);

        router.post(
            '/currency-settings/branches',
            {
                branch_id: branchId,
                currency_code: currencyCode,
                is_enabled: form.get('is_enabled') === 'on',
                is_default_transaction_currency:
                    form.get('is_default_transaction_currency') === 'on',
                can_receive: form.get('can_receive') === 'on',
                can_pay: form.get('can_pay') === 'on',
            },
            { preserveScroll: true },
        );
    }

    function toggleTenantCurrency(currency: Currency) {
        const submit = () =>
            router.post(
                `/currency-settings/tenant/${currency.code}`,
                {},
                {
                    preserveScroll: true,
                },
            );

        if (!currency.tenant_enabled) {
            submit();

            return;
        }

        confirm({
            title: `Disable ${currency.code}?`,
            description: `${currency.name} will no longer be available for tenant or branch transactions unless it is enabled again.`,
            confirmLabel: 'Disable currency',
            variant: 'destructive',
            onConfirm: submit,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currency settings" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Currency settings
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Enable ISO currencies for the tenant and its
                                existing manager-created branches.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search tenant currencies"
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <Tabs
                        value={status}
                        onValueChange={setStatus}
                        className="lg:ml-auto"
                    >
                        <TabsList>
                            <TabsTrigger value="enabled">Enabled</TabsTrigger>
                            <TabsTrigger value="disabled">Disabled</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Tenant currencies</CardTitle>
                        <CardDescription>
                            The default currency {tenant.default_currency_code}{' '}
                            is protected from disablement.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Currency
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
                                    {filteredCurrencies.map((currency) => (
                                        <tr
                                            key={currency.code}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {currency.code}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {currency.name}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge
                                                        variant={
                                                            currency.tenant_enabled
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {currency.tenant_enabled
                                                            ? 'Enabled'
                                                            : 'Disabled'}
                                                    </Badge>
                                                    {currency.tenant_default && (
                                                        <Badge variant="outline">
                                                            Default
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end">
                                                    <Button
                                                        size="sm"
                                                        variant={
                                                            currency.tenant_enabled
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        disabled={
                                                            currency.tenant_default
                                                        }
                                                        onClick={() =>
                                                            toggleTenantCurrency(
                                                                currency,
                                                            )
                                                        }
                                                    >
                                                        {currency.tenant_enabled
                                                            ? 'Disable'
                                                            : 'Enable'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredCurrencies.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={3}
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

                <Card>
                    <CardHeader>
                        <CardTitle>Branch currencies</CardTitle>
                        <CardDescription>
                            Branches come from the manager app. This page only
                            controls which enabled tenant currencies they may
                            transact in.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={saveBranchCurrency}
                            className="grid gap-4 md:grid-cols-[1fr_1fr_auto]"
                        >
                            <div className="grid gap-2">
                                <Label>Branch</Label>
                                <SearchableSelect
                                    value={branchId}
                                    onValueChange={setBranchId}
                                    options={branches.map((branch) => ({
                                        value: branch.id,
                                        label: branch.name,
                                        description: branch.code,
                                    }))}
                                    placeholder="Select branch"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Currency</Label>
                                <SearchableSelect
                                    value={currencyCode}
                                    onValueChange={setCurrencyCode}
                                    options={tenantEnabledCurrencies.map(
                                        (currency) => ({
                                            value: currency.code,
                                            label: currency.code,
                                            description: currency.name,
                                        }),
                                    )}
                                    placeholder="Select currency"
                                />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit">Save setting</Button>
                            </div>
                            <div className="flex flex-wrap gap-4 md:col-span-3">
                                {[
                                    ['is_enabled', 'Enabled'],
                                    [
                                        'is_default_transaction_currency',
                                        'Default for transactions',
                                    ],
                                    ['can_receive', 'Can receive'],
                                    ['can_pay', 'Can pay'],
                                ].map(([name, label]) => (
                                    <label
                                        key={name}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox name={name} defaultChecked />
                                        {label}
                                    </label>
                                ))}
                            </div>
                        </form>

                        <div className="mt-6 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Branch
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Base
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Enabled currencies
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {branches.map((branch) => (
                                        <tr
                                            key={branch.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="font-medium">
                                                    {branch.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {branch.code}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {branch.default_currency_code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {branch.currencies
                                                        .filter(
                                                            (setting) =>
                                                                setting.is_enabled,
                                                        )
                                                        .map((setting) => (
                                                            <Badge
                                                                key={setting.id}
                                                                variant={
                                                                    setting.is_default_transaction_currency
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {
                                                                    setting.currency_code
                                                                }
                                                            </Badge>
                                                        ))}
                                                    {branch.currencies
                                                        .length === 0 && (
                                                        <span className="text-muted-foreground">
                                                            None configured
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {branches.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No active branches are available
                                                from the manager app.
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
