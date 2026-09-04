import { LayoutDashboard } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DashboardGrid } from '../components/DashboardGrid'
import { DashboardSkeleton } from '../components/DashboardSkeleton'
import { useDashboard } from '../hooks/useDashboard'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { useAuth } from '@/shared/hooks/useAuth'
import { usePermissions } from '@/shared/hooks/usePermission'

/**
 * Le tableau de bord, composé par les rôles de celui qui le regarde.
 *
 * Cette page ne code plus aucune carte. Elle en affichait quatre, écrites en
 * dur — clients, agences, utilisateurs, rôles — qui convenaient à
 * l'administration et à personne d'autre : un planificateur y trouvait quatre
 * chiffres qui ne le concernaient pas, et rien de ses tournées.
 *
 * Ce qu'elle affiche vient désormais de `GET /dashboard`, déjà filtré :
 *
 * ```
 * rôles de l'organisation active
 * → union des widgets qu'ils montrent
 * → intersection des permissions effectives
 * ```
 *
 * Elle ne décide donc rien, et surtout **ne filtre rien** : un `PermissionGuard`
 * posé sur chaque carte serait au mieux redondant, au pire trompeur — il
 * laisserait croire que la protection est ici, alors qu'un widget interdit
 * n'est jamais arrivé.
 */
export function DashboardPage() {
  const { t } = useTranslation()
  const { membership, user } = useAuth()
  const { has } = usePermissions()
  const { data, isPending, error, refetch } = useDashboard()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('dashboard.welcome', { name: user?.fullName ?? '' })}
        description={data?.organization?.name ?? membership?.name ?? t('dashboard.subtitle')}
      />

      {/* Un squelette, et non les anciennes cartes en attendant : les afficher
          une seconde aurait montré à chacun un tableau de bord qui n'est pas le
          sien, puis l'aurait remplacé — donnant à croire que quelque chose a
          échoué. */}
      {isPending ? <DashboardSkeleton /> : null}

      {/* Pas de repli sur des chiffres qu'on aurait le droit de lire : une
          erreur se dit, elle ne se comble pas. */}
      {error ? <ErrorState error={error} onRetry={() => void refetch()} /> : null}

      {data && data.widgets.length > 0 ? <DashboardGrid widgets={data.widgets} /> : null}

      {data && data.widgets.length === 0 ? (
        <EmptyState
          icon={LayoutDashboard}
          title={t('dashboard.empty')}
          description={t('dashboard.emptyHint')}
          action={
            // Le bouton n'est proposé qu'à qui peut s'en servir. Sans la
            // permission, il aurait mené à un écran qui refuse — et laissé
            // penser que le tableau de bord vide vient d'une erreur.
            has('dashboard.configure') ? (
              <Button asChild variant="outline">
                <Link to="/roles">{t('dashboard.configureRoles')}</Link>
              </Button>
            ) : null
          }
        />
      ) : null}
    </div>
  )
}
