import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import type { ExpenseCategory, ExpenseItem, Option } from '../types';

export function ExpenseCategoryDialog({
    category,
}: {
    category?: ExpenseCategory;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: category?.name ?? '',
        code: category?.code ?? '',
        description: category?.description ?? '',
        requires_evidence: category?.requires_evidence ?? false,
        is_active: category?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (category) form.put(`/expense-categories/${category.id}`, options);
        else form.post('/expense-categories', options);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={category ? 'outline' : 'default'}
                    size={category ? 'sm' : 'default'}
                >
                    {category ? <Pencil /> : <Plus />}
                    {category ? 'Edit' : 'New expense type'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {category ? 'Edit expense type' : 'New expense type'}
                    </DialogTitle>
                    <DialogDescription>
                        Expense types group reusable expense items for
                        reporting.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Name" required error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Code" error={form.errors.code}>
                            <Input
                                placeholder="Generated from name"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field label="Description" error={form.errors.description}>
                        <Textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex flex-wrap gap-6">
                        <Check
                            label="Receipt/evidence normally required"
                            checked={form.data.requires_evidence}
                            onChange={(checked) =>
                                form.setData('requires_evidence', checked)
                            }
                        />
                        <Check
                            label="Active"
                            checked={form.data.is_active}
                            onChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save expense type
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ExpenseItemDialog({
    item,
    categories,
    units,
}: {
    item?: ExpenseItem;
    categories: ExpenseCategory[];
    units: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        expense_category_id: item?.expense_category_id ?? '',
        default_unit_of_measure_id: item?.default_unit_of_measure_id ?? '',
        name: item?.name ?? '',
        code: item?.code ?? '',
        description: item?.description ?? '',
        requires_evidence: item?.requires_evidence ?? false,
        is_active: item?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (item) form.put(`/expense-items/${item.id}`, options);
        else form.post('/expense-items', options);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={item ? 'outline' : 'default'}
                    size={item ? 'sm' : 'default'}
                >
                    {item ? <Pencil /> : <Plus />}
                    {item ? 'Edit' : 'New expense item'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Edit expense item' : 'New expense item'}
                    </DialogTitle>
                    <DialogDescription>
                        Create a selectable cost such as Yaka electricity, water
                        or site meals.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label="Expense type"
                        required
                        error={form.errors.expense_category_id}
                    >
                        <SearchableSelect
                            value={form.data.expense_category_id}
                            onValueChange={(value) =>
                                form.setData('expense_category_id', value)
                            }
                            options={categories
                                .filter((row) => row.is_active)
                                .map((row) => ({
                                    value: row.id,
                                    label: row.name,
                                }))}
                            placeholder="Select category"
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Name" required error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Code" error={form.errors.code}>
                            <Input
                                placeholder="Generated from name"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Default unit"
                        error={form.errors.default_unit_of_measure_id}
                    >
                        <SearchableSelect
                            value={form.data.default_unit_of_measure_id}
                            onValueChange={(value) =>
                                form.setData(
                                    'default_unit_of_measure_id',
                                    value,
                                )
                            }
                            options={[
                                { value: '', label: 'No default unit' },
                                ...units,
                            ]}
                            placeholder="Select unit"
                        />
                    </Field>
                    <Field label="Description" error={form.errors.description}>
                        <Textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex flex-wrap gap-6">
                        <Check
                            label="Receipt/evidence normally required"
                            checked={form.data.requires_evidence}
                            onChange={(checked) =>
                                form.setData('requires_evidence', checked)
                            }
                        />
                        <Check
                            label="Active"
                            checked={form.data.is_active}
                            onChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save expense item
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    required,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label required={required}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function Check({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
            />
            {label}
        </label>
    );
}
