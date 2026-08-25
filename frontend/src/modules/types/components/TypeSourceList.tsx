import { Lock, Pencil, Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'

import type { TypeSource } from '../types/type'

interface TypeSourceListProps {
  sources: TypeSource[]
  selectedId: string | null
  isLoading: boolean
  onSelect: (source: TypeSource) => void
  onCreate: () => void
  onEdit: (source: TypeSource) => void
  onDelete: (source: TypeSource) => void
}

/**
 * Les sources, à gauche.
 *
 * Le cadenas marque celles auxquelles le schéma se réfère : elles se renomment,
 * mais ni leur code ni leur existence ne se touchent. Le montrer évite d'aller
 * chercher un refus du serveur pour comprendre.
 */
export function TypeSourceList({
  sources,
  selectedId,
  isLoading,
  onSelect,
  onCreate,
  onEdit,
  onDelete,
}: TypeSourceListProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm font-medium">{t('types.sources')}</p>

        <PermissionGuard permission="types.create">
          <Button type="button" variant="outline" size="sm" onClick={onCreate}>
            <Plus className="size-4" aria-hidden />
            {t('types.createSource')}
          </Button>
        </PermissionGuard>
      </div>

      {isLoading ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : sources.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('types.noSource')}</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {sources.map((source) => (
            <li key={source.id}>
              <div
                className={`flex items-center gap-1 rounded-md border px-2 py-1.5 ${
                  source.id === selectedId ? 'border-primary bg-muted' : ''
                }`}
              >
                <button
                  type="button"
                  className="min-w-0 flex-1 text-left"
                  onClick={() => onSelect(source)}
                  aria-current={source.id === selectedId}
                >
                  <span className="flex items-center gap-1.5 truncate text-sm font-medium">
                    {source.isSystem ? (
                      <Lock
                        className="size-3 shrink-0 text-muted-foreground"
                        aria-label={t('types.systemSource')}
                      />
                    ) : null}
                    {source.name}
                  </span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {source.code}
                    {source.itemCount === undefined
                      ? null
                      : ` · ${t('types.itemCount', { count: source.itemCount })}`}
                  </span>
                </button>

                <PermissionGuard permission="types.update">
                  <Button
                    variant="ghost"
                    size="icon"
                    title={t('common.edit')}
                    aria-label={`${t('common.edit')} ${source.name}`}
                    onClick={() => onEdit(source)}
                  >
                    <Pencil className="size-4" aria-hidden />
                  </Button>
                </PermissionGuard>

                {source.isSystem ? null : (
                  <PermissionGuard permission="types.delete">
                    <Button
                      variant="ghost"
                      size="icon"
                      title={t('common.delete')}
                      aria-label={`${t('common.delete')} ${source.name}`}
                      onClick={() => onDelete(source)}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </Button>
                  </PermissionGuard>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
