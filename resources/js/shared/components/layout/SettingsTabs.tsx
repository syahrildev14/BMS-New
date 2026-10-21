import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/shared/lib/utils';

const tabs = [
  { label: 'Profil', href: '/settings/profile' },
  { label: 'Password', href: '/settings/password' },
];

export function SettingsTabs() {
  const { url } = usePage();

  return (
    <div className="mb-6 flex gap-1 border-b border-gray-200">
      {tabs.map((tab) => {
        const active = url.startsWith(tab.href);

        return (
          <Link
            key={tab.href}
            href={tab.href}
            className={cn(
              'border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              active ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-900',
            )}
          >
            {tab.label}
          </Link>
        );
      })}
    </div>
  );
}
