import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { MenuEntryDialog } from './MenuEntryDialog'
import { MenuGroupDialog } from './MenuGroupDialog'
import { MenuSettingsRow } from './MenuSettingsRow'
import { useMenuDraft } from '../hooks/useMenuDraft'
import { menuLabel } from '../types/menu'
import { canMove, groupsOf } from '../types/menuOrder'
import {
  useCreateRoleMenuGroup,
  useDeleteRoleMenuGroup,
  useRoleMenu,
  useUpdateRoleMenu,
} from '@/modules/roles/hooks/useRoleMenu'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'

interface MenuSettingsPanelProps {
  roleId: string
  /** Un rôle système ou plateforme se consulte, il ne se règle pas. */
  editable: boolean
}

/**
 * Réglage du menu d'un rôle.
 *
 * **C'est le seul écran où le menu se règle.** Il se réglait auparavant à deux
 * endroits — l'organisation pour l'ordre et les noms, le rôle pour la seule
 * visibilité — et il fallait savoir lequel ouvrir pour obtenir quoi. Chaque
 * rôle porte désormais son menu entier : quelles entrées il voit, dans quel
 * ordre, sous quel nom, quelle icône et dans quel groupe.
 *
 * Ce qu'il ne choisit pas, c'est la **destination** : route et permission
 * vivent en code, et les laisser saisir permettrait de fabriquer une entrée qui
 * mène à « Page introuvable » ou vers un écran interdit. Renommer « Agences »
 * en « Sites » ou la sortir des « Ressources » ne casse rien ; en réécrire
 * l'adresse, si.
 *
 * Certaines entrées ne se masquent pas — l'administration en fait partie. Un
 * administrateur n'ayant que ce rôle n'aurait plus d'écran pour revenir en
 * arrière ; l'interrupteur est alors désactivé et la raison affichée. Elles
 * restent en revanche déplaçables et renommables : c'est l'accès qu'il faut
 * préserver, pas son rang dans la liste.
 */
export function MenuSettingsPanel({ roleId, editable }: MenuSettingsPanelProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useRoleMenu(roleId)
  const update = useUpdateRoleMenu(roleId)
  const createGroup = useCreateRoleMenuGroup(roleId)
  const deleteGroup = useDeleteRoleMenuGroup(roleId)

  const [editing, setEditing] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<string | null>(null)

  const draft = useMenuDraft(data ?? [])

  if (isPending) return <ListSkeleton rows={6} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const { items, isDirty } = draft
  const doomed = items.find((item) => item.code === deleting) ?? null
  const busy = update.isPending || !editable

  // Créer et supprimer prennent effet tout de suite, alors que le reste
  // s'accumule : on ne range pas une entrée dans un groupe qui n'existe pas
  // encore. Le brouillon est donc abandonné — la liste change de forme sous
  // lui, et le rejouer déplacerait les mauvaises entrées.
  const addGroup = (group: { label: string; icon: string }) => {
    createGroup.mutate(group, {
      onSuccess: () => {
        draft.reset()
        setCreating(false)
      },
    })
  }

  const removeGroup = (code: string) => {
    deleteGroup.mutate(code, {
      onSuccess: () => {
        draft.reset()
        setDeleting(null)
      },
    })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <p className="text-sm text-muted-foreground">{t('menu.settingsHint')}</p>

        {editable ? (
          <Button variant="outline" onClick={() => setCreating(true)} disabled={update.isPending}>
            <Plus className="size-4" aria-hidden />
            {t('menu.newGroup')}
          </Button>
        ) : null}
      </div>

      <ul className="flex flex-col divide-y rounded-lg border">
        {items.map((item) => (
          <MenuSettingsRow
            key={item.code}
            item={item}
            disabled={busy}
            canMoveUp={canMove(items, item.code, -1)}
            canMoveDown={canMove(items, item.code, 1)}
            isEmptyGroup={item.isCustom && !items.some((child) => child.parent === item.code)}
            onMove={(delta) => draft.move(item.code, delta)}
            onCustomize={() => setEditing(item.code)}
            onToggle={() => draft.toggle(item.code)}
            onDelete={() => setDeleting(item.code)}
          />
        ))}
      </ul>

      {editable ? (
        <div className="flex justify-end gap-2">
          {isDirty ? (
            <Button variant="outline" onClick={draft.reset} disabled={update.isPending}>
              {t('common.cancel')}
            </Button>
          ) : null}

          <Button
            onClick={() => update.mutate(draft.payload(), { onSuccess: () => draft.reset() })}
            disabled={!isDirty || update.isPending}
          >
            {update.isPending ? t('common.saving') : t('common.save')}
          </Button>
        </div>
      ) : null}

      <MenuEntryDialog
        item={items.find((item) => item.code === editing) ?? null}
        groups={groupsOf(items)}
        onClose={() => setEditing(null)}
        onSubmit={draft.customize}
      />

      <MenuGroupDialog
        open={creating}
        isPending={createGroup.isPending}
        onOpenChange={setCreating}
        onSubmit={addGroup}
      />

      <ConfirmDialog
        open={doomed !== null}
        onOpenChange={(open) => (open ? undefined : setDeleting(null))}
        title={doomed === null ? '' : t('menu.deleteGroup', { name: menuLabel(doomed, t) })}
        description={t('menu.deleteGroupHint')}
        confirmLabel={t('common.delete')}
        isPending={deleteGroup.isPending}
        variant="destructive"
        onConfirm={() => doomed !== null && removeGroup(doomed.code)}
      />
    </div>
  )
}
