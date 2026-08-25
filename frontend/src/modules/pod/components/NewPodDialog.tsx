import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { OrderService } from '@/modules/orders/types/orderDetail'
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

import { useCreatePod } from '../hooks/usePod'

interface NewPodDialogProps {
  orderId: string
  services: OrderService[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

const NO_SERVICE = 'none'

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/**
 * Enregistrement d'une preuve de livraison.
 *
 * Deux champs seulement sont requis, parce que le serveur n'en exige pas
 * davantage : le nom de qui a reçu, et la date. La **signature et la photo
 * restent facultatives** — le §12 l'impose et `StoreProofOfDeliveryRequest` les
 * accepte nulles : une livraison peut être constatée sans signature.
 *
 * Elles ne sont pas demandées ici du tout : ce sont des `Document` déjà
 * téléversés, et aucune route ne permet d'en créer un depuis ce formulaire. Les
 * rattacher demanderait un sélecteur de documents que le §33 réserve aux pièces
 * jointes de communication.
 *
 * `tourStopId` n'est pas proposé non plus : la planification est hors périmètre
 * de cette phase, et aucun écran ne connaît les arrêts.
 */
export function NewPodDialog({ orderId, services, open, onOpenChange }: NewPodDialogProps) {
  const { t } = useTranslation()
  const create = useCreatePod(orderId)

  const [recipientName, setRecipientName] = useState('')
  const [serviceId, setServiceId] = useState(NO_SERVICE)
  const [remark, setRemark] = useState('')
  const [deliveredAt, setDeliveredAt] = useState(() => new Date().toISOString().slice(0, 16))
  const [error, setError] = useState<string | null>(null)

  const incomplete = recipientName.trim() === '' || deliveredAt === ''

  const submit = async () => {
    setError(null)

    try {
      await create.mutateAsync({
        orderId,
        orderServiceId: serviceId === NO_SERVICE ? null : serviceId,
        recipientName: recipientName.trim(),
        remark: blank(remark),
        deliveredAt: new Date(deliveredAt).toISOString(),
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
          <DialogTitle>{t('pod.add')}</DialogTitle>
          <DialogDescription>{t('pod.addHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <ControlledField
            label={t('pod.fields.recipientName')}
            value={recipientName}
            onChange={setRecipientName}
            required
          />

          <ControlledField
            label={t('pod.fields.deliveredAt')}
            type="datetime-local"
            value={deliveredAt}
            onChange={setDeliveredAt}
            required
          />

          <AsyncSelect
            label={t('pod.fields.orderService')}
            value={serviceId}
            onChange={setServiceId}
            options={[
              { value: NO_SERVICE, label: t('pod.wholeOrder') },
              ...services.map((service) => ({
                value: service.id,
                label: service.service?.name ?? service.serviceNumber,
                hint: service.serviceNumber,
              })),
            ]}
            description={t('pod.serviceHint')}
          />

          <ControlledField
            label={t('pod.fields.remark')}
            value={remark}
            onChange={setRemark}
            multiline
          />
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
