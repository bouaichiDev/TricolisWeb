import { ArrowRight } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import {
  useStatusList,
  useStatusTransitions,
  useSyncStatusTransitions,
} from '../hooks/useStatuses'
import type { Status, StatusTransitionInput } from '../types/status'

interface StatusTransitionsDialogProps {
  status: Status | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Dessine le cycle de vie au départ d'un statut.
 *
 * Chaque statut de la même entité est proposé comme destination. Cocher crée la
 * transition, décocher la retire, et l'ensemble part d'un bloc : une mise à
 * jour arête par arête laisserait, le temps de la séquence, un graphe que
 * personne n'a voulu.
 *
 * **« Posable à la main »** distingue ce qu'un opérateur déclare de ce que seuls
 * les modules produisent. Une transition existante mais non manuelle reste
 * valide — la planification l'emprunte — sans apparaître dans le sélecteur de
 * statut d'une commande.
 */
export function StatusTransitionsDialog({
  status,
  open,
  onOpenChange,
}: StatusTransitionsDialogProps) {
  const { t } = useTranslation()
  const [edits, setEdits] = useState<Record<string, boolean | undefined>>({})
  const [manualEdits, setManualEdits] = useState<Record<string, boolean>>({})
  const [error, setError] = useState<string | null>(null)

  const siblings = useStatusList({
    page: 1,
    perPage: 100,
    source: status?.source,
    sort: 'position',
    direction: 'asc',
  })
  const current = useStatusTransitions(status?.id ?? '', open)
  const sync = useSyncStatusTransitions(status?.id ?? '')

  const existing = new Map((current.data ?? []).map((item) => [item.toStatusId, item]))

  const isSelected = (id: string) => edits[id] ?? existing.has(id)
  const isManual = (id: string) => manualEdits[id] ?? existing.get(id)?.isManual ?? true

  const close = () => {
    setEdits({})
    setManualEdits({})
    setError(null)
    onOpenChange(false)
  }

  const submit = () => {
    if (status === null) return

    setError(null)

    const transitions: StatusTransitionInput[] = (siblings.data?.data ?? [])
      .filter((target) => target.id !== status.id && isSelected(target.id))
      .map((target) => ({ toStatusId: target.id, isManual: isManual(target.id) }))

    sync.mutate(transitions, {
      onSuccess: close,
      onError: (cause) => {
        setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
      },
    })
  }

  const targets = (siblings.data?.data ?? []).filter((target) => target.id !== status?.id)

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {status?.label}
            <ArrowRight className="size-4 text-muted-foreground" aria-hidden />
            {t('statuses.transitions.title')}
          </DialogTitle>
          <DialogDescription>{t('statuses.transitions.description')}</DialogDescription>
        </DialogHeader>

        {error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        {current.isPending || siblings.isPending ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : targets.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('statuses.transitions.noSibling')}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {targets.map((target) => (
              <li key={target.id} className="rounded-md border px-3 py-2">
                <ControlledCheckbox
                  label={`${target.label} (${target.code})`}
                  checked={isSelected(target.id)}
                  onChange={(checked) =>
                    setEdits((previous) => ({ ...previous, [target.id]: checked }))
                  }
                  description={target.active ? undefined : t('statuses.transitions.inactive')}
                />

                {isSelected(target.id) ? (
                  <div className="mt-1 pl-7">
                    <ControlledCheckbox
                      label={t('statuses.transitions.manual')}
                      checked={isManual(target.id)}
                      onChange={(checked) =>
                        setManualEdits((previous) => ({ ...previous, [target.id]: checked }))
                      }
                      description={t('statuses.transitions.manualHint')}
                    />
                  </div>
                ) : null}
              </li>
            ))}
          </ul>
        )}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={close}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={sync.isPending}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
