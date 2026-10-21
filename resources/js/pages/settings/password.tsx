import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useRef } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { SettingsTabs } from '@/shared/components/layout/SettingsTabs';
import { Button } from '@/shared/components/ui/button';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';

interface PasswordForm {
  current_password: string;
  password: string;
  password_confirmation: string;
  [key: string]: string;
}

export default function Password() {
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  const form = useForm<PasswordForm>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route('password.update'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
      onError: (errors) => {
        if (errors.password) {
          form.reset('password', 'password_confirmation');
          passwordInput.current?.focus();
        }

        if (errors.current_password) {
          form.reset('current_password');
          currentPasswordInput.current?.focus();
        }
      },
    });
  };

  return (
    <>
      <Head title="Pengaturan Akun" />

      <PageHeader
        title="Pengaturan Akun"
        description="Kelola profil, foto, dan keamanan akun Anda."
        breadcrumbs={[{ label: 'Pengaturan' }, { label: 'Password' }]}
      />

      <SettingsTabs />

      <form onSubmit={submit} className="flex max-w-xl flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="current_password">Password Saat Ini</Label>
          <Input
            id="current_password"
            ref={currentPasswordInput}
            type="password"
            autoComplete="current-password"
            value={form.data.current_password}
            onChange={(e) => form.setData('current_password', e.target.value)}
          />
          <FieldError message={form.errors.current_password} />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="password">Password Baru</Label>
          <Input
            id="password"
            ref={passwordInput}
            type="password"
            autoComplete="new-password"
            value={form.data.password}
            onChange={(e) => form.setData('password', e.target.value)}
          />
          <FieldError message={form.errors.password} />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="password_confirmation">Konfirmasi Password Baru</Label>
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(e) => form.setData('password_confirmation', e.target.value)}
          />
          <FieldError message={form.errors.password_confirmation} />
        </div>

        <div className="flex items-center gap-4">
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Menyimpan...' : 'Simpan Password'}
          </Button>
          {form.recentlySuccessful && <span className="text-sm text-green-600">Tersimpan</span>}
        </div>
      </form>
    </>
  );
}

Password.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
