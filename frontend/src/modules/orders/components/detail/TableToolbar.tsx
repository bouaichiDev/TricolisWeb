import type { ReactNode } from 'react'

interface TableToolbarProps {
  title: string
  description?: string
  children?: ReactNode
}

/**
 * Bandeau au-dessus d'un tableau : ce qu'il contient, et ce qu'on peut y faire.
 *
 * Le titre et la description tiennent dans le cadre du tableau plutôt que
 * au-dessus : la maquette fait du tableau une carte à part entière, avec sa
 * barre d'outils, ses lignes et son pied.
 */
export function TableToolbar({ title, description, children }: TableToolbarProps) {
  return (
    <div className="flex flex-wrap items-center gap-3 border-b px-3.5 py-2.5">
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{title}</p>
        {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {children}
    </div>
  )
}
