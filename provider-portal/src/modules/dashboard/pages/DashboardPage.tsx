import { useQuery } from '@tanstack/react-query'
import { CalendarCheck, CircleCheck, Route, Truck, Users, type LucideIcon } from 'lucide-react'

import { useAuth, usePermissions } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Card, CardContent } from '@/shared/components/ui/card'

import { dashboardApi, type ProviderDashboard } from '../api/dashboardApi'

const KPIS: { key: keyof ProviderDashboard; label: string; icon: LucideIcon }[] = [
  { key: 'toursToday', label: 'Tournées du jour', icon: CalendarCheck },
  { key: 'toursInProgress', label: 'Tournées en cours', icon: Route },
  { key: 'toursCompletedToday', label: 'Terminées aujourd’hui', icon: CircleCheck },
  { key: 'activeDrivers', label: 'Chauffeurs actifs', icon: Users },
  { key: 'activeVehicles', label: 'Véhicules actifs', icon: Truck },
]

/**
 * Tableau de bord du portail fournisseur : cinq chiffres servis par le
 * serveur, pour ce seul fournisseur. Aucun n'est calculé ni simulé ici. Sans
 * `provider.dashboard.view`, rien n'est demandé.
 */
export function DashboardPage() {
  const { context } = useAuth()
  const { can } = usePermissions()
  const allowed = can('provider.dashboard.view')
  const dashboard = useQuery({ queryKey: ['dashboard'], queryFn: dashboardApi.get, enabled: allowed })

  if (context === null) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={`Bonjour ${context.user.firstName || context.user.email}`} description={`Espace ${context.provider.name}`} />
      {allowed && dashboard.isPending && <InlineLoader />}
      {dashboard.isError && <ErrorState message={errorMessage(dashboard.error)} onRetry={() => void dashboard.refetch()} />}
      {dashboard.data && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          {KPIS.map(({ key, label, icon: Icon }) => (
            <Card key={key}>
              <CardContent className="flex items-center gap-4">
                <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                  <Icon className="size-5" aria-hidden />
                </span>
                <div className="min-w-0">
                  <p className="text-muted-foreground truncate text-sm">{label}</p>
                  <p className="text-2xl font-semibold tabular-nums">{dashboard.data[key]}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
