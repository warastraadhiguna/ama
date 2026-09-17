import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

const INTEGRITY_BADGE = {
    TRUSTED: 'bg-green-100 text-green-700',
    SUSPICIOUS: 'bg-amber-100 text-amber-700',
    REJECTED: 'bg-red-100 text-red-700',
};

export default function ActivityShow({ activity }) {
    return (
        <AdminLayout>
            <Head title={`Aktivitas #${activity.id}`} />

            <Link href="/activities" className="mb-4 inline-block text-sm text-green-700 hover:underline">
                &larr; Kembali ke daftar
            </Link>

            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-5">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-lg font-semibold text-gray-800">{activity.activity_type}</h1>
                        <p className="text-sm text-gray-500">{activity.location}</p>
                    </div>
                    <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                        {activity.status}
                    </span>
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-gray-400">Agronomist</dt>
                        <dd>{activity.creator.name}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Jabatan</dt>
                        <dd>{activity.creator.position ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Area</dt>
                        <dd>{activity.creator.work_location ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Produk</dt>
                        <dd>{activity.products.join(', ') || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-400">Waktu</dt>
                        <dd>{new Date(activity.created_at).toLocaleString('id-ID')}</dd>
                    </div>
                    {activity.plan_id && (
                        <div>
                            <dt className="text-gray-400">Dari Rencana</dt>
                            <dd>#{activity.plan_id}</dd>
                        </div>
                    )}
                </dl>

                {activity.notes && (
                    <p className="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600">{activity.notes}</p>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Evidence &amp; Integrity Review
            </h2>

            {activity.capture_sessions.length === 0 && (
                <p className="text-sm text-gray-400">Belum ada bukti (GPS/foto) yang dikirim untuk aktivitas ini.</p>
            )}

            <div className="space-y-4">
                {activity.capture_sessions.map((session) => (
                    <div key={session.id} className="rounded-lg border border-gray-200 bg-white p-5">
                        <div className="mb-3 flex items-center justify-between text-xs text-gray-400">
                            <span>Capture session #{session.id} — mulai {new Date(session.started_at).toLocaleString('id-ID')}</span>
                            <span>{session.submitted_at ? 'Submitted' : 'Belum di-complete'}</span>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <h3 className="mb-2 text-xs font-semibold uppercase text-gray-400">Lokasi GPS</h3>
                                {session.locations.length === 0 && <p className="text-sm text-gray-400">Tidak ada data GPS.</p>}
                                {session.locations.map((location) => (
                                    <div key={location.id} className="mb-2 rounded-md border border-gray-100 p-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span>{location.latitude.toFixed(6)}, {location.longitude.toFixed(6)}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-xs ${INTEGRITY_BADGE[location.integrity_status] ?? ''}`}>
                                                {location.integrity_status}
                                            </span>
                                        </div>
                                        <div className="mt-1 text-xs text-gray-500">
                                            Akurasi {location.accuracy}m
                                            {location.is_mock_location && <span className="ml-2 text-red-600">Mock location terdeteksi</span>}
                                        </div>
                                        {location.anomaly_reasons?.length > 0 && (
                                            <ul className="mt-1 list-inside list-disc text-xs text-amber-700">
                                                {location.anomaly_reasons.map((reason) => (
                                                    <li key={reason}>{reason}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div>
                                <h3 className="mb-2 text-xs font-semibold uppercase text-gray-400">Foto</h3>
                                {session.photos.length === 0 && <p className="text-sm text-gray-400">Tidak ada foto.</p>}
                                <div className="grid grid-cols-2 gap-2">
                                    {session.photos.map((photo) => (
                                        <a key={photo.id} href={photo.url} target="_blank" rel="noreferrer">
                                            <img
                                                src={photo.url}
                                                alt="Bukti kegiatan"
                                                className="aspect-square w-full rounded-md border border-gray-200 object-cover"
                                            />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
