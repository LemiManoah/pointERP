export type Option = { value: string; label: string };
export type BranchOption = Option & { currency_code: string };
export type ScopedOption = Option & {
    branch_id?: string | null;
    project_id?: string;
    site_id?: string | null;
};
export type ExpenseCategory = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    requires_evidence: boolean;
    is_active: boolean;
};
export type ExpenseItem = {
    id: string;
    expense_category_id: string;
    code: string;
    name: string;
    description: string | null;
    default_unit_of_measure_id: string | null;
    unit: string | null;
    has_quantity: boolean;
    requires_evidence: boolean;
    is_active: boolean;
};
export type ExpenseRow = {
    id: string;
    expense_number: string;
    expense_date: string;
    payee: string;
    branch: string;
    reference: string | null;
    status: string;
    status_label: string;
    currency_code: string;
    total_amount: string | null;
    paid_amount: string | null;
    balance: string | null;
    payment_status: string;
    projects: string;
};
export type PaymentRow = {
    id: string;
    expense_id: string;
    expense_number: string;
    payment_number: string;
    paid_at: string;
    amount: string | null;
    currency_code: string;
    payment_method: string;
    reference: string | null;
    status: string;
    recorded_by: string | null;
    reversal_reason: string | null;
};
