import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
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
import { Switch } from '@/components/ui/switch';

export type Trade = {
    id: string;
    code: string;
    name: string;
    category: string;
    category_label: string;
    is_active: boolean;
};

type Category = {
    value: string;
    label: string;
};

type Props = {
    trade?: Trade;
    categories: Category[];
};

function makeCode(name: string) {
    return name
        .trim()
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 40);
}

export function WorkforceTradeDialog({ trade, categories }: Props) {
    const [open, setOpen] = useState(false);
    const [codeEdited, setCodeEdited] = useState(Boolean(trade));
    const form = useForm({
        code: trade?.code ?? '',
        name: trade?.name ?? '',
        category: trade?.category ?? categories[0]?.value ?? 'skilled',
        is_active: trade?.is_active ?? true,
    });
    const editing = Boolean(trade);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = { onSuccess: () => setOpen(false) };

        if (trade) {
            form.put('/workforce/trades/' + trade.id, options);

            return;
        }

        form.post('/workforce/trades', options);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'outline' : 'default'}
                    size={editing ? 'sm' : 'default'}
                >
                    {editing ? <Pencil /> : <Plus />}
                    {editing ? 'Edit' : 'New trade'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit trade' : 'New trade'}
                    </DialogTitle>
                    <DialogDescription>
                        Trades describe practical site skills, not job titles or
                        employment terms.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="trade_name" required>
                            Trade name
                        </Label>
                        <Input
                            id="trade_name"
                            value={form.data.name}
                            onChange={(event) => {
                                const name = event.target.value;
                                form.setData((data) => ({
                                    ...data,
                                    name,
                                    code: codeEdited
                                        ? data.code
                                        : makeCode(name),
                                }));
                            }}
                            placeholder="e.g. Excavator operator"
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="trade_code">Code</Label>
                            <Input
                                id="trade_code"
                                value={form.data.code}
                                onChange={(event) => {
                                    setCodeEdited(true);
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    );
                                }}
                                placeholder="Generated from name"
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="trade_category" required>
                                Category
                            </Label>
                            <NativeSelect
                                id="trade_category"
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                                className="w-full"
                            >
                                {categories.map((category) => (
                                    <NativeSelectOption
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={form.errors.category} />
                        </div>
                    </div>
                    <div className="flex items-center justify-between rounded-md border p-3">
                        <div>
                            <Label htmlFor="trade_active">Active</Label>
                            <p className="text-xs text-muted-foreground">
                                Active trades are available on new staff and
                                deployment records.
                            </p>
                        </div>
                        <Switch
                            id="trade_active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
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
                            Save trade
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
