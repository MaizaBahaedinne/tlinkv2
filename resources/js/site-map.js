import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const element = document.getElementById('clubs-map');

if (element) {
    const markers = JSON.parse(element.dataset.markers || '[]');
    const clubsLabel = element.dataset.clubsLabel || '';
    const viewLabel = element.dataset.viewLabel || '';

    const map = L.map(element, { scrollWheelZoom: false, minZoom: 5, maxZoom: 10, zoomControl: true });
    map.fitBounds([[30.2, 7.6], [37.6, 11.8]]);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 10,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
    }).addTo(map);

    markers.forEach((item) => {
        const marker = L.circleMarker([item.lat, item.lng], {
            radius: Math.min(9 + item.count * 1.5, 20),
            color: '#ffffff',
            weight: 2,
            fillColor: item.active ? '#f5a623' : '#d00031',
            fillOpacity: 0.9,
        }).addTo(map);

        // Contenu construit en DOM (textContent) : aucune donnée n'est interprétée comme du HTML.
        const popup = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = item.name;
        const count = document.createElement('div');
        count.textContent = `${item.count} ${clubsLabel}`;
        const link = document.createElement('a');
        link.href = item.url;
        link.textContent = viewLabel;
        link.style.fontWeight = '700';
        popup.append(title, count, link);

        marker.bindPopup(popup);
        marker.bindTooltip(`${item.name} (${item.count})`, { direction: 'top' });
    });
}
