import { Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { homeRoute } from '@/app/router/navigation'
import { useConfiguration, usePlatformLogo } from '@/modules/configuration/hooks/useConfiguration'
import { useOrganizationLogo } from '@/modules/organizations/hooks/useOrganizationLogo'
import { useAuth } from '@/shared/hooks/useAuth'
import { usePermissions } from '@/shared/hooks/usePermission'

/**
 * L'en-tête de la barre latérale.
 *
 * Trois niveaux de repli, dans cet ordre :
 *
 * ```
 * le logo de l'organisation active
 * → le logo par défaut de l'installation
 * → l'icône livrée avec l'application
 * ```
 *
 * Le deuxième est ce que règle la page **Configuration** : l'identité que pose
 * un intégrateur sur l'outil qu'il revend, et que voient les organisations qui
 * n'ont pas encore la leur.
 *
 * Un compte plateforme n'agit dans aucune organisation : il saute le premier
 * niveau. Lui poser le logo d'un organisme laisserait croire qu'il en administre
 * un en particulier.
 *
 * **Le logo est posé sur une tuile blanche**, comme dans l'écran qui le règle.
 * La barre latérale est bleu nuit, et un logo est dessiné pour du papier : le
 * poser à même le fond sombre rendrait invisible tout logo à encre foncée, sans
 * qu'on puisse le deviner depuis l'écran de réglage — où l'aperçu est blanc.
 *
 * **Le logo occupe seul l'en-tête.** Le nom l'accompagnait, ce qui laissait à
 * l'image une vignette de trente-deux pixels : la plupart des logos portent
 * déjà leur nom, et le répéter à côté coûtait la place qui les rendait
 * lisibles. Le nom n'est pas perdu pour autant — il devient le texte alternatif
 * de l'image, donc le nom accessible du lien, ce que lisent un lecteur d'écran
 * et un test. Sous le logo de l'installation, c'est celui de l'application qui
 * le nomme : celui de l'organisation ferait passer l'image pour la sienne.
 *
 * Sans logo, l'icône livrée et le nom de l'application reprennent la place.
 */
export function SidebarBrand({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()
  const { isPlatformAdmin } = usePermissions()
  const { membership, organizationId } = useAuth()

  const hasOrganizationLogo = !isPlatformAdmin && (membership?.hasLogo ?? false)
  const { url: organizationLogo } = useOrganizationLogo(organizationId ?? '', hasOrganizationLogo)

  const { data: configuration } = useConfiguration()
  // Le logo de l'installation n'est demandé que si celui de l'organisation
  // manque : le charger d'avance ferait une requête pour une image qu'on ne
  // montrerait jamais.
  const fallsBack = organizationLogo === null
  const { url: platformLogo } = usePlatformLogo(fallsBack && (configuration?.hasDefaultLogo ?? false))

  const url = organizationLogo ?? platformLogo
  const showsLogo = url !== null

  // Le nom de l'organisation ne nomme que **son** logo. Sous celui de
  // l'installation, il ferait passer l'image pour la sienne.
  const name = organizationLogo !== null ? membership?.name : null

  if (showsLogo) {
    return (
      <Link
        to={homeRoute(isPlatformAdmin)}
        onClick={onNavigate}
        className="flex h-16 shrink-0 items-center px-4"
      >
        <span className="flex h-12 w-full items-center justify-center overflow-hidden rounded-lg bg-white px-3 py-2">
          <img
            src={url}
            alt={name ?? t('app.name')}
            className="max-h-full max-w-full object-contain"
          />
        </span>
      </Link>
    )
  }

  return (
    <Link
      to={homeRoute(isPlatformAdmin)}
      onClick={onNavigate}
      className="flex h-16 shrink-0 items-center gap-2.5 px-5"
    >
      <Truck className="size-6 shrink-0 text-sidebar-primary" aria-hidden />

      <span className="truncate text-lg font-semibold tracking-tight">
        {t('app.name')} <span className="text-sidebar-primary">V2</span>
      </span>
    </Link>
  )
}
