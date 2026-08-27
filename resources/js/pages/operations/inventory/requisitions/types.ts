export type BranchOption = { id: string; name: string; code: string };
export type StoreOption = { id: string; branch_id: string; name: string; code: string };
export type ProjectOption = { id: string; branch_id: string; name: string; reference: string };
export type SiteOption = { id: string; branch_id: string; project_id: string; name: string; reference: string };
export type ActivityOption = { id: string; project_id: string; name: string; code: string };
export type ItemOption = { id: string; code: string; name: string; stock_unit_id: string; stock_unit_name: string | null };
export type UnitOption = { id: string; name: string; code: string; symbol: string | null };

export type RequisitionLineForm = {
    inventory_item_id: string;
    description: string;
    unit_of_measure_id: string;
    requested_quantity: string;
    project_activity_id: string;
    purpose: string;
    notes: string;
};

export type RequisitionFormOptions = {
    branches: BranchOption[];
    defaultBranchId: string;
    canChangeBranch: boolean;
    stores: StoreOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    activities: ActivityOption[];
    items: ItemOption[];
    units: UnitOption[];
};
