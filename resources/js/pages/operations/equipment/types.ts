export type Option = { id: string; name: string };
export type BranchOption = Option;
export type ProjectOption = Option & { branch_id: string; reference: string };
export type SiteOption = Option & {
    branch_id: string;
    project_id: string;
    reference: string;
};
export type OwnerOption = Option & {
    branch_id: string | null;
    type: string;
};
export type StaffOption = Option & {
    branch_id: string;
    staff_number: string;
};

export type EquipmentCategory = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    default_meter_type: string;
    default_capacity_unit: string | null;
    fuel_efficiency_basis: string | null;
    expected_fuel_efficiency: string | null;
    fuel_tolerance_percent: string | null;
    is_active: boolean;
};

export type EquipmentLocation = {
    id: string;
    branch_id: string;
    branch_name: string;
    project_id: string | null;
    project_name: string | null;
    site_id: string | null;
    site_name: string | null;
    type: string;
    code: string;
    name: string;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    is_active: boolean;
};

export type EquipmentRecord = {
    id: string;
    branch_id: string;
    branch_name: string;
    equipment_category_id: string;
    category_name: string;
    asset_code: string;
    name: string;
    make: string | null;
    model: string | null;
    model_year: number | null;
    serial_number: string | null;
    registration_number: string | null;
    chassis_number: string | null;
    ownership_type: string;
    owner_customer_id: string | null;
    owner_name: string | null;
    capacity_value: string | null;
    capacity_unit: string | null;
    acquired_on: string | null;
    acquisition_amount: string | null;
    acquisition_currency_code: string | null;
    hire_rate: string | null;
    hire_rate_basis: string | null;
    default_location_id: string | null;
    default_location_name: string | null;
    meter_type: string;
    starting_meter_reading: string | null;
    starting_meter_date: string | null;
    fuel_efficiency_basis: string | null;
    expected_fuel_efficiency: string | null;
    fuel_tolerance_percent: string | null;
    tank_capacity: string | null;
    current_status: string;
    current_location_id: string | null;
    current_location_name: string | null;
    current_project_name: string | null;
    current_site_name: string | null;
    current_custodian_name: string | null;
    current_meter_reading: string | null;
    current_meter_read_at: string | null;
    condition_summary: string | null;
    is_active: boolean;
};

export type EquipmentMeterReading = {
    id: string;
    event_type: string;
    reading_value: string;
    read_at: string;
    previous_reading: string | null;
    usage: string | null;
    status: string;
    corrects_reading_id: string | null;
    corrected_value: string | null;
    reason: string | null;
    evidence_note: string | null;
    decision_note: string | null;
    recorded_by: string | null;
    approved_by: string | null;
    rejected_by: string | null;
    can_correct: boolean;
    can_approve: boolean;
};

export type EquipmentAssignment = {
    id: string;
    status: string;
    project_name: string;
    site_name: string;
    location_name: string;
    return_location_name: string | null;
    custodian_name: string | null;
    custodian_employer: string | null;
    assigned_at: string;
    expected_return_at: string | null;
    returned_at: string | null;
    handover_meter_reading: string | null;
    return_meter_reading: string | null;
    handover_condition: string;
    return_condition: string | null;
    assignment_notes: string | null;
    return_notes: string | null;
    handed_over_by: string;
    accepted_return_by: string | null;
    can_return: boolean;
};

export type EquipmentTransfer = {
    id: string;
    status: string;
    source_branch_name: string;
    source_location_name: string;
    destination_branch_name: string;
    destination_location_name: string;
    destination_project_name: string | null;
    destination_site_name: string | null;
    reason: string;
    transport_reference: string | null;
    requested_at: string;
    approved_at: string | null;
    dispatched_at: string | null;
    received_at: string | null;
    dispatch_meter_reading: string | null;
    receipt_meter_reading: string | null;
    dispatch_condition: string | null;
    receipt_condition: string | null;
    requested_by: string;
    can_approve: boolean;
    can_dispatch: boolean;
    can_receive: boolean;
};

export type EquipmentLocationConfirmation = {
    id: string;
    location_name: string;
    observed_at: string;
    observed_status: string | null;
    condition_observation: string | null;
    note: string | null;
    confirmed_by: string;
};
