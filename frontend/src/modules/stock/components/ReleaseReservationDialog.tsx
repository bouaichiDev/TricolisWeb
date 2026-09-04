import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { useReleaseStockReservation } from '../hooks/useStockReservations'
import { STOCK_RESERVATION_SOURCE } from '../utils/stockSources'

/** Le code que `StatusSeeder` sème pour une réservation rendue. */
const RELEASED = 'released'

interface ReleaseReservationDialogProps {
  reservationId: string
  open: boolean
  onOpenChange: (open: boolean) => void
  onReleased?: () => void
}

/**
 * Libérer une réservation.
 *
 * Ce n'est **pas** une suppression. `ReleaseStockReservationAction` renseigne
 * `releasedAt`, écrit le statut et rend la quantité au disponible, le tout sous
 * verrou. La ligne reste en base : c'est la trace de ce qui avait été promis
 * puis rendu, et l'effacer ferait disparaître cette histoire.
 *
 * Le serveur n'accepte qu'un champ, `status` : ni la date, ni la quantité
 * rendue. Les laisser saisir permettrait d'antidater une libération, ou de
 * rendre plus que ce qui avait été pris.
 *
 * Une seconde libération est refusée en 409, contrôlée deux fois. Le bouton
 * disparaît quand `releasedAt` existe, mais deux onglets ouverts suffisent à
 * contourner l'écran — c'est le serveur qui tranche.
 */
export function ReleaseReservationDialog({
  reservationId,
  open,
  onOpenChange,
  onReleased,
}: ReleaseReservationDialogProps) {
  const { t } = useTranslation()
  const release = useReleaseStockReservation(reservationId)
  const { options, isLoading } = useStatusOptions(STOCK_RESERVATION_SOURCE, RELEASED)
  const [status, setStatus] = useState(RELEASED)

  const submit = () => {
    release.mutate(
      { status },
      {
        onSuccess: () => {
          onOpenChange(false)
          onReleased?.()
        },
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('stock.release')}</DialogTitle>
          <DialogDescription>{t('stock.releaseHint')}</DialogDescription>
        </DialogHeader>

        <AsyncSelect
          label={t('stock.fields.status')}
          value={status}
          onChange={setStatus}
          options={options}
          isLoading={isLoading}
          description={t('stock.releaseStatusHint')}
          required
        />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={release.isPending}>
            {t('stock.release')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
