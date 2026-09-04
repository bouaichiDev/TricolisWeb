import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { organizationLogoApi } from '../api/organizationLogo.api'
import { organizationKeys } from './organizationKeys'

export const organizationLogoKeys = {
  all: ['organization-logo'] as const,
  detail: (id: string) => [...organizationLogoKeys.all, id] as const,
}

/**
 * URL locale du logo, prête à être posée dans un `img`.
 *
 * L'URL d'objet est révoquée au démontage : sans cela, chaque passage sur la
 * fiche laisserait son image en mémoire jusqu'au rechargement de la page.
 *
 * `enabled` évite l'aller-retour quand l'organisation n'a pas de logo — la
 * fiche le sait déjà par `hasLogo`, et une requête pour un 404 attendu ne
 * renseigne personne.
 */
export function useOrganizationLogo(organizationId: string, enabled: boolean) {
  const query = useQuery({
    queryKey: organizationLogoKeys.detail(organizationId),
    queryFn: () => organizationLogoApi.read(organizationId),
    enabled: enabled && organizationId !== '',
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
 * Déposer ou retirer le logo.
 *
 * Les deux invalident la **fiche** autant que l'image : c'est `hasLogo` qui
 * décide si l'aperçu se demande, et le laisser périmé montrerait un cadre vide
 * après un dépôt réussi.
 */
function useLogoMutation<TVariables>(
  organizationId: string,
  mutationFn: (variables: TVariables) => Promise<unknown>,
  messageKey: string,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: organizationLogoKeys.detail(organizationId) })
      void queryClient.invalidateQueries({ queryKey: organizationKeys.detail(organizationId) })
      toast.success(t(messageKey))
    },
  })
}

export function useUploadOrganizationLogo(organizationId: string) {
  return useLogoMutation(
    organizationId,
    (file: File) => organizationLogoApi.upload(organizationId, file),
    'organizations.logo.saved',
  )
}

export function useRemoveOrganizationLogo(organizationId: string) {
  return useLogoMutation(
    organizationId,
    () => organizationLogoApi.remove(organizationId),
    'organizations.logo.removed',
  )
}
