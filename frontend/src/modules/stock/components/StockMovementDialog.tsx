import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { ApiError } from '@/shared/api/errors'

import { useCreateStockMovement } from '../hooks/useStock'
import { useStockLocationOptions } from '../hooks/useStockScope'
import type { MovementDirection } from '../types/stock'

const DIRECTIONS: MovementDirection[] = ['entry', 'exit', 'transfer']

interface StockMovementDialogProps {
  stockItemId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Enregistrer un mouvement — le seul moyen de faire bouger une quantité.
 *
 * Il n'existe **pas** de route qui écrive un solde. `StockBalance` est dérivé :
 * `CreateStockMovementAction` verrouille les soldes concernés, contrôle la
 * disponibilité, écrit le mouvement puis recalcule, le tout en transaction.
 * Une quantité saisie à la main n'aurait aucune histoire, et deux corrections
 * simultanées s'écraseraient.
 *
 * Le **sens** n'est pas un champ du modèle : il se déduit des emplacements.
 * Entrée = destination seule, sortie = source seule, transfert = les deux.
 * L'écran le demande d'abord parce que c'est la question qu'on se pose, puis
 * n'envoie que ce que le sens implique.
 *
 * `movementType` reste libre : le diagramme n'énumère aucune valeur, et en
 * inventer une liste serait décider à la place du métier.
 */
export function StockMovementDialog({
  stockItemId,
  open,
  onOpenChange,
}: StockMovementDialogProps) {
  const { t } = useTranslation()
  const create = useCreateStockMovement()
  const [locationSearch, setLocationSearch] = useState('')
  const locations = useStockLocationOptions(locationSearch)

  const [direction, setDirection] = useState<MovementDirection>('entry')
  const [source, setSource] = useState('')
  const [destination, setDestination] = useState('')
  const [quantity, setQuantity] = useState('')
  const [movementType, setMovementType] = useState('')
  const [error, setError] = useState<string | null>(null)

  const needsSource = direction !== 'entry'
  const needsDestination = direction !== 'exit'

  const submit = async () => {
    setError(null)

    const parsed = Number(quantity)
    if (!Number.isFinite(parsed) || parsed <= 0) {
      setError(t('stock.quantityMustBePositive'))

      return
    }

    try {
      await create.mutateAsync({
        stockItemId,
        sourceLocationId: needsSource ? source : null,
        destinationLocationId: needsDestination ? destination : null,
        movementType: movementType.trim() === '' ? t(`stock.directions.${direction}`) : movementType,
        quantity: parsed,
      })

      onOpenChange(false)
    } catch (failure) {
      // 422 sur la disponibilite, 409 sur un emplacement ferme : le serveur
      // formule ces refus mieux que l'ecran ne le devinerait.
      setError(failure instanceof ApiError ? failure.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('stock.newMovement')}</DialogTitle>
          <DialogDescription>{t('stock.newMovementHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <AsyncSelect
            label={t('stock.direction')}
            value={direction}
            onChange={(next) => {
              setDirection(next as MovementDirection)
              // Un emplacement retenu pour l'autre sens partirait dans une
              // charge utile qui ne l'attend plus.
              setSource('')
              setDestination('')
            }}
            options={DIRECTIONS.map((value) => ({
              value,
              label: t(`stock.directions.${value}`),
              hint: t(`stock.directionHints.${value}`),
            }))}
            required
          />

          <ControlledField
            label={t('stock.filterLocations')}
            value={locationSearch}
            onChange={setLocationSearch}
            description={
              locations.isTruncated
                ? t('stock.locationsTruncated', { shown: locations.options.length, total: locations.total })
                : t('stock.filterLocationsHint')
            }
          />

          {needsSource ? (
            <AsyncSelect
              label={t('stock.sourceLocation')}
              value={source}
              onChange={setSource}
              options={locations.options}
              isLoading={locations.isLoading}
              required
            />
          ) : null}

          {needsDestination ? (
            <AsyncSelect
              label={t('stock.destinationLocation')}
              value={destination}
              onChange={setDestination}
              options={locations.options}
              isLoading={locations.isLoading}
              required
              description={
                direction === 'transfer' ? t('stock.sameDepotHint') : undefined
              }
            />
          ) : null}

          <ControlledField
            label={t('stock.quantity')}
            type="number"
            min="0"
            step="0.001"
            value={quantity}
            onChange={setQuantity}
            required
          />

          <ControlledField
            label={t('stock.movementType')}
            value={movementType}
            onChange={setMovementType}
            description={t('stock.movementTypeHint')}
          />
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={() => void submit()} disabled={create.isPending}>
            {t('stock.record')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
