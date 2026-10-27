import L from 'leaflet'

/**
 * Fond de plan — le même que le back-office.
 *
 * OpenStreetMap par défaut, surchargeable par `VITE_MAP_TILE_URL` : un
 * déploiement réel doit pointer vers son propre serveur de tuiles.
 */
export const TILE_URL = import.meta.env.VITE_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

export const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

/**
 * Marqueur en SVG en ligne : Leaflet devine sinon le chemin de ses images, ce
 * qui ne survit pas au regroupement des fichiers.
 */
export function pinIcon(className: string): L.DivIcon {
  return L.divIcon({
    className: '',
    html: `<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" class="${className} drop-shadow">
      <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/>
    </svg>`,
    iconSize: [28, 28],
    iconAnchor: [14, 28],
    popupAnchor: [0, -28],
  })
}

/** Le véhicule : une pastille, pour ne pas le confondre avec une adresse. */
export function vehicleIcon(): L.DivIcon {
  return L.divIcon({
    className: '',
    html: '<span class="block size-4 rounded-full border-2 border-white bg-primary shadow ring-4 ring-primary/30"></span>',
    iconSize: [16, 16],
    iconAnchor: [8, 8],
    popupAnchor: [0, -8],
  })
}
