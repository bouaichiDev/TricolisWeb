import { Fragment } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'

import { organizationNavigation, platformNavigation } from '@/app/router/navigation'
import { useMenu } from '@/modules/menu/hooks/useMenu'
import { menuLabel, type MenuItem } from '@/modules/menu/types/menu'

/**
 * Fil d'Ariane déduit de l'URL.
 *
 * Le premier segment est nommé d'après le menu **effectif** : si l'organisation
 * a renommé « Agences » en « Sites », le fil d'Ariane le dit aussi. Le lire
 * seulement dans le catalogue livré donnerait deux noms pour un même écran, et
 * l'organisation croirait son réglage à moitié pris en compte. Les segments
 * suivants sont affichés tels quels lorsqu'ils sont lisibles, et remplacés par
 * un libellé générique lorsqu'il s'agit d'un identifiant : afficher un ULID de
 * 26 caractères dans un fil d'Ariane n'aide personne.
 */
const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i

const ACTION_LABELS: Record<string, string> = {
  create: 'common.create',
  edit: 'common.edit',
}

/**
 * Nom du premier segment : celui du menu reçu, sinon celui du menu livré.
 *
 * Le repli est nécessaire — le menu de l'appelant ne porte que les entrées
 * qu'il a le droit d'ouvrir, et une page atteinte autrement resterait sans
 * nom. Les deux listes livrées sont parcourues quelle que soit la portée du
 * compte, pour la même raison.
 */
function labelForRoot(segment: string, menu: MenuItem[], t: (key: string) => string): string {
  const path = `/${segment}`

  const chosen = menu.find((item) => item.route === path)
  if (chosen !== undefined) return menuLabel(chosen, t)

  for (const entry of [...organizationNavigation, ...platformNavigation]) {
    if (entry.to === path) return t(entry.labelKey)
    const child = entry.children?.find((item) => item.to === path)
    if (child) return t(child.labelKey)
  }

  return segment
}

export function Breadcrumbs() {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  // La requête est partagée avec la barre latérale et longuement mise en
  // cache : la lire ici ne déclenche pas d'appel supplémentaire.
  const { data } = useMenu()

  const segments = pathname.split('/').filter(Boolean)
  if (segments.length === 0) return null

  const root = labelForRoot(segments[0], data ?? [], t)

  return (
    <nav aria-label="fil d’Ariane" className="min-w-0">
      <ol className="flex min-w-0 items-center gap-1.5 text-sm">
        <li className="min-w-0 truncate">
          {segments.length === 1 ? (
            <span className="font-medium">{root}</span>
          ) : (
            <Link to={`/${segments[0]}`} className="text-muted-foreground hover:text-foreground">
              {root}
            </Link>
          )}
        </li>

        {segments.slice(1).map((segment, index) => {
          const isLast = index === segments.length - 2
          const actionKey = ACTION_LABELS[segment]
          const label = actionKey ? t(actionKey) : ULID.test(segment) ? t('common.detail') : segment

          return (
            <Fragment key={`${segment}-${index}`}>
              <li aria-hidden className="text-muted-foreground/50">
                /
              </li>
              <li className="min-w-0 truncate">
                <span className={isLast ? 'font-medium' : 'text-muted-foreground'}>{label}</span>
              </li>
            </Fragment>
          )
        })}
      </ol>
    </nav>
  )
}
