import { useTranslation } from 'react-i18next'

import { CounterCard } from '../components/CounterCard'
import { useCounters } from '../hooks/useCounters'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Tableau de bord.
 *
 * Le §11 interdit les valeurs fictives : chaque chiffre affiché vient du
 * `meta.total` d'une liste réelle, jamais d'un nombre écrit ici. Une carte dont
 * l'utilisateur n'a pas la permission de voir la liste n'apparaît pas — elle
 * afficherait un total qu'il ne peut pas consulter.
 */
export function DashboardPage() {
  const { t } = useTranslation()
  const { membership, user } = useAuth()
  const counters = useCounters()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('dashboard.welcome', { name: user?.fullName ?? '' })}
        description={membership?.name ?? t('dashboard.subtitle')}
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {counters.map((counter) => (
          <PermissionGuard key={counter.key} permission={counter.permission}>
            <CounterCard counter={counter} label={t(`nav.${counter.key}`)} />
          </PermissionGuard>
        ))}
      </div>
    </div>
  )
}
