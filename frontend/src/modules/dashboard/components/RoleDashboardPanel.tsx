import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { RoleDashboardCatalogue } from './RoleDashboardCatalogue'
import { RoleDashboardOrder } from './RoleDashboardOrder'
import { RoleDashboardPreview } from './RoleDashboardPreview'
import { useResetRoleDashboard, useRoleDashboard, useUpdateRoleDashboard } from '../hooks/useRoleDashboard'
import { useRoleDashboardDraft } from '../hooks/useRoleDashboardDraft'
import type { RoleDashboardWidget } from '../types/dashboard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'

const NONE: RoleDashboardWidget[] = []

interface RoleDashboardPanelProps {
  roleId: string
  /** Un rôle de portée plateforme se consulte, il ne se règle pas. */
  editable: boolean
}

/**
 * Réglage du tableau de bord d'un rôle.
 *
 * Trois parties, dans l'ordre où l'on s'en sert : l'**ordre** des widgets
 * actifs, l'**aperçu** de ce que cela donnera, puis le **catalogue** où l'on
 * coche. Le catalogue vient en dernier bien qu'il soit le plus long : on ouvre
 * cet écran plus souvent pour remanier une composition que pour la créer.
 *
 * Rien n'est envoyé au fil des clics — on enregistre **une fois**. Composer un
 * tableau de bord demande une dizaine de gestes, et les transmettre un par un
 * aurait produit dix écritures, dix lignes de journal pour une seule décision,
 * et un état à moitié enregistré si le réseau lâche au milieu.
 *
 * Ce que cet écran ne fait jamais : accorder une permission. Un widget que le
 * rôle n'a pas le droit de voir reste proposé, interrupteur éteint, avec la
 * permission manquante écrite — le tableau de bord range, les permissions
 * protègent.
 */
export function RoleDashboardPanel({ roleId, editable }: RoleDashboardPanelProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useRoleDashboard(roleId)
  const update = useUpdateRoleDashboard(roleId)
  const reset = useResetRoleDashboard(roleId)
  const [confirmReset, setConfirmReset] = useState(false)

  // `NONE` plutôt qu'un `[]` littéral : un tableau neuf à chaque rendu ferait
  // recalculer les mémoïsations du brouillon en continu, pour un résultat
  // identique.
  const draft = useRoleDashboardDraft(data ?? NONE)

  if (isPending) return <ListSkeleton rows={6} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const busy = update.isPending || reset.isPending || !editable

  return (
    <div className="flex flex-col gap-6">
      <p className="text-sm text-muted-foreground">{t('dashboardSettings.hint')}</p>

      <section className="flex flex-col gap-2">
        <h4 className="text-sm font-semibold">{t('dashboardSettings.order')}</h4>
        <RoleDashboardOrder
          active={draft.active}
          disabled={busy}
          onMove={draft.move}
          onMoveTo={draft.moveTo}
        />
      </section>

      <section className="flex flex-col gap-2">
        <h4 className="text-sm font-semibold">{t('dashboardSettings.preview')}</h4>
        <RoleDashboardPreview active={draft.active} />
      </section>

      <section className="flex flex-col gap-2">
        <h4 className="text-sm font-semibold">{t('dashboardSettings.catalogue')}</h4>
        <RoleDashboardCatalogue
          widgets={data ?? NONE}
          isEnabled={draft.isEnabled}
          disabled={busy}
          onToggle={draft.toggle}
        />
      </section>

      {editable ? (
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={() => setConfirmReset(true)} disabled={busy}>
            {t('dashboardSettings.resetAction')}
          </Button>

          {draft.isDirty ? (
            <Button variant="outline" onClick={draft.reset} disabled={busy}>
              {t('common.cancel')}
            </Button>
          ) : null}

          <Button
            onClick={() => update.mutate(draft.payload(), { onSuccess: () => draft.reset() })}
            disabled={!draft.isDirty || busy}
          >
            {update.isPending ? t('common.saving') : t('dashboardSettings.save')}
          </Button>
        </div>
      ) : null}

      {/* La réinitialisation prend effet tout de suite, contrairement au reste :
          elle supprime la configuration, et l'accumuler dans le brouillon
          aurait demandé d'y représenter une absence de ligne. Le brouillon est
          donc abandonné — la liste change de forme sous lui. */}
      <ConfirmDialog
        open={confirmReset}
        onOpenChange={setConfirmReset}
        title={t('dashboardSettings.resetTitle')}
        description={t('dashboardSettings.resetHint')}
        confirmLabel={t('dashboardSettings.resetAction')}
        isPending={reset.isPending}
        onConfirm={() =>
          reset.mutate(undefined, {
            onSuccess: () => {
              draft.reset()
              setConfirmReset(false)
            },
          })
        }
      />
    </div>
  )
}
