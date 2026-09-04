import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget, ListData, ListItem } from '../../types/dashboard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

/**
 * Quelques lignes, et un lien vers la liste complète.
 *
 * Six au plus, et c'est le serveur qui borne : la limite est dans la requête,
 * pas ici. Charger cent lignes pour en afficher six coûterait autant que de les
 * afficher toutes, et le tableau de bord n'est pas un écran de consultation —
 * il indique où regarder ; la liste sait filtrer, trier et paginer.
 *
 * **La carte n'est pas cliquable, ses lignes le sont.** C'est la seule des cinq
 * dans ce cas : un lien qui envelopperait des lignes elles-mêmes liées
 * imbriquerait deux ancres, ce que le HTML n'admet pas et que chaque navigateur
 * démêle à sa façon. Le lien vers la liste entière est donc en pied de carte —
 * ce qui est aussi ce qu'on y cherche du regard.
 */
export function ListWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const items = (widget.data as ListData | null)?.items ?? []

  return (
    <WidgetCard title={t(widget.labelKey)}>
      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('dashboard.widgetEmpty')}</p>
      ) : (
        <ul className="flex flex-col divide-y">
          {items.map((item) => (
            <ListWidgetRow key={item.id} item={item} />
          ))}
        </ul>
      )}

      {widget.route ? (
        <Link
          to={widget.route}
          className="mt-auto pt-1 text-sm font-medium text-primary hover:underline"
        >
          {t('dashboard.seeAll')}
        </Link>
      ) : null}
    </WidgetCard>
  )
}

function ListWidgetRow({ item }: { item: ListItem }) {
  const content = (
    <>
      <span className="flex min-w-0 flex-col">
        <span className="truncate text-sm font-medium">{item.title ?? '—'}</span>
        {item.subtitle ? (
          <span className="truncate text-xs text-muted-foreground">{item.subtitle}</span>
        ) : null}
      </span>
      {item.status ? <StatusBadge status={item.status} source={item.statusSource ?? undefined} /> : null}
    </>
  )

  if (!item.route) {
    return <li className="flex items-center justify-between gap-3 py-2">{content}</li>
  }

  return (
    <li className="py-2">
      <Link to={item.route} className="flex items-center justify-between gap-3 hover:underline">
        {content}
      </Link>
    </li>
  )
}
