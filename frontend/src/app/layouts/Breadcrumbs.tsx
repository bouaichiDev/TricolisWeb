import { Fragment } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'

import { organizationNavigation, platformNavigation } from '@/app/router/navigation'

/**
 * Fil d'Ariane déduit de l'URL.
 *
 * Le premier segment est traduit à partir de la configuration du menu — une
 * seule source, donc un renommage se fait à un seul endroit. Les segments
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
 * Les deux menus sont parcourus, quelle que soit la portée du compte.
 *
 * Le fil d'Ariane nomme la page atteinte ; borner la recherche au menu du
 * compte laisserait sans libellé une page accessible autrement que par le menu.
 */
function labelForRoot(segment: string): string | null {
  const path = `/${segment}`

  for (const entry of [...organizationNavigation, ...platformNavigation]) {
    if (entry.to === path) return entry.labelKey
    const child = entry.children?.find((item) => item.to === path)
    if (child) return child.labelKey
  }

  return null
}

export function Breadcrumbs() {
  const { t } = useTranslation()
  const { pathname } = useLocation()

  const segments = pathname.split('/').filter(Boolean)
  if (segments.length === 0) return null

  const rootKey = labelForRoot(segments[0])

  return (
    <nav aria-label="fil d’Ariane" className="min-w-0">
      <ol className="flex min-w-0 items-center gap-1.5 text-sm">
        <li className="min-w-0 truncate">
          {segments.length === 1 ? (
            <span className="font-medium">{rootKey ? t(rootKey) : segments[0]}</span>
          ) : (
            <Link to={`/${segments[0]}`} className="text-muted-foreground hover:text-foreground">
              {rootKey ? t(rootKey) : segments[0]}
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
