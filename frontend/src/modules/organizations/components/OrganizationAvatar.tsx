import { Building2 } from 'lucide-react'

import { useOrganizationLogo } from '../hooks/useOrganizationLogo'

interface OrganizationAvatarProps {
  organizationId: string
  hasLogo: boolean
  name: string
}

/**
 * Le logo d'une organisation, en petit, dans une liste.
 *
 * **Aucun repli sur le logo de la plateforme.** La barre latérale y retombe —
 * c'est l'identité de l'installation — mais ici il rendrait toutes les
 * organisations identiques, ce qui est exactement le contraire de ce que la
 * colonne sert à faire. Sans logo, l'icône neutre : elle dit « pas de logo »
 * sans prétendre en montrer un.
 *
 * L'image n'est demandée que si `hasLogo` est vrai. C'est ce qui rend la colonne
 * tenable : sur une page de vingt-cinq organisations, seules celles qui en ont
 * un déclenchent une requête, et React Query les garde dix minutes — la même
 * organisation revue sur la fiche ne recharge rien.
 */
export function OrganizationAvatar({ organizationId, hasLogo, name }: OrganizationAvatarProps) {
  const { url } = useOrganizationLogo(organizationId, hasLogo)

  return (
    <span className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-white">
      {url === null ? (
        <Building2 className="size-4 text-muted-foreground" aria-hidden />
      ) : (
        <img src={url} alt={name} className="max-h-full max-w-full object-contain p-1" />
      )}
    </span>
  )
}
