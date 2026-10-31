import Swal from 'sweetalert2';

/**
 * Theming disamakan dengan brand ERP: minimalis, aksen biru, tanpa warna mencolok.
 * Dipakai di SELURUH modul lewat helper ini — jangan panggil Swal.fire() langsung di component.
 */
const baseConfig = {
  buttonsStyling: false,
  customClass: {
    popup: 'rounded-xl shadow-lg',
    confirmButton:
      'inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 mx-1',
    cancelButton:
      'inline-flex items-center justify-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 mx-1',
  },
};

export async function confirmDelete(options?: {
  title?: string;
  text?: string;
}): Promise<boolean> {
  const result = await Swal.fire({
    ...baseConfig,
    icon: 'warning',
    title: options?.title ?? 'Hapus data ini?',
    text: options?.text ?? 'Data yang sudah dihapus tidak dapat dikembalikan.',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  });

  return result.isConfirmed;
}

export function successAlert(message: string, title = 'Berhasil') {
  return Swal.fire({
    ...baseConfig,
    icon: 'success',
    title,
    text: message,
    timer: 1800,
    showConfirmButton: false,
  });
}

export function errorAlert(message: string, title = 'Gagal') {
  return Swal.fire({
    ...baseConfig,
    icon: 'error',
    title,
    text: message,
    confirmButtonText: 'Tutup',
  });
}

export async function confirmLogout(): Promise<boolean> {
  const result = await Swal.fire({
    ...baseConfig,
    icon: 'question',
    title: 'Keluar dari aplikasi?',
    text: 'Anda akan diarahkan kembali ke halaman login.',
    showCancelButton: true,
    confirmButtonText: 'Ya, keluar',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  });

  return result.isConfirmed;
}

export async function confirmBackup(): Promise<boolean> {
  const result = await Swal.fire({
    ...baseConfig,
    icon: 'question',
    title: 'Buat backup database sekarang?',
    text: 'File backup akan dibuat dan langsung diunduh ke perangkat Anda.',
    showCancelButton: true,
    confirmButtonText: 'Ya, buat backup',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  });

  return result.isConfirmed;
}

export function warningAlert(message: string, title = 'Perhatian') {
  return Swal.fire({
    ...baseConfig,
    icon: 'warning',
    title,
    text: message,
    confirmButtonText: 'Mengerti',
  });
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char);
}

/**
 * For rejections that name a concrete list of affected records (e.g. items
 * missing a daily price) — a bullet list reads far more professionally than
 * squeezing everything into one run-on sentence.
 */
export function listAlert(message: string, items: string[], title = 'Perhatian') {
  const listHtml = items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');

  return Swal.fire({
    ...baseConfig,
    icon: 'warning',
    title,
    html: `
      <p class="text-sm text-gray-600 text-left mb-3">${escapeHtml(message)}</p>
      <ul class="text-sm text-left list-disc list-inside space-y-1 text-gray-700">${listHtml}</ul>
    `,
    confirmButtonText: 'Mengerti',
  });
}
