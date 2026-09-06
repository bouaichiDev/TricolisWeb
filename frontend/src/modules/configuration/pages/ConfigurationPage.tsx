import { useTranslation } from 'react-i18next'

import {
  useConfiguration,
  usePlatformLogo,
  useRemovePlatformLogo,
  useUploadPlatformLogo,
} from '../hooks/useConfiguration'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { LogoField } from '@/shared/components/form/LogoField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * La configuration de l'installation.
 *
 * Un seul réglage aujourd'hui — le logo par défaut — et c'est délibérément une
 * **page**, pas un champ posé sur un écran existant. Les réglages de
 * l'installation elle-même n'ont pas d'autre endroit où aller : les glisser dans
 * la fiche d'une organisation les rendrait introuvables, puisqu'ils ne
 * concernent aucune organisation en particulier. La page existe donc pour le
 * suivant autant que pour celui-ci.
 *
 * Elle est réservée à la plateforme, par la route comme par l'API : un organisme
 * n'a pas à décider de l'apparence d'un outil qu'il partage avec d'autres.
 */
export function ConfigurationPage() {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useConfiguration()
  const hasDefaultLogo = data?.hasDefaultLogo ?? false

  const { url, isPending: isLoadingLogo } = usePlatformLogo(hasDefaultLogo)
  const upload = useUploadPlatformLogo()
  const remove = useRemovePlatformLogo()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('configuration.title')} description={t('configuration.subtitle')} />

      {error ? <ErrorState error={error} onRetry={() => void refetch()} /> : null}

      {error === null ? (
        <SectionCard title={t('configuration.defaultLogo')}>
          <LogoField
            url={url}
            hasLogo={hasDefaultLogo}
            // Tant que la configuration se charge, l'aperçu est en attente lui
            // aussi : afficher « aucun logo » puis le remplacer une seconde plus
            // tard ferait croire à un dépôt qu'on n'a pas fait.
            isLoading={isPending || isLoadingLogo}
            isBusy={upload.isPending || remove.isPending}
            hint={t('configuration.defaultLogoHint')}
            onUpload={(file) => upload.mutate(file)}
            onRemove={() => remove.mutate(undefined)}
          />
        </SectionCard>
      ) : null}
    </div>
  )
}
