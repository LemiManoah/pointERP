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
};

export type Contract = {
    id: string;
    branch_id: string;
    customer_id: string;
    branch_name: string;
    customer_name: string;
    reference: string;
    title: string;
    scope_summary: string | null;
    contract_value: string | null;
    currency_code: string;
    starts_on: string | null;
    ends_on: string | null;
    retention_percent: string | null;
    payment_terms: string | null;
    status: 'draft' | 'active' | 'completed' | 'closed' | 'archived';
};

type ContractFormData = Record<string, string> & {
    branch_id: string;
    customer_id: string;
    reference: string;
    title: string;
    scope_summary: string;
    contract_value: string;
    currency_code: string;
    starts_on: string;
    ends_on: string;
    retention_percent: string;
    payment_terms: string;
    status: Contract['status'];
};

type Props = {
    contract?: Contract;
    branches: Option[];
    customers: Option[];
    currencies: Option[];
};

export function ContractDialog({
    contract,
    branches,
    customers,
    currencies,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(contract);
    const form = useForm<ContractFormData>({
        branch_id: contract?.branch_id ?? branches[0]?.id ?? '',
        customer_id: contract?.customer_id ?? customers[0]?.id ?? '',
        reference: contract?.reference ?? '',
        title: contract?.title ?? '',
        scope_summary: contract?.scope_summary ?? '',
        contract_value: contract?.contract_value ?? '',
        currency_code: contract?.currency_code ?? currencies[0]?.id ?? 'UGX',
        starts_on: contract?.starts_on ?? '',
        ends_on: contract?.ends_on ?? '',
        retention_percent: contract?.retention_percent ?? '',
        payment_terms: contract?.payment_terms ?? '',
        status: contract?.status ?? 'draft',
    });
    const customerOptions = customers.filter(
        (customer) =>
            !customer.branch_id || customer.branch_id === form.data.branch_id,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (contract) {
            form.put(`/contracts/${contract.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/contracts', {
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
                    {isEditing ? 'Edit' : 'New contract'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${contract?.reference}` : 'New contract'}
                    </DialogTitle>
                    <DialogDescription>
                        Store commercial scope used by projects and later IPCs.
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
                            <Label>Company</Label>
                            <SearchableSelect
                                value={form.data.customer_id}
                                onValueChange={(value) =>
                                    form.setData('customer_id', value)
                                }
                                options={customerOptions.map((customer) => ({
                                    value: customer.id,
                                    label: customer.name,
                                }))}
                                placeholder="Select company"
                                searchPlaceholder="Search companies..."
                            />
                            <InputError message={form.errors.customer_id} />
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
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="scope_summary">Scope summary</Label>
                        <Input
                            id="scope_summary"
                            value={form.data.scope_summary}
                            onChange={(event) =>
                                form.setData('scope_summary', event.target.value)
                            }
                        />
                        <InputError message={form.errors.scope_summary} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="contract_value">Value</Label>
                            <Input
                                id="contract_value"
                                value={form.data.contract_value}
                                onChange={(event) =>
                                    form.setData(
                                        'contract_value',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.contract_value} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Currency</Label>
                            <SearchableSelect
                                value={form.data.currency_code}
                                onValueChange={(value) =>
                                    form.setData('currency_code', value)
                                }
                                options={currencies.map((currency) => ({
                                    value: currency.id,
                                    label: currency.name,
                                }))}
                                placeholder="Currency"
                                searchPlaceholder="Search currencies..."
                            />
                            <InputError message={form.errors.currency_code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="retention_percent">Retention %</Label>
                            <Input
                                id="retention_percent"
                                value={form.data.retention_percent}
                                onChange={(event) =>
                                    form.setData(
                                        'retention_percent',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.retention_percent} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_on">Starts on</Label>
                            <Input
                                id="starts_on"
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData('starts_on', event.target.value)
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
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <NativeSelect
                                id="status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target.value as Contract['status'],
                                    )
                                }
                            >
                                {[
                                    'draft',
                                    'active',
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

                    <div className="grid gap-2">
                        <Label htmlFor="payment_terms">Payment terms</Label>
                        <Input
                            id="payment_terms"
                            value={form.data.payment_terms}
                            onChange={(event) =>
                                form.setData('payment_terms', event.target.value)
                            }
                        />
                        <InputError message={form.errors.payment_terms} />
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
                            Save contract
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
