import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    ProjectDialog,
    type Option,
    type Project,
} from './partials/project-dialog';

type Props = {
    projects: Project[];
    branches: Option[];
    customers: Option[];
    contracts: Option[];
    users: Option[];
    currencies: Option[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects', href: '/projects' },
];

export default function ProjectsIndex({
    projects,
    branches,
    customers,
    contracts,
    users,
    currencies,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('active');
    const debouncedSearch = useDebouncedValue(search);
    const filteredProjects = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();
        const activeStatuses = ['planned', 'active', 'on_hold'];

        return projects.filter(
            (project) =>
                (status === 'active'
                    ? activeStatuses.includes(project.status)
                    : ['completed', 'closed', 'archived'].includes(
                          project.status,
                      )) &&
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
    }, [debouncedSearch, projects, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Projects
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Operational project scope for sites, BOQ
                                activities, DSRs and evidence.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search projects"
                                className="w-full pl-9 sm:w-72"
                            />
                        </div>
                    </div>
                    <ProjectDialog
                        branches={branches}
                        customers={customers}
                        contracts={contracts}
                        users={users}
                        currencies={currencies}
                    />
                </div>

                <div className="flex justify-end">
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">
                                Completed/archive
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
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
                                            Branch
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
                                                    {project.reference}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                {project.branch_name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {project.manager_name ??
                                                    'Unassigned'}
                                            </td>
                                            <td className="py-3 pr-4 text-muted-foreground">
                                                {project.sites_count} sites,{' '}
                                                {project.activities_count}{' '}
                                                activities
                                            </td>
                                            <td className="py-3 pr-4">
                                                <Badge variant="secondary">
                                                    {project.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <ProjectDialog
                                                        project={project}
                                                        branches={branches}
                                                        customers={customers}
                                                        contracts={contracts}
                                                        users={users}
                                                        currencies={currencies}
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
