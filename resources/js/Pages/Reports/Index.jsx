import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../Layouts/AdminLayout';

const INTEGRITY_LABEL = {
    TRUSTED: { label: 'Verified', tone: 'bg-green-50 text-green-800' },
    SUSPICIOUS: { label: 'Needs Review', tone: 'bg-amber-50 text-amber-800' },
    REJECTED: { label: 'Rejected', tone: 'bg-red-50 text-red-800' },
};

const INPUT = 'w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm';

function Select({ label, value, onChange, options }) {
    return (
        <label className="block text-xs text-gray-600">
            {label}
            <select className={INPUT} value={value} onChange={(e) => onChange(e.target.value)}>
                <option value="">Semua</option>
                {options.map((o) => (
                    <option key={o.id} value={o.id}>{o.name}</option>
                ))}
            </select>
        </label>
    );
}

export default function ReportsIndex({ summary, filters, filterOptions }) {
    const [form, setForm] = useState({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        work_location_id: filters.work_location_id ?? '',
        creator_id: filters.creator_id ?? '',
        activity_type_id: filters.activity_type_id ?? '',
        product_id: filters.product_id ?? '',
    });

    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const set = (field) => (value) => setForm((prev) => ({ ...prev, [field]: value }));

    function apply(e) {
        e.preventDefault();
        router.get('/reports', query(), { preserveState: true });
    }

    const exportHref = '/reports/export' + (Object.keys(query()).length ? '?' + new URLSearchParams(query()) : '');
    const rate = summary.realization_rate;
    const integrity = Object.entries(summary.by_integrity_status ?? {});

    return (
        <AdminLayout>
            <Head title="Laporan" />

            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-lg font-semibold text-gray-800">Laporan</h1>
                <a href={exportHref} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm hover:bg-gray-50">
                    Unduh CSV
                </a>
            </div>

            <form onSubmit={apply} className="mb-6 grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-4">
                <label className="block text-xs text-gray-600">
                    Dari tanggal
                    <input type="date" className={INPUT} value={form.date_from} onChange={(e) => set('date_from')(e.target.value)} />
                </label>
                <label className="block text-xs text-gray-600">
                    Sampai tanggal
                    <input type="date" className={INPUT} value={form.date_to} onChange={(e) => set('date_to')(e.target.value)} />
                </label>
                <Select label="Lokasi tugas" value={form.work_location_id} onChange={set('work_location_id')} options={filterOptions.workLocations} />
                <Select label="Agronomist" value={form.creator_id} onChange={set('creator_id')} options={filterOptions.agronomists} />
                <Select label="Jenis kegiatan" value={form.activity_type_id} onChange={set('activity_type_id')} options={filterOptions.activityTypes} />
                <Select label="Produk" value={form.product_id} onChange={set('product_id')} options={filterOptions.products} />
                <div className="flex items-end">
                    <button className="rounded-md bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800">Terapkan</button>
                </div>
            </form>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div className="rounded-lg bg-green-50 p-4 text-green-800">
                    <div className="text-2xl font-semibold">{summary.activities_total}</div>
                    <div className="mt-1 text-sm">Aktivitas</div>
                </div>
                <div className="rounded-lg bg-blue-50 p-4 text-blue-800">
                    <div className="text-2xl font-semibold">{summary.plans_total}</div>
                    <div className="mt-1 text-sm">Rencana</div>
                </div>
                <div className="rounded-lg bg-blue-50 p-4 text-blue-800">
                    <div className="text-2xl font-semibold">{summary.plans_realized}</div>
                    <div className="mt-1 text-sm">Rencana terealisasi</div>
                </div>
                <div className="rounded-lg bg-amber-50 p-4 text-amber-800">
                    <div className="text-2xl font-semibold">{rate === null ? '-' : `${Math.round(rate * 1000) / 10}%`}</div>
                    <div className="mt-1 text-sm">Tingkat realisasi</div>
                    <div className="text-xs opacity-75">{summary.plans_cancelled} dibatalkan tidak dihitung</div>
                </div>
            </div>

            <h2 className="mb-2 mt-6 text-sm font-semibold text-gray-800">Integritas lokasi</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {integrity.length === 0 && <div className="text-sm text-gray-500">Belum ada data lokasi.</div>}
                {integrity.map(([status, count]) => {
                    const meta = INTEGRITY_LABEL[status] ?? { label: status, tone: 'bg-gray-50 text-gray-700' };
                    return (
                        <div key={status} className={`rounded-lg p-4 ${meta.tone}`}>
                            <div className="text-2xl font-semibold">{count}</div>
                            <div className="mt-1 text-sm">{meta.label}</div>
                        </div>
                    );
                })}
            </div>
        </AdminLayout>
    );
}
