import { useQuery } from '@tanstack/react-query'

import { platformApi } from '@/shared/api/platform'

import { useObjectUrl } from './useObjectUrl'

/**
 * URL locale du logo par défaut de l'installation, ou `null`.
 *
 * Le même que celui du back-office. Les requêtes sont partagées (même clé)
 * entre l'écran de connexion et la barre latérale.
 */
export function usePlatformLogo(): string | null {
  const configuration = useQuery({
    queryKey: ['platform', 'configuration'],
    queryFn: platformApi.configuration,
    staleTime: 10 * 60 * 1000,
    retry: false,
  })
  const logo = useQuery({
    queryKey: ['platform', 'logo'],
    queryFn: platformApi.logo,
    enabled: configuration.data?.hasDefaultLogo === true,
    staleTime: 10 * 60 * 1000,
    retry: false,
  })
  return useObjectUrl(logo.data)
}
