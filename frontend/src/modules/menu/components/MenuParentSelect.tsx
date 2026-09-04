import { useTranslation } from 'react-i18next'

import { menuIcon } from './menuIcons'
import { menuLabel, type MenuItem } from '../types/menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

/** Valeur du choix « premier niveau » : `null` ne traverse pas un `Select`. */
const ROOT = '__root__'

interface MenuParentSelectProps {
  groups: MenuItem[]
  value: string | null
  onChange: (parent: string | null) => void
}

/**
 * Groupe d'accueil d'une entrée, ou le premier niveau.
 *
 * C'est ce qui permet de **sortir une entrée d'un sous-menu pour en faire un
 * menu**, et l'inverse. Les deux gestes se font ici parce qu'ils sont le même :
 * changer le parent. Deux boutons « promouvoir » et « ranger dans… » auraient
 * demandé à l'administrateur de savoir dans quel sens il va avant de savoir où
 * il arrive.
 *
 * Seuls les **groupes** sont proposés — et seulement pour une entrée qui n'en
 * est pas un. La barre latérale rend deux niveaux : un groupe rangé dans un
 * groupe placerait ses entrées au troisième, où rien ne les affiche.
 *
 * `null` ne peut pas transiter par un `Select`, dont la valeur est une chaîne :
 * le premier niveau prend donc un code réservé, traduit aux deux bords. Une
 * chaîne vide aurait été refusée par Radix, qui la réserve à « aucune valeur ».
 */
export function MenuParentSelect({ groups, value, onChange }: MenuParentSelectProps) {
  const { t } = useTranslation()

  return (
    <Select
      value={value ?? ROOT}
      onValueChange={(next) => onChange(next === ROOT ? null : next)}
    >
      <SelectTrigger id="menu-entry-parent">
        <SelectValue />
      </SelectTrigger>

      <SelectContent>
        <SelectItem value={ROOT}>{t('menu.parentRoot')}</SelectItem>

        {groups.map((group) => {
          const Icon = menuIcon(group.icon)

          return (
            <SelectItem key={group.code} value={group.code}>
              <span className="flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" aria-hidden />
                {menuLabel(group, t)}
              </span>
            </SelectItem>
          )
        })}
      </SelectContent>
    </Select>
  )
}
