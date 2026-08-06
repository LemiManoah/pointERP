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
import { CurrencyForm } from './partials/currency-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Currencies', href: '/currencies' },
    { title: 'New currency', href: '/currencies/create' },
];

export default function CurrenciesCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New currency" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New currency
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a controlled ISO currency for tenant and branch
                        setup.
                    </p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Currency details</CardTitle>
                        <CardDescription>
                            Use a three-letter ISO code such as UGX, USD, or
                            SSP.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CurrencyForm />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
