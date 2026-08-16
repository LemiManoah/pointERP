import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { formatNumber } from '@/lib/utils';

export type EquipmentScopeData = {
    summary: {
        deployed: number;
        under_maintenance: number;
        out_of_service: number;
        working_hours_30d: number;
        fuel_litres_30d: number;
        open_maintenance: number;
        dsr_posted: number;
        dsr_unposted: number;
        unlinked_snapshots: number;
        fuel_exceptions: number;
        fleet_adjustments: number;
    };
    equipment: Array<{
        id: string;
        asset_code: string;
        name: string;
        category_name: string;
        current_status: string;
        location_name: string | null;
        custodian_name: string | null;
        meter_type: string;
        current_meter_reading: string | null;
        condition_summary: string | null;
    }>;
    reconciliation: Array<{
        id: string;
        report_id: string;
        report_reference: string;
        report_date: string;
        equipment_id: string | null;
        equipment_name: string;
        equipment_identifier: string | null;
        operating_status: string;
        working_hours: string | null;
        fuel_quantity: string | null;
        fleet_posting_status: string;
        fuel_exception_status: string | null;
        adjustment_count: number;
    }>;
};

export function EquipmentScopePanel({ fleet }: { fleet: EquipmentScopeData }) {
    return (
        <div className="grid gap-8">
            <div className="grid gap-px overflow-hidden rounded-md border bg-border sm:grid-cols-2 lg:grid-cols-5">
                <Metric
                    label="Currently deployed"
                    value={fleet.summary.deployed}
                />
                <Metric
                    label="Under maintenance"
                    value={fleet.summary.under_maintenance}
                />
                <Metric
                    label="Out of service"
                    value={fleet.summary.out_of_service}
                />
                <Metric
                    label="Working hours (30d)"
                    value={fleet.summary.working_hours_30d}
                />
                <Metric
                    label="Fuel litres (30d)"
                    value={fleet.summary.fuel_litres_30d}
                />
                <Metric
                    label="Open maintenance"
                    value={fleet.summary.open_maintenance}
                />
                <Metric
                    label="DSR lines posted"
                    value={fleet.summary.dsr_posted}
                />
                <Metric
                    label="DSR lines unposted"
                    value={fleet.summary.dsr_unposted}
                />
                <Metric
                    label="Unlinked snapshots"
                    value={fleet.summary.unlinked_snapshots}
                />
                <Metric
                    label="Fuel exceptions"
                    value={fleet.summary.fuel_exceptions}
                />
                <Metric
                    label="Approved corrections"
                    value={fleet.summary.fleet_adjustments}
                />
            </div>

            <section className="grid gap-3">
                <div>
                    <h3 className="font-semibold">Current deployment</h3>
                    <p className="text-sm text-muted-foreground">
                        Assets whose accepted custody and location currently
                        point to this scope.
                    </p>
                </div>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                <th className="px-3 py-2 font-medium">Asset</th>
                                <th className="px-3 py-2 font-medium">
                                    Location / custodian
                                </th>
                                <th className="px-3 py-2 font-medium">Meter</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {fleet.equipment.map((equipment) => (
                                <tr
                                    key={equipment.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-3 py-3">
                                        <Link
                                            href={`/equipment/${equipment.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {equipment.asset_code}
                                        </Link>
                                        <div>{equipment.name}</div>
                                        <div className="text-muted-foreground">
                                            {equipment.category_name}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {equipment.location_name ??
                                            'Location not confirmed'}
                                        <div className="text-muted-foreground">
                                            {equipment.custodian_name ??
                                                'No current custodian'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {equipment.meter_type === 'none'
                                            ? 'No meter'
                                            : formatNumber(
                                                  equipment.current_meter_reading,
                                              )}
                                        <div className="text-muted-foreground">
                                            {title(equipment.meter_type)}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant={
                                                equipment.current_status ===
                                                'out_of_service'
                                                    ? 'destructive'
                                                    : equipment.current_status ===
                                                        'under_maintenance'
                                                      ? 'outline'
                                                      : 'secondary'
                                            }
                                        >
                                            {title(equipment.current_status)}
                                        </Badge>
                                        {equipment.condition_summary && (
                                            <div className="mt-1 max-w-64 text-muted-foreground">
                                                {equipment.condition_summary}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {fleet.equipment.length === 0 && (
                                <Empty
                                    colSpan={4}
                                    text="No equipment is currently deployed here."
                                />
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="grid gap-3">
                <div>
                    <h3 className="font-semibold">
                        Approved DSR reconciliation
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Recent approved report lines and the fleet-ledger result
                        created from each snapshot.
                    </p>
                </div>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left text-muted-foreground">
                                <th className="px-3 py-2 font-medium">
                                    Report
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Equipment snapshot
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Usage / fuel
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Fleet result
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {fleet.reconciliation.map((line) => (
                                <tr
                                    key={line.id}
                                    className="border-b align-top last:border-0"
                                >
                                    <td className="px-3 py-3">
                                        <Link
                                            href={`/daily-site-reports/${line.report_id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {line.report_reference}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {line.report_date}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {line.equipment_id ? (
                                            <Link
                                                href={`/equipment/${line.equipment_id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {line.equipment_identifier ??
                                                    line.equipment_name}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {line.equipment_identifier ??
                                                    line.equipment_name}
                                            </span>
                                        )}
                                        <div className="text-muted-foreground">
                                            {line.equipment_name}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {formatNumber(line.working_hours)}{' '}
                                        working hours
                                        <div className="text-muted-foreground">
                                            {formatNumber(line.fuel_quantity)}{' '}
                                            litres fuel
                                        </div>
                                        {line.adjustment_count > 0 && (
                                            <div className="text-muted-foreground">
                                                Includes{' '}
                                                {formatNumber(
                                                    line.adjustment_count,
                                                )}{' '}
                                                approved correction(s)
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant={postingVariant(
                                                line.fleet_posting_status,
                                            )}
                                        >
                                            {title(line.fleet_posting_status)}
                                        </Badge>
                                        {line.fuel_exception_status &&
                                            line.fuel_exception_status !==
                                                'within_tolerance' && (
                                                <div className="mt-1 text-muted-foreground">
                                                    Fuel:{' '}
                                                    {title(
                                                        line.fuel_exception_status,
                                                    )}
                                                </div>
                                            )}
                                    </td>
                                </tr>
                            ))}
                            {fleet.reconciliation.length === 0 && (
                                <Empty
                                    colSpan={4}
                                    text="No approved DSR equipment lines are available."
                                />
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="bg-background px-3 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">
                {formatNumber(value)}
            </div>
        </div>
    );
}

function Empty({ colSpan, text }: { colSpan: number; text: string }) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="px-3 py-10 text-center text-muted-foreground"
            >
                {text}
            </td>
        </tr>
    );
}

function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function postingVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'posted') return 'secondary';
    if (status === 'unposted') return 'destructive';
    return 'outline';
}
