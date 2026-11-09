import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useEffect } from 'react';
import { Check } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Checkbox } from '@/shared/components/ui/checkbox';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { applyThemeColor, colorProblem, foregroundFor, isHexColor } from '@/shared/lib/color';
import { cn } from '@/shared/lib/utils';

interface Preset {
  name: string;
  color: string;
}

export default function AdminAppearanceIndex({ color, defaultColor, presets }: { color: string; defaultColor: string; presets: Preset[] }) {
  const form = useForm({ color });
  const selected = form.data.color.toUpperCase();
  const problem = colorProblem(selected);

  useEffect(() => {
    form.setData('color', color);
    // Only when the saved color itself changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [color]);

  // AppLayout puts the saved color back on the next navigation.
  useEffect(() => {
    if (colorProblem(selected) === null) applyThemeColor(selected);
  }, [selected]);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.put(route('admin.appearance.update'), { preserveScroll: true });
  };

  const reset = () => {
    form.clearErrors();
    form.delete(route('admin.appearance.destroy'), {
      preserveScroll: true,
      onSuccess: () => form.setData('color', defaultColor),
    });
  };

  return (
    <>
      <Head title="Tampilan" />

      <PageHeader
        title="Tampilan"
        description="Warna utama untuk seluruh pengguna dan halaman login. Warna status seperti hijau, kuning, dan merah tidak berubah."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Tampilan' }]}
      />

      <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
        <section className="flex flex-col gap-5 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
          <div className="flex flex-col gap-2">
            <Label>Warna siap pakai</Label>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {presets.map((preset) => {
                const active = selected === preset.color;

                return (
                  <button
                    key={preset.color}
                    type="button"
                    aria-pressed={active}
                    onClick={() => form.setData('color', preset.color)}
                    className={cn(
                      'flex items-center gap-2 rounded-md border border-gray-200 p-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
                      active && 'border-primary',
                    )}
                  >
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full" style={{ backgroundColor: preset.color }}>
                      {active && <Check className="h-4 w-4" style={{ color: foregroundFor(preset.color) }} />}
                    </span>
                    {preset.name}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="color">Warna lain</Label>
            <div className="flex items-center gap-2">
              <input
                type="color"
                aria-label="Pemilih warna"
                value={isHexColor(selected) ? selected.toLowerCase() : defaultColor.toLowerCase()}
                onChange={(e) => form.setData('color', e.target.value.toUpperCase())}
                className="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-900"
              />
              <Input id="color" value={form.data.color} maxLength={7} onChange={(e) => form.setData('color', e.target.value)} placeholder="#2563EB" />
            </div>
            <FieldError message={form.errors.color ?? problem ?? undefined} />
          </div>

          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" disabled={form.processing} onClick={reset}>
              Kembali ke Bawaan
            </Button>
            <Button type="submit" disabled={form.processing || problem !== null}>
              {form.processing ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </div>
        </section>

        <section className="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
          <div>
            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Pratinjau</h2>
            <p className="text-xs text-gray-500 dark:text-gray-400">Hanya terlihat oleh Anda sampai disimpan.</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button">Tombol Utama</Button>
            <Button type="button" variant="outline">
              Tombol Biasa
            </Button>
            <Button type="button" variant="destructive">
              Hapus
            </Button>
          </div>
          <Input placeholder="Klik di sini untuk melihat warna fokus" />
          <div className="flex items-center gap-4 text-sm">
            <span className="font-medium text-primary-text hover:underline">Contoh tautan</span>
            <span className="flex items-center gap-2 text-gray-700 dark:text-gray-300">
              <Checkbox checked aria-label="Contoh checkbox" />
              Contoh pilihan
            </span>
          </div>
          <div className="rounded-md bg-primary-soft px-3 py-2 text-sm font-medium text-primary-text">Contoh menu aktif</div>
        </section>
      </form>
    </>
  );
}

AdminAppearanceIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
