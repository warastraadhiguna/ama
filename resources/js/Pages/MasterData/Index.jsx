import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const INPUT = 'w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm';

function RowForm({ resource, categories, initial, onDone }) {
    const editing = initial !== null;
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: initial?.name ?? '',
        code: initial?.code ?? '',
        is_active: initial?.is_active ?? true,
        product_category_id: initial?.product_category_id ?? '',
    });

    function submit(e) {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone?.();
            },
        };
        if (editing) {
            put(`/master-data/${resource}/${initial.id}`, options);
        } else {
            post(`/master-data/${resource}`, options);
        }
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <label className="block text-xs text-gray-600">
                Nama
                <input className={INPUT} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                {errors.name && <span className="text-red-600">{errors.name}</span>}
            </label>
            <label className="block text-xs text-gray-600">
                Kode
                <input className={INPUT} value={data.code} onChange={(e) => setData('code', e.target.value)} />
                {errors.code && <span className="text-red-600">{errors.code}</span>}
            </label>
            {resource === 'products' && (
                <label className="block text-xs text-gray-600">
                    Kategori
                    <select className={INPUT} value={data.product_category_id} onChange={(e) => setData('product_category_id', e.target.value)}>
                        <option value="">-</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                    {errors.product_category_id && <span className="text-red-600">{errors.product_category_id}</span>}
                </label>
            )}
            <label className="flex items-end gap-2 pb-1.5 text-sm text-gray-700">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                Aktif
            </label>
            <div className="flex items-end gap-2">
                <button disabled={processing} className="rounded-md bg-green-700 px-3 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                    {editing ? 'Simpan' : 'Tambah'}
                </button>
                {editing && (
                    <button type="button" onClick={onDone} className="text-sm text-gray-500 hover:underline">
                        Batal
                    </button>
                )}
            </div>
        </form>
    );
}

export default function MasterDataIndex({ resource, tabs, rows, categories }) {
    const [editingId, setEditingId] = useState(null);

    return (
        <AdminLayout>
            <Head title="Master Data" />

            <h1 className="mb-4 text-lg font-semibold text-gray-800">Master Data</h1>

            <div className="mb-4 flex flex-wrap gap-2 text-sm">
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={`/master-data/${tab.key}`}
                        className={`rounded-full border px-3 py-1 ${
                            tab.key === resource ? 'border-green-700 bg-green-700 text-white' : 'border-gray-300 bg-white text-gray-700'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <RowForm key={`new-${resource}`} resource={resource} categories={categories} initial={null} />
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-2">Nama</th>
                            <th className="px-4 py-2">Kode</th>
                            {resource === 'products' && <th className="px-4 py-2">Kategori</th>}
                            <th className="px-4 py-2">Status</th>
                            <th className="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((row) =>
                            editingId === row.id ? (
                                <tr key={row.id}>
                                    <td colSpan={resource === 'products' ? 5 : 4} className="bg-amber-50 px-4 py-3">
                                        <RowForm resource={resource} categories={categories} initial={row} onDone={() => setEditingId(null)} />
                                    </td>
                                </tr>
                            ) : (
                                <tr key={row.id}>
                                    <td className="px-4 py-2 text-gray-800">{row.name}</td>
                                    <td className="px-4 py-2 text-gray-600">{row.code}</td>
                                    {resource === 'products' && <td className="px-4 py-2">{row.product_category ?? '-'}</td>}
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                            {row.is_active ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <button onClick={() => setEditingId(row.id)} className="text-green-700 hover:underline">
                                            Ubah
                                        </button>
                                    </td>
                                </tr>
                            ),
                        )}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">Belum ada data.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
