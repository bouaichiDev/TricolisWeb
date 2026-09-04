import { useTranslation } from 'react-i18next'

import type { ChartSeries } from '../../types/dashboard'

/**
 * Des montants, une devise par ligne, **sans barre**.
 *
 * C'est le seul graphe du tableau de bord qui n'en dessine aucune, et c'est le
 * propos. Une longueur proportionnelle affirme une comparaison : deux fois plus
 * long, deux fois plus. Or 5 000 CHF et 5 000 MAD ne se rangent pas sur la même
 * règle, et la barre le prétendrait sans qu'on puisse la contredire.
 *
 * On refuse déjà de sommer les devises dans une valeur unique ; les mettre sur
 * une échelle commune reviendrait à le faire du regard.
 *
 * Le montant est mis en forme dans sa propre devise : c'est `Intl` qui décide
 * du séparateur, du nombre de décimales et de la place du symbole, et cela
 * change d'une monnaie à l'autre.
 */
export function AmountsList({ series }: { series: ChartSeries[] }) {
  const { i18n } = useTranslation()

  return (
    <ul className="flex flex-col gap-3">
      {series.map((entry) => (
        <li key={entry.code} className="flex items-baseline justify-between gap-3">
          <span className="text-sm text-muted-foreground">{entry.code}</span>
          <span className="text-xl font-semibold">
            {formatAmount(entry.value, entry.code, i18n.language)}
          </span>
        </li>
      ))}
    </ul>
  )
}

/**
 * Un code de devise inconnu d'`Intl` lève plutôt que de se dégrader : le total
 * revient alors nu, ce qui reste juste. Faire tomber la carte entière pour un
 * code mal saisi le serait moins.
 */
function formatAmount(value: number, currencyCode: string, language: string): string {
  try {
    return new Intl.NumberFormat(language, { style: 'currency', currency: currencyCode }).format(value)
  } catch {
    return value.toLocaleString(language)
  }
}
