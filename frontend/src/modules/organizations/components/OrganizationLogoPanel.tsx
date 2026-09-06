import { useTranslation } from 'react-i18next'

import {
  useOrganizationLogo,
  useRemoveOrganizationLogo,
  useUploadOrganizationLogo,
} from '../hooks/useOrganizationLogo'
import { LogoField } from '@/shared/components/form/LogoField'

interface OrganizationLogoPanelProps {
  organizationId: string
  hasLogo: boolean
}

/**
 * Le logo de l'organisation, tel qu'il paraîtra sur ses documents.
 *
 * Il n'est pas décoratif : les modèles de facture l'écrivent
 * `<img src="{{ organization.logo }}">`, et le PDF l'embarque. La barre latérale
 * le porte aussi, quand cette organisation est celle qui est active.
 *
 * Le geste lui-même vit dans `LogoField`, partagé avec la configuration de la
 * plateforme : deux copies auraient divergé au premier ajustement.
 */
export function OrganizationLogoPanel({ organizationId, hasLogo }: OrganizationLogoPanelProps) {
  const { t } = useTranslation()

  const { url, isPending } = useOrganizationLogo(organizationId, hasLogo)
  const upload = useUploadOrganizationLogo(organizationId)
  const remove = useRemoveOrganizationLogo(organizationId)

  return (
    <LogoField
      url={url}
      hasLogo={hasLogo}
      isLoading={isPending}
      isBusy={upload.isPending || remove.isPending}
      hint={t('organizations.logo.hint')}
      onUpload={(file) => upload.mutate(file)}
      onRemove={() => remove.mutate(undefined)}
    />
  )
}
