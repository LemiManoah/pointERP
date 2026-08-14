import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { SearchableSelect } from '@/components/searchable-select';
import type { BranchSummary, CurrentTenant } from '@/types';

type BranchPageProps = {
    currentTenant: CurrentTenant | null;
    currentBranch: BranchSummary | null;
    accessibleBranches: BranchSummary[];
    canViewAllBranches: boolean;
};

const allBranchesValue = '__all__';

export function BranchSelector() {
    const {
        currentTenant,
        currentBranch,
        accessibleBranches,
        canViewAllBranches,
    } =
        usePage<BranchPageProps>().props;

    const selectedBranch = currentBranch ?? accessibleBranches[0] ?? null;

    if (currentTenant && !currentTenant.is_multibranch) {
        return (
            <div className="flex min-w-0 items-center gap-2 text-sm">
                <Building2 className="size-4 text-muted-foreground" />
                <span className="truncate font-medium">
                    {selectedBranch?.name ?? 'No branch access'}
                </span>
            </div>
        );
    }

    const options = [
        ...(canViewAllBranches
            ? [
                  {
                      value: allBranchesValue,
                      label: 'All branches',
                      description: 'Consolidated branch view',
                  },
              ]
            : []),
        ...accessibleBranches.map((branch) => ({
            value: branch.id,
            label: branch.name,
            description: `${branch.code} - ${branch.default_currency_code}`,
        })),
    ];

    if (options.length === 0) {
        return (
            <div className="flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                <Building2 className="size-4" />
                <span className="truncate">No branch access</span>
            </div>
        );
    }

    const value = currentBranch?.id ?? allBranchesValue;

    return (
        <div className="flex min-w-0 flex-1 items-center gap-2 sm:max-w-xs">
            <Building2 className="size-4 text-muted-foreground" />
            <SearchableSelect
                value={value}
                options={options}
                placeholder="Select branch"
                searchPlaceholder="Search branches..."
                onValueChange={(nextValue) =>
                    router.put(
                        '/current-branch',
                        {
                            branch_id:
                                nextValue === allBranchesValue
                                    ? null
                                    : nextValue,
                        },
                        {
                            preserveScroll: true,
                        },
                    )
                }
            />
        </div>
    );
}
