import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { SearchableSelect } from '@/components/searchable-select';
import type { BranchSummary } from '@/types';

type BranchPageProps = {
    currentBranch: BranchSummary | null;
    accessibleBranches: BranchSummary[];
    canViewAllBranches: boolean;
};

const allBranchesValue = '__all__';

export function BranchSelector() {
    const { currentBranch, accessibleBranches, canViewAllBranches } =
        usePage<BranchPageProps>().props;

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
        return null;
    }

    const value = currentBranch?.id ?? allBranchesValue;

    return (
        <div className="hidden max-w-xs min-w-56 items-center gap-2 md:flex">
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
