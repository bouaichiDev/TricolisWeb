import { useMemo, useState } from 'react'

import type { RoleDashboardWidget, RoleDashboardWidgetSelection } from '../types/dashboard'

/**
 * Brouillon de configuration : **un seul enregistrement** pour tous les gestes.
 *
 * Cocher, décocher, monter, descendre : rien ne part au fil des clics. Composer
 * un tableau de bord demande une dizaine de gestes, et les envoyer un par un
 * aurait produit une dizaine d'écritures — donc une dizaine de lignes de
 * journal pour une seule décision, et un état à moitié enregistré si le réseau
 * lâche au milieu.
 *
 * L'état tenu est la **liste ordonnée des clés actives**, et non une copie des
 * widgets. Le rang n'existe que les uns par rapport aux autres : le déduire de
 * la position dans le tableau au moment d'enregistrer évite de renuméroter à
 * chaque déplacement, et de laisser des trous que personne ne voit.
 */
export interface RoleDashboardDraft {
  /** Widgets actifs, dans l'ordre où ils s'afficheront. */
  active: RoleDashboardWidget[]
  isDirty: boolean
  isEnabled: (key: string) => boolean
  toggle: (widget: RoleDashboardWidget) => void
  move: (key: string, delta: number) => void
  /** Déplace `key` à l'emplacement qu'occupe `targetKey`. */
  moveTo: (key: string, targetKey: string) => void
  reset: () => void
  payload: () => RoleDashboardWidgetSelection[]
}

/**
 * Les clés actives du serveur, dans l'ordre qu'il a rendu.
 *
 * La réponse est déjà triée par rang puis par clé : la relire suffit, et
 * retrier ici aurait risqué de diverger du tri que le tableau de bord applique
 * réellement.
 */
function enabledKeysOf(widgets: RoleDashboardWidget[]): string[] {
  return widgets.filter((widget) => widget.isEnabled).map((widget) => widget.key)
}

export function useRoleDashboardDraft(widgets: RoleDashboardWidget[]): RoleDashboardDraft {
  const serverKeys = useMemo(() => enabledKeysOf(widgets), [widgets])
  const [keys, setKeys] = useState<string[] | null>(null)

  const current = keys ?? serverKeys
  const byKey = useMemo(() => new Map(widgets.map((widget) => [widget.key, widget])), [widgets])

  const active = useMemo(
    () => current.map((key) => byKey.get(key)).filter((widget): widget is RoleDashboardWidget => widget !== undefined),
    [current, byKey],
  )

  const isDirty = keys !== null && (keys.length !== serverKeys.length || keys.some((key, index) => key !== serverKeys[index]))

  return {
    active,
    isDirty,
    isEnabled: (key) => current.includes(key),

    /**
     * Un widget dont le rôle n'a pas la permission ne s'active pas.
     *
     * Le serveur le refuserait de toute façon — la validation le dit en clair.
     * Le refuser ici évite un aller-retour pour une erreur que l'interrupteur
     * désactivé annonçait déjà.
     */
    toggle: (widget) => {
      if (!widget.availableForRole) return

      setKeys(
        current.includes(widget.key)
          ? current.filter((key) => key !== widget.key)
          : [...current, widget.key],
      )
    },

    move: (key, delta) => {
      const index = current.indexOf(key)
      const target = index + delta

      if (index === -1 || target < 0 || target >= current.length) return

      const next = [...current]
      next.splice(target, 0, ...next.splice(index, 1))
      setKeys(next)
    },

    moveTo: (key, targetKey) => {
      const from = current.indexOf(key)
      const to = current.indexOf(targetKey)

      if (from === -1 || to === -1 || from === to) return

      const next = [...current]
      next.splice(to, 0, ...next.splice(from, 1))
      setKeys(next)
    },

    reset: () => setKeys(null),

    /**
     * Les rangs sont renumérotés en bloc sur l'ordre affiché, sans trou : ils
     * n'ont de sens que les uns par rapport aux autres, et conserver ceux du
     * catalogue aurait fait remonter un widget qu'on venait de descendre.
     */
    payload: () => current.map((key, index) => ({ key, position: index + 1 })),
  }
}
