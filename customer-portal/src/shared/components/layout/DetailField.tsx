import type { ReactNode } from 'react'

/** Un couple libellé / valeur ; une valeur vide s'affiche en tiret. */
export function DetailField({ label, children }: { label: string; children?: ReactNode }) {
  const empty = children === null || children === undefined || children === ''

  return (
    <div className="flex flex-col gap-0.5 py-2">
      <dt className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</dt>
      <dd className={empty ? 'text-muted-foreground text-sm' : 'text-sm'}>{empty ? '—' : children}</dd>
    </div>
  )
}
