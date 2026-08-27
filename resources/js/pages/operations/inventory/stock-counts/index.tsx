import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Batch = { id: string; batch_number: string; system_quantity: string };
type Item = { id: string; name: string; code: string; unit: string; tracking_type: string; system_quantity: string; batches: Batch[] };
type Store = { id: string; name: string; code: string; branch_name: string; items: Item[] };
type CountLine = { inventory_item_id: string; inventory_batch_id: string | null; system_quantity: string; counted_quantity: string; name: string; code: string; unit: string; batch_number: string | null };
type Props = { stores: Store[]; countKey: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stock movements', href: '/inventory/stock-movements' }, { title: 'Stock count', href: '/inventory/stock-counts' }];

export default function InventoryStockCount({ stores, countKey }: Props) {
    const form = useForm({ inventory_store_id: '', count_key: countKey, reason: '', lines: [] as CountLine[] });
    const chooseStore = (storeId: string) => {
        const store = stores.find((candidate) => candidate.id === storeId);
        const lines = store?.items.flatMap((item): CountLine[] => item.tracking_type === 'batch'
            ? item.batches.map((batch) => ({ inventory_item_id: item.id, inventory_batch_id: batch.id, system_quantity: batch.system_quantity, counted_quantity: '', name: item.name, code: item.code, unit: item.unit, batch_number: batch.batch_number }))
            : [{ inventory_item_id: item.id, inventory_batch_id: null, system_quantity: item.system_quantity, counted_quantity: '', name: item.name, code: item.code, unit: item.unit, batch_number: null }]) ?? [];
        form.setData({ ...form.data, inventory_store_id: storeId, lines });
    };
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/inventory/stock-counts'); };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Physical stock count" />
            <form onSubmit={submit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-2xl font-semibold">Physical stock count</h1><p className="mt-1 text-sm text-muted-foreground">Enter what is physically present. The system records only the difference.</p></div><Button asChild variant="outline"><Link href="/inventory/stock-movements"><ArrowLeft />Back to movements</Link></Button></div>
                <Card><CardHeader><CardTitle className="text-base">Count location</CardTitle></CardHeader><CardContent><Field label="Store" required error={form.errors.inventory_store_id}><SearchableSelect value={form.data.inventory_store_id} options={stores.map((store) => ({ value: store.id, label: store.name, description: store.branch_name }))} onValueChange={chooseStore} placeholder="Select store" /></Field></CardContent></Card>
                <Card>
                    <CardHeader><CardTitle className="text-base">Counted quantities</CardTitle></CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b text-left text-muted-foreground"><Th>Item</Th><Th>Batch</Th><Th>Unit</Th><Th>System quantity</Th><Th>Physical count</Th><Th>Variance</Th></tr></thead><tbody>
                            {form.data.lines.map((line, index) => { const variance = line.counted_quantity === '' ? null : Number(line.counted_quantity) - Number(line.system_quantity); return <tr key={`${line.inventory_item_id}-${line.inventory_batch_id ?? 'none'}`} className="border-b last:border-0"><Td>{line.name}<div className="text-muted-foreground">{line.code}</div></Td><Td>{line.batch_number ?? '-'}</Td><Td>{line.unit}</Td><Td>{formatNumber(line.system_quantity)}</Td><Td><Input className="w-36" type="number" min="0" step="0.0001" value={line.counted_quantity} onChange={(event) => form.setData('lines', form.data.lines.map((candidate, lineIndex) => lineIndex === index ? { ...candidate, counted_quantity: event.target.value } : candidate))} aria-label={`Physical count for ${line.name}`} /><InputError message={form.errors[`lines.${index}.counted_quantity`]} /></Td><Td>{variance === null ? '-' : formatNumber(variance)}</Td></tr>; })}
                            {form.data.lines.length === 0 && <tr><td colSpan={6} className="py-12 text-center text-muted-foreground">Select a store to load its inventory.</td></tr>}
                        </tbody></table></div><InputError message={form.errors.lines} />
                    </CardContent>
                </Card>
                <Card><CardContent className="grid gap-4 pt-6"><Field label="Count reason or reference" required error={form.errors.reason}><Textarea value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder="For example: Monthly physical count, August 2026" /></Field><div className="flex justify-end"><Button type="submit" disabled={form.processing || form.data.lines.every((line) => line.counted_quantity === '')}><ClipboardCheck />Reconcile count</Button></div></CardContent></Card>
            </form>
        </AppLayout>
    );
}

function Field({ label, required = false, error, children }: { label: string; required?: boolean; error?: string; children: ReactNode }) { return <div className="grid min-w-0 gap-2"><Label>{label}{required && <span className="text-destructive"> *</span>}</Label>{children}<InputError message={error} /></div>; }
function Th({ children }: { children: ReactNode }) { return <th className="py-3 pr-4 font-medium">{children}</th>; }
function Td({ children }: { children: ReactNode }) { return <td className="py-3 pr-4 align-top">{children}</td>; }
