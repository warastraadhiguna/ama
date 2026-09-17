import { Head } from '@inertiajs/react';
import AdminLayout from '../Layouts/AdminLayout';

const CARDS = [
    { key: 'activities_today', label: 'Aktivitas Hari Ini', tone: 'bg-green-50 text-green-800' },
    { key: 'planned_today', label: 'Rencana Hari Ini', tone: 'bg-blue-50 text-blue-800' },
    { key: 'not_realized', label: 'Belum Direalisasikan', tone: 'bg-amber-50 text-amber-800' },
    { key: 'location_alerts', label: 'Location Alerts', tone: 'bg-red-50 text-red-800' },
];

export default function Dashboard({ summary }) {
    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <h1 className="mb-4 text-lg font-semibold text-gray-800">Dashboard</h1>

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
