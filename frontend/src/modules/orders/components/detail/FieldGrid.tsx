import { useTranslation } from 'react-i18next'

import { cn } from '@/shared/utils/cn'

export interface GridField {
  labelKey: string
  value: string | number | null | undefined
}

interface FieldGridProps {
  items: GridField[]
  columns?: 2 | 3 | 4
}

const COLUMNS: Record<2 | 3 | 4, string> = {
  2: 'sm:grid-cols-2',
  3: 'sm:grid-cols-2 lg:grid-cols-3',
  4: 'sm:grid-cols-2 lg:grid-cols-4',
}

/**
 * Grille libellé / valeur, libellé en petites capitales.
 *
 * Reprend la hiérarchie de la maquette : le libellé s'efface, la valeur se lit.
 * Une valeur absente affiche un tiret — une case vide laisse croire à un défaut
 * d'affichage, un tiret dit que la donnée n'existe pas.
 */
export function FieldGrid({ items, columns = 3 }: FieldGridProps) {
  const { t } = useTranslation()

  return (
    <dl className={cn('grid gap-x-5 gap-y-3.5', COLUMNS[columns])}>
      {items.map((item) => {
        const empty = item.value === null || item.value === undefined || item.value === ''

        return (
          <div key={item.labelKey} className="flex min-w-0 flex-col gap-0.5">
            <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
              {t(item.labelKey)}
            </dt>
            <dd className={cn('break-words font-mono text-sm', empty && 'text-muted-foreground')}>
              {empty ? '—' : String(item.value)}
            </dd>
          </div>
        )
      })}
    </dl>
  )
}
