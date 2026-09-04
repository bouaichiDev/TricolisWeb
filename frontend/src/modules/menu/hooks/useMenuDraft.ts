import { useState } from 'react'

import type { RoleMenuUpdateItem } from '@/modules/roles/api/roleMenu.api'
import type { MenuItem } from '../types/menu'
import { flattenTree, moveWithinSiblings, reparent, withPositions } from '../types/menuOrder'

/**
 * Ce qu'une entrée peut recevoir de personnalisé.
 *
 * `parent` est **absent** quand le rattachement ne change pas, et vaut `null`
 * quand l'entrée remonte au premier niveau. Les deux se ressemblent en JSON et
 * ne veulent pas dire la même chose : confondre « je n'y touche pas » et « à la
 * racine » sortirait de son groupe toute entrée qu'on renomme.
 */
export interface MenuEntryPatch {
  label?: string | null
  icon?: string | null
  parent?: string | null
}

/**
 * Brouillon du réglage de menu.
 *
 * Rien n'est envoyé au fil des clics : l'administrateur déplace, renomme et
 * masque, puis enregistre **une fois**. Un enregistrement par geste
 * multiplierait les requêtes sur une opération qui se pense d'un bloc, et
 * laisserait un menu à moitié réordonné si la connexion lâchait au milieu.
 *
 * Le brouillon est `null` tant que rien n'a bougé : la liste affichée est alors
 * celle du serveur, et un rechargement de la requête se voit tout de suite.
 * Dès la première retouche il prend le relais, et « Annuler » consiste à le
 * remettre à `null`.
 */
export function useMenuDraft(serverItems: MenuItem[]) {
  const [draft, setDraft] = useState<MenuItem[] | null>(null)

  const items = draft ?? flattenTree(serverItems)

  const patch = (code: string, changes: Partial<MenuItem>) => {
    setDraft(items.map((item) => (item.code === code ? { ...item, ...changes } : item)))
  }

  return {
    items,

    /** Vrai dès qu'un champ diffère de ce que le serveur a renvoyé. */
    isDirty: draft !== null && differs(items, serverItems),

    toggle: (code: string) => {
      const current = items.find((item) => item.code === code)
      if (current !== undefined) patch(code, { isVisible: !current.isVisible })
    },

    /**
     * Une chaîne vide revient au libellé du catalogue.
     *
     * Le champ vidé **est** le bouton « revenir au défaut » : enregistrer « »
     * afficherait une entrée sans nom dans la barre latérale.
     */
    customize: (code: string, changes: MenuEntryPatch) => {
      const label = changes.label?.trim()

      const renamed = items.map((item) =>
        item.code === code
          ? {
              ...item,
              ...(changes.label === undefined
                ? {}
                : { label: label === '' ? null : (label ?? null) }),
              ...(changes.icon === undefined || changes.icon === null ? {} : { icon: changes.icon }),
            }
          : item,
      )

      // Le rattachement passe par `reparent`, qui reconstruit l'arbre : le
      // poser comme un champ ordinaire laisserait l'entrée à sa place et le
      // groupe la réclamerait sans l'obtenir.
      setDraft(
        'parent' in changes ? reparent(renamed, code, changes.parent ?? null) : renamed,
      )
    },

    move: (code: string, delta: -1 | 1) => setDraft(moveWithinSiblings(items, code, delta)),

    reset: () => setDraft(null),

    /**
     * Charge utile : toutes les entrées, pas seulement celles qui ont changé.
     *
     * Les rangs n'ont de sens que les uns par rapport aux autres — n'envoyer
     * que l'entrée déplacée laisserait les autres sur leurs anciens rangs, et
     * l'ordre obtenu ne serait pas celui affiché.
     *
     * L'icône fait exception : elle n'est envoyée que si elle a changé. Le
     * serveur renvoie l'icône **effective**, sans dire si elle vient du
     * catalogue ou d'un choix ; la réécrire à chaque enregistrement figerait
     * dans la base des icônes que personne n'a choisies, et une entrée cesserait
     * de suivre celle que le catalogue lui donnera demain.
     */
    payload: (): RoleMenuUpdateItem[] =>
      withPositions(items).map((item) => {
        const before = serverItems.find((candidate) => candidate.code === item.code)

        return {
          code: item.code,
          isVisible: item.isVisible,
          position: item.position,
          label: item.label,
          parent: item.parent,
          ...(before !== undefined && before.icon === item.icon ? {} : { icon: item.icon }),
        }
      }),
  }
}

/**
 * L'ordre compte autant que les valeurs : comparer par indice, pas par code.
 */
function differs(items: MenuItem[], serverItems: MenuItem[]): boolean {
  const reference = flattenTree(serverItems)

  return items.some((item, index) => {
    const before = reference[index]

    return (
      before === undefined ||
      before.code !== item.code ||
      before.isVisible !== item.isVisible ||
      before.label !== item.label ||
      before.icon !== item.icon ||
      before.parent !== item.parent
    )
  })
}
