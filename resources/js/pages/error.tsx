import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn, RefreshCw } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/shared/components/ui/button';

type ErrorStatus = 401 | 403 | 404 | 500 | 503;

const messages: Record<ErrorStatus, { title: string; description: string }> = {
  401: {
    title: 'Perlu Masuk',
    description: 'Sesi Anda sudah berakhir atau Anda belum masuk. Silakan masuk terlebih dahulu untuk melanjutkan.',
  },
  403: {
    title: 'Akses Ditolak',
    description: 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Hubungi admin bila Anda merasa memerlukan akses.',
  },
  404: {
    title: 'Halaman Tidak Ditemukan',
    description: 'Halaman yang Anda cari tidak ada, sudah dipindahkan, atau alamatnya salah ketik.',
  },
  500: {
    title: 'Terjadi Kesalahan Server',
    description: 'Maaf, ada masalah di sistem kami. Coba lagi beberapa saat lagi. Bila terus berulang, hubungi admin.',
  },
  503: {
    title: 'Sedang Dalam Pemeliharaan',
    description: 'Sistem sedang diperbarui atau dalam pemeliharaan. Silakan coba lagi beberapa menit lagi.',
  },
};

export default function ErrorPage({ status }: { status: ErrorStatus }) {
  const { title, description } = messages[status] ?? messages[500];

  return (
    <>
      <Head title={title} />

      <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6">
        <div className="flex w-full max-w-md flex-col items-center gap-6 text-center">
          <AppLogoIcon className="size-10 fill-current text-[var(--foreground)] dark:text-white" />

          <div className="flex flex-col gap-2">
            <p className="text-primary text-6xl font-bold tracking-tight">{status}</p>
            <h1 className="text-xl font-semibold">{title}</h1>
            <p className="text-muted-foreground text-sm">{description}</p>
          </div>

          <div className="flex flex-wrap justify-center gap-2">
            {status === 401 ? (
              <Button asChild>
                <Link href={route('login')}>
                  <LogIn className="h-4 w-4" />
                  Masuk
                </Link>
              </Button>
            ) : status === 503 || status === 500 ? (
              <Button onClick={() => window.location.reload()}>
                <RefreshCw className="h-4 w-4" />
                Muat Ulang
              </Button>
            ) : (
              <Button asChild>
                <Link href={route('dashboard')}>
                  <Home className="h-4 w-4" />
                  Ke Dashboard
                </Link>
              </Button>
            )}

            <Button variant="outline" onClick={() => window.history.back()}>
              <ArrowLeft className="h-4 w-4" />
              Kembali
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}
