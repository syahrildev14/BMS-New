import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, ShoppingCart, Truck, Warehouse } from 'lucide-react';
import type { ReactNode } from 'react';
import { BarList } from '@/shared/components/charts/BarList';
import { StatTile } from '@/shared/components/charts/StatTile';
import { FinanceAnalyticsSection } from '@/features/dashboard/FinanceAnalyticsSection';
import { cn } from '@/shared/lib/utils';
import type { AppUser, DashboardWidgets } from '@/shared/types/prd.type';

interface DashboardPageProps {
  widgets: DashboardWidgets;
}

interface PageProps {
  auth?: { user?: AppUser | null };
  [key: string]: unknown;
}

interface Section {
  title: string;
  icon: typeof ClipboardList;
  href: string;
  linkLabel: string;
  tiles: { label: string; value: string; tone?: 'default' | 'good' | 'bad'; hint?: string }[];
}

interface ChartCard {
  title: string;
  icon: typeof ClipboardList;
  node: ReactNode;
}

export function DashboardPage({ widgets }: DashboardPageProps) {
  const { auth } = usePage<PageProps>().props;
  const user = auth?.user;
  const roleName = user?.roles?.[0]?.name;

  const sections: Section[] = [];
  const charts: ChartCard[] = [];

  if (widgets.dapur) {
    sections.push({
      title: 'Order Saya',
      icon: ClipboardList,
      href: '/dapur/orders',
      linkLabel: 'Kelola Order',
      tiles: [
        { label: 'Order aktif', value: String(widgets.dapur.open_orders) },
        {
          label: 'Menunggu STT',
          value: String(widgets.dapur.pending_stt),
          tone: widgets.dapur.pending_stt > 0 ? 'bad' : 'good',
        },
      ],
    });

    charts.push({
      title: 'Order per Hari (14 hari terakhir)',
      icon: ClipboardList,
      node: <BarList rows={widgets.dapur.trend} formatValue={String} emptyLabel="Belum ada order pada periode ini." />,
    });
  }

  if (widgets.ops) {
    sections.push({
      title: 'Operasional Pengiriman',
      icon: Truck,
      href: '/ops/delivery-notes',
      linkLabel: 'Kelola Surat Jalan',
      tiles: [
        {
          label: 'Order belum ada SJ',
          value: String(widgets.ops.orders_pending_sj),
          tone: widgets.ops.orders_pending_sj > 0 ? 'bad' : 'good',
        },
        ...(widgets.ops.pending_revisions !== undefined
          ? [
              {
                label: 'Revisi menunggu approval',
                value: String(widgets.ops.pending_revisions),
                tone: (widgets.ops.pending_revisions > 0 ? 'bad' : 'good') as 'good' | 'bad',
              },
            ]
          : []),
      ],
    });
  }

  if (widgets.purchasing) {
    sections.push({
      title: 'Purchase Order',
      icon: ShoppingCart,
      href: '/purchasing/purchase-orders',
      linkLabel: 'Kelola PO',
      tiles: [
        {
          label: 'Draft PO',
          value: String(widgets.purchasing.draft_pos),
          tone: widgets.purchasing.draft_pos > 0 ? 'bad' : 'good',
        },
      ],
    });
  }

  if (widgets.gudang) {
    sections.push({
      title: 'Gudang',
      icon: Warehouse,
      href: '/gudang/stock',
      linkLabel: 'Kelola Stok',
      tiles: [
        { label: 'PO menunggu diterima', value: String(widgets.gudang.pos_awaiting_receive) },
        {
          label: 'Barang stok kosong',
          value: String(widgets.gudang.out_of_stock_items),
          tone: widgets.gudang.out_of_stock_items > 0 ? 'bad' : 'good',
        },
      ],
    });
  }

  const hasAnything = sections.length > 0 || widgets.finance;

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
          Selamat datang{user?.name ? `, ${user.name}` : ''}
        </h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">
          {roleName ? `Role: ${roleName} — ` : ''}
          Ringkasan pekerjaan yang perlu ditindaklanjuti hari ini.
        </p>
      </div>

      {!hasAnything ? (
        <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-400 dark:border-gray-800 dark:bg-gray-900">
          Belum ada modul yang bisa diakses untuk akun ini. Hubungi Admin kalau ini tidak sesuai.
        </div>
      ) : (
        <>
          {sections.length > 0 && (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {sections.map((section) => {
                const Icon = section.icon;

                return (
                  <div
                    key={section.title}
                    className="flex flex-col gap-4 rounded-xl border border-white/30 bg-white/10 p-5 shadow-[0_8px_32px_var(--primary-glow)] backdrop-blur-xl dark:border-white/10 dark:bg-white/5"
                  >
                    <div className="flex items-center gap-2">
                      <Icon className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                      <h2 className="text-sm font-semibold text-gray-800 dark:text-gray-100">{section.title}</h2>
                    </div>

                    <div className={cn('grid gap-2', section.tiles.length === 1 ? 'grid-cols-1' : 'grid-cols-2')}>
                      {section.tiles.map((tile) => (
                        <StatTile key={tile.label} {...tile} />
                      ))}
                    </div>

                    {/* mt-auto pins this to the card's bottom edge even when a
                        sibling card in the same grid row is taller (grid rows
                        stretch to equal height by default), so the quick-link
                        lands at a consistent spot across every card instead of
                        floating right under whatever amount of tiles a given
                        section happens to have. */}
                    <Link
                      href={section.href}
                      className="mt-auto text-sm font-medium text-primary-text hover:underline"
                    >
                      {section.linkLabel} &rarr;
                    </Link>
                  </div>
                );
              })}
            </div>
          )}

          {charts.length > 0 && (
            <div className="grid gap-4 lg:grid-cols-2">
              {charts.map((chart) => {
                const Icon = chart.icon;

                return (
                  <div key={chart.title} className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div className="mb-3 flex items-center gap-2">
                      <Icon className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                      <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200">{chart.title}</h2>
                    </div>
                    {chart.node}
                  </div>
                );
              })}
            </div>
          )}

          {widgets.finance && (
            <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
              <FinanceAnalyticsSection {...widgets.finance} />
            </div>
          )}
        </>
      )}
    </div>
  );
}
