import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { FileText, PackageSearch, Truck } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

const highlights = [
    { icon: PackageSearch, text: 'Dapur input pesanan langsung, harga tersinkron harian' },
    { icon: Truck, text: 'Surat Jalan Utama & Susulan, tanpa duplikasi pengiriman' },
    { icon: FileText, text: 'Invoice terpecah otomatis per supplier, siap audit' },
];

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col overflow-hidden bg-zinc-950 p-10 text-white lg:flex">
                <div
                    className="absolute inset-0 opacity-40"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 15% 15%, color-mix(in srgb, var(--primary) 35%, transparent), transparent 45%), radial-gradient(circle at 85% 85%, color-mix(in srgb, var(--primary) 30%, transparent), transparent 50%)',
                    }}
                />
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px)',
                        backgroundSize: '44px 44px',
                    }}
                />

                <Link href={route('home')} className="relative z-20 flex items-center gap-2 text-lg font-semibold">
                    <AppLogoIcon className="size-8 fill-current text-white" />
                    {name}
                </Link>

                <div className="relative z-20 mt-auto flex flex-col gap-8">
                    <div>
                        <p className="text-2xl font-semibold text-balance">Satu sistem untuk seluruh rantai pasok SPPG/MBG.</p>
                        <p className="mt-2 max-w-sm text-sm text-zinc-400">
                            Dari pesanan Dapur, pengiriman Supplier, stok Gudang, sampai penagihan Finance — terhubung dan tercatat.
                        </p>
                    </div>

                    <ul className="flex flex-col gap-4">
                        {highlights.map(({ icon: Icon, text }) => (
                            <li key={text} className="flex items-start gap-3 text-sm text-zinc-300">
                                <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-white/10">
                                    <Icon className="size-4" />
                                </span>
                                {text}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link href={route('home')} className="relative z-20 flex items-center justify-center lg:hidden">
                        <AppLogoIcon className="h-10 fill-current text-black sm:h-12" />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-muted-foreground text-sm text-balance">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
