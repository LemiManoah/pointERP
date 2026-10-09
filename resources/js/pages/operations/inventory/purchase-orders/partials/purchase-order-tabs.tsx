import type { ReactNode } from 'react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

export type PurchaseOrderTab = 'orders' | 'receive' | 'create';

export function PurchaseOrderTabs({
    active,
    canReceive,
    canCreate,
    onValueChange,
    children,
}: {
    active: PurchaseOrderTab;
    canReceive: boolean;
    canCreate: boolean;
    onValueChange: (value: PurchaseOrderTab) => void;
    children: ReactNode;
}) {
    return (
        <Tabs
            value={active}
            onValueChange={(value) => onValueChange(value as PurchaseOrderTab)}
            className="max-w-full min-w-0 gap-6"
        >
            <TabsList className="h-auto max-w-full justify-start overflow-x-auto whitespace-nowrap [&_[data-slot=tabs-trigger]]:shrink-0">
                <TabsTrigger value="orders">Purchase orders</TabsTrigger>
                {canReceive && (
                    <TabsTrigger value="receive">Receive PO</TabsTrigger>
                )}
                {canCreate && (
                    <TabsTrigger value="create">Create PO</TabsTrigger>
                )}
            </TabsList>
            {children}
        </Tabs>
    );
}
