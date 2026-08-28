export type Option = { id: string; name: string; code: string };
export type BranchOption = Option & { default_currency_code: string };
export type StoreOption = Option & { branch_id: string };
export type SupplierOption = Option & {
    branch_id: string | null;
    type: string;
};
export type UnitOption = Option & { symbol: string | null };
export type ProcurementItemOption = Option & {
    stock_unit_id: string;
    preferred_supplier_id: string | null;
    default_unit_cost: string | null;
    conversions: { unit_id: string; multiplier: string; divisor: string }[];
};
export type ProcurementOptions = {
    branches: BranchOption[];
    defaultBranchId: string;
    canChangeBranch: boolean;
    stores: StoreOption[];
    suppliers: SupplierOption[];
    items: ProcurementItemOption[];
    units: UnitOption[];
    canOverridePrice: boolean;
    canViewCosts: boolean;
};
