import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type StaffPosition = {
    id: string;
    name: string;
    code: string;
    is_active: boolean;
    staff_count: number;
};

type StaffPositionFormData = Record<string, string | boolean> & {
    name: string;
    code: string;
    is_active: boolean;
};

type Props = {
    position?: StaffPosition;
    onCancel?: () => void;
    onSuccess?: () => void;
};

function generateCodeFromName(name: string): string {
    return name
        .trim()
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function StaffPositionForm({ position, onCancel, onSuccess }: Props) {
    const [isCodeTouched, setIsCodeTouched] = useState(Boolean(position?.code));
    const form = useForm<StaffPositionFormData>({
        name: position?.name ?? '',
        code: position?.code ?? '',
        is_active: position?.is_active ?? true,
    });

    function handleNameChange(name: string) {
        if (!position && !isCodeTouched) {
            form.setData({
                ...form.data,
                name,
                code: generateCodeFromName(name),
            });
        } else {
            form.setData('name', name);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (position) {
            form.put(`/staff-positions/${position.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/staff-positions', {
            onSuccess: () => {
                form.reset();
                setIsCodeTouched(false);
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) => handleNameChange(event.target.value)}
                    placeholder="Project Manager"
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="code">Code</Label>
                <Input
                    id="code"
                    value={form.data.code}
                    onChange={(event) => {
                        setIsCodeTouched(true);
                        form.setData('code', event.target.value.toUpperCase());
                    }}
                    placeholder="PROJECT-MANAGER"
                />
                <InputError message={form.errors.code} />
            </div>
            <label className="flex items-center gap-3 text-sm">
                <Checkbox
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                Active
            </label>
            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save position
                </Button>
            </div>
        </form>
    );
}
