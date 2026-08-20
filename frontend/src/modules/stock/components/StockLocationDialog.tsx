import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useAgencyOptions, useDepotOptions } from '@/modules/orders/hooks/useOrderScope'
import { ApiError } from '@/shared/api/errors'
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

import { useCreateStockLocation, useUpdateStockLocation } from '../hooks/useStockLocationMutations'
import type { StockLocation } from '../types/stock'

/** Les quatre coordonnées d'un emplacement, toutes facultatives. */
const COORDINATES = ['zoneCode', 'aisle', 'rack', 'level'] as const

type Coordinate = (typeof COORDINATES)[number]

interface StockLocationDialogProps {
  location: StockLocation | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Créer ou modifier un emplacement de dépôt.
 *
 * Le dépôt se choisit par son agence : `/agencies/{agency}/depots` est la seule
 * route qui les liste, la dépendance est donc la forme de l'API, pas une
 * précaution d'interface. Il n'est demandé qu'à la création — déplacer un
 * emplacement d'un dépôt à l'autre déplacerait le stock qu'il contient.
 *
 * `locationCode` est unique **par dépôt** : deux dépôts peuvent tenir un
 * « A-01-2 », et c'est `StoreStockLocationRequest` qui l'impose.
 */
export function StockLocationDialog({ location, open, onOpenChange }: StockLocationDialogProps) {
  const { t } = useTranslation()
  const create = useCreateStockLocation()
  const update = useUpdateStockLocation()

  const [agencyId, setAgencyId] = useState('')
  const [depotId, setDepotId] = useState(location?.depotId ?? '')
  const [locationCode, setLocationCode] = useState(location?.locationCode ?? '')
  const [barcode, setBarcode] = useState(location?.barcode ?? '')
  const [coordinates, setCoordinates] = useState<Record<Coordinate, string>>({
    zoneCode: location?.zoneCode ?? '',
    aisle: location?.aisle ?? '',
    rack: location?.rack ?? '',
    level: location?.level ?? '',
  })
  const [error, setError] = useState<string | null>(null)

  const agencies = useAgencyOptions()
  const depots = useDepotOptions(agencyId)
  const isEdit = location !== null

  const blank = (value: string) => (value.trim() === '' ? null : value.trim())

  const submit = async () => {
    setError(null)

    const payload = {
      locationCode: locationCode.trim(),
      barcode: blank(barcode),
      zoneCode: blank(coordinates.zoneCode),
      aisle: blank(coordinates.aisle),
      rack: blank(coordinates.rack),
      level: blank(coordinates.level),
      status: location?.status ?? 'active',
    }

    try {
      if (isEdit) await update.mutateAsync({ id: location.id, ...payload })
      else await create.mutateAsync({ ...payload, depotId })

      onOpenChange(false)
    } catch (failure) {
      setError(failure instanceof ApiError ? failure.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('stock.editLocation') : t('stock.newLocation')}</DialogTitle>
          <DialogDescription>{t('stock.locationHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          {isEdit ? null : (
            <>
              <AsyncSelect
                label={t('orders.fields.agency')}
                value={agencyId}
                onChange={(next) => {
                  setAgencyId(next)
                  setDepotId('')
                }}
                options={agencies.options}
                isLoading={agencies.isLoading}
                required
              />

              <AsyncSelect
                label={t('orders.fields.depot')}
                value={depotId}
                onChange={setDepotId}
                options={depots.options}
                isLoading={depots.isLoading}
                disabled={agencyId === ''}
                description={agencyId === '' ? t('stock.pickAgencyFirst') : undefined}
                required
              />
            </>
          )}

          <ControlledField
            label={t('stock.fields.locationCode')}
            value={locationCode}
            onChange={setLocationCode}
            required
            description={t('stock.locationCodeHint')}
          />

          <div className="grid gap-4 sm:grid-cols-4">
            {COORDINATES.map((field) => (
              <ControlledField
                key={field}
                label={t(`stock.fields.${field}`)}
                value={coordinates[field]}
                onChange={(value) =>
                  setCoordinates((current) => ({ ...current, [field]: value }))
                }
              />
            ))}
          </div>

          <ControlledField
            label={t('stock.fields.barcode')}
            value={barcode}
            onChange={setBarcode}
          />
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={create.isPending || update.isPending}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
