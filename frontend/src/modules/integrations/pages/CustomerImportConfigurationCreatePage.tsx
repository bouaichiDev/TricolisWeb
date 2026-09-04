import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { CustomerImportConfigurationForm } from '../components/CustomerImportConfigurationForm'
import { useCreateCustomerImportConfiguration } from '../hooks/useCustomerImportConfigurations'
import type { CustomerImportConfigurationPayload } from '../types/customerIntegration'

/**
 * Création d'une configuration d'import.
 *
 * `customerId` peut venir de l'URL : l'onglet Intégrations d'une fiche client y
 * amène avec le client déjà choisi. La route imbriquée prend alors le relais.
 */
export function CustomerImportConfigurationCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const presetCustomerId = params.get('customerId') ?? ''

  const create = useCreateCustomerImportConfiguration(
    presetCustomerId === '' ? undefined : presetCustomerId,
  )

  const submit = async (payload: CustomerImportConfigurationPayload) => {
    const configuration = await create.mutateAsync(payload)
    await navigate(`/integrations/imports/${configuration.id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('integrations.imports.create')}
        description={t('integrations.imports.createHint')}
      />

      <CustomerImportConfigurationForm
        defaultValues={presetCustomerId === '' ? undefined : { customerId: presetCustomerId }}
        onSubmit={submit}
        onCancel={() => void navigate('/integrations/imports')}
        submitLabel={t('common.create')}
      />
    </div>
  )
}
