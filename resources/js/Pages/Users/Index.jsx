import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

export default function UsersIndex({ users, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch(e) {
        e.preventDefault();
        router.get('/users', search ? { search } : {}, { preserveState: true });
    }

    return (
        <AdminLayout>
            <Head title="Pengguna" />

            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-lg font-semibold text-gray-800">Pengguna</h1>
                <Link
                    href="/users/create"
                    className="rounded-md bg-green-700 px-3 py-1.5 text-sm text-white hover:bg-green-800"
                >
                    Tambah Pengguna
                </Link>
            </div>

            <form onSubmit={applySearch} className="mb-4 flex gap-2">
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Cari nama, email, atau NIP"
                    className="w-full max-w-sm rounded-md border border-gray-300 px-3 py-1.5 text-sm"
                />
                <button className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm">Cari</button>
            </form>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-2">Nama</th>
                            <th className="px-4 py-2">Role</th>
                            <th className="px-4 py-2">Jabatan</th>
                            <th className="px-4 py-2">Lokasi Tugas</th>
                            <th className="px-4 py-2">Status</th>
                            <th className="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {users.data.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-2">
                                    <div className="font-medium text-gray-800">{user.name}</div>
                                    <div className="text-xs text-gray-500">{user.email}</div>
                                </td>
                                <td className="px-4 py-2">{user.role ?? '-'}</td>
                                <td className="px-4 py-2">{user.position ?? '-'}</td>
                                <td className="px-4 py-2">{user.work_location ?? '-'}</td>
                                <td className="px-4 py-2">
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs ${
                                            user.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                        }`}
                                    >
                                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </td>
                                <td className="px-4 py-2 text-right">
                                    <Link href={`/users/${user.id}/edit`} className="text-green-700 hover:underline">
                                        Ubah
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-6 text-center text-gray-500">
                                    Tidak ada pengguna.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {users.last_page > 1 && (
                <div className="mt-4 flex gap-2 text-sm">
                    {users.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            className={`rounded border px-2 py-1 ${
                                link.active ? 'border-green-700 bg-green-700 text-white' : 'border-gray-300 bg-white'
                            } disabled:opacity-40`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
