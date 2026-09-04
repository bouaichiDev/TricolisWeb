import { Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { homeRoute } from '@/app/router/navigation'
import { useOrganizationLogo } from '@/modules/organizations/hooks/useOrganizationLogo'
import { useAuth } from '@/shared/hooks/useAuth'
import { usePermissions } from '@/shared/hooks/usePermission'

/**
 * L'en-tête de la barre latérale : le logo de l'organisation active, ou celui
 * de l'application.
 *
 * Un compte plateforme n'agit dans aucune organisation et voit donc toujours
 * l'identité de Tricolis : c'est l'outil qu'il administre, pas un organisme en
 * particulier.
 *
 * **Le logo est posé sur une tuile blanche**, comme dans l'écran qui le règle.
 * La barre latérale est bleu nuit, et un logo est dessiné pour du papier : le
 * poser à même le fond sombre rendrait invisible tout logo à encre foncée, sans
 * qu'on puisse le deviner depuis l'écran de réglage — où l'aperçu est blanc.
 *
 * **Le nom accompagne toujours le logo.** Beaucoup de logos ne sont qu'un
 * symbole, et l'afficher seul laisserait une barre latérale que rien ne nomme ;
 * ceux qui portent déjà leur nom le répètent, ce qui est le moindre des deux
 * défauts.
 */
export function SidebarBrand({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()
  const { isPlatformAdmin } = usePermissions()
  const { membership, organizationId } = useAuth()

  const hasLogo = !isPlatformAdmin && (membership?.hasLogo ?? false)
  const { url } = useOrganizationLogo(organizationId ?? '', hasLogo)

  const showsLogo = url !== null && hasLogo

  return (
    <Link
      to={homeRoute(isPlatformAdmin)}
      onClick={onNavigate}
      className="flex h-16 shrink-0 items-center gap-2.5 px-5"
    >
      {showsLogo ? (
        <span className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white p-1">
          <img src={url} alt="" className="max-h-full max-w-full object-contain" />
        </span>
      ) : (
        <Truck className="size-6 shrink-0 text-sidebar-primary" aria-hidden />
      )}

      <span className="truncate text-lg font-semibold tracking-tight">
        {showsLogo ? (
          membership?.name
        ) : (
          <>
            {t('app.name')} <span className="text-sidebar-primary">V2</span>
          </>
        )}
      </span>
    </Link>
  )
}
