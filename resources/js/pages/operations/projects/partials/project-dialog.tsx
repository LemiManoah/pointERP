import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';

export type Option = {
    id: string;
    name: string;
    branch_id?: string | null;
    customer_id?: string | null;
    email?: string;
};

export type Project = {
    id: string;
    branch_id: string;
    customer_id: string | null;
    contract_id: string | null;
    reference: string;
    name: string;
    description: string | null;
    manager_id: string | null;
    manager_name: string | null;
    branch_name: string;
    customer_name: string | null;
    contract_reference: string | null;
    base_currency_code: string;
    budget_amount: string | null;
    starts_on: string | null;
    ends_on: string | null;
    reporting_deadline: string | null;
    status:
        | 'planned'
        | 'active'
        | 'on_hold'
        | 'completed'
        | 'closed'
        | 'archived';
    sites_count: number;
    activities_count: number;
};

type ProjectFormData = Record<string, string> & {
    branch_id: string;
    customer_id: string;
    contract_id: string;
    reference: string;
    name: string;
    description: string;
    manager_id: string;
    base_currency_code: string;
    budget_amount: string;
    starts_on: string;
    ends_on: string;
    reporting_deadline: string;
    status: Project['status'];
};

type Props = {
    project?: Project;
    branches: Option[];
    customers: Option[];
    contracts: Option[];
    users: Option[];
    currencies: Option[];
};

export function ProjectDialog({
    project,
    branches,
    customers,
    contracts,
    users,
    currencies,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(project);
    const form = useForm<ProjectFormData>({
        branch_id: project?.branch_id ?? branches[0]?.id ?? '',
        customer_id: project?.customer_id ?? '',
        contract_id: project?.contract_id ?? '',
        reference: project?.reference ?? '',
        name: project?.name ?? '',
        description: project?.description ?? '',
        manager_id: project?.manager_id ?? '',
        base_currency_code:
            project?.base_currency_code ?? currencies[0]?.id ?? 'UGX',
        budget_amount: project?.budget_amount ?? '',
        starts_on: project?.starts_on ?? '',
        ends_on: project?.ends_on ?? '',
        reporting_deadline: project?.reporting_deadline?.slice(0, 5) ?? '',
        status: project?.status ?? 'planned',
    });
    const branchCustomers = customers.filter(
        (customer) =>
            !customer.branch_id || customer.branch_id === form.data.branch_id,
    );
    const branchContracts = contracts.filter(
        (contract) => contract.branch_id === form.data.branch_id,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (project) {
            form.put(`/projects/${project.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/projects', {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New project'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? `Edit ${project?.reference}`
                            : 'New project'}
                    </DialogTitle>
                    <DialogDescription>
                        Projects are the operational scope for sites, DSRs,
                        documents and later resource ledgers.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Branch</Label>
                            <SearchableSelect
                                value={form.data.branch_id}
                                onValueChange={(value) =>
                                    form.setData('branch_id', value)
                                }
                                options={branches.map((branch) => ({
                                    value: branch.id,
                                    label: branch.name,
                                }))}
                                placeholder="Select branch"
                                searchPlaceholder="Search branches..."
                            />
                            <InputError message={form.errors.branch_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Manager</Label>
                            <SearchableSelect
                                value={form.data.manager_id}
                                onValueChange={(value) =>
                                    form.setData('manager_id', value)
                                }
                                options={[
                                    { value: '', label: 'No manager' },
                                    ...users.map((user) => ({
                                        value: user.id,
                                        label: user.name,
                                    })),
                                ]}
                                placeholder="Select manager"
                                searchPlaceholder="Search users..."
                            />
                            <InputError message={form.errors.manager_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Customer</Label>
                            <SearchableSelect
                                value={form.data.customer_id}
                                onValueChange={(value) =>
                                    form.setData('customer_id', value)
                                }
                                options={[
                                    { value: '', label: 'No customer' },
                                    ...branchCustomers.map((customer) => ({
                                        value: customer.id,
                                        label: customer.name,
                                    })),
                                ]}
                                placeholder="Select customer"
                                searchPlaceholder="Search customers..."
                            />
                            <InputError message={form.errors.customer_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Contract</Label>
                            <SearchableSelect
                                value={form.data.contract_id}
                                onValueChange={(value) =>
                                    form.setData('contract_id', value)
                                }
                                options={[
                                    { value: '', label: 'No contract' },
                                    ...branchContracts.map((contract) => ({
                                        value: contract.id,
                                        label: contract.name,
                                    })),
                                ]}
                                placeholder="Select contract"
                                searchPlaceholder="Search contracts..."
                            />
                            <InputError message={form.errors.contract_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="reference">Reference</Label>
                            <Input
                                id="reference"
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.reference} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label>Currency</Label>
                            <SearchableSelect
                                value={form.data.base_currency_code}
                                onValueChange={(value) =>
                                    form.setData('base_currency_code', value)
                                }
                                options={currencies.map((currency) => ({
                                    value: currency.id,
                                    label: currency.name,
                                }))}
                                placeholder="Currency"
                                searchPlaceholder="Search currencies..."
                            />
                            <InputError
                                message={form.errors.base_currency_code}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="budget_amount">Budget</Label>
                            <Input
                                id="budget_amount"
                                value={form.data.budget_amount}
                                onChange={(event) =>
                                    form.setData(
                                        'budget_amount',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.budget_amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reporting_deadline">
                                Daily report deadline
                            </Label>
                            <Input
                                id="reporting_deadline"
                                type="time"
                                value={form.data.reporting_deadline}
                                onChange={(event) =>
                                    form.setData(
                                        'reporting_deadline',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.reporting_deadline}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <NativeSelect
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target.value as Project['status'],
                                    )
                                }
                            >
                                {[
                                    'planned',
                                    'active',
                                    'on_hold',
                                    'completed',
                                    'closed',
                                    'archived',
                                ].map((status) => (
                                    <NativeSelectOption
                                        key={status}
                                        value={status}
                                    >
                                        {status}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={form.errors.status} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_on">Starts on</Label>
                            <Input
                                id="starts_on"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_on',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_on">Ends on</Label>
                            <Input
                                id="ends_on"
                                type="date"
                                value={form.data.ends_on}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save project
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
