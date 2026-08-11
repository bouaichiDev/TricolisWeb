import { useTranslation } from 'react-i18next'

import { PageHeader } from '@/shared/components/layout/PageHeader'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Tableau de bord — version minimale de la Phase 1.
 *
 * Le §11 est explicite : « Ne pas utiliser de valeurs statiques fictives. » Le
 * backend n'expose **aucun endpoint d'agrégation** — les compteurs devraient
 * être tirés du champ `meta.total` de chaque liste, ce qui coûte une requête
 * paginée par carte. Les cartes seront ajoutées quand les modules concernés
 * existeront, avec cette source réelle.
 */
export function DashboardPage() {
  const { t } = useTranslation()
  const { membership, user } = useAuth()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('dashboard.title')} description={t('dashboard.subtitle')} />

      <p className="text-sm text-muted-foreground">
        {user?.fullName} — {membership?.name}
      </p>
    </div>
  )
}
