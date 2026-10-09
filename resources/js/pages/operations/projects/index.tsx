import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { Eye, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    ProjectDialog,
    type Option,
    type Project,
} from './partials/project-dialog';

type Props = {
    projects: Project[];
    defaultBranchId: string | null;
    branches: Option[];
    customers: Option[];
    contracts: Option[];
    users: Option[];
    currencies: Option[];
    projectTypes: Option[];
    branchFilter: { visible: boolean; branches: Option[] };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects', href: '/projects' },
];

export default function ProjectsIndex({
    projects,
    defaultBranchId,
    branches,
    customers,
    contracts,
    users,
    currencies,
    projectTypes,
    branchFilter,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [managerId, setManagerId] = useState('all');
    const [branchId, setBranchId] = useState('');
    const debouncedSearch = useDebouncedValue(search);
    const managerOptions = useMemo(() => {
        const managers = new Map<string, string>();

        projects.forEach((project) => {
            if (project.manager_id && project.manager_name) {
                managers.set(project.manager_id, project.manager_name);
            }
        });

        return [...managers.entries()].sort((left, right) =>
            left[1].localeCompare(right[1]),
        );
    }, [projects]);
    const filteredProjects = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return projects.filter(
            (project) =>
                (statusFilter === 'all' || project.status === statusFilter) &&
                (managerId === 'all' ||
                    (managerId === 'unassigned'
                        ? project.manager_id === null
                        : project.manager_id === managerId)) &&
                (!branchFilter.visible ||
                    !branchId ||
                    project.branch_id === branchId) &&
                (!term ||
                    [
                        project.reference,
                        project.name,
                        project.branch_name,
                        project.manager_name ?? '',
                        project.customer_name ?? '',
                    ]
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [
        branchFilter.visible,
        branchId,
        debouncedSearch,
        managerId,
        projects,
        statusFilter,
    ]);
    const openCount = filteredProjects.filter((project) =>
        ['planned', 'active', 'on_hold'].includes(project.status),
    ).length;
    const completedCount = filteredProjects.filter((project) =>
        ['completed', 'closed'].includes(project.status),
    ).length;
    const archivedCount = filteredProjects.filter(
        (project) => project.status === 'archived',
    ).length;
    const summaryCards = [
        {
            label: 'Projects',
            value: filteredProjects.length,
        },
        {
            label: 'Open',
            value: openCount,
        },
        {
            label: 'Completed',
            value: completedCount,
        },
        {
            label: 'Archived',
            value: archivedCount,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Projects
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Projects, sites and daily delivery records.
                        </p>
                    </div>
                    <ProjectDialog
                        defaultBranchId={defaultBranchId}
                        branches={branches}
                        customers={customers}
                        contracts={contracts}
                        users={users}
                        currencies={currencies}
                        projectTypes={projectTypes}
                    />
                </div>

                <div
                    aria-label="Project filters"
                    className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center xl:flex-nowrap"
                >
                    <div className="relative min-w-0 flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search projects"
                            className="w-full pl-9"
                        />
                    </div>
                    {branchFilter.visible && (
                        <div className="w-full sm:w-40 sm:flex-none">
                            <NativeSelect
                                aria-label="Filter projects by branch"
                                value={branchId}
                                onChange={(event) =>
                                    setBranchId(event.target.value)
                                }
                            >
                                <NativeSelectOption value="">
                                    All branches
                                </NativeSelectOption>
                                {branchFilter.branches.map((branch) => (
                                    <NativeSelectOption
                                        key={branch.id}
                                        value={branch.id}
                                    >
                                        {branch.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                    )}
                    <div className="w-full sm:w-40 sm:flex-none">
                        <NativeSelect
                            aria-label="Filter projects by status"
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(event.target.value)
                            }
                        >
                            <NativeSelectOption value="all">
                                All statuses
                            </NativeSelectOption>
                            <NativeSelectOption value="planned">
                                Planned
                            </NativeSelectOption>
                            <NativeSelectOption value="active">
                                Active
                            </NativeSelectOption>
                            <NativeSelectOption value="on_hold">
                                On hold
                            </NativeSelectOption>
                            <NativeSelectOption value="completed">
                                Completed
                            </NativeSelectOption>
                            <NativeSelectOption value="closed">
                                Closed
                            </NativeSelectOption>
                            <NativeSelectOption value="archived">
                                Archived
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>
                    <div className="w-full sm:w-44 sm:flex-none">
                        <NativeSelect
                            aria-label="Filter projects by manager"
                            value={managerId}
                            onChange={(event) =>
                                setManagerId(event.target.value)
                            }
                        >
                            <NativeSelectOption value="all">
                                All managers
                            </NativeSelectOption>
                            <NativeSelectOption value="unassigned">
                                Unassigned
                            </NativeSelectOption>
                            {managerOptions.map(([id, name]) => (
                                <NativeSelectOption key={id} value={id}>
                                    {name}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </div>
                </div>

                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => {
                        return (
                            <Card key={card.label}>
                                <CardContent className="px-3 py-2.5">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {card.label}
                                    </p>
                                    <p className="mt-0.5 text-xl font-semibold tracking-tight">
                                        {formatNumber(card.value)}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Project
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Budget and dates
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Manager
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Scope
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredProjects.map((project) => (
                                        <tr
                                            key={project.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <Link
                                                    href={`/projects/${project.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {project.name}
                                                </Link>
                                                <div className="text-muted-foreground">
                                                    {project.project_type_label ??
                                                        'Project type not specified'}
                                                </div>
                                                {project.location && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {project.location}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                <div>
                                                    {formatCurrencyAmount(
                                                        project.base_currency_code,
                                                        project.budget_amount,
                                                    )}
                                                </div>
                                                {project.recorded_cost_amount !==
                                                    undefined &&
                                                    project.recorded_cost_amount !==
                                                        null &&
                                                    project.recorded_cost_currency_code && (
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            Recorded costs:{' '}
                                                            {formatCurrencyAmount(
                                                                project.recorded_cost_currency_code,
                                                                project.recorded_cost_amount,
                                                            )}
                                                        </div>
                                                    )}
                                                {(project.starts_on ||
                                                    project.ends_on) && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {project.starts_on && (
                                                            <div>
                                                                Starts{' '}
                                                                {format(
                                                                    parseISO(
                                                                        project.starts_on,
                                                                    ),
                                                                    'dd MMM yyyy',
                                                                )}
                                                            </div>
                                                        )}
                                                        {project.ends_on && (
                                                            <div>
                                                                Ends{' '}
                                                                {format(
                                                                    parseISO(
                                                                        project.ends_on,
                                                                    ),
                                                                    'dd MMM yyyy',
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {project.manager_name ??
                                                    'Unassigned'}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {formatNumber(
                                                    project.sites_count,
                                                )}{' '}
                                                sites,{' '}
                                                {formatNumber(
                                                    project.activities_count,
                                                )}{' '}
                                                work activities
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {project.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                '/projects/' +
                                                                project.id
                                                            }
                                                        >
                                                            <Eye />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <ProjectDialog
                                                        project={project}
                                                        defaultBranchId={
                                                            defaultBranchId
                                                        }
                                                        branches={branches}
                                                        customers={customers}
                                                        contracts={contracts}
                                                        users={users}
                                                        currencies={currencies}
                                                        projectTypes={
                                                            projectTypes
                                                        }
                                                    />
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Archive project?',
                                                                description: `${project.name} will move between active and archive project lists.`,
                                                                confirmLabel:
                                                                    'Continue',
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        `/projects/${project.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        Archive
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredProjects.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No projects match the current
                                                tab and search.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
