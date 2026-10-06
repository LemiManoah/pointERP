type CalculationLine = {
    work_item_key: string | null;
    item_type: string;
    planned_quantity: string;
    selling_rate: string | null;
    percentage_rate?: string | null;
    percentage_base_keys?: string[];
};

export function boqAmount(line: CalculationLine, lines: CalculationLine[]): number | null {
    if (line.item_type !== 'percentage_adjustment') {
        if (line.selling_rate == null || line.selling_rate.trim() === '') return null;
        return Math.round(Number(line.planned_quantity) * Number(line.selling_rate) * 10000) / 10000;
    }
    const keys = line.percentage_base_keys ?? [];
    if (line.percentage_rate == null || line.percentage_rate.trim() === '' || keys.length === 0) return null;
    const bases = lines.filter((base) => base.work_item_key && keys.includes(base.work_item_key));
    if (bases.length !== keys.length || bases.some((base) => base.item_type === 'percentage_adjustment')) return null;
    let total = 0;
    for (const base of bases) {
        const amount = boqAmount(base, []);
        if (amount === null) return null;
        total += amount;
    }
    return Math.round(total * Number(line.percentage_rate) * 100) / 10000;
}
