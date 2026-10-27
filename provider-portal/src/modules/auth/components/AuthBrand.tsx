import { Truck } from 'lucide-react'

import { usePlatformLogo } from '@/shared/hooks/usePlatformLogo'

/**
 * La marque, en haut des écrans qui précèdent la session.
 *
 * C'est le logo par défaut de l'installation, le même que sur la connexion du
 * back-office. Repli sur l'icône livrée avec l'application s'il n'y en a pas,
 * ou si le serveur ne répond pas : la marque ne doit jamais bloquer l'écran.
 */
export function AuthBrand() {
  const url = usePlatformLogo()

  if (url !== null) {
    return <img src={url} alt="Tricolis" className="h-11 w-auto max-w-56 object-contain" />
  }

  return (
    <span className="flex items-center gap-2.5">
      <Truck className="text-primary size-7" aria-hidden />
      <span className="text-xl font-semibold tracking-tight">
        Tricolis <span className="text-primary">Fournisseur</span>
      </span>
    </span>
  )
}
