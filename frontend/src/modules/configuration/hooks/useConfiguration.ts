import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { configurationApi } from '../api/configuration.api'

export const configurationKeys = {
  all: ['configuration'] as const,
  settings: () => [...configurationKeys.all, 'settings'] as const,
  logo: () => [...configurationKeys.all, 'logo'] as const,
}

/**
 * La configuration de l'installation.
 *
 * Le cache est long : elle ne change qu'au passage d'un administrateur
 * plateforme sur son écran de réglage, et la redemander à chaque navigation
 * ferait une requête par page pour un booléen. Le réglage l'invalide
 * explicitement.
 *
 * La barre latérale l'appelle sur chaque page ; une seule requête part, React
 * Query rendant la même promesse à tous les appelants.
 */
export function useConfiguration() {
  return useQuery({
    queryKey: configurationKeys.settings(),
    queryFn: configurationApi.read,
    staleTime: 10 * 60 * 1000,
  })
}

/**
 * URL locale du logo par défaut, prête à être posée dans un `img`.
 *
 * L'URL d'objet est révoquée au démontage : sans cela, chaque montage laisserait
 * son image en mémoire jusqu'au rechargement de la page.
 *
 * `enabled` évite l'aller-retour quand il n'y en a pas — la configuration le
 * dit déjà, et une requête pour un 404 attendu ne renseigne personne.
 */
export function usePlatformLogo(enabled: boolean) {
  const query = useQuery({
    queryKey: configurationKeys.logo(),
    queryFn: configurationApi.readLogo,
    enabled,
    staleTime: 10 * 60 * 1000,
    retry: false,
  })

  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (query.data === undefined) {
      setUrl(null)

      return
    }

    const objectUrl = URL.createObjectURL(query.data)
    setUrl(objectUrl)

    return () => URL.revokeObjectURL(objectUrl)
  }, [query.data])

  return { url, isPending: query.isPending }
}

/**
 * Déposer ou retirer le logo par défaut.
 *
 * Les deux invalident **la configuration autant que l'image** : c'est
 * `hasDefaultLogo` qui décide si l'aperçu se demande, et le laisser périmé
 * montrerait un cadre vide après un dépôt réussi.
 */
function useLogoMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  messageKey: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: configurationKeys.all })
      toast.success(t(messageKey))
    },
  })
}

export function useUploadPlatformLogo() {
  return useLogoMutation((file: File) => configurationApi.uploadLogo(file), 'organizations.logo.saved')
}

export function useRemovePlatformLogo() {
  return useLogoMutation(() => configurationApi.removeLogo(), 'organizations.logo.removed')
}
