import { Truck } from 'lucide-react'
import { useState } from 'react'

import { usePlatformLogo } from '@/shared/hooks/usePlatformLogo'

import { publicTrackingApi } from '../api/publicTrackingApi'

interface PublicTrackingBrandProps {
  token: string
  organization: { name: string; hasLogo: boolean } | null
}

/**
 * La marque du transporteur (§30) : son logo et son nom, sinon le logo par
 * défaut de l'installation — les deux systèmes existants, aucun troisième.
 */
export function PublicTrackingBrand({ token, organization }: PublicTrackingBrandProps) {
  const platformLogo = usePlatformLogo()
  const [broken, setBroken] = useState(false)
  const logo = organization?.hasLogo && !broken ? publicTrackingApi.logoUrl(token) : platformLogo

  return (
    <header className="flex items-center gap-3">
      {logo ? (
        <img src={logo} alt={organization?.name ?? 'Transporteur'} referrerPolicy="no-referrer" className="h-10 max-w-40 object-contain" onError={() => setBroken(true)} />
      ) : (
        <Truck className="text-primary size-8" aria-hidden />
      )}
      {organization && <span className="text-lg font-semibold">{organization.name}</span>}
    </header>
  )
}
