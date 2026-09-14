import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Download,
    Edit2,
    Layers,
    Plus,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string };
type ItemOption = Option & { unit_id: string; unit_cost: string | null };

type ResourceTemplate = {
    id?: string;
    resource_type: string;
    resource_type_label?: string;
    inventory_item_id?: string | null;
    equipment_category_id?: string | null;
    workforce_trade_id?: string | null;
    subcontractor_id?: string | null;
    unit_of_measure_id?: string | null;
    name: string;
    quantity_per_work_unit: string;
    unit_cost: string | number | null;
    notes?: string | null;
    unit_symbol?: string | null;
};

type WorkItemTemplate = {
    id: string;
    code: string | null;
    category: string;
    name: string;
    unit_of_measure_id: string;
    unit_name: string;
    unit_symbol: string | null;
    default_selling_rate: string | null;
    default_unit_cost: string | null;
    specifications: string | null;
    is_active: boolean;
    resources: ResourceTemplate[];
};

type Props = {
    templates: {
        data: WorkItemTemplate[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
    categories: string[];
    units: Option[];
    items: ItemOption[];
    equipmentCategories: Option[];
    workforceTrades: Option[];
    subcontractors: Option[];
    resourceTypes: Option[];
    currencyCode: string;
    filters: {
        category: string;
        search: string;
    };
    can: {
        create: boolean;
        manage: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Work Activity Library', href: '/work-item-templates' },
];

function blankResource(): ResourceTemplate {
    return {
        resource_type: 'material',
        inventory_item_id: null,
        equipment_category_id: null,
        workforce_trade_id: null,
        subcontractor_id: null,
        unit_of_measure_id: null,
        name: '',
        quantity_per_work_unit: '1.000000',
        unit_cost: '',
        notes: '',
    };
}

export default function WorkItemTemplatesIndex({
    templates,
    categories,
    units,
    items,
    equipmentCategories,
    workforceTrades,
    subcontractors,
    resourceTypes,
    currencyCode,
    filters,
    can,
}: Props) {
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category ?? '',
    );
    const [expandedIds, setExpandedIds] = useState<Record<string, boolean>>({});

    // Dialog states
    const [dialogOpen, setDialogOpen] = useState(false);
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [editingTemplate, setEditingTemplate] =
        useState<WorkItemTemplate | null>(null);

    const form = useForm({
        code: '',
        category: '',
        name: '',
        unit_of_measure_id: units[0]?.value ?? '',
        default_selling_rate: '',
        default_unit_cost: '',
        specifications: '',
        is_active: true,
        resources: [blankResource()] as ResourceTemplate[],
    });

    const importForm = useForm<{ file: File | null }>({
        file: null,
    });

    const toggleExpand = (id: string) => {
        setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    function applyFilter(newSearch = search, newCat = selectedCategory) {
        router.get(
            '/work-item-templates',
            {
                search: newSearch || undefined,
                category: newCat || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function openCreateDialog() {
        setEditingTemplate(null);
        form.reset();
        form.setData({
            code: '',
            category: categories[0] ?? 'Concrete Works',
            name: '',
            unit_of_measure_id: units[0]?.value ?? '',
            default_selling_rate: '',
            default_unit_cost: '',
            specifications: '',
            is_active: true,
            resources: [blankResource()],
        });
        setDialogOpen(true);
    }

    function openEditDialog(template: WorkItemTemplate) {
        setEditingTemplate(template);
        form.setData({
            code: template.code ?? '',
            category: template.category,
            name: template.name,
            unit_of_measure_id: template.unit_of_measure_id,
            default_selling_rate: template.default_selling_rate ?? '',
            default_unit_cost: template.default_unit_cost ?? '',
            specifications: template.specifications ?? '',
            is_active: template.is_active,
            resources:
                template.resources.length > 0
                    ? template.resources.map((res) => ({
                          ...res,
                          inventory_item_id: res.inventory_item_id ?? null,
                          equipment_category_id:
                              res.equipment_category_id ?? null,
                          workforce_trade_id: res.workforce_trade_id ?? null,
                          subcontractor_id: res.subcontractor_id ?? null,
                          quantity_per_work_unit: String(
                              res.quantity_per_work_unit,
                          ),
                          unit_cost:
                              res.unit_cost !== null
                                  ? String(res.unit_cost)
                                  : '',
                          notes: res.notes ?? '',
                      }))
                    : [blankResource()],
        });
        setDialogOpen(true);
    }

    // Dynamic unit cost calculation in the form
    const calculatedFormCost = useMemo(() => {
        return form.data.resources.reduce((sum, res) => {
            const qty = Number(res.quantity_per_work_unit) || 0;
            let cost = Number(res.unit_cost) || 0;
            if (cost === 0 && res.inventory_item_id) {
                const item = items.find(
                    (opt) => opt.value === res.inventory_item_id,
                );
                if (item?.unit_cost) {
                    cost = Number(item.unit_cost);
                }
            }
            return sum + qty * cost;
        }, 0);
    }, [form.data.resources, items]);

    function updateResource(index: number, values: Partial<ResourceTemplate>) {
        form.setData(
            'resources',
            form.data.resources.map((res, i) =>
                i === index ? { ...res, ...values } : res,
            ),
        );
    }

    function addResourceRow() {
        form.setData('resources', [...form.data.resources, blankResource()]);
    }

    function removeResourceRow(index: number) {
        if (form.data.resources.length === 1) {
            form.setData('resources', [blankResource()]);
            return;
        }
        form.setData(
            'resources',
            form.data.resources.filter((_, i) => i !== index),
        );
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (editingTemplate) {
            form.put(`/work-item-templates/${editingTemplate.id}`, {
                onSuccess: () => setDialogOpen(false),
            });
        } else {
            form.post('/work-item-templates', {
                onSuccess: () => setDialogOpen(false),
            });
        }
    }

    function handleDelete(template: WorkItemTemplate) {
        confirm({
            title: 'Delete work activity template?',
            description: `Are you sure you want to remove "${template.name}"? Historical estimates using this template will not be affected.`,
            confirmLabel: 'Delete template',
            variant: 'destructive',
            onConfirm: () => {
                router.delete(`/work-item-templates/${template.id}`);
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Work Activity Library" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Layers className="size-6 text-primary" />
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Work Activity Library & Rate Analysis
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Standard civil engineering work activities and their
                            default resource recipes (materials, plant, and
                            labour norms).
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href="/work-item-templates/template/download"
                            download
                        >
                            <Button
                                type="button"
                                variant="outline"
                                className="gap-2"
                            >
                                <Download className="size-4" />
                                Download CSV Template
                            </Button>
                        </a>
                        <a href="/work-item-categories">
                            <Button
                                type="button"
                                variant="outline"
                                className="gap-2"
                            >
                                <Layers className="size-4" />
                                Categories
                            </Button>
                        </a>
                        {can.manage && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setImportDialogOpen(true)}
                                    className="gap-2"
                                >
                                    <Upload className="size-4" />
                                    Import activity templates
                                </Button>
                                <Button
                                    onClick={openCreateDialog}
                                    className="gap-2"
                                >
                                    <Plus className="size-4" />
                                    New work activity template
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 items-center gap-3">
                        <div className="relative max-w-sm flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' &&
                                    applyFilter(search, selectedCategory)
                                }
                                placeholder="Search by name, code or category..."
                                className="pl-9"
                            />
                        </div>
                        <NativeSelect
                            value={selectedCategory}
                            onChange={(e) => {
                                setSelectedCategory(e.target.value);
                                applyFilter(search, e.target.value);
                            }}
                            className="w-48"
                        >
                            <NativeSelectOption value="">
                                All categories
                            </NativeSelectOption>
                            {categories.map((cat) => (
                                <NativeSelectOption key={cat} value={cat}>
                                    {cat}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </div>
                </div>

                {/* Templates List */}
                <div className="flex flex-col gap-4">
                    {templates.data.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                                <Layers className="mb-3 size-12 text-muted-foreground/50" />
                                <h3 className="text-lg font-semibold">
                                    No work activity templates found
                                </h3>
                                <p className="mt-1 max-w-md text-sm text-muted-foreground">
                                    {search || selectedCategory
                                        ? 'No templates match your filters. Try clearing the search or category.'
                                        : 'Get started by creating your company standard rate analysis templates.'}
                                </p>
                                {can.manage && !search && !selectedCategory && (
                                    <Button
                                        onClick={openCreateDialog}
                                        className="mt-4 gap-2"
                                    >
                                        <Plus className="size-4" />
                                        Create template
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        templates.data.map((item) => {
                            const isExpanded = !!expandedIds[item.id];
                            return (
                                <Card key={item.id} className="overflow-hidden">
                                    <div
                                        className="flex cursor-pointer flex-col gap-3 p-4 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between"
                                        onClick={() => toggleExpand(item.id)}
                                    >
                                        <div className="flex items-start gap-3">
                                            <button
                                                type="button"
                                                className="mt-1 text-muted-foreground hover:text-foreground"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    toggleExpand(item.id);
                                                }}
                                            >
                                                {isExpanded ? (
                                                    <ChevronDown className="size-5" />
                                                ) : (
                                                    <ChevronRight className="size-5" />
                                                )}
                                            </button>
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="font-mono text-xs"
                                                    >
                                                        {item.code || 'NO CODE'}
                                                    </Badge>
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        {item.category}
                                                    </Badge>
                                                    <span className="text-base font-semibold">
                                                        {item.name}
                                                    </span>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        [
                                                        {item.unit_symbol ||
                                                            item.unit_name}
                                                        ]
                                                    </span>
                                                </div>
                                                {item.specifications && (
                                                    <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                                        {item.specifications}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-6 self-end sm:self-auto">
                                            <div className="text-right">
                                                <div className="text-xs text-muted-foreground">
                                                    Dynamic Unit Cost
                                                </div>
                                                <div className="font-mono text-sm font-semibold text-foreground">
                                                    {item.default_unit_cost !==
                                                    null
                                                        ? formatCurrencyAmount(
                                                              currencyCode,
                                                              Number(
                                                                  item.default_unit_cost,
                                                              ),
                                                          )
                                                        : '—'}
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <div className="text-xs text-muted-foreground">
                                                    Selling Rate
                                                </div>
                                                <div className="font-mono text-sm font-semibold text-primary">
                                                    {item.default_selling_rate !==
                                                    null
                                                        ? formatCurrencyAmount(
                                                              currencyCode,
                                                              Number(
                                                                  item.default_selling_rate,
                                                              ),
                                                          )
                                                        : '—'}
                                                </div>
                                            </div>

                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {item.resources.length}{' '}
                                                {item.resources.length === 1
                                                    ? 'input'
                                                    : 'inputs'}
                                            </Badge>

                                            {can.manage && (
                                                <div
                                                    className="flex items-center gap-1"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEditDialog(item)
                                                        }
                                                        title="Edit template"
                                                    >
                                                        <Edit2 className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(item)
                                                        }
                                                        className="text-destructive hover:text-destructive"
                                                        title="Delete template"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Expanded Resource Recipe Breakdown */}
                                    {isExpanded && (
                                        <div className="border-t bg-muted/20 p-4">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Resource Consumption Norms
                                                    (Per 1{' '}
                                                    {item.unit_symbol ||
                                                        item.unit_name}
                                                    )
                                                </span>
                                            </div>
                                            {item.resources.length === 0 ? (
                                                <p className="text-xs text-muted-foreground italic">
                                                    No resource assumptions
                                                    defined for this template.
                                                </p>
                                            ) : (
                                                <div className="overflow-x-auto rounded border bg-background">
                                                    <table className="w-full text-left text-xs">
                                                        <thead className="border-b bg-muted/50 text-muted-foreground">
                                                            <tr>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Type
                                                                </th>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Resource
                                                                    Input
                                                                </th>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Norm /
                                                                    Consumption
                                                                </th>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Unit Cost
                                                                </th>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Cost / Work
                                                                    Unit
                                                                </th>
                                                                <th className="px-3 py-2 font-medium">
                                                                    Notes
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y">
                                                            {item.resources.map(
                                                                (res, rIdx) => {
                                                                    const qty =
                                                                        Number(
                                                                            res.quantity_per_work_unit,
                                                                        ) || 0;
                                                                    const cost =
                                                                        Number(
                                                                            res.unit_cost,
                                                                        ) || 0;
                                                                    const subtotal =
                                                                        qty *
                                                                        cost;
                                                                    return (
                                                                        <tr
                                                                            key={
                                                                                rIdx
                                                                            }
                                                                            className="hover:bg-muted/30"
                                                                        >
                                                                            <td className="px-3 py-2">
                                                                                <Badge
                                                                                    variant="secondary"
                                                                                    className="text-[10px] capitalize"
                                                                                >
                                                                                    {
                                                                                        res.resource_type
                                                                                    }
                                                                                </Badge>
                                                                            </td>
                                                                            <td className="px-3 py-2 font-medium">
                                                                                {
                                                                                    res.name
                                                                                }
                                                                            </td>
                                                                            <td className="px-3 py-2 font-mono">
                                                                                {
                                                                                    res.quantity_per_work_unit
                                                                                }{' '}
                                                                                {res.unit_symbol ||
                                                                                    ''}
                                                                            </td>
                                                                            <td className="px-3 py-2 font-mono text-muted-foreground">
                                                                                {cost >
                                                                                0
                                                                                    ? formatCurrencyAmount(
                                                                                          currencyCode,
                                                                                          cost,
                                                                                      )
                                                                                    : '—'}
                                                                            </td>
                                                                            <td className="px-3 py-2 font-mono font-semibold">
                                                                                {subtotal >
                                                                                0
                                                                                    ? formatCurrencyAmount(
                                                                                          currencyCode,
                                                                                          subtotal,
                                                                                      )
                                                                                    : '—'}
                                                                            </td>
                                                                            <td className="px-3 py-2 text-muted-foreground">
                                                                                {res.notes ||
                                                                                    '—'}
                                                                            </td>
                                                                        </tr>
                                                                    );
                                                                },
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </Card>
                            );
                        })
                    )}
                </div>

                {/* Create/Edit Modal */}
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-h-[92vh] w-[96vw] overflow-y-auto sm:max-w-6xl">
                        <DialogHeader>
                            <DialogTitle>
                                {editingTemplate
                                    ? 'Edit Work Activity Template'
                                    : 'New Work Activity Template'}
                            </DialogTitle>
                            <DialogDescription>
                                Define the standard work activity and its
                                resource recipe (materials, plant, and labour
                                norms).
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-5 py-2"
                        >
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="category">Category *</Label>
                                    <SearchableSelect
                                        value={form.data.category}
                                        options={categories.map((category) => ({
                                            value: category,
                                            label: category,
                                        }))}
                                        onValueChange={(value) =>
                                            form.setData('category', value)
                                        }
                                        placeholder="Select category"
                                    />
                                    <InputError
                                        message={form.errors.category}
                                        className="mt-1"
                                    />
                                    <InputError
                                        message={form.errors.category}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="code">Item Code</Label>
                                    <Input
                                        id="code"
                                        value={form.data.code}
                                        onChange={(e) =>
                                            form.setData('code', e.target.value)
                                        }
                                        placeholder="e.g. CONC-025"
                                        className="mt-1"
                                    />
                                    <InputError
                                        message={form.errors.code}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="unit">
                                        Unit of Measure *
                                    </Label>
                                    <NativeSelect
                                        id="unit"
                                        value={form.data.unit_of_measure_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'unit_of_measure_id',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    >
                                        {units.map((u) => (
                                            <NativeSelectOption
                                                key={u.value}
                                                value={u.value}
                                            >
                                                {u.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <InputError
                                        message={form.errors.unit_of_measure_id}
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="name">Activity Name *</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Grade 25 Reinforced Concrete in Slabs & Beams"
                                    className="mt-1"
                                    required
                                />
                                <InputError
                                    message={form.errors.name}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="default_selling_rate">
                                        Suggested Selling Rate (Optional)
                                    </Label>
                                    <Input
                                        id="default_selling_rate"
                                        type="number"
                                        min="0"
                                        step="any"
                                        value={form.data.default_selling_rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'default_selling_rate',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. 450000"
                                        className="mt-1 font-mono"
                                    />
                                    <InputError
                                        message={
                                            form.errors.default_selling_rate
                                        }
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label>Dynamic Calculated Unit Cost</Label>
                                    <div className="mt-1 flex h-9 w-full items-center rounded-md border border-input bg-muted/50 px-3 py-1 font-mono text-sm font-semibold text-primary">
                                        {formatCurrencyAmount(
                                            currencyCode,
                                            calculatedFormCost,
                                        )}
                                    </div>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Dynamically calculated from resource
                                        consumption norms below.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="specifications">
                                    Specifications / Engineering Mix Notes
                                </Label>
                                <Textarea
                                    id="specifications"
                                    rows={2}
                                    value={form.data.specifications}
                                    onChange={(e) =>
                                        form.setData(
                                            'specifications',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 1:1.5:3 mix ratio, 20mm aggregate, water-cement ratio 0.45"
                                    className="mt-1"
                                />
                                <InputError
                                    message={form.errors.specifications}
                                    className="mt-1"
                                />
                            </div>

                            {/* Resource Recipe Sub-form */}
                            <div className="mt-2 flex flex-col gap-3 rounded-lg border bg-muted/20 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="text-sm font-semibold">
                                            Resource Assumptions (Norms)
                                        </h4>
                                        <p className="text-xs text-muted-foreground">
                                            Inputs required to produce 1 unit of
                                            this work activity.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addResourceRow}
                                        className="gap-1 text-xs"
                                    >
                                        <Plus className="size-3.5" />
                                        Add Resource
                                    </Button>
                                </div>

                                <div className="flex flex-col gap-3">
                                    {form.data.resources.map((res, index) => (
                                        <div
                                            key={index}
                                            className="grid items-center gap-3 rounded-lg border bg-background p-3.5 shadow-xs sm:grid-cols-[10rem_minmax(14rem,1fr)_8rem_9rem_3rem]"
                                        >
                                            <div>
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Type
                                                </Label>
                                                <NativeSelect
                                                    value={res.resource_type}
                                                    onChange={(e) =>
                                                        updateResource(index, {
                                                            resource_type:
                                                                e.target.value,
                                                            inventory_item_id:
                                                                null,
                                                            equipment_category_id:
                                                                null,
                                                            workforce_trade_id:
                                                                null,
                                                            subcontractor_id:
                                                                null,
                                                        })
                                                    }
                                                    className="mt-1 h-9 text-xs"
                                                >
                                                    {resourceTypes.map((t) => (
                                                        <NativeSelectOption
                                                            key={t.value}
                                                            value={t.value}
                                                        >
                                                            {t.label}
                                                        </NativeSelectOption>
                                                    ))}
                                                </NativeSelect>
                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    {res.resource_type === 'material'
                                                        ? 'Inventory Item'
                                                        : res.resource_type === 'equipment'
                                                          ? 'Equipment Category'
                                                          : res.resource_type === 'labour'
                                                            ? 'Workforce Trade'
                                                            : res.resource_type === 'subcontractor'
                                                              ? 'Subcontractor'
                                                              : 'Resource Description'}
                                                </Label>
                                                {res.resource_type === 'material' ? (
                                                    <SearchableSelect
                                                        value={res.inventory_item_id ?? ''}
                                                        onValueChange={(val) => {
                                                            const item = items.find((opt) => opt.value === val);
                                                            updateResource(index, {
                                                                inventory_item_id: val || null,
                                                                name: item?.label ?? res.name,
                                                                unit_of_measure_id: item?.unit_id ?? res.unit_of_measure_id,
                                                                unit_cost: item?.unit_cost ? String(item.unit_cost) : res.unit_cost,
                                                            });
                                                        }}
                                                        options={items}
                                                        placeholder="Select material"
                                                        className="mt-1"
                                                    />
                                                ) : res.resource_type === 'equipment' ? (
                                                    <SearchableSelect
                                                        value={res.equipment_category_id ?? ''}
                                                        onValueChange={(val) => updateResource(index, {
                                                            equipment_category_id: val || null,
                                                            name: equipmentCategories.find((option) => option.value === val)?.label ?? res.name,
                                                        })}
                                                        options={equipmentCategories}
                                                        placeholder="Select equipment category"
                                                        className="mt-1"
                                                    />
                                                ) : res.resource_type === 'labour' ? (
                                                    <SearchableSelect
                                                        value={res.workforce_trade_id ?? ''}
                                                        onValueChange={(val) => updateResource(index, {
                                                            workforce_trade_id: val || null,
                                                            name: workforceTrades.find((option) => option.value === val)?.label ?? res.name,
                                                        })}
                                                        options={workforceTrades}
                                                        placeholder="Select workforce trade"
                                                        className="mt-1"
                                                    />
                                                ) : res.resource_type === 'subcontractor' ? (
                                                    <SearchableSelect
                                                        value={res.subcontractor_id ?? ''}
                                                        onValueChange={(val) => updateResource(index, {
                                                            subcontractor_id: val || null,
                                                            name: subcontractors.find((option) => option.value === val)?.label ?? res.name,
                                                        })}
                                                        options={subcontractors}
                                                        placeholder="Select subcontractor"
                                                        className="mt-1"
                                                    />
                                                ) : (
                                                    <Input
                                                        value={res.name}
                                                        onChange={(e) => updateResource(index, { name: e.target.value })}
                                                        placeholder="Describe the resource"
                                                        className="mt-1 h-9 text-xs"
                                                        required
                                                    />
                                                )}
                                                {res.resource_type === 'material' && !res.inventory_item_id && (
                                                    <Input
                                                        value={res.name}
                                                        onChange={(e) => updateResource(index, { name: e.target.value })}
                                                        placeholder="Material name (if not in inventory)"
                                                        className="mt-1.5 h-8 text-xs"
                                                        required
                                                    />
                                                )}                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Qty per unit *
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    min="0.000001"
                                                    value={
                                                        res.quantity_per_work_unit
                                                    }
                                                    onChange={(e) =>
                                                        updateResource(index, {
                                                            quantity_per_work_unit:
                                                                e.target.value,
                                                        })
                                                    }
                                                    placeholder="1.0"
                                                    className="mt-1 h-9 font-mono text-xs"
                                                    required
                                                />
                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Unit Cost
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={
                                                        res.unit_cost !== null
                                                            ? String(
                                                                  res.unit_cost,
                                                              )
                                                            : ''
                                                    }
                                                    onChange={(e) =>
                                                        updateResource(index, {
                                                            unit_cost:
                                                                e.target.value,
                                                        })
                                                    }
                                                    placeholder="0.00"
                                                    className="mt-1 h-9 font-mono text-xs"
                                                />
                                            </div>

                                            <div className="flex justify-end pt-5">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        removeResourceRow(index)
                                                    }
                                                    className="h-9 w-9 p-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    title="Remove resource"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <DialogFooter className="mt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDialogOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {editingTemplate
                                        ? 'Update Template'
                                        : 'Save Template'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Import Modal */}
                <Dialog
                    open={importDialogOpen}
                    onOpenChange={setImportDialogOpen}
                >
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Upload className="size-5 text-primary" />
                                <span>Import Work Activity Templates</span>
                            </DialogTitle>
                            <DialogDescription>
                                Add reusable activities and resource norms to
                                the library. This does not import a project BOQ
                                or create an estimate.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (!importForm.data.file) return;
                                importForm.post('/work-item-templates/import', {
                                    forceFormData: true,
                                    onSuccess: () => {
                                        setImportDialogOpen(false);
                                        importForm.reset();
                                    },
                                });
                            }}
                            className="flex flex-col gap-4 py-2"
                        >
                            <div className="rounded-md border border-dashed p-6 text-center">
                                <input
                                    type="file"
                                    id="csv-file-upload"
                                    accept=".csv,text/csv,text/plain"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0] || null;
                                        importForm.setData('file', file);
                                    }}
                                />
                                <label
                                    htmlFor="csv-file-upload"
                                    className="flex cursor-pointer flex-col items-center gap-2"
                                >
                                    <Upload className="size-8 text-muted-foreground" />
                                    <span className="text-sm font-medium">
                                        {importForm.data.file
                                            ? importForm.data.file.name
                                            : 'Click to select CSV file'}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Only .csv files up to 5MB supported
                                    </span>
                                </label>
                            </div>

                            {importForm.errors.file && (
                                <InputError message={importForm.errors.file} />
                            )}

                            <div className="flex items-center justify-between rounded bg-muted/40 p-3 text-xs text-muted-foreground">
                                <span>Need the template format?</span>
                                <a
                                    href="/work-item-templates/template/download"
                                    download
                                    className="flex items-center gap-1 font-medium text-primary hover:underline"
                                >
                                    <Download className="size-3.5" />
                                    Download sample CSV
                                </a>
                            </div>

                            <DialogFooter className="mt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setImportDialogOpen(false);
                                        importForm.reset();
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        !importForm.data.file ||
                                        importForm.processing
                                    }
                                >
                                    {importForm.processing
                                        ? 'Importing...'
                                        : 'Upload & Import'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
