import { useForm } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { LinkOptions } from './document-dialog';

type FormData = Record<string, string> & {
    type: string;
    id: string;
};

export function LinkDialog({
    documentId,
    linkOptions,
}: {
    documentId: string;
    linkOptions: LinkOptions;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        type: 'project',
        id: '',
    });

    const options =
        form.data.type === 'contract'
            ? linkOptions.contracts
            : form.data.type === 'site'
              ? linkOptions.sites
              : form.data.type === 'daily_site_report'
                ? linkOptions.dailySiteReports
                : form.data.type === 'equipment'
                  ? linkOptions.equipment
                  : form.data.type === 'equipment_maintenance_work_order'
                    ? linkOptions.maintenanceWorkOrders
                    : form.data.type === 'inventory_item'
                      ? linkOptions.inventoryItems
                      : linkOptions.projects;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(`/documents/${documentId}/links`, {
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <LinkIcon />
                    Link record
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link document</DialogTitle>
                    <DialogDescription>
                        Attach this document to an accessible operational
                        record.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Record type</Label>
                        <NativeSelect
                            value={form.data.type}
                            onChange={(event) => {
                                form.setData('type', event.target.value);
                                form.setData('id', '');
                            }}
                        >
                            <NativeSelectOption value="project">
                                Project
                            </NativeSelectOption>
                            <NativeSelectOption value="site">
                                Site
                            </NativeSelectOption>
                            <NativeSelectOption value="contract">
                                Contract
                            </NativeSelectOption>
                            <NativeSelectOption value="daily_site_report">
                                DSR
                            </NativeSelectOption>
                            <NativeSelectOption value="equipment">
                                Equipment
                            </NativeSelectOption>
                            <NativeSelectOption value="equipment_maintenance_work_order">
                                Maintenance work order
                            </NativeSelectOption>
                            <NativeSelectOption value="inventory_item">
                                Inventory item
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label>Record</Label>
                        <SearchableSelect
                            value={form.data.id}
                            onValueChange={(value) => form.setData('id', value)}
                            options={options.map((option) => ({
                                value: option.id,
                                label: option.name,
                            }))}
                            placeholder="Select record"
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
                            Link
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
