/**
 * Bentuk response Laravel API Resource untuk single object.
 * Sesuaikan jika backend tidak selalu membungkus dengan { data: ... }.
 */
export interface ApiResponse<T> {
  data: T;
  message?: string;
}

/**
 * Bentuk response Laravel ResourceCollection dengan pagination bawaan
 * (Resource::collection($paginator)) — meta & links persis format Laravel.
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

/**
 * Query params generic untuk list endpoint: pagination, search, sort, filter bebas.
 * Setiap fitur boleh extend ini dengan filter spesifik (mis. filter kategori Barang).
 */
export interface ResourceQueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  order?: 'asc' | 'desc';
  [key: string]: string | number | boolean | undefined;
}

/**
 * Bentuk error validasi 422 dari Laravel Form Request.
 */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}
