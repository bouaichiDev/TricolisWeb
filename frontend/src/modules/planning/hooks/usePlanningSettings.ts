import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { organizationsApi } from '@/modules/organizations/api/organizations.api'
import { useAuth } from '@/shared/hooks/useAuth'

/** Réglages de planification, tels qu'ils vivent dans `organizations.settings`. */
export interface PlanningSettings {
  planning?: {
    /** Codes des services reconnus comme chargement. */
    loadingServiceCodes?: string[]
    /**
     * Crée le chargement manquant d'une commande au moment de la planifier.
     *
     * Absent vaut « non » : une organisation qui n'a jamais réglé la question
     * ne doit pas voir des prestations apparaître dans ses commandes.
     */
    autoCreateLoadingService?: boolean
  }
}

export const planningSettingKeys = {
  all: ['planning-settings'] as const,
  organization: (id: string) => [...planningSettingKeys.all, id] as const,
}

/**
 * L'organisation active, pour ses réglages.
 *
 * L'identifiant vient de l'appartenance résolue, jamais de l'URL : un
 * identifiant saisi à la main n'a aucune prise.
 */
export function useOrganizationSettings() {
  const { organizationId } = useAuth()

  return useQuery({
    queryKey: planningSettingKeys.organization(organizationId ?? ''),
    queryFn: async () => {
      const organization = await organizationsApi.get(organizationId as string)

      return organization as typeof organization & { settings: PlanningSettings | null }
    },
    enabled: organizationId !== null,
  })
}

/**
 * Enregistre les codes des services de chargement.
 *
 * Les autres réglages sont recopiés : un `PATCH` remplace `settings` en entier,
 * et n'envoyer que la planification effacerait tout le reste.
 */
export function useUpdateLoadingServiceCodes() {
  const queryClient = useQueryClient()
  const { organizationId } = useAuth()
  const { t } = useTranslation()
  const current = useOrganizationSettings()

  return useMutation({
    mutationFn: (codes: string[]) => {
      const settings = { ...(current.data?.settings ?? {}) }

      return organizationsApi.update(organizationId as string, {
        settings: { ...settings, planning: { ...settings.planning, loadingServiceCodes: codes } },
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planningSettingKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['organizations'] })
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Active ou coupe la création automatique du chargement.
 *
 * Enregistrée au basculement, sans bouton : c'est un interrupteur, et une
 * option qu'on croit active parce qu'on l'a cochée sans enregistrer est pire
 * que pas d'option du tout.
 */
export function useUpdateAutoCreateLoading() {
  const queryClient = useQueryClient()
  const { organizationId } = useAuth()
  const { t } = useTranslation()
  const current = useOrganizationSettings()

  return useMutation({
    mutationFn: (enabled: boolean) => {
      // `PATCH` remplace `settings` en entier : n'envoyer que cette option
      // effacerait les codes de chargement et tout le reste.
      const settings = { ...(current.data?.settings ?? {}) }

      return organizationsApi.update(organizationId as string, {
        settings: {
          ...settings,
          planning: { ...settings.planning, autoCreateLoadingService: enabled },
        },
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: planningSettingKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['organizations'] })
      toast.success(t('toast.updated'))
    },
  })
}
