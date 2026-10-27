import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'

import { useAuth } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'

import { dashboardApi } from '../api/dashboardApi'
import { DashboardGrid } from '../components/DashboardGrid'
import { IdentityCards } from '../components/IdentityCards'
import type { DashboardPeriod } from '../types/dashboard'

/**
 * Tableau de bord du portail : l'identité du client, puis les widgets que le
 * transporteur a configurés pour ses rôles — le moteur du back-office, borné
 * à ce client. Aucun chiffre n'est calculé ici.
 */
export function DashboardPage() {
  const { context } = useAuth()
  const [draft, setDraft] = useState<DashboardPeriod>({ from: '', to: '' })
  const [period, setPeriod] = useState<DashboardPeriod | null>(null)
  const dashboard = useQuery({ queryKey: ['dashboard', period], queryFn: () => dashboardApi.get(period) })

  if (context === null) return null

  const resetPeriod = () => {
    setPeriod(null)
    setDraft({ from: '', to: '' })
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`Bonjour ${context.user.firstName || context.user.email}`}
        description={`Espace ${context.customer.name}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Input type="date" aria-label="Début de période" className="w-40" value={draft.from} onChange={(event) => setDraft({ ...draft, from: event.target.value })} />
            <span className="text-muted-foreground text-sm">→</span>
            <Input type="date" aria-label="Fin de période" className="w-40" value={draft.to} onChange={(event) => setDraft({ ...draft, to: event.target.value })} />
            <Button variant="outline" disabled={!draft.from || !draft.to} onClick={() => setPeriod(draft)}>
              Appliquer
            </Button>
            {period && (
              <Button variant="ghost" onClick={resetPeriod}>
                Toute la période
              </Button>
            )}
          </div>
        }
      />
      <IdentityCards context={context} />
      {dashboard.isPending && <InlineLoader />}
      {dashboard.isError && <ErrorState message={errorMessage(dashboard.error)} onRetry={() => void dashboard.refetch()} />}
      {dashboard.data && <DashboardGrid widgets={dashboard.data.widgets} period={period} />}
    </div>
  )
}
