import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useStatusList } from '@/modules/statuses/hooks/useStatuses'
import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
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

interface ChangeEntityStatusDialogProps {
  /** Alias métier de `MorphMap` : `order_line`, `package`, `order_service`. */
  source: string
  /** Élément visé, ou `null` quand le dialogue est fermé. */
  entityId: string | null
  title?: string | null
  currentStatus?: string | null
  isPending: boolean
  onSubmit: (status: string, onError: (cause: unknown) => void) => void
  onClose: () => void
}

/**
 * Changement de statut d'un élément de la commande.
 *
 * **Les statuts proposés viennent du référentiel**, filtrés sur l'entité et sur
 * les actifs. Une ligne et un colis portent un statut en chaîne libre : rien ne
 * les contraint côté serveur, et c'est justement pourquoi la liste doit venir
 * d'un référentiel plutôt que d'une constante — sinon deux écrans proposeraient
 * deux vocabulaires.
 *
 * Quand l'entité n'a encore aucun statut décrit, l'écran le dit et renvoie là où
 * cela se règle, plutôt que d'ouvrir une liste vide.
 */
export function ChangeEntityStatusDialog({
  source,
  entityId,
  title,
  currentStatus,
  isPending,
  onSubmit,
  onClose,
}: ChangeEntityStatusDialogProps) {
  const { t } = useTranslation()
  const open = entityId !== null
  const [status, setStatus] = useState('')
  const [error, setError] = useState<string | null>(null)

  const referential = useStatusList(
    { page: 1, perPage: 100, source, active: true, sort: 'position', direction: 'asc' },
    open,
  )

  const options = (referential.data?.data ?? []).map((item) => ({
    value: item.code,
    label: item.label,
    hint: item.code === currentStatus ? t('orders.statusDialog.current') : undefined,
    disabled: item.code === currentStatus,
  }))

  const close = () => {
    setStatus('')
    setError(null)
    onClose()
  }

  const submit = () => {
    if (status === '') return

    setError(null)
    onSubmit(status, (cause) =>
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected')),
    )
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && close()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('orders.statusDialog.title')}</DialogTitle>
          <DialogDescription>{title ?? ''}</DialogDescription>
        </DialogHeader>

        {error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        {!referential.isPending && options.length === 0 ? (
          <Alert>
            <AlertDescription className="flex flex-col items-start gap-2">
              {t('orders.statusDialog.noReferential')}
              <Button asChild variant="outline" size="sm">
                <Link to="/statuses" onClick={close}>
                  {t('nav.statuses')}
                </Link>
              </Button>
            </AlertDescription>
          </Alert>
        ) : (
          <AsyncSelect
            label={t('orders.statusDialog.newStatus')}
            value={status}
            onChange={setStatus}
            options={options}
            isLoading={referential.isPending}
            required
          />
        )}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={close}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={status === '' || isPending}>
            {t('orders.statusDialog.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
