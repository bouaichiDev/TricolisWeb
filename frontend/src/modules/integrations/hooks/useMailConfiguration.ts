import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { mailConfigurationApi } from '../api/mail-configuration.api'
import type { MailConfigurationPayload } from '../types/mailConfiguration'

export const mailConfigurationKeys = {
  all: ['mail-configuration'] as const,
}

/** La boîte réglée, ou `null` quand l'organisation part avec celle du projet. */
export function useMailConfiguration() {
  return useQuery({
    queryKey: mailConfigurationKeys.all,
    queryFn: () => mailConfigurationApi.show(),
  })
}

export function useSaveMailConfiguration() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: MailConfigurationPayload) => mailConfigurationApi.save(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: mailConfigurationKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteMailConfiguration() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: () => mailConfigurationApi.remove(),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: mailConfigurationKeys.all })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * L'essai d'envoi.
 *
 * L'échec n'est pas signalé par un toast générique : le message du serveur
 * distant est ce qui permet de chercher — « 535 authentification refusée » se
 * cherche, « envoi impossible » non. La page l'affiche en entier.
 */
export function useTestMailConfiguration() {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (recipient?: string) => mailConfigurationApi.test(recipient),
    onSuccess: (result) => toast.success(t('mailConfiguration.testSent', { recipient: result.recipient })),
  })
}
