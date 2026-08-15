import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, ChevronRight, LogOut } from 'lucide-react'; //icons
import { menuConfig } from '@/shared/constants/menu.config';
import { cn } from '@/shared/lib/utils';


interface AppLayoutProps {
  children: React.ReactNode;
}

/**
 * Shell utama ERP: Sidebar (kiri, collapsible di mobile) + Navbar (atas).
 * Semua Page (features/*) dibungkus layout ini dari pages/ entry, bukan di-render langsung.
 */
export function AppLayout({ children }: AppLayoutProps) {
  const { url } = usePage();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [openGroup, setOpenGroup] = useState<string | null>(null);

  const breadcrumbs = url
    .split("?")[0]
    .split("/")
    .filter(Boolean)
    .map((segment, index, array) => ({
      label: segment
        .replace(/-/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase()),
      href: "/" + array.slice(0, index + 1).join("/"),
    }));

  return (
    <div className="flex min-h-screen bg-gray-50">
      {/* Sidebar */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-40 w-64 transform border-r border-gray-200 bg-white transition-transform lg:static lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex h-16 items-center gap-2 border-b border-gray-100 px-5">
          <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-sm font-bold text-white">
            B
          </div>
          <span className="font-semibold text-muted">Sistem BMS</span>
        </div>

        <nav className="flex flex-col gap-1 p-3">
          {menuConfig.map((item) => {
            const Icon = item.icon;

            if (!item.children) {
              return (
                <Link
                  key={item.label}
                  href={item.href!}
                  className={cn(
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100',
                    url === item.href && 'bg-red-50 text-red-700',
                  )}
                >
                  <Icon className="h-4 w-4" />
                  {item.label}
                </Link>
              );
            }

            const isOpen = openGroup === item.label || item.children.some((c) => url.startsWith(c.href));

            return (
              <div key={item.label}>
                <button
                  onClick={() => setOpenGroup(isOpen ? null : item.label)}
                  className="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
                >
                  <span className="flex items-center gap-3">
                    <Icon className="h-4 w-4" />
                    {item.label}
                  </span>
                  <ChevronDown className={cn('h-4 w-4 transition-transform', isOpen && 'rotate-180')} />
                </button>

                {isOpen && (
                  <div className="ml-7 mt-1 flex flex-col gap-0.5 border-l border-gray-100 pl-3">
                    {item.children.map((child) => (
                      <Link
                        key={child.href}
                        href={child.href}
                        className={cn(
                          'rounded-md px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-800',
                          url.startsWith(child.href) && 'font-medium text-red-700',
                        )}
                      >
                        {child.label}
                      </Link>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </nav>
      </aside>

      {/* Overlay mobile */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/20 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Main area */}
      <div className="flex flex-1 flex-col lg:pl-0">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
          <button
            className="rounded-md p-2 bg-red-700 hover:bg-gray-100 lg:hidden"
            onClick={() => setSidebarOpen(true)}
          >
            <Menu className="h-5 w-5" />
          </button>

          <div className="ml-auto flex items-center gap-4">
            <button
              type="button"
              onClick={() => router.post(route('logout'))}
              className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-600 transition hover:bg-gray-100"
            >
              <span className="hidden sm:inline">Logout</span>
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </header>

        <div className="border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
          <nav className="flex items-center text-sm text-gray-500">

            {breadcrumbs.map((item) => (
              <div key={item.href} className="flex items-center">
                <ChevronRight className="mx-2 h-4 w-4" />

                <Link
                  href={item.href}
                  className={
                    item.href === url
                      ? "font-semibold text-accent"
                      : "hover:text-red-600"
                  }
                >
                  {item.label}
                </Link>
              </div>
            ))}
          </nav>
        </div>
        <main className="flex-1 p-4 sm:p-6">{children}</main>
      </div>
    </div>
  );
}
