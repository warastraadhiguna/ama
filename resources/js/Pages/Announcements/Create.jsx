import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

const INPUT = 'w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm';

export default function AnnouncementCreate({ roles, workLocations }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        body: '',
        role: '',
        work_location_id: '',
    });

    function submit(e) {
        e.preventDefault();
        if (!window.confirm('Kirim pengumuman ini? Pengumuman tidak dapat ditarik kembali.')) return;
        post('/announcements', { preserveScroll: true, onSuccess: () => reset() });
    }

    return (
        <AdminLayout>
            <Head title="Pengumuman" />

            <h1 className="mb-4 text-lg font-semibold text-gray-800">Pengumuman</h1>

            <form onSubmit={submit} className="grid max-w-2xl gap-4 rounded-lg border border-gray-200 bg-white p-5">
                <label className="block">
                    <span className="mb-1 block text-sm text-gray-700">Judul</span>
                    <input className={INPUT} value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={120} />
                    {errors.title && <span className="mt-1 block text-xs text-red-600">{errors.title}</span>}
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm text-gray-700">Isi</span>
                    <textarea rows={5} className={INPUT} value={data.body} onChange={(e) => setData('body', e.target.value)} maxLength={2000} />
                    {errors.body && <span className="mt-1 block text-xs text-red-600">{errors.body}</span>}
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block">
                        <span className="mb-1 block text-sm text-gray-700">Penerima: role</span>
                        <select className={INPUT} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            <option value="">Semua role</option>
                            {roles.map((r) => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm text-gray-700">Penerima: lokasi tugas</span>
                        <select className={INPUT} value={data.work_location_id} onChange={(e) => setData('work_location_id', e.target.value)}>
                            <option value="">Semua lokasi</option>
                            {workLocations.map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                    </label>
                </div>
                <p className="text-xs text-gray-500">Hanya pengguna aktif yang menerima. Pengumuman muncul di menu Notifikasi aplikasi.</p>
                <div>
                    <button disabled={processing} className="rounded-md bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50">
                        Kirim
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
