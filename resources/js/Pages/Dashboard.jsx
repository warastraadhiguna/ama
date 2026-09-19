import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../Layouts/AdminLayout';

const CARDS = [
    { key: 'activities_today', label: 'Aktivitas', tone: 'bg-green-50 text-green-800' },
    { key: 'planned_today', label: 'Rencana', tone: 'bg-blue-50 text-blue-800' },
    { key: 'not_realized', label: 'Belum Direalisasikan', tone: 'bg-amber-50 text-amber-800' },
    { key: 'location_alerts', label: 'Location Alerts', tone: 'bg-red-50 text-red-800' },
];

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

export default function Dashboard({ summary, day, isToday, filters, filterOptions }) {
    const [form, setForm] = useState({
        date: filters.date ?? '',
        work_location_id: filters.work_location_id ?? '',
        creator_id: filters.creator_id ?? '',
        activity_type_id: filters.activity_type_id ?? '',
        product_id: filters.product_id ?? '',
    });
    const set = (field) => (value) => setForm((prev) => ({ ...prev, [field]: value }));

    function apply(e) {
        e.preventDefault();
        router.get('/dashboard', Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '')), { preserveState: true });
    }

    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <h1 className="mb-1 text-lg font-semibold text-gray-800">Dashboard</h1>
            <p className="mb-4 text-sm text-gray-500">{isToday ? `Hari ini (${day})` : day}</p>

            <form onSubmit={apply} className="mb-6 grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">
                <label className="block text-xs text-gray-600">
                    Tanggal
                    <input type="date" className={INPUT} value={form.date} onChange={(e) => set('date')(e.target.value)} />
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
                {CARDS.map((card) => (
                    <div key={card.key} className={`rounded-lg p-4 ${card.tone}`}>
                        <div className="text-2xl font-semibold">{summary[card.key]}</div>
                        <div className="mt-1 text-sm">{card.label}</div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
