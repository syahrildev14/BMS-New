import { useMemo, useState } from 'react';

export type SortDirection = 'asc' | 'desc';

export interface SortState {
  key: string;
  dir: SortDirection;
}

export interface TableColumn<T> {
  key: string;
  /** Value used for searching/sorting this column. Return null/undefined to exclude a row from matching on this field. */
  accessor: (row: T) => string | number | null | undefined;
  /** Include this column's value when matching the search query. Default true. */
  searchable?: boolean;
  /** Allow sorting by this column. Default true. */
  sortable?: boolean;
}

export interface UseTableControlsOptions {
  /** Rows per page. Default 10. */
  pageSize?: number;
}

/**
 * Client-side search + sort + pagination for small, fully-loaded tables
 * (Inertia props, no server pagination). Pass column accessors once;
 * `toggleSort` + `rows` + `setPage` handle the rest. Sorting is stable and
 * only reorders — it never mutates row identity, so per-row keyed state
 * (e.g. Pricing's cost-price inputs keyed by item id) keeps working
 * correctly across page/sort/search changes.
 */
export function useTableControls<T>(data: T[], columns: TableColumn<T>[], options: UseTableControlsOptions = {}) {
  const pageSize = options.pageSize ?? 10;
  const [search, setSearchState] = useState('');
  const [sort, setSort] = useState<SortState | null>(null);
  const [page, setPageState] = useState(1);

  const setSearch = (value: string) => {
    setSearchState(value);
    setPageState(1);
  };

  /** Changing page is a user-visible navigation action — scroll back to the top of the table. */
  const setPage = (nextPage: number) => {
    setPageState(nextPage);
    if (typeof window !== 'undefined') {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const toggleSort = (key: string) => {
    setSort((prev) => {
      if (!prev || prev.key !== key) return { key, dir: 'asc' };
      if (prev.dir === 'asc') return { key, dir: 'desc' };
      return null;
    });
  };

  const filteredSorted = useMemo(() => {
    let result = data;

    const query = search.trim().toLowerCase();
    if (query) {
      const searchableColumns = columns.filter((c) => c.searchable !== false);
      result = result.filter((row) =>
        searchableColumns.some((col) => String(col.accessor(row) ?? '').toLowerCase().includes(query)),
      );
    }

    if (sort) {
      const column = columns.find((c) => c.key === sort.key);
      if (column) {
        result = [...result].sort((a, b) => {
          const aVal = column.accessor(a);
          const bVal = column.accessor(b);

          if (aVal == null && bVal == null) return 0;
          if (aVal == null) return 1;
          if (bVal == null) return -1;

          const comparison =
            typeof aVal === 'number' && typeof bVal === 'number' ? aVal - bVal : String(aVal).localeCompare(String(bVal), 'id');

          return sort.dir === 'asc' ? comparison : -comparison;
        });
      }
    }

    return result;
  }, [data, search, sort, columns]);

  const totalItems = filteredSorted.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  const currentPage = Math.min(page, totalPages);

  const rows = useMemo(
    () => filteredSorted.slice((currentPage - 1) * pageSize, currentPage * pageSize),
    [filteredSorted, currentPage, pageSize],
  );

  return {
    search,
    setSearch,
    sort,
    toggleSort,
    rows,
    page: currentPage,
    setPage,
    totalPages,
    totalItems,
    pageSize,
  };
}
