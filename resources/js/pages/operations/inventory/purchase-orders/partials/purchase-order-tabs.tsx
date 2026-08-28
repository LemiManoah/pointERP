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
            className="gap-6"
        >
            <TabsList className="h-auto flex-wrap justify-start">
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
