import { useEffect } from 'react'
import { useMap } from 'react-leaflet'

export interface MapTarget {
  latitude: number
  longitude: number
  /** Change à chaque demande, même vers le même point : c'est lui qui relance. */
  token: number
}

/**
 * Amène la carte sur un point désigné depuis une liste.
 *
 * Cliquer une commande à gauche doit la montrer à droite ; sans ce déplacement,
 * il faudrait la chercher à l'œil parmi trente marqueurs.
 *
 * Le `token` distingue deux demandes vers le **même** point : recliquer la même
 * commande après avoir déplacé la carte doit y revenir, ce qu'une comparaison
 * de coordonnées seule ne verrait pas.
 */
export function MapFocus({ target }: { target: MapTarget | null }) {
  const map = useMap()

  useEffect(() => {
    if (target === null) return

    map.flyTo([target.latitude, target.longitude], Math.max(map.getZoom(), 13), {
      duration: 0.6,
    })
  }, [map, target])

  return null
}
