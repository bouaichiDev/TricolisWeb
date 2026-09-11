import { useTranslation } from 'react-i18next'

import type { ImportResult } from '../types/customerIntegration'

interface ImportGeocodingSummaryProps {
  geocoding: NonNullable<ImportResult['geocoding']>
}

/**
 * Ce que le service GPS a fait des adresses importées.
 *
 * Trois cas, et chacun se dit : une adresse **située** apparaît sur la carte ;
 * une adresse **introuvable** reste livrable mais n'y apparaît pas ; une adresse
 * **en attente** n'a pas tenu dans le temps de la requête et sera située en
 * arrière-plan. Taire les deux derniers laisserait chercher sur la carte des
 * commandes qui n'y sont pas encore.
 */
export function ImportGeocodingSummary({ geocoding }: ImportGeocodingSummaryProps) {
  const { t } = useTranslation()

  const parts = [
    t('integrations.imports.run.located', { count: geocoding.located }),
    geocoding.unlocated > 0
      ? t('integrations.imports.run.unlocated', { count: geocoding.unlocated })
      : null,
    geocoding.pending > 0 ? t('integrations.imports.run.pending', { count: geocoding.pending }) : null,
  ]

  return (
    <p className="text-xs text-muted-foreground">{parts.filter((part) => part !== null).join(' ')}</p>
  )
}
