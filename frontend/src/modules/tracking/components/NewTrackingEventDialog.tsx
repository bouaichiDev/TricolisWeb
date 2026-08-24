import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
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

import { useCreateTrackingEvent } from '../hooks/useTracking'

interface NewTrackingEventDialogProps {
  orderId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Champ vide : absent de la charge utile plutôt qu'envoyé à vide. */
const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

const coordinate = (value: string): number | null => {
  const parsed = Number(value.trim())

  return value.trim() === '' || !Number.isFinite(parsed) ? null : parsed
}

/**
 * Ajout d'un événement de suivi.
 *
 * `eventType` et `status` sont des **champs libres**, parce qu'ils le sont côté
 * serveur : `StoreTrackingEventRequest` ne les valide qu'en longueur, aucune
 * énumération PHP ne les borne. Proposer une liste déroulante inventerait un
 * vocabulaire métier, et interdirait celui que l'exploitation utilise déjà.
 *
 * `occurredAt` est requis et pré-rempli à maintenant : un événement de suivi
 * s'enregistre le plus souvent au moment où il survient, mais peut être saisi
 * après coup.
 */
export function NewTrackingEventDialog({
  orderId,
  open,
  onOpenChange,
}: NewTrackingEventDialogProps) {
  const { t } = useTranslation()
  const create = useCreateTrackingEvent(orderId)

  const [eventType, setEventType] = useState('')
  const [status, setStatus] = useState('')
  const [description, setDescription] = useState('')
  const [latitude, setLatitude] = useState('')
  const [longitude, setLongitude] = useState('')
  const [occurredAt, setOccurredAt] = useState(() => new Date().toISOString().slice(0, 16))
  const [error, setError] = useState<string | null>(null)

  const incomplete = eventType.trim() === '' || status.trim() === '' || occurredAt === ''

  const submit = async () => {
    setError(null)

    try {
      await create.mutateAsync({
        orderId,
        eventType: eventType.trim(),
        status: status.trim(),
        description: blank(description),
        latitude: coordinate(latitude),
        longitude: coordinate(longitude),
        occurredAt: new Date(occurredAt).toISOString(),
      })

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('tracking.add')}</DialogTitle>
          <DialogDescription>{t('tracking.addHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <div className="grid gap-4 sm:grid-cols-2">
            <ControlledField
              label={t('tracking.fields.eventType')}
              value={eventType}
              onChange={setEventType}
              required
              description={t('tracking.freeTextHint')}
            />
            <ControlledField
              label={t('tracking.fields.status')}
              value={status}
              onChange={setStatus}
              required
            />
          </div>

          <ControlledField
            label={t('tracking.fields.occurredAt')}
            type="datetime-local"
            value={occurredAt}
            onChange={setOccurredAt}
            required
          />

          <ControlledField
            label={t('tracking.fields.description')}
            value={description}
            onChange={setDescription}
            multiline
          />

          <div className="grid gap-4 sm:grid-cols-2">
            <ControlledField
              label={t('tracking.fields.latitude')}
              value={latitude}
              onChange={setLatitude}
              description={t('tracking.coordinatesHint')}
            />
            <ControlledField
              label={t('tracking.fields.longitude')}
              value={longitude}
              onChange={setLongitude}
            />
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={incomplete || create.isPending}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
