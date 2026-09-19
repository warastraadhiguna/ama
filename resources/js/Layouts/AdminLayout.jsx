import { Link, usePage, router } from '@inertiajs/react';

const NAV_ITEMS = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/activities', label: 'Aktivitas' },
    { href: '/reports', label: 'Laporan', permission: 'reports.view' },
    { href: '/users', label: 'Pengguna', permission: 'users.manage' },
];

export default function AdminLayout({ children }) {
    const { auth, flash } = usePage().props;
    const visibleNav = NAV_ITEMS.filter(
        (item) => !item.permission || auth.user?.permissions?.includes(item.permission),
    );

    function logout(e) {
        e.preventDefault();
        router.post('/logout');
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <div className="flex items-center gap-8">
                        <span className="text-sm font-semibold text-green-800">AMA Web Admin</span>
                        <nav className="flex gap-4 text-sm">
                            {visibleNav.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="text-gray-600 hover:text-green-800"
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    <div className="flex items-center gap-3 text-sm text-gray-600">
                        <span>
                            {auth.user?.name}
                            {auth.user?.roles?.length > 0 && (
                                <span className="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">
                                    {auth.user.roles.join(', ')}
                                </span>
                            )}
                        </span>
                        <button onClick={logout} className="text-gray-500 hover:text-red-600">
                            Keluar
                        </button>
                    </div>
                </div>
            </header>

            {flash?.success && (
                <div className="mx-auto mt-4 max-w-6xl rounded-md bg-green-50 px-4 py-2 text-sm text-green-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mx-auto mt-4 max-w-6xl rounded-md bg-red-50 px-4 py-2 text-sm text-red-800">
                    {flash.error}
                </div>
            )}

            <main className="mx-auto max-w-6xl px-4 py-6">{children}</main>
        </div>
    );
}
