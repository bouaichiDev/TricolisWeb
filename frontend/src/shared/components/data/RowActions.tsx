import type { LucideIcon } from 'lucide-react'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'

export interface RowAction {
  key: string
  icon: LucideIcon
  /** Libellé lu par les technologies d'assistance, et affiché en infobulle. */
  label: string
  /** Écran que l'action ouvre. Exclusif avec `onClick`. */
  to?: string
  onClick?: () => void
  /** Droit sans lequel l'action n'est pas proposée. */
  permission?: string | string[]
  /** `danger` colore l'action destructrice, et elle seule. */
  tone?: 'default' | 'danger'
  disabled?: boolean
}

/**
 * Les actions d'une ligne, rendues de la même façon partout.
 *
 * **C'est la colonne qui remplace le clic sur la ligne.** Une ligne entière
 * cliquable a un défaut qu'on ne voit qu'à l'usage : elle rend le texte
 * insélectionnable — tenter de copier un numéro de commande ouvrait la fiche —
 * et elle n'annonce rien à un lecteur d'écran, qui ne voit qu'un tableau. Une
 * colonne d'actions dit ce qu'on peut faire, laisse le texte tranquille, et
 * chaque action y est un vrai bouton ou un vrai lien.
 *
 * Ce composant ne décide pas **quelles** actions existent — cela dépend du
 * module — mais il décide de tout le reste : l'ordre de lecture, la taille des
 * icônes, l'alignement à droite, le libellé accessible, la teinte de l'action
 * destructrice et le droit qui la conditionne. Les écrire à la main dans trente
 * écrans donnait trente colonnes légèrement différentes.
 *
 * `stopPropagation` sur chaque action : là où une ligne reste cliquable — un
 * panneau latéral, une sélection — le clic sur « Supprimer » ne doit pas
 * déclencher les deux.
 */
export function RowActions({ actions }: { actions: RowAction[] }) {
  return (
    <span className="flex justify-end gap-0.5">
      {actions.map((action) => (
        <PermissionGuard key={action.key} permission={action.permission}>
          <Button
            asChild={action.to !== undefined && !action.disabled}
            variant="ghost"
            size="icon"
            title={action.label}
            aria-label={action.label}
            disabled={action.disabled}
            className={cn(
              'size-8',
              action.tone === 'danger' && 'text-destructive hover:text-destructive',
            )}
            onClick={
              action.onClick === undefined
                ? undefined
                : (event) => {
                    event.stopPropagation()
                    action.onClick?.()
                  }
            }
          >
            {action.to !== undefined && !action.disabled ? (
              <Link to={action.to} onClick={(event) => event.stopPropagation()}>
                <action.icon className="size-4" aria-hidden />
              </Link>
            ) : (
              <action.icon className="size-4" aria-hidden />
            )}
          </Button>
        </PermissionGuard>
      ))}
    </span>
  )
}
