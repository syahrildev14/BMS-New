import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useRef, useState } from 'react';
import { Camera, Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { SettingsTabs } from '@/shared/components/layout/SettingsTabs';
import { UserAvatar } from '@/shared/components/ui/avatar';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { confirmDelete } from '@/shared/lib/alerts';
import type { AppUser } from '@/shared/types/prd.type';

interface ProfileForm {
  name: string;
  email: string;
  [key: string]: string;
}

interface AvatarForm {
  avatar: File | null;
  [key: string]: File | null;
}

interface DeleteAccountForm {
  password: string;
  [key: string]: string;
}

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
  const { props } = usePage<{ auth: { user: AppUser } }>();
  const { auth } = props;
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

  const profileForm = useForm<ProfileForm>({ name: auth.user.name, email: auth.user.email });
  const avatarForm = useForm<AvatarForm>({ avatar: null });
  const deleteForm = useForm<DeleteAccountForm>({ password: '' });

  const submitProfile: FormEventHandler = (e) => {
    e.preventDefault();
    profileForm.patch(route('profile.update'));
  };

  const pickAvatar = () => fileInputRef.current?.click();

  const onAvatarSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setPreviewUrl(URL.createObjectURL(file));
    avatarForm.setData('avatar', file);
    avatarForm.post(route('profile.avatar.update'), {
      forceFormData: true,
      onFinish: () => setPreviewUrl(null),
    });
  };

  const removeAvatar = async () => {
    const ok = await confirmDelete({ title: 'Hapus foto profil?', text: 'Foto profil akan dikembalikan ke inisial nama.' });
    if (ok) avatarForm.delete(route('profile.avatar.destroy'));
  };

  const submitDelete: FormEventHandler = (e) => {
    e.preventDefault();
    deleteForm.delete(route('profile.destroy'), {
      onSuccess: () => setDeleteDialogOpen(false),
      onError: () => deleteForm.reset('password'),
    });
  };

  return (
    <>
      <Head title="Pengaturan Akun" />

      <PageHeader title="Pengaturan Akun" description="Kelola profil, foto, dan keamanan akun Anda." breadcrumbs={[{ label: 'Pengaturan' }, { label: 'Profil' }]} />

      <SettingsTabs />

      <div className="flex max-w-xl flex-col gap-8">
        <div className="flex items-center gap-4">
          <UserAvatar avatarUrl={previewUrl ?? auth.user.avatar_url} name={auth.user.name} className="h-16 w-16 text-base" />

          <div className="flex flex-col gap-2">
            <div className="flex gap-2">
              <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={onAvatarSelected} />
              <Button type="button" variant="outline" size="sm" onClick={pickAvatar} disabled={avatarForm.processing}>
                <Camera className="h-4 w-4" />
                {avatarForm.processing ? 'Mengunggah...' : 'Ganti Foto'}
              </Button>
              {auth.user.avatar_url && (
                <Button type="button" variant="ghost" size="sm" onClick={removeAvatar}>
                  <Trash2 className="h-4 w-4" />
                  Hapus
                </Button>
              )}
            </div>
            <FieldError message={avatarForm.errors.avatar} />
          </div>
        </div>

        <form onSubmit={submitProfile} className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="name">Nama</Label>
            <Input id="name" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} />
            <FieldError message={profileForm.errors.name} />
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="email">Email</Label>
            <Input id="email" type="email" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} />
            <FieldError message={profileForm.errors.email} />

            {mustVerifyEmail && auth.user.email_verified_at === null && (
              <div className="mt-1 text-sm text-gray-500">
                Email Anda belum terverifikasi.{' '}
                <Link href={route('verification.send')} method="post" as="button" className="text-blue-600 underline hover:text-blue-700">
                  Kirim ulang email verifikasi.
                </Link>
                {status === 'verification-link-sent' && <p className="mt-1 text-green-600">Link verifikasi baru telah dikirim.</p>}
              </div>
            )}
          </div>

          <div className="flex items-center gap-4">
            <Button type="submit" disabled={profileForm.processing}>
              {profileForm.processing ? 'Menyimpan...' : 'Simpan'}
            </Button>
            {profileForm.recentlySuccessful && <span className="text-sm text-green-600">Tersimpan</span>}
          </div>
        </form>

        <div className="flex flex-col gap-2 rounded-lg border border-red-200 bg-red-50 p-4">
          <p className="text-sm font-medium text-red-700">Hapus Akun</p>
          <p className="text-sm text-red-600">Setelah akun dihapus, seluruh data terkait tidak dapat dikembalikan.</p>
          <div>
            <Button type="button" variant="destructive" size="sm" onClick={() => setDeleteDialogOpen(true)}>
              Hapus Akun
            </Button>
          </div>
        </div>
      </div>

      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Hapus akun Anda?</DialogTitle>
          </DialogHeader>

          <p className="text-sm text-gray-500">
            Setelah akun dihapus, seluruh data terkait akan hilang permanen. Masukkan password untuk konfirmasi.
          </p>

          <form onSubmit={submitDelete} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="delete_password">Password</Label>
              <Input
                id="delete_password"
                type="password"
                value={deleteForm.data.password}
                onChange={(e) => deleteForm.setData('password', e.target.value)}
              />
              <FieldError message={deleteForm.errors.password} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                Batal
              </Button>
              <Button type="submit" variant="destructive" disabled={deleteForm.processing}>
                {deleteForm.processing ? 'Menghapus...' : 'Hapus Akun'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

Profile.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
