import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export type DocumentType = {
    id: string;
    name: string;
    code: string;
    description: string | null;
    requires_expiry_date: boolean;
    is_confidential: boolean;
    is_system: boolean;
    is_active: boolean;
    tenant_specific: boolean;
};

type FormData = Record<string, string | boolean> & {
    name: string;
    code: string;
    description: string;
    requires_expiry_date: boolean;
    is_confidential: boolean;
    is_active: boolean;
    tenant_specific: boolean;
};

export function DocumentTypeDialog({ type }: { type?: DocumentType }) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(type);
    const form = useForm<FormData>({
        name: type?.name ?? '',
        code: type?.code ?? '',
        description: type?.description ?? '',
        requires_expiry_date: type?.requires_expiry_date ?? false,
        is_confidential: type?.is_confidential ?? false,
        is_active: type?.is_active ?? true,
        tenant_specific: type?.tenant_specific ?? false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (type) {
            form.put(`/document-types/${type.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/document-types', {
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
                    {isEditing ? 'Edit' : 'New type'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit document type' : 'New document type'}
                    </DialogTitle>
                    <DialogDescription>
                        Classify controlled construction records and evidence.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 md:grid-cols-2">
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
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <CheckRow
                            label="Requires expiry"
                            checked={form.data.requires_expiry_date}
                            onChange={(checked) =>
                                form.setData('requires_expiry_date', checked)
                            }
                        />
                        <CheckRow
                            label="Confidential by default"
                            checked={form.data.is_confidential}
                            onChange={(checked) =>
                                form.setData('is_confidential', checked)
                            }
                        />
                        <CheckRow
                            label="Active"
                            checked={form.data.is_active}
                            onChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        <CheckRow
                            label="Tenant-specific"
                            checked={form.data.tenant_specific}
                            onChange={(checked) =>
                                form.setData('tenant_specific', checked)
                            }
                        />
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
                            Save type
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CheckRow({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-3 rounded-md border px-3 py-2 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onChange(Boolean(value))}
            />
            {label}
        </label>
    );
}
