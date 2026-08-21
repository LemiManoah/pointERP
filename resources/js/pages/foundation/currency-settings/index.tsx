import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { CurrencyDialog } from '../currencies/partials/currency-dialog';
import type { CurrencyFormData } from '../currencies/partials/currency-form';
import { ExchangeRateDialog } from '../exchange-rates/partials/exchange-rate-dialog';
import type {
    BranchOption,
    CurrencyOption,
    ExchangeRate,
} from '../exchange-rates/partials/exchange-rate-form';

type TenantCurrency = {
    code: string;
    name: string;
    symbol: string | null;
    tenant_enabled: boolean;
    tenant_default: boolean;
};

type ReferenceCurrency = CurrencyFormData & {
    is_active: boolean;
};

type Props = {
    tenant: {
        id: string;
        name: string;
        default_currency_code: string;
        is_multibranch: boolean;
        multi_currency_enabled: boolean;
    };
    currencies: TenantCurrency[];
    referenceCurrencies: ReferenceCurrency[];
    branches: BranchOption[];
    exchangeRates: ExchangeRate[];
    defaultBranchId: string | null;
    canManageFacilityWide: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currency', href: '/currency-settings' },
];

export default function CurrencySettingsIndex({
    tenant,
    currencies,
    referenceCurrencies,
    branches,
    exchangeRates,
    defaultBranchId,
    canManageFacilityWide,
}: Props) {
    const confirm = useConfirmDialog();
    const [activeTab, setActiveTab] = useState('settings');
    const [currencySearch, setCurrencySearch] = useState('');
    const [currencyStatus, setCurrencyStatus] = useState('enabled');
    const [rateSearch, setRateSearch] = useState('');
    const [rateStatus, setRateStatus] = useState('draft');
    const [referenceSearch, setReferenceSearch] = useState('');
    const [referenceStatus, setReferenceStatus] = useState('active');
    const debouncedCurrencySearch = useDebouncedValue(currencySearch);
    const debouncedRateSearch = useDebouncedValue(rateSearch);
    const debouncedReferenceSearch = useDebouncedValue(referenceSearch);

    useEffect(() => {
        if (!tenant.multi_currency_enabled && activeTab !== 'settings') {
            setActiveTab('settings');
        }
    }, [activeTab, tenant.multi_currency_enabled]);

    const tenantEnabledCurrencies = useMemo(
        () => currencies.filter((currency) => currency.tenant_enabled),
        [currencies],
    );

    const filteredTenantCurrencies = useMemo(() => {
        const term = debouncedCurrencySearch.trim().toLowerCase();

        return currencies.filter((currency) => {
            const matchesStatus =
                (currencyStatus === 'enabled' && currency.tenant_enabled) ||
                (currencyStatus === 'disabled' && !currency.tenant_enabled);
            const matchesSearch =
                !term ||
                [currency.code, currency.name, currency.symbol ?? '']
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [currencies, currencyStatus, debouncedCurrencySearch]);

    const filteredExchangeRates = useMemo(() => {
        const term = debouncedRateSearch.trim().toLowerCase();

        return exchangeRates.filter((rate) => {
            const matchesStatus = rate.status === rateStatus;
            const matchesSearch =
                !term ||
                [
                    rate.branch_name ?? 'facility-wide',
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
    }, [debouncedRateSearch, exchangeRates, rateStatus]);

    const filteredReferenceCurrencies = useMemo(() => {
        const term = debouncedReferenceSearch.trim().toLowerCase();

        return referenceCurrencies.filter((currency) => {
            const matchesStatus =
                (referenceStatus === 'active' && currency.is_active) ||
                (referenceStatus === 'inactive' && !currency.is_active);
            const matchesSearch =
                !term ||
                [currency.code, currency.name, currency.symbol ?? '']
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [debouncedReferenceSearch, referenceCurrencies, referenceStatus]);

    const exchangeCurrencyOptions: CurrencyOption[] =
        tenantEnabledCurrencies.map((currency) => ({
            code: currency.code,
            name: currency.name,
        }));

    function toggleMultiCurrency() {
        const submit = () =>
            router.put(
                '/currency-settings/multi-currency',
                {},
                { preserveScroll: true },
            );

        if (!tenant.multi_currency_enabled) {
            submit();

            return;
        }

        confirm({
            title: 'Turn off multi-currency?',
            description:
                'The facility will keep its existing currency records, but users will only work in the default currency until multi-currency is turned on again.',
            confirmLabel: 'Turn off',
            variant: 'destructive',
            onConfirm: submit,
        });
    }

    function toggleTenantCurrency(currency: TenantCurrency) {
        const submit = () =>
            router.put(
                `/currency-settings/tenant/${currency.code}`,
                {},
                { preserveScroll: true },
            );

        if (!currency.tenant_enabled) {
            submit();

            return;
        }

        confirm({
            title: `Remove ${currency.code} from facility currencies?`,
            description: `${currency.name} will no longer be available for new transactions or exchange rates unless it is added again.`,
            confirmLabel: 'Remove currency',
            variant: 'destructive',
            onConfirm: submit,
        });
    }

    function toggleReferenceCurrency(currency: ReferenceCurrency) {
        confirm({
            title: currency.is_active
                ? 'Deactivate reference currency?'
                : 'Activate reference currency?',
            description: `${currency.code} will ${currency.is_active ? 'no longer' : 'again'} be available in setup workflows.`,
            confirmLabel: currency.is_active ? 'Deactivate' : 'Activate',
            variant: currency.is_active ? 'destructive' : 'default',
            onConfirm: () =>
                router.delete(`/currencies/${currency.code}`, {
                    preserveScroll: true,
                }),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currency" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Currency
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Turn multi-currency on, choose facility currencies,
                            then maintain exchange rates between them.
                        </p>
                    </div>
                    <Button
                        variant={
                            tenant.multi_currency_enabled
                                ? 'destructive'
                                : 'default'
                        }
                        onClick={toggleMultiCurrency}
                    >
                        {tenant.multi_currency_enabled
                            ? 'Turn off multi-currency'
                            : 'Turn on multi-currency'}
                    </Button>
                </div>

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                        {tenant.multi_currency_enabled && (
                            <>
                                <TabsTrigger value="exchange-rates">
                                    Exchange rates
                                </TabsTrigger>
                                <TabsTrigger value="currencies">
                                    Currencies
                                </TabsTrigger>
                            </>
                        )}
                    </TabsList>
                </Tabs>

                {activeTab === 'settings' && (
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Facility currency setup</CardTitle>
                                <CardDescription>
                                    {tenant.name} uses{' '}
                                    {tenant.default_currency_code} as its
                                    default currency.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        Default: {tenant.default_currency_code}
                                    </Badge>
                                    <Badge
                                        variant={
                                            tenant.multi_currency_enabled
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {tenant.multi_currency_enabled
                                            ? 'Multi-currency on'
                                            : 'Multi-currency off'}
                                    </Badge>
                                </div>
                                {!tenant.multi_currency_enabled && (
                                    <p className="text-sm text-muted-foreground">
                                        Turn on multi-currency before adding
                                        facility currencies or exchange rates.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {tenant.multi_currency_enabled && (
                            <Card>
                                <CardHeader>
                                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <CardTitle>
                                                Facility currencies
                                            </CardTitle>
                                            <CardDescription>
                                                Add the currencies this facility
                                                can transact in. If a currency
                                                is missing, add it from the
                                                Currencies tab.
                                            </CardDescription>
                                        </div>
                                        <Tabs
                                            value={currencyStatus}
                                            onValueChange={setCurrencyStatus}
                                        >
                                            <TabsList>
                                                <TabsTrigger value="enabled">
                                                    Added
                                                </TabsTrigger>
                                                <TabsTrigger value="disabled">
                                                    Available
                                                </TabsTrigger>
                                            </TabsList>
                                        </Tabs>
                                    </div>
                                    <div className="relative mt-2">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={currencySearch}
                                            onChange={(event) =>
                                                setCurrencySearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Search currencies"
                                            className="w-full pl-9 sm:w-72"
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <CurrencyTable
                                        currencies={filteredTenantCurrencies}
                                        onToggle={toggleTenantCurrency}
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {activeTab === 'exchange-rates' &&
                    tenant.multi_currency_enabled && (
                        <Card>
                            <CardHeader>
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <CardTitle>Exchange rates</CardTitle>
                                        <CardDescription>
                                            A rate means 1 FROM currency equals
                                            the entered amount in TO currency.
                                            Old approved rates are kept as
                                            history when a newer rate replaces
                                            them.
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-col gap-3 lg:items-end">
                                        <Tabs
                                            value={rateStatus}
                                            onValueChange={setRateStatus}
                                        >
                                            <TabsList>
                                                <TabsTrigger value="draft">
                                                    Draft
                                                </TabsTrigger>
                                                <TabsTrigger value="approved">
                                                    Approved
                                                </TabsTrigger>
                                                <TabsTrigger value="superseded">
                                                    Old rates
                                                </TabsTrigger>
                                            </TabsList>
                                        </Tabs>
                                        <ExchangeRateDialog
                                            branches={branches}
                                            currencies={exchangeCurrencyOptions}
                                            isMultiBranch={
                                                tenant.is_multibranch
                                            }
                                            defaultBranchId={defaultBranchId}
                                            canManageFacilityWide={
                                                canManageFacilityWide
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="relative mt-2">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={rateSearch}
                                        onChange={(event) =>
                                            setRateSearch(event.target.value)
                                        }
                                        placeholder="Search rates"
                                        className="w-full pl-9 sm:w-72"
                                    />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <ExchangeRatesTable
                                    exchangeRates={filteredExchangeRates}
                                    branches={branches}
                                    currencies={exchangeCurrencyOptions}
                                    isMultiBranch={tenant.is_multibranch}
                                    defaultBranchId={defaultBranchId}
                                    canManageFacilityWide={
                                        canManageFacilityWide
                                    }
                                    confirm={confirm}
                                />
                            </CardContent>
                        </Card>
                    )}

                {activeTab === 'currencies' &&
                    tenant.multi_currency_enabled && (
                        <Card>
                            <CardHeader>
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <CardTitle>
                                            Reference currencies
                                        </CardTitle>
                                        <CardDescription>
                                            Add a missing ISO currency here,
                                            then return to Settings to add it to
                                            the facility.
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-col gap-3 lg:items-end">
                                        <Tabs
                                            value={referenceStatus}
                                            onValueChange={setReferenceStatus}
                                        >
                                            <TabsList>
                                                <TabsTrigger value="active">
                                                    Active
                                                </TabsTrigger>
                                                <TabsTrigger value="inactive">
                                                    Inactive
                                                </TabsTrigger>
                                            </TabsList>
                                        </Tabs>
                                        <CurrencyDialog />
                                    </div>
                                </div>
                                <div className="relative mt-2">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={referenceSearch}
                                        onChange={(event) =>
                                            setReferenceSearch(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Search reference currencies"
                                        className="w-full pl-9 sm:w-72"
                                    />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <ReferenceCurrenciesTable
                                    currencies={filteredReferenceCurrencies}
                                    onToggle={toggleReferenceCurrency}
                                />
                            </CardContent>
                        </Card>
                    )}
            </div>
        </AppLayout>
    );
}

function CurrencyTable({
    currencies,
    onToggle,
}: {
    currencies: TenantCurrency[];
    onToggle: (currency: TenantCurrency) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Currency</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {currencies.map((currency) => (
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
                                            ? 'Added'
                                            : 'Available'}
                                    </Badge>
                                    {currency.tenant_default && (
                                        <Badge variant="outline">Default</Badge>
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
                                        disabled={currency.tenant_default}
                                        onClick={() => onToggle(currency)}
                                    >
                                        {currency.tenant_enabled
                                            ? 'Remove'
                                            : 'Add'}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                    {currencies.length === 0 && (
                        <tr>
                            <td
                                colSpan={3}
                                className="py-8 text-center text-muted-foreground"
                            >
                                No currencies match the current filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function ExchangeRatesTable({
    exchangeRates,
    branches,
    currencies,
    isMultiBranch,
    defaultBranchId,
    canManageFacilityWide,
    confirm,
}: {
    exchangeRates: ExchangeRate[];
    branches: BranchOption[];
    currencies: CurrencyOption[];
    isMultiBranch: boolean;
    defaultBranchId: string | null;
    canManageFacilityWide: boolean;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Scope</th>
                        <th className="py-3 pr-4 font-medium">Direction</th>
                        <th className="py-3 pr-4 font-medium">Effective</th>
                        <th className="py-3 pr-4 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {exchangeRates.map((rate) => (
                        <tr key={rate.id} className="border-b last:border-0">
                            <td className="py-3 pr-4">
                                {rate.branch_name ?? 'Facility-wide'}
                            </td>
                            <td className="py-3 pr-4">
                                1 {rate.from_currency_code} ={' '}
                                {formatNumber(rate.rate)}{' '}
                                {rate.to_currency_code}
                            </td>
                            <td className="py-3 pr-4">{rate.effective_date}</td>
                            <td className="py-3 pr-4">
                                <Badge
                                    variant={
                                        rate.status === 'approved'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {rate.status === 'superseded'
                                        ? 'old rate'
                                        : rate.status}
                                </Badge>
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    <ExchangeRateDialog
                                        exchangeRate={rate}
                                        branches={branches}
                                        currencies={currencies}
                                        isMultiBranch={isMultiBranch}
                                        defaultBranchId={defaultBranchId}
                                        canManageFacilityWide={
                                            canManageFacilityWide
                                        }
                                    />
                                    {rate.status === 'draft' && (
                                        <>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    confirm({
                                                        title: 'Approve exchange rate?',
                                                        description:
                                                            'This locks the draft and moves older approved rates for the same pair into Old rates.',
                                                        confirmLabel: 'Approve',
                                                        onConfirm: () =>
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
                                                            'Only the draft will be removed. Approved history is kept.',
                                                        confirmLabel: 'Delete',
                                                        variant: 'destructive',
                                                        onConfirm: () =>
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
                    {exchangeRates.length === 0 && (
                        <tr>
                            <td
                                colSpan={5}
                                className="py-8 text-center text-muted-foreground"
                            >
                                No exchange rates match the current filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function ReferenceCurrenciesTable({
    currencies,
    onToggle,
}: {
    currencies: ReferenceCurrency[];
    onToggle: (currency: ReferenceCurrency) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-3 pr-4 font-medium">Code</th>
                        <th className="py-3 pr-4 font-medium">Name</th>
                        <th className="py-3 pr-4 font-medium">Symbol</th>
                        <th className="py-3 pr-4 font-medium">Decimals</th>
                        <th className="py-3 font-medium">Status</th>
                        <th className="py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {currencies.map((currency) => (
                        <tr
                            key={currency.code}
                            className="border-b last:border-0"
                        >
                            <td className="py-3 pr-4 font-medium">
                                {currency.code}
                            </td>
                            <td className="py-3 pr-4">{currency.name}</td>
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
                                    {currency.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </td>
                            <td className="py-3">
                                <div className="flex justify-end gap-2">
                                    <CurrencyDialog currency={currency} />
                                    <Button
                                        variant={
                                            currency.is_active
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        size="sm"
                                        onClick={() => onToggle(currency)}
                                    >
                                        {currency.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                    {currencies.length === 0 && (
                        <tr>
                            <td
                                colSpan={6}
                                className="py-8 text-center text-muted-foreground"
                            >
                                No currencies match the current filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
