import { useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import type { Option } from './project-dialog';

export type AssignedProjectUser = {
    id: string;
    name: string;
    email: string;
    role: string | null;
    can_manage: boolean;
};

type Assignment = {
    user_id: string;
    role: string;
    can_manage: boolean;
};

type FormData = {
    users: Assignment[];
};

type Props = {
    projectId: string;
    users: Option[];
    assignedUsers: AssignedProjectUser[];
};

export function ProjectAccessDialog({
    projectId,
    users,
    assignedUsers,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        users: assignedUsers.map((user) => ({
            user_id: user.id,
            role: user.role ?? '',
            can_manage: user.can_manage,
        })),
    });

    function addUser() {
        form.setData('users', [
            ...form.data.users,
            { user_id: '', role: '', can_manage: false },
        ]);
    }

    function updateUser(index: number, assignment: Partial<Assignment>) {
        form.setData(
            'users',
            form.data.users.map((user, userIndex) =>
                userIndex === index ? { ...user, ...assignment } : user,
            ),
        );
    }

    function removeUser(index: number) {
        form.setData(
            'users',
            form.data.users.filter((_, userIndex) => userIndex !== index),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(`/projects/${projectId}/users`, {
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ShieldCheck />
                    Manage access
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Project access</DialogTitle>
                    <DialogDescription>
                        Assign users to this project without changing their
                        global roles.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4">
                        {form.data.users.map((assignment, index) => (
                            <div
                                key={index}
                                className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_160px_auto_auto]"
                            >
                                <SearchableSelect
                                    value={assignment.user_id}
                                    onValueChange={(value) =>
                                        updateUser(index, { user_id: value })
                                    }
                                    options={users.map((user) => ({
                                        value: user.id,
                                        label: user.name,
                                    }))}
                                    placeholder="Select user"
                                    searchPlaceholder="Search users..."
                                />
                                <Input
                                    value={assignment.role}
                                    onChange={(event) =>
                                        updateUser(index, {
                                            role: event.target.value,
                                        })
                                    }
                                    placeholder="Project role"
                                />
                                <Label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={assignment.can_manage}
                                        onCheckedChange={(checked) =>
                                            updateUser(index, {
                                                can_manage: checked === true,
                                            })
                                        }
                                    />
                                    Manage
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => removeUser(index)}
                                >
                                    Remove
                                </Button>
                            </div>
                        ))}
                        <InputError message={form.errors.users} />
                    </div>
                    <div className="flex justify-between gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addUser}
                        >
                            Add user
                        </Button>
                        <div className="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Save access
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
