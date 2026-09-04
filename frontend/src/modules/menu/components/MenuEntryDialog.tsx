import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { MenuIconGrid } from './MenuIconGrid'
import { MenuParentSelect } from './MenuParentSelect'
import type { MenuEntryPatch } from '../hooks/useMenuDraft'
import type { MenuItem } from '../types/menu'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface MenuEntryDialogProps {
  /** Entrée en cours de personnalisation ; `null` ferme la boîte. */
  item: MenuItem | null
  /** Groupes d'accueil possibles, dans l'ordre courant du menu. */
  groups: MenuItem[]
  onClose: () => void
  onSubmit: (code: string, changes: MenuEntryPatch) => void
}

/**
 * Renommer une entrée, lui donner une autre icône, la changer de groupe.
 *
 * Ces réglages sont dans une boîte de dialogue, et non dans la ligne : la liste
 * compte une quarantaine d'entrées, et y poser autant de champs de saisie la
 * rendrait illisible pour le geste le plus courant — vérifier d'un coup d'œil
 * ce qui est visible et dans quel ordre.
 *
 * Le rattachement est **le même geste dans les deux sens** : sortir une entrée
 * d'un sous-menu pour en faire un menu, ou l'inverse, revient à changer son
 * groupe. Un groupe, lui, n'a pas de niveau où descendre : le champ ne lui est
 * pas proposé.
 *
 * **Rien n'est envoyé ici.** La boîte remplit le brouillon ; l'enregistrement
 * reste un geste unique, en bas de l'écran. Sinon renommer une entrée écrirait
 * en base un ordre encore en cours de réarrangement.
 */
export function MenuEntryDialog({ item, groups, onClose, onSubmit }: MenuEntryDialogProps) {
  return (
    <Dialog open={item !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="sm:max-w-md">
        {/* Monté avec l'entrée, démonté avec elle : le formulaire part donc de
            ses valeurs sans avoir à les resynchroniser à l'ouverture. */}
        {item === null ? null : (
          <MenuEntryForm
            key={item.code}
            item={item}
            groups={groups}
            onClose={onClose}
            onSubmit={onSubmit}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

/**
 * Le champ vide affiche le libellé du catalogue en indication : c'est ce qui
 * rend le retour au défaut évident sans ajouter un bouton pour lui seul.
 */
function MenuEntryForm({
  item,
  groups,
  onClose,
  onSubmit,
}: MenuEntryDialogProps & { item: MenuItem }) {
  const { t } = useTranslation()
  const [label, setLabel] = useState(item.label ?? '')
  const [icon, setIcon] = useState(item.icon)
  const [parent, setParent] = useState(item.parent)

  const submit = () => {
    // `parent` n'est transmis que pour une entrée qui peut bouger : l'envoyer
    // pour un groupe demanderait un déplacement que l'arbre refuse, et la
    // liste se reconstruirait pour rien.
    onSubmit(item.code, item.canReparent ? { label, icon, parent } : { label, icon })
    onClose()
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('menu.customize')}</DialogTitle>
        <DialogDescription>{t('menu.customizeHint')}</DialogDescription>
      </DialogHeader>

      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="menu-entry-label">{t('menu.label')}</Label>
          <Input
            id="menu-entry-label"
            value={label}
            maxLength={60}
            placeholder={t(item.labelKey)}
            onChange={(event) => setLabel(event.target.value)}
          />
          <p className="text-xs text-muted-foreground">{t('menu.labelHint')}</p>
        </div>

        {item.canReparent ? (
          <div className="flex flex-col gap-2">
            <Label htmlFor="menu-entry-parent">{t('menu.parent')}</Label>
            <MenuParentSelect
              groups={groups.filter((group) => group.code !== item.code)}
              value={parent}
              onChange={setParent}
            />
            <p className="text-xs text-muted-foreground">{t('menu.parentHint')}</p>
          </div>
        ) : null}

        <div className="flex flex-col gap-2">
          <Label>{t('menu.icon')}</Label>
          <MenuIconGrid value={icon} onChange={setIcon} />
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={onClose}>
          {t('common.cancel')}
        </Button>
        <Button onClick={submit}>{t('common.confirm')}</Button>
      </DialogFooter>
    </>
  )
}
