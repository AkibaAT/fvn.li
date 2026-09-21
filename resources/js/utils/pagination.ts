import type { PaginationMeta } from '@/types';

type PageSource = {
    current_page: number;
    last_page: number;
    total: number;
    per_page?: number;
};

/**
 * Builds the Pagination meta object from a backend paginator. Pass `data` to
 * compute the "from/to" summary line ("Showing X to Y of Z"); without it the
 * component falls back to "Page X of Y".
 */
export function buildPageMeta(source: PageSource, data?: readonly unknown[]): PaginationMeta {
    const meta: PaginationMeta = {
        current_page: source.current_page,
        last_page: source.last_page,
        total: source.total,
        ...(source.per_page !== undefined ? { per_page: source.per_page } : {}),
    };

    if (data) {
        const perPage = source.per_page ?? 0;
        meta.from = data.length ? (source.current_page - 1) * perPage + 1 : 0;
        meta.to = data.length ? (source.current_page - 1) * perPage + data.length : 0;
    }

    return meta;
}
