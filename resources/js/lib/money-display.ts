const groupedInteger = (value: string) =>
    value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

export function exactMoney(value: string | number): string {
    const raw = String(value).replaceAll(',', '').trim();
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(raw);
    if (!match) {
        return raw;
    }

    const integer = match[2].replace(/^0+(?=\d)/, '');
    const fraction = (match[3] ?? '').replace(/0+$/, '');

    return `${match[1]}${groupedInteger(integer)}${fraction ? `.${fraction}` : ''}`;
}

export function compactMoney(value: string | number): string {
    const raw = String(value).replaceAll(',', '').trim();
    const match = /^(-?)(\d+)(?:\.\d+)?$/.exec(raw);
    if (!match) {
        return exactMoney(value);
    }

    const integer = match[2].replace(/^0+(?=\d)/, '');
    const scales = [
        { digits: 18, suffix: 'Qi' },
        { digits: 15, suffix: 'Q' },
        { digits: 12, suffix: 'T' },
        { digits: 9, suffix: 'B' },
        { digits: 6, suffix: 'M' },
    ];
    let scaleIndex = scales.findIndex((scale) => integer.length > scale.digits);
    if (scaleIndex === -1) {
        return exactMoney(value);
    }

    const amount = BigInt(integer);
    let divisor = 10n ** BigInt(scales[scaleIndex].digits);
    let tenths = (amount * 10n + divisor / 2n) / divisor;
    if (tenths >= 10_000n && scaleIndex > 0) {
        scaleIndex--;
        divisor = 10n ** BigInt(scales[scaleIndex].digits);
        tenths = (amount * 10n + divisor / 2n) / divisor;
    }

    const whole = tenths / 10n;
    const fraction = tenths % 10n;

    return `${match[1]}${whole}${fraction ? `.${fraction}` : ''}${scales[scaleIndex].suffix}`;
}
