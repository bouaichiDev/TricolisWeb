import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { MenuIconGrid } from './MenuIconGrid'
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

interface MenuGroupDialogProps {
  open: boolean
  isPending: boolean
  onOpenChange: (open: boolean) => void
  onSubmit: (group: { label: string; icon: string }) => void
}

/** Icône de départ : neutre, et le sélecteur est juste en dessous. */
const DEFAULT_ICON = 'Folder'

/**
 * Création d'un groupe de menu.
 *
 * Un nom et une icône, rien d'autre — **un groupe n'ouvre rien**. C'est un
 * titre repliable au-dessus d'entrées qui, elles, gardent leur destination du
 * code. C'est aussi ce qui permet à une organisation d'en créer, là où le reste
 * du menu reste figé : un groupe ne peut mener nulle part, donc pas non plus à
 * « Page introuvable ».
 *
 * L'écran le dit franchement : le groupe naît vide et ne se verra pas encore.
 * Sans cette phrase, l'administrateur le chercherait dans sa barre latérale et
 * croirait la création ratée.
 */
export function MenuGroupDialog({
  open,
  isPending,
  onOpenChange,
  onSubmit,
}: MenuGroupDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        {/* Monté avec l'ouverture : le formulaire repart vide à chaque fois,
            sans avoir à se réinitialiser. */}
        {open ? <MenuGroupForm isPending={isPending} onSubmit={onSubmit} /> : null}
      </DialogContent>
    </Dialog>
  )
}

function MenuGroupForm({
  isPending,
  onSubmit,
}: Pick<MenuGroupDialogProps, 'isPending' | 'onSubmit'>) {
  const { t } = useTranslation()
  const [label, setLabel] = useState('')
  const [icon, setIcon] = useState(DEFAULT_ICON)

  const trimmed = label.trim()

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('menu.newGroup')}</DialogTitle>
        <DialogDescription>{t('menu.newGroupHint')}</DialogDescription>
      </DialogHeader>

      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="menu-group-label">{t('menu.label')}</Label>
          <Input
            id="menu-group-label"
            value={label}
            maxLength={60}
            autoFocus
            onChange={(event) => setLabel(event.target.value)}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label>{t('menu.icon')}</Label>
          <MenuIconGrid value={icon} onChange={setIcon} />
        </div>
      </div>

      <DialogFooter>
        {/* Un groupe sans nom afficherait un titre vide dans la barre latérale,
            impossible à retrouver pour le corriger. */}
        <Button
          onClick={() => onSubmit({ label: trimmed, icon })}
          disabled={trimmed === '' || isPending}
        >
          {isPending ? t('common.saving') : t('common.create')}
        </Button>
      </DialogFooter>
    </>
  )
}
