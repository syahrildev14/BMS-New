import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, LogOut, PanelLeftClose, PanelLeftOpen, Settings } from 'lucide-react'; //icons
import { menuConfig } from '@/shared/constants/menu.config';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/shared/components/ui/collapsible';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/components/ui/tooltip';
import { UserAvatar } from '@/shared/components/ui/avatar';
import { useMediaQuery } from '@/shared/hooks/use-media-query';
import { cn } from '@/shared/lib/utils';
import { successAlert, errorAlert, confirmLogout } from '@/shared/lib/alerts';
import type { AppUser } from '@/shared/types/prd.type';

const SIDEBAR_COLLAPSED_KEY = 'bms-sidebar-collapsed';

interface AppLayoutProps {
  children: React.ReactNode;
}

interface FlashProps {
  flash?: { success?: string | null; error?: string | null };
  auth?: { user?: AppUser | null; permissions?: string[] };
  [key: string]: unknown;
}

/**
 * Shell utama ERP: Sidebar (kiri, collapsible di mobile + collapse-to-icon di desktop) + Navbar (atas).
 * Semua Page (features/*) dibungkus layout ini dari pages/ entry, bukan di-render langsung.
 */
export function AppLayout({ children }: AppLayoutProps) {
  const { url, props } = usePage<FlashProps>();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [openGroup, setOpenGroup] = useState<string | null>(null);
  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false;
    return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true';
  });
  const isDesktop = useMediaQuery('(min-width: 1024px)');
  /** Icon-only rail is a desktop affordance — on mobile the drawer always shows full labels regardless of the persisted preference. */
  const showCollapsedUI = collapsed && isDesktop;

  useEffect(() => {
    if (props.flash?.success) successAlert(props.flash.success);
    if (props.flash?.error) errorAlert(props.flash.error);
  }, [props.flash]);

  const permissions = props.auth?.permissions ?? [];
  const can = (permission?: string) => !permission || permissions.includes(permission);

  const visibleMenu = menuConfig
    .map((item) => ({
      ...item,
      children: item.children?.filter((child) => can(child.permission)),
    }))
    .filter((item) => can(item.permission) && (!item.children || item.children.length > 0));

  const toggleCollapsed = () => {
    setCollapsed((prev) => {
      const next = !prev;
      window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(next));
      return next;
    });
  };

  const handleLogout = async () => {
    const confirmed = await confirmLogout();
    if (confirmed) router.post(route('logout'));
  };

  return (
    <TooltipProvider delayDuration={150}>
      <div className="flex min-h-screen bg-gray-50">
        {/* Sidebar */}
        <aside
          className={cn(
            'fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto border-r border-gray-200 bg-white transition-all duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:self-start',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            collapsed && 'lg:w-16',
          )}
        >
          <div className={cn('flex h-16 items-center gap-2 border-b border-gray-100 px-5', collapsed && 'lg:justify-center lg:px-0')}>
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-blue-600 text-sm font-bold text-white shadow-sm">
              B
            </div>
            <span className={cn('font-semibold text-gray-900', collapsed && 'lg:hidden')}>Sistem BMS</span>
          </div>

          <div
            className={cn(
              'hidden items-center border-b border-gray-100 px-3 py-2 lg:flex',
              collapsed ? 'lg:justify-center' : 'lg:justify-end',
            )}
          >
            <button
              type="button"
              onClick={toggleCollapsed}
              className="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
            >
              {collapsed ? <PanelLeftOpen className="h-4 w-4" /> : <PanelLeftClose className="h-4 w-4" />}
            </button>
          </div>

          <nav className="flex flex-col gap-1 p-3">
            {visibleMenu.map((item) => {
              const Icon = item.icon;

              if (!item.children) {
                const active = url === item.href;
                const link = (
                  <Link
                    href={item.href!}
                    className={cn(
                      'flex items-center gap-3 rounded-md border-l-2 border-transparent px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900',
                      active && 'border-blue-600 bg-blue-50 text-blue-700 hover:bg-blue-50 hover:text-blue-700',
                      collapsed && 'lg:justify-center lg:border-l-0 lg:px-0',
                    )}
                  >
                    <Icon className="h-4 w-4 shrink-0" />
                    <span className={cn(collapsed && 'lg:hidden')}>{item.label}</span>
                  </Link>
                );

                if (!showCollapsedUI) return <div key={item.label}>{link}</div>;

                return (
                  <Tooltip key={item.label}>
                    <TooltipTrigger asChild>{link}</TooltipTrigger>
                    <TooltipContent side="right" className="hidden lg:block">
                      {item.label}
                    </TooltipContent>
                  </Tooltip>
                );
              }

              const groupActive = item.children.some((c) => url.startsWith(c.href));
              const isOpen = openGroup === item.label || groupActive;

              if (showCollapsedUI) {
                return (
                  <Tooltip key={item.label}>
                    <DropdownMenu>
                      <TooltipTrigger asChild>
                        <DropdownMenuTrigger asChild>
                          <button
                            type="button"
                            className={cn(
                              'flex w-full items-center justify-center rounded-md px-3 py-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 lg:justify-center lg:px-0',
                              groupActive && 'bg-blue-50 text-blue-700 hover:bg-blue-50 hover:text-blue-700',
                            )}
                          >
                            <Icon className="h-4 w-4 shrink-0" />
                            <span className="lg:hidden">{item.label}</span>
                          </button>
                        </DropdownMenuTrigger>
                      </TooltipTrigger>
                      <DropdownMenuContent side="right" align="start">
                        <DropdownMenuLabel>{item.label}</DropdownMenuLabel>
                        {item.children.map((child) => (
                          <DropdownMenuItem key={child.href} asChild>
                            <Link href={child.href}>{child.label}</Link>
                          </DropdownMenuItem>
                        ))}
                      </DropdownMenuContent>
                    </DropdownMenu>
                    <TooltipContent side="right" className="hidden lg:block">
                      {item.label}
                    </TooltipContent>
                  </Tooltip>
                );
              }

              return (
                <Collapsible key={item.label} open={isOpen} onOpenChange={(open) => setOpenGroup(open ? item.label : null)}>
                  <CollapsibleTrigger asChild>
                    <button
                      type="button"
                      className={cn(
                        'flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-gray-100 hover:text-gray-900',
                        groupActive ? 'text-gray-900' : 'text-gray-600',
                      )}
                    >
                      <span className="flex items-center gap-3">
                        <Icon className="h-4 w-4" />
                        {item.label}
                      </span>
                      <ChevronDown className={cn('h-4 w-4 transition-transform', isOpen && 'rotate-180')} />
                    </button>
                  </CollapsibleTrigger>

                  <CollapsibleContent>
                    <div className="ml-7 mt-1 flex flex-col gap-0.5 border-l border-gray-100 pl-3">
                      {item.children.map((child) => (
                        <Link
                          key={child.href}
                          href={child.href}
                          className={cn(
                            'rounded-md px-2 py-1.5 text-sm text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900',
                            url.startsWith(child.href) && 'bg-blue-50 font-medium text-blue-700 hover:bg-blue-50 hover:text-blue-700',
                          )}
                        >
                          {child.label}
                        </Link>
                      ))}
                    </div>
                  </CollapsibleContent>
                </Collapsible>
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
          <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6">
            <button
              className="rounded-md p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 lg:hidden"
              onClick={() => setSidebarOpen(true)}
            >
              <Menu className="h-5 w-5" />
            </button>

            <div className="ml-auto flex items-center gap-4">
              {props.auth?.user && (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button
                      type="button"
                      className="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1.5 transition-colors hover:bg-gray-100"
                    >
                      <UserAvatar avatarUrl={props.auth.user.avatar_url} name={props.auth.user.name} />
                      <div className="hidden flex-col items-start sm:flex">
                        <span className="text-sm font-medium leading-tight text-gray-700">{props.auth.user.name}</span>
                        {props.auth.user.roles?.[0]?.name && (
                          <span className="text-xs leading-tight text-gray-400 capitalize">{props.auth.user.roles[0].name}</span>
                        )}
                      </div>
                      <ChevronDown className="hidden h-4 w-4 text-gray-400 sm:inline" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuLabel>{props.auth.user.email}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                      <Link href={route('profile.edit')} className="flex items-center gap-2">
                        <Settings className="h-4 w-4" />
                        Pengaturan Akun
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={handleLogout} className="flex items-center gap-2 text-red-600 focus:bg-red-50 focus:text-red-600">
                      <LogOut className="h-4 w-4" />
                      Logout
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              )}
            </div>
          </header>

          <main className="flex-1 p-4 sm:p-6">{children}</main>
        </div>
      </div>
    </TooltipProvider>
  );
}
