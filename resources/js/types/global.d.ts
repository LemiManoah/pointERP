import type { Auth, BranchSummary, CurrentTenant } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            currentTenant: CurrentTenant | null;
            currentBranch: BranchSummary | null;
            accessibleBranches: BranchSummary[];
            canViewAllBranches: boolean;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
