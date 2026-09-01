import { useTranslation } from 'react-i18next'

import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import { useActiveServices } from '@/modules/services/hooks/useServices'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import type { TemplateFormValues } from '../schemas/templateSchema'
import { isDocumentType } from '../types/template'

interface TemplateScopeFieldsProps {
  values: TemplateFormValues
  onChange: (patch: Partial<TemplateFormValues>) => void
}

/** Valeur du select quand rien n'est choisi : Radix refuse une chaîne vide. */
const NONE = '__none__'

/**
 * À qui s'applique le modèle : un client, un service, ou tout le monde.
 *
 * **Aucun client** ne veut dire « le modèle du transporteur ». C'est le cas par
 * défaut, et le repli du serveur s'appuie dessus : le modèle d'un client
 * l'emporte, sinon le global sert. Un client n'hérite jamais du modèle d'un
 * autre.
 *
 * Le **service** ne concerne que les messages. Une facture n'est pas rattachée
 * à une prestation : elle en facture plusieurs, et le serveur force `serviceId`
 * à nul pour un document.
 */
export function TemplateScopeFields({ values, onChange }: TemplateScopeFieldsProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')
  const services = useActiveServices()

  const document = isDocumentType(values.templateType)

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <AsyncSelect
        label={t('templates.fields.customer')}
        value={values.customerId === '' ? NONE : values.customerId}
        onChange={(customerId) => onChange({ customerId: customerId === NONE ? '' : customerId })}
        options={[
          { value: NONE, label: t('templates.globalScope') },
          ...customers.options,
        ]}
        isLoading={customers.isLoading}
        description={t('templates.customerHint')}
      />

      {document ? null : (
        <AsyncSelect
          label={t('templates.fields.service')}
          value={values.serviceId === '' ? NONE : values.serviceId}
          onChange={(serviceId) => onChange({ serviceId: serviceId === NONE ? '' : serviceId })}
          options={[
            { value: NONE, label: t('templates.allServices') },
            ...(services.data?.data ?? []).map((service) => ({
              value: service.id,
              label: service.name,
              hint: service.code,
            })),
          ]}
          isLoading={services.isPending}
          description={t('templates.serviceHint')}
        />
      )}
    </div>
  )
}
