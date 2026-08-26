import L from 'leaflet'

/**
 * Fond de plan.
 *
 * OpenStreetMap par défaut, surchargeable par `VITE_MAP_TILE_URL`. Sa politique
 * d'usage interdit un trafic important sans hébergement propre : un déploiement
 * réel doit pointer vers son propre serveur de tuiles, et cette variable est là
 * pour ça.
 */
export const TILE_URL =
  import.meta.env.VITE_MAP_TILE_URL ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

export const ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

/**
 * Marqueur construit à la main.
 *
 * Leaflet devine sinon le chemin de ses images depuis celui de son CSS, ce qui
 * ne survit pas au regroupement des fichiers : le marqueur disparaît en
 * production sans la moindre erreur. Un SVG en ligne évite l'aller-retour et ne
 * dépend d'aucun fichier.
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

/**
 * Pastille numérotée : l'ordre des arrêts est ce qu'on vient lire sur la carte.
 *
 * Un marqueur identique pour tous laisserait deviner le sens de la tournée
 * d'après le tracé, ce qu'un aller-retour rend impossible.
 */
export function sequenceIcon(sequence: number, color: string): L.DivIcon {
  return L.divIcon({
    className: '',
    html: `<span style="background:${color}" class="flex size-7 items-center justify-center rounded-full border-2 border-white text-xs font-semibold text-white shadow">${sequence}</span>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14],
    popupAnchor: [0, -14],
  })
}
