import { ArrowRight, Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import {
  useDeleteStatusPropagation,
  useStatusPropagations,
  useUpdateStatusPropagation,
} from '../hooks/useStatusPropagations'
import type { StatusPropagation } from '../types/status'
import { StatusPropagationForm } from './StatusPropagationForm'

/**
 * Ce qu'un statut entraîne sur les entités voisines.
 *
 * Une commande, ses services, ses colis et ses lignes avancent ensemble : quand
 * tous les colis d'un service sont chargés, le service l'est, et la commande
 * avec lui. Ces règles se déclaraient nulle part, et chaque statut devait donc
 * être repassé à la main.
 *
 * Une règle se lit d'un trait — « Colis « Chargé » → Service « En route » » —
 * parce que c'est ainsi qu'on la vérifie : de gauche à droite, comme elle
 * s'exécute. Elle se **corrige** sur place : une règle mal visée se répare,
 * elle n'a pas à être supprimée puis resaisie.
 */
export function StatusPropagationsPanel() {
  const { t } = useTranslation()
  const { data, isPending } = useStatusPropagations()
  const update = useUpdateStatusPropagation()
  const remove = useDeleteStatusPropagation()

  const [editing, setEditing] = useState<StatusPropagation | null>(null)
  const [deleting, setDeleting] = useState<StatusPropagation | null>(null)

  const rules = data?.rules ?? []
  const hierarchy = data?.hierarchy ?? {}

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('statuses.propagations.description')}</p>

      {isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : rules.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('statuses.propagations.empty')}</p>
      ) : (
        <ul className="flex flex-col divide-y rounded-lg border px-4">
          {rules.map((rule) => (
            <li key={rule.id} className="flex flex-wrap items-center gap-3 py-3">
              <RuleSentence rule={rule} />

              {rule.active ? null : <Badge variant="outline">{t('common.disabled')}</Badge>}

              <PermissionGuard permission="statuses.update">
                <span className="ml-auto flex gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => update.mutate({ id: rule.id, active: !rule.active })}
                  >
                    {rule.active
                      ? t('statuses.propagations.disable')
                      : t('statuses.propagations.enable')}
                  </Button>

                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setEditing(rule)}
                    aria-label={t('statuses.propagations.edit')}
                  >
                    <Pencil className="size-4" aria-hidden />
                  </Button>

                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setDeleting(rule)}
                    aria-label={t('common.delete')}
                  >
                    <Trash2 className="size-4" aria-hidden />
                  </Button>
                </span>
              </PermissionGuard>
            </li>
          ))}
        </ul>
      )}

      <PermissionGuard permission="statuses.create">
        {/* La `key` remet le formulaire à l'état de la règle choisie. */}
        <StatusPropagationForm
          key={editing?.id ?? 'new'}
          rule={editing}
          hierarchy={hierarchy}
          onDone={() => setEditing(null)}
        />
      </PermissionGuard>

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.delete')}
        description={t('statuses.propagations.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}

/** « Colis « Chargé » → Service « En route » », lu comme il s'exécute. */
function RuleSentence({ rule }: { rule: StatusPropagation }) {
  const { t } = useTranslation()

  const side = (part: StatusPropagation['from']) =>
    part === null
      ? '—'
      : `${t(`entities.${part.source}`, { defaultValue: part.source })} « ${part.label} »`

  return (
    <span className="flex flex-wrap items-center gap-2 text-sm">
      <span>{side(rule.from)}</span>
      <ArrowRight className="size-4 text-muted-foreground" aria-hidden />
      <span>{side(rule.to)}</span>
      {rule.mode === 'any' ? (
        <Badge variant="secondary">{t('statuses.propagations.modeAny')}</Badge>
      ) : null}
    </span>
  )
}
