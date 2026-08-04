const numberFormatter = new Intl.NumberFormat();

export function formatNumber(value: number): string {
    return numberFormatter.format(value);
}
