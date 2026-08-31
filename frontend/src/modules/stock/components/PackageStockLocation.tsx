import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useStockLocation } from '../hooks/useStockLocations'

interface PackageStockLocationProps {
  stockLocationId: string | null
}

/**
 * Emplacement courant d'un colis, résolu en code lisible.
 *
 * `PackageResource` n'expose que `currentStockLocationId` — un ULID, que
 * personne ne reconnaît. La fiche de l'emplacement est donc demandée pour en
 * tirer le code, et seulement quand l'identifiant existe : la plupart des colis
 * n'en ont pas, et le hook ne lance alors aucune requête.
 *
 * **Rien n'est écrit ici.** Déplacer un colis d'un emplacement à l'autre
 * demanderait une règle métier — quel mouvement de stock l'accompagne, à quelle
 * quantité — que le backend ne définit pas. Tant qu'elle n'existe pas, cet
 * emplacement se lit et ne se modifie pas.
 */
export function PackageStockLocation({ stockLocationId }: PackageStockLocationProps) {
  const { t } = useTranslation()
  const { data: location, isPending } = useStockLocation(stockLocationId ?? undefined)

  if (stockLocationId === null) {
    return <span className="text-muted-foreground">{t('stock.noPackageLocation')}</span>
  }

  if (isPending) return <span className="text-muted-foreground">{t('common.loading')}</span>

  if (!location) return <span>{stockLocationId}</span>

  return (
    <Link to={`/stock/locations/${location.id}`} className="hover:underline">
      {location.zoneCode === null || location.zoneCode === ''
        ? location.locationCode
        : `${location.zoneCode} · ${location.locationCode}`}
    </Link>
  )
}
