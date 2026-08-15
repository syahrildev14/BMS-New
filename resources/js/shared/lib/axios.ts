import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';

/**
 * Axios instance tunggal untuk seluruh aplikasi.
 * SEMUA request HTTP wajib lewat sini — tidak ada axios.get() langsung di component/feature lain.
 *
 * Karena Sanctum menggunakan cookie-based auth (SPA within same domain via Inertia),
 * withCredentials wajib true dan XSRF header ditangani otomatis oleh axios
 * selama nama cookie/header sesuai default Laravel (XSRF-TOKEN / X-XSRF-TOKEN).
 */
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
});

// Request interceptor — tempat menambahkan header dinamis (locale, tenant, dsb) di masa depan
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  return config;
});

// Response interceptor — normalisasi error supaya konsisten dikonsumsi oleh createResourceService/hooks
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    // 419 = CSRF token expired (khas Sanctum). Reload agar cookie ter-refresh.
    if (error.response?.status === 419) {
      window.location.reload();
    }

    // 401 = unauthenticated -> redirect ke login (Inertia full reload agar session flush)
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }

    return Promise.reject(error);
  },
);
