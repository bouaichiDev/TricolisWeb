import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useMenuCatalogue, useUpdateMenu } from '../hooks/useMenu'
import { menuIcon } from './menuIcons'
import type { MenuItem } from '../types/menu'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'
import { Switch } from '@/shared/components/ui/switch'

/**
 * Réglage du menu de l'organisation.
 *
 * L'organisation choisit **quelles entrées elle voit**, pas leur libellé ni
 * leur destination : route, icône et clé i18n appartiennent au catalogue, en
 * code. Les laisser saisir permettrait d'écrire une route qui n'existe pas, et
 * l'écran afficherait « Page introuvable ».
 *
 * Certaines entrées ne se masquent pas — l'administration en fait partie. Un
 * organisme qui la retirerait n'aurait plus d'écran pour revenir en arrière ;
 * l'interrupteur est alors désactivé et la raison affichée.
 */
export function MenuSettingsPanel() {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useMenuCatalogue()
  const update = useUpdateMenu()

  const [draft, setDraft] = useState<Record<string, boolean> | null>(null)

  if (isPending) return <ListSkeleton rows={6} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const items = data ?? []
  const visibility = draft ?? Object.fromEntries(items.map((item) => [item.code, item.isVisible]))
  const dirty = items.some((item) => visibility[item.code] !== item.isVisible)

  const toggle = (item: MenuItem) => {
    setDraft({ ...visibility, [item.code]: !visibility[item.code] })
  }

  const save = () => {
    update.mutate(
      items.map((item) => ({ code: item.code, isVisible: visibility[item.code] })),
      { onSuccess: () => setDraft(null) },
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('menu.settingsHint')}</p>

      <ul className="flex flex-col divide-y rounded-lg border">
        {items.map((item) => {
          const Icon = menuIcon(item.icon)
          const isChild = item.parent !== null

          return (
            <li
              key={item.code}
              className={`flex items-center justify-between gap-4 p-3 ${isChild ? 'pl-10' : ''}`}
            >
              <div className="flex min-w-0 items-center gap-3">
                <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{t(item.labelKey)}</p>
                  {item.canHide ? null : (
                    <p className="text-xs text-muted-foreground">{t('menu.alwaysVisible')}</p>
                  )}
                </div>
              </div>

              <Switch
                checked={visibility[item.code]}
                disabled={!item.canHide || update.isPending}
                onCheckedChange={() => toggle(item)}
                aria-label={t(item.labelKey)}
              />
            </li>
          )
        })}
      </ul>

      <div className="flex justify-end gap-2">
        {dirty ? (
          <Button variant="outline" onClick={() => setDraft(null)} disabled={update.isPending}>
            {t('common.cancel')}
          </Button>
        ) : null}

        <Button onClick={save} disabled={!dirty || update.isPending}>
          {update.isPending ? t('common.saving') : t('common.save')}
        </Button>
      </div>
    </div>
  )
}
