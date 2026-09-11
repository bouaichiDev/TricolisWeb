import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { usePermission } from '@/shared/hooks/usePermission'

import { useSetStatusDefault, useStatusDefaults } from '../hooks/useStatusDefaults'
import type { StatusDefault } from '../types/status'

/** Valeur désignant « pas de choix » ; Radix refuse une option vide. */
const NO_CHOICE = 'none'

/**
 * Le statut que chaque entité reçoit à sa création.
 *
 * **La liste n'est écrite nulle part ici** : c'est celle du serveur, dérivée des
 * tables qui portent une colonne `status`. Une entité ajoutée au code apparaît
 * sans toucher à cet écran.
 *
 * Chaque choix s'enregistre aussitôt : il n'y a qu'une valeur par ligne, et un
 * bouton « Enregistrer » pour quarante listes ferait perdre le travail au
 * premier clic à côté.
 */
export function StatusDefaultsPanel() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const defaults = useStatusDefaults()
  const canEdit = usePermission('statuses.update')

  const needle = search.trim().toLowerCase()
  const rows = (defaults.data ?? []).filter(
    (row) =>
      needle === '' ||
      row.source.includes(needle) ||
      t(`entities.${row.source}`, { defaultValue: row.source }).toLowerCase().includes(needle),
  )

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('statuses.defaults.description')}</p>

      <div className="sm:max-w-sm">
        <SearchInput
          value={search}
          onChange={setSearch}
          placeholder={t('statuses.defaults.searchPlaceholder')}
        />
      </div>

      {defaults.isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : (
        <ul className="flex flex-col divide-y rounded-lg border px-4">
          {rows.map((row) => (
            <DefaultRow key={row.source} row={row} canEdit={canEdit} />
          ))}
        </ul>
      )}
    </div>
  )
}

function DefaultRow({ row, canEdit }: { row: StatusDefault; canEdit: boolean }) {
  const { t } = useTranslation()
  const save = useSetStatusDefault()

  const fallback =
    row.systemDefault === null
      ? t('statuses.defaults.noSystemDefault')
      : t('statuses.defaults.systemDefault', { code: row.systemDefault })

  return (
    <li className="grid gap-2 py-3 sm:grid-cols-[1fr_minmax(0,22rem)] sm:items-center">
      <span className="flex flex-col">
        <span className="text-sm font-medium">
          {t(`entities.${row.source}`, { defaultValue: row.source })}
        </span>
        <span className="text-xs text-muted-foreground">{row.source}</span>
      </span>

      <AsyncSelect
        label={t('statuses.defaults.atCreation')}
        value={row.statusId ?? NO_CHOICE}
        onChange={(value) =>
          save.mutate({ source: row.source, statusId: value === NO_CHOICE ? null : value })
        }
        options={[
          { value: NO_CHOICE, label: fallback },
          ...row.options
            .filter((option) => option.active)
            .map((option) => ({ value: option.id, label: option.label, hint: option.code })),
        ]}
        disabled={!canEdit || save.isPending}
      />
    </li>
  )
}
