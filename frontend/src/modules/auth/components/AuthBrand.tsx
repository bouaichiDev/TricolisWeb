import { Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { useConfiguration, usePlatformLogo } from '@/modules/configuration/hooks/useConfiguration'

/**
 * La marque, en haut des écrans qui précèdent la session.
 *
 * **C'est le logo par défaut de l'installation**, celui que règle la page
 * Configuration — le même que voit tout organisme sans logo propre dans sa
 * barre latérale. Un intégrateur qui revend l'outil le pose une fois, et il
 * apparaît dès l'écran de connexion, là où on regarde en premier à qui l'on
 * confie son mot de passe.
 *
 * Le logo de l'organisation, lui, n'a pas sa place ici : personne n'est encore
 * connecté, et rien ne dit à quelle organisation appartient celui qui tape son
 * adresse.
 *
 * Repli sur l'icône livrée avec l'application quand aucun logo n'est déposé.
 * L'image est posée sur fond clair, comme dans l'écran qui la règle : un logo
 * est dessiné pour du papier.
 */
export function AuthBrand() {
  const { t } = useTranslation()

  const { data: configuration } = useConfiguration()
  const { url } = usePlatformLogo(configuration?.hasDefaultLogo ?? false)

  if (url !== null) {
    return <img src={url} alt={t('app.name')} className="h-11 w-auto max-w-56 object-contain" />
  }

  return (
    <span className="flex items-center gap-2.5">
      <Truck className="size-7 text-primary" aria-hidden />
      <span className="text-xl font-semibold tracking-tight">
        {t('app.name')} <span className="text-primary">V2</span>
      </span>
    </span>
  )
}
