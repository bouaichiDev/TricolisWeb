import type { ReactNode } from 'react'

interface DetailFieldProps {
  label: string
  children?: ReactNode
}

/**
 * Couple libelle / valeur d'une fiche.
 *
 * Une valeur absente affiche un tiret plutot que rien : une ligne vide laisse
 * croire a un defaut d'affichage, un tiret dit que la donnee n'existe pas.
 */
export function DetailField({ label, children }: DetailFieldProps) {
  const empty = children === null || children === undefined || children === ''

  return (
    <div className="flex flex-col gap-0.5 py-2">
      <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className={empty ? 'text-sm text-muted-foreground' : 'text-sm'}>
        {empty ? '—' : children}
      </dd>
    </div>
  )
}
