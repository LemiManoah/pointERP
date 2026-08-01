import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CurrencyForm, type CurrencyFormData } from './partials/currency-form';

type Props = {
    currency: CurrencyFormData;
};

export default function CurrenciesEdit({ currency }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Currencies', href: '/foundation/currencies' },
        { title: currency.code, href: `/foundation/currencies/${currency.code}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${currency.code}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit currency
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Update the reference record used by setup workflows.
                    </p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>{currency.code}</CardTitle>
                        <CardDescription>
                            Currency codes are locked after creation to protect
                            references.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CurrencyForm currency={currency} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
