import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { DatePicker } from '@/components/date-picker';
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
import { Textarea } from '@/components/ui/textarea';

export type Option = {
    id: string;
    name: string;
};

export type DocumentTypeOption = Option & {
    code: string;
    requires_expiry_date: boolean;
    is_confidential: boolean;
};

export type LinkOptions = {
    contracts: Option[];
    projects: Option[];
    sites: Option[];
    dailySiteReports: Option[];
    equipment: Option[];
    maintenanceWorkOrders: Option[];
    inventoryItems: Option[];
    expenses: Option[];
    companies: Option[];
    revisions: Option[];
    disciplines: Option[];
    confidentialities: Option[];
};

export type DocumentRecord = {
    id: string;
    title: string;
    reference: string | null;
    external_url?: string | null;
    document_number?: string | null;
    revision?: string | null;
    discipline?: string | null;
    issuer_id?: string | null;
    issuer?: string | null;
    type_name: string;
    type_code: string;
    branch_id?: string | null;
    branch_name: string | null;
    document_type_id?: string;
    description?: string | null;
    document_date?: string | null;
    expires_on: string | null;
    confidentiality: string;
    status: string;
};

type FormData = Record<
    string,
    string | File | null | Array<{ type: string; id: string }>
> & {
    branch_id: string;
    document_type_id: string;
    title: string;
    reference: string;
    external_url: string;
    document_number: string;
    revision: string;
    discipline: string;
    issuer_id: string;
    description: string;
    document_date: string;
    expires_on: string;
    confidentiality: string;
    status: string;
    file: File | null;
    version_notes: string;
    links: Array<{ type: string; id: string }>;
};

type Props = {
    document?: DocumentRecord;
    documentTypes: DocumentTypeOption[];
    branches: Option[];
    linkOptions: LinkOptions;
    defaultLink?: { type: string; id: string };
    defaultBranchId?: string | null;
    buttonLabel?: string;
};

function createDocumentNumber(): string {
    const suffix = Math.random().toString(36).slice(2, 8).toUpperCase();

    return `DOC-${new Date().getFullYear()}-${suffix}`;
}

export function DocumentDialog({
    document,
    documentTypes,
    branches,
    linkOptions,
    defaultLink,
    defaultBranchId,
    buttonLabel,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(document);
    const isSingleBranch = branches.length === 1;
    const form = useForm<FormData>({
        branch_id:
            document?.branch_id ??
            defaultBranchId ??
            (isSingleBranch ? (branches[0]?.id ?? '') : ''),
        document_type_id:
            document?.document_type_id ?? documentTypes[0]?.id ?? '',
        title: document?.title ?? '',
        reference: document?.reference ?? '',
        external_url: document?.external_url ?? '',
        document_number: document?.document_number ?? createDocumentNumber(),
        revision: document?.revision ?? '',
        discipline: document?.discipline ?? '',
        issuer_id: document?.issuer_id ?? '',
        description: document?.description ?? '',
        document_date: document?.document_date ?? '',
        expires_on: document?.expires_on ?? '',
        confidentiality: document?.confidentiality ?? 'normal',
        status: document?.status ?? 'active',
        file: null,
        version_notes: '',
        links: [defaultLink ?? { type: 'project', id: '' }],
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (document) {
            form.put(`/documents/${document.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/documents', {
            forceFormData: true,
            onSuccess: () => setOpen(false),
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
                    {isEditing ? 'Edit' : (buttonLabel ?? 'Upload document')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit document' : 'Upload document'}
                    </DialogTitle>
                    <DialogDescription>
                        Classify and link controlled project evidence.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label required>Type</Label>
                            <SearchableSelect
                                value={form.data.document_type_id}
                                onValueChange={(value) =>
                                    form.setData('document_type_id', value)
                                }
                                options={documentTypes.map((type) => ({
                                    value: type.id,
                                    label: type.name,
                                    description: type.code,
                                }))}
                                placeholder="Select type"
                            />
                            <InputError
                                message={form.errors.document_type_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Branch</Label>
                            <SearchableSelect
                                value={form.data.branch_id}
                                onValueChange={(value) =>
                                    form.setData('branch_id', value)
                                }
                                options={[
                                    ...(isSingleBranch
                                        ? []
                                        : [
                                              {
                                                  value: '',
                                                  label: 'Tenant-wide',
                                              },
                                          ]),
                                    ...branches.map((branch) => ({
                                        value: branch.id,
                                        label: branch.name,
                                    })),
                                ]}
                                placeholder="Select branch"
                            />
                            <InputError message={form.errors.branch_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Confidentiality</Label>
                            <SearchableSelect
                                value={form.data.confidentiality}
                                onValueChange={(value) =>
                                    form.setData('confidentiality', value)
                                }
                                options={linkOptions.confidentialities.map(
                                    (option) => ({
                                        value: option.id,
                                        label: option.name,
                                    }),
                                )}
                            />
                            <InputError message={form.errors.confidentiality} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="title" required>
                                Title
                            </Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reference">Reference</Label>
                            <Input
                                id="reference"
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.reference} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="document_number">
                                Document no.
                            </Label>
                            <Input
                                id="document_number"
                                value={form.data.document_number}
                                onChange={(event) =>
                                    form.setData(
                                        'document_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.document_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Revision</Label>
                            <SearchableSelect
                                value={form.data.revision}
                                onValueChange={(value) =>
                                    form.setData('revision', value)
                                }
                                options={[
                                    { value: '', label: 'Not specified' },
                                    ...linkOptions.revisions.map((option) => ({
                                        value: option.id,
                                        label: option.name,
                                    })),
                                ]}
                                placeholder="Select revision"
                            />
                            <InputError message={form.errors.revision} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Discipline</Label>
                            <SearchableSelect
                                value={form.data.discipline}
                                onValueChange={(value) =>
                                    form.setData('discipline', value)
                                }
                                options={[
                                    { value: '', label: 'Not specified' },
                                    ...linkOptions.disciplines.map((option) => ({
                                        value: option.id,
                                        label: option.name,
                                    })),
                                ]}
                                placeholder="Select discipline"
                            />
                            <InputError message={form.errors.discipline} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Issuer company</Label>
                            <SearchableSelect
                                value={form.data.issuer_id}
                                onValueChange={(value) =>
                                    form.setData('issuer_id', value)
                                }
                                options={[
                                    { value: '', label: 'No issuer' },
                                    ...linkOptions.companies.map((company) => ({
                                        value: company.id,
                                        label: company.name,
                                    })),
                                ]}
                                placeholder="Select company"
                            />
                            <InputError message={form.errors.issuer_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="document_date">Document date</Label>
                            <DatePicker
                                id="document_date"
                                value={form.data.document_date}
                                onChange={(value) =>
                                    form.setData('document_date', value)
                                }
                                placeholder="Select document date"
                            />
                            <InputError message={form.errors.document_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="expires_on">Expires on</Label>
                            <DatePicker
                                id="expires_on"
                                value={form.data.expires_on}
                                onChange={(value) =>
                                    form.setData('expires_on', value)
                                }
                                placeholder="Select expiry date"
                            />
                            <InputError message={form.errors.expires_on} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label required>Status</Label>
                            <NativeSelect
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                <NativeSelectOption value="active">
                                    Active
                                </NativeSelectOption>
                                <NativeSelectOption value="superseded">
                                    Superseded
                                </NativeSelectOption>
                                <NativeSelectOption value="expired">
                                    Expired
                                </NativeSelectOption>
                                <NativeSelectOption value="archived">
                                    Archived
                                </NativeSelectOption>
                            </NativeSelect>
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

                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="external_url">
                            Protected Google Drive or Docs link
                        </Label>
                        <Input
                            id="external_url"
                            type="url"
                            className="min-w-0"
                            placeholder="https://drive.google.com/..."
                            value={form.data.external_url}
                            onChange={(event) =>
                                form.setData('external_url', event.target.value)
                            }
                        />
                        <InputError message={form.errors.external_url} />
                    </div>

                    {!isEditing && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="file">Upload file</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.file} />
                            </div>
                            <LinkPicker
                                links={form.data.links}
                                linkOptions={linkOptions}
                                onChange={(links) =>
                                    form.setData('links', links)
                                }
                            />
                        </>
                    )}

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
                            Save document
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LinkPicker({
    links,
    linkOptions,
    onChange,
}: {
    links: Array<{ type: string; id: string }>;
    linkOptions: LinkOptions;
    onChange: (links: Array<{ type: string; id: string }>) => void;
}) {
    const optionsFor = (type: string) => {
        if (type === 'contract') return linkOptions.contracts;
        if (type === 'site') return linkOptions.sites;
        if (type === 'daily_site_report') return linkOptions.dailySiteReports;
        if (type === 'equipment') return linkOptions.equipment;
        if (type === 'equipment_maintenance_work_order')
            return linkOptions.maintenanceWorkOrders;
        if (type === 'inventory_item') return linkOptions.inventoryItems;
        if (type === 'expense') return linkOptions.expenses;
        return linkOptions.projects;
    };

    return (
        <div className="grid gap-3">
            <Label>Links</Label>
            {links.map((link, index) => (
                <div
                    key={index}
                    className="grid gap-3 md:grid-cols-[12rem_1fr_auto]"
                >
                    <NativeSelect
                        value={link.type}
                        onChange={(event) =>
                            onChange(
                                links.map((current, currentIndex) =>
                                    currentIndex === index
                                        ? { type: event.target.value, id: '' }
                                        : current,
                                ),
                            )
                        }
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
                        <NativeSelectOption value="expense">
                            Expense
                        </NativeSelectOption>
                    </NativeSelect>
                    <SearchableSelect
                        value={link.id}
                        onValueChange={(value) =>
                            onChange(
                                links.map((current, currentIndex) =>
                                    currentIndex === index
                                        ? { ...current, id: value }
                                        : current,
                                ),
                            )
                        }
                        options={optionsFor(link.type).map((option) => ({
                            value: option.id,
                            label: option.name,
                        }))}
                        placeholder="Select record"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Remove link"
                        onClick={() =>
                            onChange(
                                links.filter(
                                    (_, currentIndex) => currentIndex !== index,
                                ),
                            )
                        }
                    >
                        <Trash2 />
                        <span className="sr-only">Remove link</span>
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                onClick={() =>
                    onChange([...links, { type: 'project', id: '' }])
                }
            >
                Add link
            </Button>
        </div>
    );
}
