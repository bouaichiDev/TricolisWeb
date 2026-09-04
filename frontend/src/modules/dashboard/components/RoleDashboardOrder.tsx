import { ChevronDown, ChevronUp, GripVertical } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { RoleDashboardWidget } from '../types/dashboard'
import { Button } from '@/shared/components/ui/button'

interface RoleDashboardOrderProps {
  active: RoleDashboardWidget[]
  disabled: boolean
  onMove: (key: string, delta: number) => void
  onMoveTo: (key: string, targetKey: string) => void
}

/**
 * L'ordre des widgets actifs.
 *
 * Deux façons de déplacer, et il en faut deux. Le **glisser-déposer** est le
 * geste naturel quand on remanie une liste entière ; les **flèches** sont le
 * seul geste possible au clavier, et un écran de configuration qu'on ne peut
 * régler qu'à la souris exclut ceux qui n'en utilisent pas. Elles servent aussi
 * quand on ne déplace qu'une carte d'un rang.
 *
 * Le glisser-déposer est celui du navigateur — `draggable`, `dragover`,
 * `drop` — et non celui d'une bibliothèque. Le projet n'en embarque aucune, et
 * en installer une pour réordonner une liste verticale aurait ajouté une
 * dépendance à ce que quatre gestionnaires d'événements font déjà.
 *
 * Les rangs ne sont pas affichés : ils n'ont de sens que les uns par rapport
 * aux autres, et sont renumérotés en bloc à l'enregistrement. Montrer « 3 »
 * aurait invité à le corriger à la main.
 */
export function RoleDashboardOrder({ active, disabled, onMove, onMoveTo }: RoleDashboardOrderProps) {
  const { t } = useTranslation()
  const [dragged, setDragged] = useState<string | null>(null)

  if (active.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('dashboardSettings.noneActive')}</p>
  }

  return (
    <ul className="flex flex-col divide-y rounded-lg border">
      {active.map((widget, index) => (
        <li
          key={widget.key}
          draggable={!disabled}
          onDragStart={() => setDragged(widget.key)}
          onDragEnd={() => setDragged(null)}
          // Sans `preventDefault`, le navigateur refuse le dépôt : c'est lui
          // qui autorise la zone, et son absence rend la liste inerte sans
          // qu'aucune erreur ne soit levée.
          onDragOver={(event) => event.preventDefault()}
          onDrop={() => {
            if (dragged !== null && dragged !== widget.key) onMoveTo(dragged, widget.key)
            setDragged(null)
          }}
          className="flex items-center gap-3 px-4 py-2"
        >
          <GripVertical className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          <span className="min-w-0 flex-1 truncate text-sm">{t(widget.labelKey)}</span>

          <Button
            variant="ghost"
            size="icon"
            disabled={disabled || index === 0}
            onClick={() => onMove(widget.key, -1)}
            aria-label={t('dashboardSettings.moveUp')}
          >
            <ChevronUp className="size-4" aria-hidden />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            disabled={disabled || index === active.length - 1}
            onClick={() => onMove(widget.key, 1)}
            aria-label={t('dashboardSettings.moveDown')}
          >
            <ChevronDown className="size-4" aria-hidden />
          </Button>
        </li>
      ))}
    </ul>
  )
}
