import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { Link } from '@inertiajs/react';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

// Vite doesn't rewrite Leaflet's default icon URLs automatically — without
// this the map renders with broken marker images.
const defaultIcon = L.icon({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
});

const STATUS_COLOR = {
    TRUSTED: 'text-green-700',
    SUSPICIOUS: 'text-amber-700',
    REJECTED: 'text-red-700',
};

export default function ActivityMap({ activities }) {
    const points = activities.filter((activity) => activity.coordinates);

    if (points.length === 0) {
        return (
            <div className="flex h-80 items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-400">
                Belum ada titik lokasi untuk ditampilkan pada peta.
            </div>
        );
    }

    const center = [points[0].coordinates.latitude, points[0].coordinates.longitude];

    return (
        <div className="h-80 overflow-hidden rounded-lg border border-gray-200">
            <MapContainer center={center} zoom={11} style={{ height: '100%', width: '100%' }}>
                <TileLayer
                    attribution="&copy; OpenStreetMap contributors"
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {points.map((activity) => (
                    <Marker
                        key={activity.id}
                        position={[activity.coordinates.latitude, activity.coordinates.longitude]}
                        icon={defaultIcon}
                    >
                        <Popup>
                            <div className="space-y-1 text-sm">
                                <div className="font-medium">{activity.creator_name}</div>
                                <div>{activity.activity_type}</div>
                                <div className="text-gray-500">{new Date(activity.created_at).toLocaleString('id-ID')}</div>
                                {activity.integrity_status && (
                                    <div className={STATUS_COLOR[activity.integrity_status] ?? ''}>
                                        {activity.integrity_status}
                                    </div>
                                )}
                                <Link
                                    href={`/activities/${activity.id}`}
                                    className="inline-block text-green-700 underline"
                                >
                                    Lihat detail
                                </Link>
                            </div>
                        </Popup>
                    </Marker>
                ))}
            </MapContainer>
        </div>
    );
}
