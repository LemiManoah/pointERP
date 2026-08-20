export type Category = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
};
export type Unit = {
    id: string;
    tenant_id: string | null;
    code: string;
    name: string;
    symbol: string | null;
    quantity_dimension:
        | 'mass'
        | 'volume'
        | 'length'
        | 'area'
        | 'count'
        | 'time';
    is_base_unit: boolean;
    is_active: boolean;
};
export type Item = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    material_class:
        | 'consumable'
        | 'construction_material'
        | 'spare_part'
        | 'fuel_related'
        | 'other';
    tracking_type: 'none' | 'serial' | 'batch' | 'other';
    batch_number: string | null;
    is_expires: boolean;
    is_for_sale: boolean;
    inventory_category_id: string;
    stock_unit_id: string;
    category?: Category;
    stock_unit?: Unit;
    preferred_supplier?: {
        id: string;
        name: string;
        code: string;
        type: string;
    } | null;
    minimum_stock: string | null;
    reorder_quantity: string | null;
    default_unit_cost?: string | null;
    default_selling_price?: string | null;
    is_active: boolean;
};
export type Store = {
    id: string;
    branch_id: string;
    branch?: { id: string; name: string; code: string };
    project_id: string | null;
    project?: { id: string; name: string; reference: string } | null;
    site_id: string | null;
    site?: { id: string; name: string; reference: string } | null;
    equipment_location_id: string | null;
    code: string;
    name: string;
    type: 'warehouse' | 'depot' | 'site_store' | 'temporary' | 'other';
    address: string | null;
    is_active: boolean;
};
export type Option = {
    id: string;
    name: string;
    code?: string;
    reference?: string;
    branch_id?: string;
    project_id?: string;
    type?: string;
};
