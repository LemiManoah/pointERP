import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProcurementOptions } from '../procurement-types';
import {
    type EditablePurchaseOrder,
    PurchaseOrderForm,
} from './partials/purchase-order-form';

type Props = {
    options: ProcurementOptions;
    purchaseOrder: (EditablePurchaseOrder & { order_number: string }) | null;
};

export default function PurchaseOrderFormPage({
    options,
    purchaseOrder,
}: Props) {
    const title = purchaseOrder
        ? `Edit ${purchaseOrder.order_number}`
        : 'New purchase order';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Purchase orders', href: '/inventory/purchase-orders' },
        {
            title,
            href: purchaseOrder
                ? `/inventory/purchase-orders/${purchaseOrder.id}/edit`
                : '/inventory/purchase-orders/create',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                </div>
                <PurchaseOrderForm
                    options={options}
                    purchaseOrder={purchaseOrder ?? undefined}
                />
            </div>
        </AppLayout>
    );
}
