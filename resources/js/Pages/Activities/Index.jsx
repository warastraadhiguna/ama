import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';
import ActivityMap from '../../Components/ActivityMap';

const STATUS_BADGE = {
    DRAFT: 'bg-gray-100 text-gray-700',
    SUBMITTED: 'bg-blue-100 text-blue-700',
    SYNCED: 'bg-blue-100 text-blue-700',
    VERIFIED: 'bg-green-100 text-green-700',
    REJECTED: 'bg-red-100 text-red-700',
};

const INTEGRITY_BADGE = {
    TRUSTED: 'bg-green-100 text-green-700',
    SUSPICIOUS: 'bg-amber-100 text-amber-700',
    REJECTED: 'bg-red-100 text-red-700',
};

export default function ActivitiesIndex({ activities, filters, filterOptions }) {
    const [form, setForm] = useState({
        date: filters.date ?? '',
        work_location_id: filters.work_location_id ?? '',
        creator_id: filters.creator_id ?? '',
        activity_type_id: filters.activity_type_id ?? '',
        product_id: filters.product_id ?? '',
        status: filters.status ?? '',
    });

    function applyFilters(e) {
        e.preventDefault();
        const query = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
        router.get('/activities', query, { preserveState: true });
    }

    function update(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }));
    }

    return (
        <AdminLayout>
            <Head title="Aktivitas" />

            <h1 className="mb-4 text-lg font-semibold text-gray-800">Monitoring Aktivitas</h1>

            <form onSubmit={applyFilters} className="mb-4 grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">
                <input
                    type="date"
                    value={form.date}
                    onChange={(e) => update('date', e.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                />
                <select
                    value={form.work_location_id}
                    onChange={(e) => update('work_location_id', e.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                >
                    <option value="">Semua Area</option>
                    {filterOptions.workLocations.map((w) => (
                        <option key={w.id} value={w.id}>{w.name}</option>
                    ))}
                </select>
                <select
                    value={form.creator_id}
                    onChange={(e) => update('creator_id', e.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                >
                    <option value="">Semua Agronomist</option>
                    {filterOptions.agronomists.map((a) => (
                        <option key={a.id} value={a.id}>{a.name}</option>
                    ))}
                </select>
                <select
                    value={form.activity_type_id}
                    onChange={(e) => update('activity_type_id', e.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                >
                    <option value="">Semua Jenis Kegiatan</option>
                    {filterOptions.activityTypes.map((t) => (
                        <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                </select>
                <select
                    value={form.product_id}
                    onChange={(e) => update('product_id', e.target.value)}
                    className="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                >
                    <option value="">Semua Produk</option>
                    {filterOptions.products.map((p) => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </select>
                <div className="flex gap-2">
                    <select
                        value={form.status}
                        onChange={(e) => update('status', e.target.value)}
                        className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    >
                        <option value="">Semua Status</option>
                        <option value="DRAFT">Draft</option>
                        <option value="SUBMITTED">Submitted</option>
                        <option value="VERIFIED">Verified</option>
                        <option value="REJECTED">Rejected</option>
                    </select>
                    <button type="submit" className="rounded-md bg-green-700 px-3 py-1.5 text-sm text-white hover:bg-green-800">
                        Filter
                    </button>
                </div>
            </form>

            <div className="mb-4">
                <ActivityMap activities={activities.data} />
            </div>

            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-2">Waktu</th>
                            <th className="px-4 py-2">Agronomist</th>
                            <th className="px-4 py-2">Jenis Kegiatan</th>
                            <th className="px-4 py-2">Produk</th>
                            <th className="px-4 py-2">Lokasi</th>
                            <th className="px-4 py-2">Status</th>
                            <th className="px-4 py-2">Integrity</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {activities.data.map((activity) => (
                            <tr key={activity.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-gray-500">
                                    {new Date(activity.created_at).toLocaleString('id-ID')}
                                </td>
                                <td className="px-4 py-2">
                                    <div>{activity.creator_name}</div>
                                    <div className="text-xs text-gray-400">{activity.work_location}</div>
                                </td>
                                <td className="px-4 py-2">{activity.activity_type}</td>
                                <td className="px-4 py-2">{activity.products.join(', ')}</td>
                                <td className="px-4 py-2">{activity.location}</td>
                                <td className="px-4 py-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_BADGE[activity.status] ?? 'bg-gray-100 text-gray-700'}`}>
                                        {activity.status}
                                    </span>
                                </td>
                                <td className="px-4 py-2">
                                    {activity.integrity_status && (
                                        <span className={`rounded-full px-2 py-0.5 text-xs ${INTEGRITY_BADGE[activity.integrity_status] ?? ''}`}>
                                            {activity.integrity_status}
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-2">
                                    <Link href={`/activities/${activity.id}`} className="text-green-700 hover:underline">
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {activities.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-gray-400">
                                    Tidak ada aktivitas yang cocok dengan filter.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {activities.links && (
                <div className="mt-4 flex flex-wrap gap-1 text-sm">
                    {activities.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveState
                            dangerouslySetInnerHTML={{ __html: link.label }}
                            className={`rounded-md px-3 py-1 ${link.active ? 'bg-green-700 text-white' : 'bg-white text-gray-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
