import { ClipboardList } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Récapitulatif de l'activité d'un chauffeur.
 *
 * **Vide tant que les tournées n'existent pas.** Ce que ce panneau doit
 * montrer — tournées effectuées, arrêts servis, kilomètres, incidents — n'est
 * produit par aucune table aujourd'hui : `tours` et `tour_stops` ne sont pas
 * encore alimentées, et `tracking_events` ne porte pas le chauffeur.
 *
 * L'onglet existe pour dire où cela se trouvera, et pour que le jour venu il
 * n'y ait qu'à le remplir. Y afficher des chiffres tirés d'ailleurs, ou des
 * zéros, laisserait croire à une activité nulle plutôt qu'à une mesure qui
 * n'existe pas encore.
 */
export function DriverActivityReport({ driverId }: { driverId: string }) {
  const { t } = useTranslation()

  return (
    <SectionCard title={t('drivers.report.title')} description={t('drivers.report.subtitle')}>
      <EmptyState
        icon={ClipboardList}
        title={t('drivers.report.pending')}
        description={t('drivers.report.pendingHint', { id: driverId })}
      />
    </SectionCard>
  )
}
