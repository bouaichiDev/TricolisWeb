import { useTranslation } from 'react-i18next'

import { AuditChangeList } from '@/modules/orders/components/detail/AuditChangeList'
import { useAuditLogs } from '@/modules/audit/hooks/useAuditLogs'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { formatDateTime } from '@/shared/utils/format'

import { ClaimAttachments } from './ClaimAttachments'
import { useClaim } from '../hooks/useClaims'
import type { Claim } from '../types/claim'

interface ClaimDetailDrawerProps {
  claim: Claim | null
  onClose: () => void
}

/** Les champs de traitement, dans l'ordre où ils se remplissent. */
const TREATMENT = ['decision', 'followUp', 'result'] as const

/**
 * Fiche d'une réclamation : ce qu'elle dit, ce qu'on en a fait, et par où elle
 * est passée.
 *
 * Le détail est rechargé parce que `ClaimListResource` n'expose ni
 * `description`, ni `cause`, ni le traitement — la ligne de liste seule
 * afficherait une réclamation vide.
 *
 * **L'historique vient du journal d'audit.** Aucune table `ClaimStatusHistory`
 * n'existe, et le §53 interdit d'en créer une : chaque écriture est déjà
 * enregistrée avec son avant et son après, ce qui suffit à retracer les étapes.
 */
export function ClaimDetailDrawer({ claim, onClose }: ClaimDetailDrawerProps) {
  const { t } = useTranslation()

  const detail = useClaim(claim?.id ?? null)
  const shown = detail.data ?? claim

  const history = useAuditLogs(
    { page: 1, perPage: 50, entityType: 'claim', entityId: claim?.id ?? '' },
    claim !== null,
  )

  const treatment = shown
    ? TREATMENT.filter((key) => shown[key] != null && shown[key] !== '')
    : []

  return (
    <Sheet open={claim !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{shown?.title ?? ''}</SheetTitle>
          <SheetDescription>
            {shown ? `${shown.claimType} · ${formatDateTime(shown.createdAt)}` : ''}
          </SheetDescription>
        </SheetHeader>

        {shown ? (
          <div className="flex flex-col gap-5 px-4 pb-6">
            <div className="flex flex-wrap items-center gap-2">
              <StatusBadge status={shown.status} />
              {shown.responsibleUser ? (
                <span className="text-sm text-muted-foreground">
                  {t('claims.fields.responsible')} ·{' '}
                  {`${shown.responsibleUser.firstName} ${shown.responsibleUser.lastName}`.trim()}
                </span>
              ) : (
                <span className="text-sm text-muted-foreground">{t('claims.noResponsible')}</span>
              )}
            </div>

            {shown.description ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('claims.fields.description')}
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{shown.description}</p>
              </div>
            ) : null}

            {shown.cause ? (
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('claims.fields.cause')}
                </p>
                <p className="mt-1 text-sm">{shown.cause}</p>
              </div>
            ) : null}

            {treatment.length > 0 ? (
              <section className="flex flex-col gap-3 border-t pt-4">
                <p className="text-sm font-medium">{t('claims.sections.treatment')}</p>
                {treatment.map((key) => (
                  <div key={key}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t(`claims.fields.${key}`)}
                    </p>
                    <p className="mt-1 whitespace-pre-wrap text-sm">{shown[key]}</p>
                  </div>
                ))}
              </section>
            ) : null}

            <section className="border-t pt-4">
              <ClaimAttachments claimId={shown.id} />
            </section>

            <section className="flex flex-col gap-2 border-t pt-4">
              <p className="text-sm font-medium">{t('claims.history')}</p>
              <AuditChangeList logs={history.data?.data ?? []} />
            </section>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
