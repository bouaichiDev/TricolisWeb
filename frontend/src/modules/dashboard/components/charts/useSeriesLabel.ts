import { useTranslation } from 'react-i18next'

import type { ChartData } from '../../types/dashboard'
import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'

/**
 * Comment nommer un code de série, et dans quel ordre les ranger.
 *
 * Deux provenances, exclusives, et le serveur dit laquelle s'applique :
 *
 * - `source` — l'entité au **référentiel des statuts**. Le libellé vient de ce
 *   qu'un administrateur y a réglé, et le rang qu'il a choisi donne l'ordre du
 *   cycle de vie. C'est le cas des commandes et des tournées ;
 * - `labels` — un **espace de traduction** livré : `orderSources`,
 *   `communicationChannels`. Ces codes viennent d'énumérations PHP, que
 *   personne ne renomme et que le référentiel ne connaît pas.
 *
 * Les deux absents, le code se nomme lui-même — une devise, par exemple.
 *
 * Ce partage vit ici et non dans chaque composant : la barre de composition et
 * le camembert posent exactement la même question, et deux réponses auraient
 * fini par diverger sur un cas.
 */
export function useSeriesLabel(data: ChartData | null) {
  const { t } = useTranslation()
  const { statuses } = useStatusOptions(data?.source ?? '')

  const labelOfCode = (code: string): string => {
    if (data?.source) {
      return statuses.find((status) => status.code === code)?.label ?? code
    }

    // `defaultValue` plutôt qu'une clé rendue brute : un canal ajouté au code
    // sans sa traduction s'affiche sous son code, ce qui reste lisible —
    // `communicationChannels.telex` ne le serait pas.
    if (data?.labels) {
      return t(`${data.labels}.${code}`, { defaultValue: code })
    }

    return code
  }

  return {
    labelOfCode,
    /** Ordre du cycle de vie, vide quand il n'y en a pas. */
    referentialOrder: statuses.map((status) => status.code),
  }
}
