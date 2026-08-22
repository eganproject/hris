// Entry terpisah dari app.js: Leaflet hanya dimuat di halaman peta absensi, bukan
// di setiap halaman aplikasi.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.getElementById('attendance-map');

if (root) {
    const points = JSON.parse(root.dataset.points || '[]');

    // Titik tengah default: Indonesia, dipakai saat belum ada yang absen.
    const map = L.map(root).setView([-2.5, 118], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[char]));

    // Bendera hijau = WFH, biru = dinas luar. Lingkaran di sekitarnya menggambarkan
    // akurasi GPS yang dilaporkan perangkat, supaya titik tidak terbaca lebih presisi
    // daripada kenyataannya.
    const COLORS = { wfh: '#059669', business_trip: '#2563eb' };

    // Bentuknya harus sama dengan <x-map-flag> agar keterangan warna, daftar samping,
    // dan penanda di peta terbaca sebagai satu hal yang sama.
    const flagSvg = (color) => `
        <svg width="24" height="30" viewBox="0 0 24 30" fill="none" style="filter:drop-shadow(0 1px 2px rgba(0,0,0,.35))">
            <path d="M4 28V2" stroke="#374151" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M5.5 3.5h13.5l-3.2 4.75 3.2 4.75H5.5z" fill="${color}" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"/>
        </svg>`;

    const markers = points.map((point) => {
        const color = COLORS[point.status] ?? '#6b7280';

        if (point.accuracy) {
            L.circle([point.lat, point.lng], {
                radius: point.accuracy,
                color,
                weight: 1,
                opacity: 0.35,
                fillOpacity: 0.08,
            }).addTo(map);
        }

        const marker = L.marker([point.lat, point.lng], {
            icon: L.divIcon({
                html: flagSvg(color),
                className: '', // buang kotak putih bawaan divIcon
                iconSize: [24, 30],
                iconAnchor: [4, 29], // ujung bawah tiang = titik koordinat sebenarnya
                popupAnchor: [8, -26],
            }),
            title: point.name,
        }).addTo(map);

        const photo = point.photo_url
            ? `<img src="${escapeHtml(point.photo_url)}" alt="Selfie ${escapeHtml(point.name)}" style="width:100%;height:120px;object-fit:cover;border-radius:6px;margin-bottom:8px">`
            : '';

        marker.bindPopup(`
            <div style="min-width:190px">
                ${photo}
                <p style="margin:0;font-weight:600;color:#030712">${escapeHtml(point.name)}</p>
                <p style="margin:2px 0 0;font-size:11px;color:#6b7280">${escapeHtml([point.number, point.position].filter(Boolean).join(' · '))}</p>
                <p style="margin:6px 0 0;font-size:12px;color:${color};font-weight:600">${escapeHtml(point.status_label)}</p>
                <p style="margin:2px 0 0;font-size:12px;color:#374151">Masuk ${escapeHtml(point.clock_in)} · Pulang ${escapeHtml(point.clock_out)}</p>
                <p style="margin:2px 0 0;font-size:11px;color:#6b7280">${point.accuracy ? `Akurasi ±${escapeHtml(point.accuracy)} m` : 'Akurasi tidak dilaporkan'}</p>
                <a href="${escapeHtml(point.history_url)}" style="display:inline-block;margin-top:6px;font-size:12px;color:#2563eb">Lihat riwayat</a>
            </div>
        `);

        marker.on('click', () => map.panTo([point.lat, point.lng]));

        return marker;
    });

    if (markers.length > 0) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.25), { maxZoom: 16 });
    }

    // Klik baris daftar di samping peta → buka popup penanda yang bersangkutan.
    document.querySelectorAll('[data-focus-point]').forEach((row) => {
        row.addEventListener('click', () => {
            const marker = markers[Number(row.dataset.focusPoint)];
            if (!marker) return;
            map.setView(marker.getLatLng(), 16);
            marker.openPopup();
        });
    });
}
