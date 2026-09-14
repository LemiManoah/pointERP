import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Edit2, Plus, Power } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Category = { id: string; code: string; name: string; is_active: boolean };
type Props = { categories: Category[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Work Activity Library', href: '/work-item-templates' },
    { title: 'Categories', href: '/work-item-categories' },
];

export default function WorkItemCategoriesIndex({ categories }: Props) {
    const [editing, setEditing] = useState<Category | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', code: '', is_active: true });

    function startCreate() {
        setEditing(null);
        form.setData({ name: '', code: '', is_active: true });
        form.clearErrors();
        setOpen(true);
    }

    function startEdit(category: Category) {
        setEditing(category);
        form.setData({
            name: category.name,
            code: category.code,
            is_active: category.is_active,
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (editing) form.put('/work-item-categories/' + editing.id, options);
        else form.post('/work-item-categories', options);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Work Activity Categories" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/work-item-templates">
                                <ArrowLeft /> Work activities
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-2xl font-semibold">
                            Work Activity Categories
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage the groups used to organise work activities.
                        </p>
                    </div>
                    <Button onClick={startCreate}>
                        <Plus /> New category
                    </Button>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4">Code</th>
                                        <th className="py-3 pr-4">Category</th>
                                        <th className="py-3 pr-4">Status</th>
                                        <th className="py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-mono">
                                                {category.code}
                                            </td>
                                            <td className="py-3 pr-4 font-medium">
                                                {category.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {category.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            startEdit(category)
                                                        }
                                                    >
                                                        <Edit2 /> Edit
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.delete(
                                                                '/work-item-categories/' +
                                                                    category.id,
                                                            )
                                                        }
                                                    >
                                                        <Power />{' '}
                                                        {category.is_active
                                                            ? 'Deactivate'
                                                            : 'Restore'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {categories.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No categories yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit category' : 'New category'}
                            </DialogTitle>
                        </DialogHeader>
                        <form onSubmit={submit} className="grid gap-4">
                            <div>
                                <Label htmlFor="category-name">
                                    Category name *
                                </Label>
                                <Input
                                    id="category-name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    autoFocus
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div>
                                <Label htmlFor="category-code">Code</Label>
                                <Input
                                    id="category-code"
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                    placeholder="Generated from name if empty"
                                />
                                <InputError message={form.errors.code} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                />{' '}
                                Active
                            </label>
                            <Button type="submit" disabled={form.processing}>
                                <Plus /> Save category
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
