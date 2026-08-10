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
};

export type Customer = {
    id: string;
    branch_id: string | null;
    branch_name: string | null;
    type: 'client' | 'subcontractor' | 'supplier' | 'other';
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    tax_number: string | null;
    address: string | null;
    status: 'active' | 'inactive';
};

type CustomerFormData = Record<string, string> & {
    branch_id: string;
    type: Customer['type'];
    name: string;
    code: string;
    email: string;
    phone: string;
    tax_number: string;
    address: string;
    status: Customer['status'];
};

type Props = {
    customer?: Customer;
    branches: Option[];
};

export function CustomerDialog({ customer, branches }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(customer);
    const form = useForm<CustomerFormData>({
        branch_id: customer?.branch_id ?? '',
        type: customer?.type ?? 'client',
        name: customer?.name ?? '',
        code: customer?.code ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        tax_number: customer?.tax_number ?? '',
        address: customer?.address ?? '',
        status: customer?.status ?? 'active',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (customer) {
            form.put(`/customers/${customer.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/customers', {
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
                    {isEditing ? 'Edit' : 'New company'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${customer?.name}` : 'New company'}
                    </DialogTitle>
                    <DialogDescription>
                        Store clients, subcontractors, suppliers and other
                        project parties.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="branch_id">Branch</Label>
                        <SearchableSelect
                            value={form.data.branch_id}
                            onValueChange={(value) =>
                                form.setData('branch_id', value)
                            }
                            options={[
                                {
                                    value: '',
                                    label: 'Tenant-wide',
                                },
                                ...branches.map((branch) => ({
                                    value: branch.id,
                                    label: branch.name,
                                })),
                            ]}
                            placeholder="Select branch"
                            searchPlaceholder="Search branches..."
                        />
                        <InputError message={form.errors.branch_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="type">Type</Label>
                        <NativeSelect
                            id="type"
                            value={form.data.type}
                            onChange={(event) =>
                                form.setData(
                                    'type',
                                    event.target.value as Customer['type'],
                                )
                            }
                        >
                            <NativeSelectOption value="client">
                                Client
                            </NativeSelectOption>
                            <NativeSelectOption value="subcontractor">
                                Subcontractor
                            </NativeSelectOption>
                            <NativeSelectOption value="supplier">
                                Supplier
                            </NativeSelectOption>
                            <NativeSelectOption value="other">
                                Other
                            </NativeSelectOption>
                        </NativeSelect>
                        <InputError message={form.errors.type} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
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
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.code} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                            />
                            <InputError message={form.errors.phone} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tax_number">Tax number</Label>
                        <Input
                            id="tax_number"
                            value={form.data.tax_number}
                            onChange={(event) =>
                                form.setData('tax_number', event.target.value)
                            }
                        />
                        <InputError message={form.errors.tax_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input
                            id="address"
                            value={form.data.address}
                            onChange={(event) =>
                                form.setData('address', event.target.value)
                            }
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData(
                                    'status',
                                    event.target.value as Customer['status'],
                                )
                            }
                        >
                            <NativeSelectOption value="active">
                                Active
                            </NativeSelectOption>
                            <NativeSelectOption value="inactive">
                                Inactive
                            </NativeSelectOption>
                        </NativeSelect>
                        <InputError message={form.errors.status} />
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
                            Save company
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
