import { ChevronDown, ChevronUp, Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { menuIcon } from './menuIcons'
import { menuLabel, type MenuItem } from '../types/menu'
import { Button } from '@/shared/components/ui/button'
import { Switch } from '@/shared/components/ui/switch'

interface MenuSettingsRowProps {
  item: MenuItem
  disabled: boolean
  canMoveUp: boolean
  canMoveDown: boolean
  /** Vrai pour un groupe créé qui n'a encore rien à ouvrir. */
  isEmptyGroup: boolean
  onMove: (delta: -1 | 1) => void
  onCustomize: () => void
  onToggle: () => void
  onDelete: () => void
}

/**
 * Une entrée dans l'écran de réglage du menu.
 *
 * Les flèches déplacent l'entrée **parmi ses frères** seulement, et se
 * désactivent aux extrémités de la fratrie. Un enfant ne quitte donc jamais son
 * groupe : `parent` vient du catalogue, en code, et permettre le geste ne
 * changerait rien au résultat — l'écran mentirait.
 *
 * Le libellé affiché est celui que l'organisation verra, pas la traduction
 * livrée : c'est le seul moyen de relire son propre réglage.
 *
 * La corbeille n'apparaît que sur un groupe que l'organisation s'est créé.
 * Un groupe livré ne lui appartient pas : elle le masque, elle ne le supprime
 * pas.
 */
export function MenuSettingsRow({
  item,
  disabled,
  canMoveUp,
  canMoveDown,
  isEmptyGroup,
  onMove,
  onCustomize,
  onToggle,
  onDelete,
}: MenuSettingsRowProps) {
  const { t } = useTranslation()
  const Icon = menuIcon(item.icon)
  const name = menuLabel(item, t)

  return (
    <li
      className={`flex items-center justify-between gap-2 p-3 ${item.parent === null ? '' : 'pl-10'}`}
    >
      <div className="flex min-w-0 items-center gap-3">
        <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
        <div className="min-w-0">
          <p className="truncate text-sm font-medium">{name}</p>
          {item.canHide ? null : (
            <p className="text-xs text-muted-foreground">{t('menu.alwaysVisible')}</p>
          )}
          {/* Un groupe vide ne s'affiche pas : il montrerait un titre qui
              n'ouvre rien. Le dire ici évite de croire la création ratée. */}
          {isEmptyGroup ? (
            <p className="text-xs text-muted-foreground">{t('menu.emptyGroup')}</p>
          ) : null}
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          disabled={disabled || !canMoveUp}
          onClick={() => onMove(-1)}
          aria-label={t('menu.moveUp', { name })}
        >
          <ChevronUp className="size-4" aria-hidden />
        </Button>

        <Button
          variant="ghost"
          size="icon"
          disabled={disabled || !canMoveDown}
          onClick={() => onMove(1)}
          aria-label={t('menu.moveDown', { name })}
        >
          <ChevronDown className="size-4" aria-hidden />
        </Button>

        <Button
          variant="ghost"
          size="icon"
          disabled={disabled}
          onClick={onCustomize}
          aria-label={t('menu.customizeEntry', { name })}
        >
          <Pencil className="size-4" aria-hidden />
        </Button>

        {/* Seul un groupe créé se supprime : un groupe livré n'appartient pas
            à l'organisation, elle le masque. */}
        {item.isCustom ? (
          <Button
            variant="ghost"
            size="icon"
            disabled={disabled}
            onClick={onDelete}
            aria-label={t('menu.deleteGroup', { name })}
          >
            <Trash2 className="size-4" aria-hidden />
          </Button>
        ) : null}

        <Switch
          checked={item.isVisible}
          disabled={!item.canHide || disabled}
          onCheckedChange={onToggle}
          aria-label={name}
          className="ml-2"
        />
      </div>
    </li>
  )
}
