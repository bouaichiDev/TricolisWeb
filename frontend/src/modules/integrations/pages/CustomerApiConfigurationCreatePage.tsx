import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { ApiKeyCreatedDialog } from '../components/ApiKeyCreatedDialog'
import { CustomerApiConfigurationForm } from '../components/CustomerApiConfigurationForm'
import { useCreateCustomerApiConfiguration } from '../hooks/useCustomerApiConfigurations'
import type { CustomerApiConfigurationPayload } from '../types/customerIntegration'

/**
 * Création d'un accès API client.
 *
 * La réponse porte la clé en clair — **la seule fois**. Elle est gardée dans
 * l'état local de cette page le temps de l'afficher, puis effacée : la
 * navigation vers la fiche n'a lieu qu'à la fermeture du dialogue, pour que la
 * clé ne disparaisse pas sous les yeux de celui qui devait la copier.
 *
 * Cette valeur n'entre ni au cache, ni au stockage local, ni dans l'URL (§22).
 */
export function CustomerApiConfigurationCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const presetCustomerId = params.get('customerId') ?? ''

  const create = useCreateCustomerApiConfiguration(
    presetCustomerId === '' ? undefined : presetCustomerId,
  )

  const [issued, setIssued] = useState<{ apiKey: string; id: string; name: string } | null>(null)

  const submit = async (payload: CustomerApiConfigurationPayload) => {
    const result = await create.mutateAsync(payload)

    setIssued({
      apiKey: result.apiKey,
      id: result.configuration.id,
      name: result.configuration.name,
    })
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('integrations.api.create')}
        description={t('integrations.api.createHint')}
      />

      <CustomerApiConfigurationForm
        defaultValues={presetCustomerId === '' ? undefined : { customerId: presetCustomerId }}
        onSubmit={submit}
        onCancel={() => void navigate('/integrations/api-access')}
        submitLabel={t('common.create')}
      />

      <ApiKeyCreatedDialog
        apiKey={issued?.apiKey ?? null}
        configurationName={issued?.name}
        onClose={() => {
          const id = issued?.id
          // La clé est effacée de l'état avant toute navigation : elle ne doit
          // survivre ni au démontage, ni à un retour arrière.
          setIssued(null)
          if (id !== undefined) void navigate(`/integrations/api-access/${id}`)
        }}
      />
    </div>
  )
}
