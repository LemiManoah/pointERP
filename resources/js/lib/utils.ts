import type { InertiaLinkProps } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatNumber(
    value: number | string | null | undefined,
    options: Intl.NumberFormatOptions = {},
): string {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const numericValue =
        typeof value === 'number'
            ? value
            : Number(String(value).replaceAll(',', ''));

    if (!Number.isFinite(numericValue)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 4,
        ...options,
    }).format(numericValue);
}

export function formatCurrencyAmount(
    currencyCode: string | null | undefined,
    value: number | string | null | undefined,
): string {
    if (value === null || value === undefined || value === '') {
        return 'Not set';
    }

    return `${currencyCode ?? ''} ${formatNumber(value)}`.trim();
}
