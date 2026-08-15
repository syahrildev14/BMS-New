import { Fragment } from 'react';
import { ChevronRight } from 'lucide-react';

interface Breadcrumb {
  label: string;
  href?: string;
}

interface PageHeaderProps {
  title: string;
  description?: string;
  breadcrumbs?: Breadcrumb[];
  /** Slot kanan: tombol "Tambah Barang", export, dsb — konsisten di semua modul */
  actions?: React.ReactNode;
}

export function PageHeader({ title, description, breadcrumbs, actions }: PageHeaderProps) {
  return (
    <div className="mb-6 flex flex-col gap-3 border-b border-gray-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        {breadcrumbs && breadcrumbs.length > 0 && (
          <nav className="mb-1 flex items-center text-xs text-gray-400">
            {breadcrumbs.map((crumb, i) => (
              <Fragment key={crumb.label}>
                {i > 0 && <ChevronRight className="mx-1 h-3 w-3" />}
                <span className={i === breadcrumbs.length - 1 ? 'text-gray-500' : ''}>
                  {crumb.label}
                </span>
              </Fragment>
            ))}
          </nav>
        )}
        <h1 className="text-xl font-semibold text-gray-900">{title}</h1>
        {description && <p className="mt-1 text-sm text-gray-500">{description}</p>}
      </div>

      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}
