import { Fragment } from 'react'
import { Link, useLocation } from 'react-router-dom'

import { useNavigation } from './navigation'

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i
const ACTIONS: Record<string, string> = { create: 'Nouvelle', edit: 'Modifier' }

/**
 * Fil d'Ariane déduit de l'URL, comme au back-office : le premier segment
 * porte le nom de l'entrée de menu, un identifiant devient « Détail ».
 */
export function Breadcrumbs() {
  const { pathname } = useLocation()
  const navigation = useNavigation()
  const segments = pathname.split('/').filter(Boolean)

  if (segments.length === 0) return null

  const root = navigation.find((item) => item.path === `/${segments[0]}`)?.label ?? segments[0]

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
          const label = ACTIONS[segment] ?? (ULID.test(segment) ? 'Détail' : segment)

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
