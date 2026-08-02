export type User = {
    id: string;
    tenant_id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_active: boolean;
    is_director: boolean;
    last_login_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type CurrentTenant = {
    id: string;
    name: string;
    code: string;
    default_currency_code: string;
    is_multibranch: boolean;
    multi_currency_enabled: boolean;
    timezone: string;
    status: string;
};

export type BranchSummary = {
    id: string;
    name: string;
    code: string;
    country_code: string;
    default_currency_code: string;
    status: string;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
