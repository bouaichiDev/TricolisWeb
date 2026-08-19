import { useTranslation } from 'react-i18next'

export interface SummaryItem {
  labelKey: string
  value: string | number | null | undefined
}

/**
 * Les quelques valeurs qu'on lit vraiment, sur une ligne.
 *
 * Chaque élément — ligne, colis, service — en montre une poignée : ce qui
 * l'identifie et ce qui le mesure. Tout le reste vit sous le repli.
 *
 * Une valeur absente affiche un tiret plutôt que rien : une case vide laisse
 * croire à un défaut d'affichage, un tiret dit que la donnée n'existe pas.
 */
export function SummaryRow({ items }: { items: SummaryItem[] }) {
  const { t } = useTranslation()

  return (
    <dl className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
      {items.map((item) => (
        <div key={item.labelKey} className="flex flex-col">
          <dt className="text-xs uppercase tracking-wide text-muted-foreground">
            {t(item.labelKey)}
          </dt>
          <dd className={item.value === null || item.value === undefined ? 'text-muted-foreground' : ''}>
            {item.value === null || item.value === undefined || item.value === ''
              ? '—'
              : String(item.value)}
          </dd>
        </div>
      ))}
    </dl>
  )
}
