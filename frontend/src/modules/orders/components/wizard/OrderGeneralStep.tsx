import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { useAgencyOptions, useCustomerOptions, useDepotOptions } from '../../hooks/useOrderScope'
import type { OrderDraftController } from '../../hooks/useOrderDraft'
import { fieldError, generalIssues, type OrderErrorReport } from '../../schemas/orderErrors'

interface OrderGeneralStepProps {
  controller: OrderDraftController
  report: OrderErrorReport
  /** Client et agence sont figés après création : `UpdateOrderRequest` ne les accepte pas. */
  scopeLocked?: boolean
}

/**
 * En-tête de la commande : périmètre, références, dates.
 *
 * Changer de client remet l'article de catalogue de chaque ligne à zéro : un
 * article appartient au catalogue d'un client précis, le garder produirait un
 * 422 sur une ligne que l'utilisateur croirait valide.
 */
export function OrderGeneralStep({
  controller,
  report,
  scopeLocked = false,
}: OrderGeneralStepProps) {
  const { t } = useTranslation()
  const { draft, patch } = controller
  const issues = generalIssues(report)

  const customers = useCustomerOptions('')
  const agencies = useAgencyOptions()
  const depots = useDepotOptions(draft.agencyId)

  const onCustomerChange = (customerId: string) => {
    controller.setDraft((current) => ({
      ...current,
      customerId,
      lines: current.lines.map((line) => ({ ...line, catalogItemId: null })),
    }))
  }

  const onAgencyChange = (agencyId: string) => {
    // Le dépôt dépend de l'agence : le conserver enverrait un dépôt d'une autre
    // agence, que `OrderScopeGuard` refuserait.
    controller.setDraft((current) => ({ ...current, agencyId, depotId: '' }))
  }

  return (
    <div className="flex flex-col gap-4">
      <SectionCard title={t('orders.review.header')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <AsyncSelect
            label={t('orders.fields.customer')}
            value={draft.customerId}
            onChange={onCustomerChange}
            options={customers.options}
            isLoading={customers.isLoading}
            disabled={scopeLocked}
            required
            error={fieldError(issues, 'customerId')}
          />

          <AsyncSelect
            label={t('orders.fields.agency')}
            value={draft.agencyId}
            onChange={onAgencyChange}
            options={agencies.options}
            isLoading={agencies.isLoading}
            disabled={scopeLocked}
            required
            error={fieldError(issues, 'agencyId')}
          />

          <AsyncSelect
            label={t('orders.fields.depot')}
            value={draft.depotId}
            onChange={(depotId) => patch({ depotId })}
            options={depots.options}
            isLoading={depots.isLoading}
            disabled={draft.agencyId === ''}
            description={draft.agencyId === '' ? t('depots.selectAgencyFirst') : undefined}
            error={fieldError(issues, 'depotId')}
          />

          <ControlledField
            label={t('orders.fields.orderDate')}
            type="date"
            value={draft.orderDate}
            onChange={(orderDate) => patch({ orderDate })}
            required
            error={fieldError(issues, 'orderDate')}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('orders.fields.externalReference')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <ControlledField
            label={t('orders.fields.externalReference')}
            value={draft.externalReference}
            onChange={(externalReference) => patch({ externalReference })}
            error={fieldError(issues, 'externalReference')}
          />

          <ControlledField
            label={t('orders.fields.customerReference')}
            value={draft.customerReference}
            onChange={(customerReference) => patch({ customerReference })}
            error={fieldError(issues, 'customerReference')}
          />

          <ControlledField
            label={t('orders.fields.orderType')}
            value={draft.orderType}
            onChange={(orderType) => patch({ orderType })}
            error={fieldError(issues, 'orderType')}
          />

          <ControlledField
            label={t('orders.fields.groupCode')}
            value={draft.groupCode}
            onChange={(groupCode) => patch({ groupCode })}
            error={fieldError(issues, 'groupCode')}
          />

          <ControlledField
            label={t('orders.fields.currencyCode')}
            value={draft.currencyCode}
            onChange={(currencyCode) => patch({ currencyCode })}
            error={fieldError(issues, 'currencyCode')}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('orders.fields.internalRemark')}>
        <div className="grid gap-4">
          <ControlledField
            label={t('orders.fields.internalRemark')}
            value={draft.internalRemark}
            onChange={(internalRemark) => patch({ internalRemark })}
            multiline
            error={fieldError(issues, 'internalRemark')}
          />

          <ControlledField
            label={t('orders.fields.workerRemark')}
            value={draft.workerRemark}
            onChange={(workerRemark) => patch({ workerRemark })}
            multiline
            error={fieldError(issues, 'workerRemark')}
          />
        </div>
      </SectionCard>
    </div>
  )
}
