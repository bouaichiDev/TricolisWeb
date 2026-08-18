import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Button } from '@/shared/components/ui/button'

import { useServiceOptions } from '../../hooks/useServiceScope'
import type { PackageDraft, ServiceDraft } from '../../schemas/orderDraft'
import { ORDER_SERVICE_STATUSES } from '../../types/order'
import { fieldError, issuesOf, type OrderErrorReport } from '../../schemas/orderErrors'
import { AddressPicker } from './AddressPicker'
import { OrderServiceContactsEditor } from './OrderServiceContactsEditor'
import { OrderServiceMeasures } from './OrderServiceMeasures'
import { OrderServicePackagesEditor } from './OrderServicePackagesEditor'

interface OrderServiceCardProps {
  service: ServiceDraft
  position: number
  customerId: string
  packages: PackageDraft[]
  report: OrderErrorReport
  onChange: (values: Partial<ServiceDraft>) => void
  onRemove: () => void
  canRemove: boolean
}

/**
 * Un service de commande : l'unité adressée et planifiée.
 *
 * C'est ce service qui porte l'adresse, le créneau, les contacts et les colis —
 * il n'existe pas d'entité « arrêt » séparée dans le modèle. Choisir un service
 * du référentiel reprend son unité et sa durée par défaut : ce sont les valeurs
 * du référentiel, pas des valeurs inventées.
 */
export function OrderServiceCard({
  service,
  position,
  customerId,
  packages,
  report,
  onChange,
  onRemove,
  canRemove,
}: OrderServiceCardProps) {
  const { t } = useTranslation()
  const services = useServiceOptions()
  const issues = issuesOf(report, service.key)
  const picked = services.byId.get(service.serviceId)

  const onServiceChange = (serviceId: string) => {
    const chosen = services.byId.get(serviceId)

    onChange({
      serviceId,
      unit: service.unit === '' ? (chosen?.unit ?? '') : service.unit,
      requiredTimeMinutes:
        service.requiredTimeMinutes === '0' && chosen?.defaultDurationMinutes != null
          ? String(chosen.defaultDurationMinutes)
          : service.requiredTimeMinutes,
    })
  }

  return (
    <li className="rounded-lg border p-4">
      <div className="mb-4 flex items-start justify-between gap-2">
        <span className="text-sm font-medium">{t('orders.services.position', { position })}</span>

        {canRemove ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onRemove}
            aria-label={t('orders.services.remove')}
          >
            <Trash2 className="size-4" aria-hidden />
          </Button>
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <AsyncSelect
          label={t('orders.services.service')}
          value={service.serviceId}
          onChange={onServiceChange}
          options={services.options}
          isLoading={services.isLoading}
          required
          description={
            picked?.requiresContact === true ? t('orders.services.requiresContact') : undefined
          }
          error={fieldError(issues, 'serviceId')}
        />

        <ControlledField
          label={t('orders.fields.serviceNumber')}
          value={service.serviceNumber}
          onChange={(serviceNumber) => onChange({ serviceNumber })}
          required
          description={t('orders.services.serviceNumberHint')}
          error={fieldError(issues, 'serviceNumber')}
        />

        <ControlledField
          label={t('orders.fields.sequence')}
          type="number"
          min="1"
          step="1"
          value={service.sequence}
          onChange={(sequence) => onChange({ sequence })}
          required
          description={t('orders.services.sequenceHint')}
          error={fieldError(issues, 'sequence')}
        />

        <AddressPicker
          customerId={customerId}
          value={service.addressId}
          onChange={(addressId) => onChange({ addressId })}
          required
          error={fieldError(issues, 'addressId')}
        />

        <AsyncSelect
          label={t('orders.fields.status')}
          value={service.status}
          onChange={(status) => onChange({ status })}
          options={ORDER_SERVICE_STATUSES.map((status) => ({
            value: status,
            label: t(`orderServiceStatuses.${status}`),
          }))}
          required
          error={fieldError(issues, 'status')}
        />
      </div>

      <OrderServiceMeasures service={service} issues={issues} onChange={onChange} />

      <div className="mt-4 border-t pt-4">
        <ControlledField
          label={t('orders.fields.instructions')}
          value={service.instructions}
          onChange={(instructions) => onChange({ instructions })}
          multiline
          error={fieldError(issues, 'instructions')}
        />
      </div>

      <div className="mt-4 border-t pt-4">
        <OrderServiceContactsEditor
          serviceKey={service.key}
          addressId={service.addressId}
          contacts={service.contacts}
          report={report}
          onChange={(contacts) => onChange({ contacts })}
        />
      </div>

      <div className="mt-4 border-t pt-4">
        <OrderServicePackagesEditor
          packages={packages}
          selected={service.packages}
          onChange={(items) => onChange({ packages: items })}
        />
      </div>
    </li>
  )
}
