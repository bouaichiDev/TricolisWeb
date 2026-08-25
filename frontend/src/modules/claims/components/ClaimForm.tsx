import { useTranslation } from 'react-i18next'

import type { OrderService } from '@/modules/orders/types/orderDetail'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'

import { StatusFromReferential } from './StatusFromReferential'
import { NO_SERVICE, type ClaimFormValues } from '../schemas/claimForm'

interface ClaimFormProps {
  values: ClaimFormValues
  onChange: (values: Partial<ClaimFormValues>) => void
  services: OrderService[]
  /**
   * Vrai en modification seulement.
   *
   * `StoreClaimRequest` **refuse** `decision`, `followUp`, `result`, `cost` et
   * `closedAt` : une réclamation naît ouverte, et ce qu'on en a fait s'écrit
   * ensuite. Afficher ces champs à la création promettrait une saisie que le
   * serveur rejetterait.
   */
  showTreatment: boolean
}

/**
 * Formulaire d'une réclamation, en trois sections.
 *
 * Le client n'y figure pas : il vient de la commande, et la création passe par
 * `POST /customers/{customer}/claims`, où il est dans l'URL. Le §15 interdit de
 * laisser choisir le client d'une autre commande — ici, il n'est même pas
 * demandé.
 *
 * `claimType` est un champ libre, comme côté serveur.
 */
export function ClaimForm({ values, onChange, services, showTreatment }: ClaimFormProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-6">
      <section className="flex flex-col gap-4">
        <p className="text-sm font-medium">{t('claims.sections.claim')}</p>

        <ControlledField
          label={t('claims.fields.title')}
          value={values.title}
          onChange={(title) => onChange({ title })}
          required
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <ControlledField
            label={t('claims.fields.claimType')}
            value={values.claimType}
            onChange={(claimType) => onChange({ claimType })}
            required
            description={t('claims.freeTextHint')}
          />

          <StatusFromReferential
            source="claim"
            label={t('claims.fields.status')}
            value={values.status}
            onChange={(status) => onChange({ status })}
            required
          />
        </div>

        <ControlledField
          label={t('claims.fields.description')}
          value={values.description}
          onChange={(description) => onChange({ description })}
          multiline
        />

        <ControlledField
          label={t('claims.fields.cause')}
          value={values.cause}
          onChange={(cause) => onChange({ cause })}
        />
      </section>

      <section className="flex flex-col gap-4 border-t pt-4">
        <p className="text-sm font-medium">{t('claims.sections.context')}</p>

        <AsyncSelect
          label={t('claims.fields.orderService')}
          value={values.orderServiceId}
          onChange={(orderServiceId) => onChange({ orderServiceId })}
          options={[
            { value: NO_SERVICE, label: t('claims.wholeOrder') },
            ...services.map((service) => ({
              value: service.id,
              label: service.service?.name ?? service.serviceNumber,
              hint: service.serviceNumber,
            })),
          ]}
          description={t('claims.serviceHint')}
        />
      </section>

      {showTreatment ? (
        <section className="flex flex-col gap-4 border-t pt-4">
          <div>
            <p className="text-sm font-medium">{t('claims.sections.treatment')}</p>
            <p className="text-xs text-muted-foreground">{t('claims.treatmentHint')}</p>
          </div>

          <ControlledField
            label={t('claims.fields.decision')}
            value={values.decision}
            onChange={(decision) => onChange({ decision })}
            multiline
          />

          <ControlledField
            label={t('claims.fields.followUp')}
            value={values.followUp}
            onChange={(followUp) => onChange({ followUp })}
            multiline
          />

          <div className="grid gap-4 sm:grid-cols-3">
            <ControlledField
              label={t('claims.fields.result')}
              value={values.result}
              onChange={(result) => onChange({ result })}
            />
            <ControlledField
              label={t('claims.fields.cost')}
              type="number"
              min="0"
              step="0.01"
              value={values.cost}
              onChange={(cost) => onChange({ cost })}
            />
            <ControlledField
              label={t('claims.fields.closedAt')}
              type="datetime-local"
              value={values.closedAt}
              onChange={(closedAt) => onChange({ closedAt })}
            />
          </div>
        </section>
      ) : null}
    </div>
  )
}
