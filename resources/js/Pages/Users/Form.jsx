import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm text-gray-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

const INPUT = 'w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm';

export default function UserForm({ user, devices, options }) {
    const editing = user !== null;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        nip: user?.nip ?? '',
        phone: user?.phone ?? '',
        role: user?.role ?? options.roles.find((r) => r === 'AGRONOMIST') ?? options.roles[0] ?? '',
        position_id: user?.position_id ?? '',
        work_location_id: user?.work_location_id ?? '',
        is_active: user?.is_active ?? true,
        password: '',
    });

    function submit(e) {
        e.preventDefault();
        if (editing) {
            put(`/users/${user.id}`);
        } else {
            post('/users');
        }
    }

    function revoke(device) {
        if (window.confirm(`Cabut perangkat ${device.label}? Pengguna harus login ulang dari perangkat lain.`)) {
            router.post(`/users/${user.id}/devices/${device.id}/revoke`, {}, { preserveScroll: true });
        }
    }

    return (
        <AdminLayout>
            <Head title={editing ? 'Ubah Pengguna' : 'Tambah Pengguna'} />

            <div className="mb-4 flex items-center gap-3">
                <Link href="/users" className="text-sm text-gray-500 hover:text-green-800">
                    &larr; Pengguna
                </Link>
                <h1 className="text-lg font-semibold text-gray-800">
                    {editing ? `Ubah ${user.name}` : 'Tambah Pengguna'}
                </h1>
            </div>

            <form onSubmit={submit} className="grid max-w-2xl gap-4 rounded-lg border border-gray-200 bg-white p-5 sm:grid-cols-2">
                <Field label="Nama" error={errors.name}>
                    <input className={INPUT} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="Email" error={errors.email}>
                    <input type="email" className={INPUT} value={data.email} onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <Field label="NIP (opsional)" error={errors.nip}>
                    <input className={INPUT} value={data.nip} onChange={(e) => setData('nip', e.target.value)} />
                </Field>
                <Field label="Telepon (opsional)" error={errors.phone}>
                    <input className={INPUT} value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                </Field>
                <Field label="Role" error={errors.role}>
                    <select className={INPUT} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {options.roles.map((role) => (
                            <option key={role} value={role}>{role}</option>
                        ))}
                    </select>
                </Field>
                <Field label="Jabatan" error={errors.position_id}>
                    <select className={INPUT} value={data.position_id} onChange={(e) => setData('position_id', e.target.value)}>
                        <option value="">-</option>
                        {options.positions.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                </Field>
                <Field label="Lokasi Tugas" error={errors.work_location_id}>
                    <select className={INPUT} value={data.work_location_id} onChange={(e) => setData('work_location_id', e.target.value)}>
                        <option value="">-</option>
                        {options.work_locations.map((w) => (
                            <option key={w.id} value={w.id}>{w.name}</option>
                        ))}
                    </select>
                </Field>
                <Field label={editing ? 'Password baru (kosongkan bila tidak diubah)' : 'Password'} error={errors.password}>
                    <input type="password" autoComplete="new-password" className={INPUT} value={data.password} onChange={(e) => setData('password', e.target.value)} />
                </Field>

                <div className="sm:col-span-2">
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Akun aktif
                    </label>
                    {errors.is_active && <span className="mt-1 block text-xs text-red-600">{errors.is_active}</span>}
                </div>

                <div className="sm:col-span-2">
                    <button
                        disabled={processing}
                        className="rounded-md bg-green-700 px-4 py-1.5 text-sm text-white hover:bg-green-800 disabled:opacity-50"
                    >
                        Simpan
                    </button>
                </div>
            </form>

            {editing && (
                <section className="mt-8 max-w-2xl">
                    <h2 className="mb-2 text-sm font-semibold text-gray-800">Perangkat</h2>
                    <div className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white text-sm">
                        {devices.length === 0 && <div className="px-4 py-3 text-gray-500">Belum ada perangkat terdaftar.</div>}
                        {devices.map((device) => (
                            <div key={device.id} className="flex items-center justify-between px-4 py-3">
                                <div>
                                    <div className="text-gray-800">{device.label}</div>
                                    <div className="text-xs text-gray-500">
                                        {device.app_version ? `v${device.app_version} · ` : ''}
                                        {device.last_active_at ? `aktif ${device.last_active_at.slice(0, 16).replace('T', ' ')} UTC` : 'belum pernah aktif'}
                                    </div>
                                </div>
                                {device.revoked_at ? (
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Dicabut</span>
                                ) : (
                                    <button onClick={() => revoke(device)} className="text-red-600 hover:underline">
                                        Cabut
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </AdminLayout>
    );
}
