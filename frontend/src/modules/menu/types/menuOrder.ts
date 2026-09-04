import type { MenuItem } from './menu'

/**
 * Ordre du menu, tel que l'écran de réglage le manipule.
 *
 * L'API transporte une **liste plate** ordonnée par `position`, la hiérarchie
 * tenant dans `parent`. Réordonner revient donc à réécrire cette liste, puis à
 * renuméroter : ces fonctions gardent les deux gestes ensemble, et les gardent
 * hors des composants, où ils seraient réécrits à chaque rendu.
 *
 * Deux gestes distincts, à ne pas confondre : les flèches déplacent une entrée
 * **parmi ses frères**, sans changer son groupe ; le rattachement change son
 * groupe, sans que l'entrée quitte sa place dans la liste. Les mêler ferait
 * d'un simple « monter » une promotion involontaire dès qu'on atteint le haut
 * d'un groupe.
 */

/**
 * Remet la liste en ordre d'arbre : chaque racine suivie de ses enfants.
 *
 * C'est ce qui fait qu'un groupe déplacé **emmène ses entrées**. Sans ce
 * regroupement, monter « Exploitation » d'un cran laisserait ses commandes et
 * ses services au milieu des ressources.
 */
export function flattenTree(items: MenuItem[]): MenuItem[] {
  const ordered: MenuItem[] = []
  const seen = new Set<string>()

  for (const root of items.filter((item) => item.parent === null)) {
    ordered.push(root)
    seen.add(root.code)

    for (const child of items.filter((item) => item.parent === root.code)) {
      ordered.push(child)
      seen.add(child.code)
    }
  }

  // Un enfant dont le parent a quitté le catalogue passe à la fin plutôt que
  // de disparaître : une entrée qu'on ne voit plus est une entrée qu'on ne
  // peut plus régler.
  return [...ordered, ...items.filter((item) => !seen.has(item.code))]
}

/** Frères d'une entrée, dans l'ordre courant. */
function siblingsOf(items: MenuItem[], code: string): MenuItem[] {
  const item = items.find((candidate) => candidate.code === code)

  return item === undefined ? [] : items.filter((candidate) => candidate.parent === item.parent)
}

/** Vrai si l'entrée a un frère dans cette direction. */
export function canMove(items: MenuItem[], code: string, delta: -1 | 1): boolean {
  const siblings = siblingsOf(items, code)
  const at = siblings.findIndex((candidate) => candidate.code === code)

  return at !== -1 && siblings[at + delta] !== undefined
}

/**
 * Échange une entrée avec le frère voisin, puis regroupe.
 *
 * Renvoie la liste inchangée quand le déplacement n'a pas lieu d'être — bord de
 * la fratrie, code inconnu : un appelant n'a pas à vérifier avant d'appeler.
 */
export function moveWithinSiblings(items: MenuItem[], code: string, delta: -1 | 1): MenuItem[] {
  const siblings = siblingsOf(items, code)
  const at = siblings.findIndex((candidate) => candidate.code === code)
  const neighbour = siblings[at + delta]

  if (at === -1 || neighbour === undefined) return items

  const next = [...items]
  const from = next.indexOf(siblings[at])
  const to = next.indexOf(neighbour)
  next[from] = neighbour
  next[to] = siblings[at]

  return flattenTree(next)
}

/**
 * Groupes pouvant accueillir une entrée, dans l'ordre courant.
 *
 * Un groupe est une entrée sans route. C'est le seul niveau qui puisse en
 * contenir un autre : la barre latérale en rend deux, et un troisième ne
 * s'afficherait nulle part.
 */
export function groupsOf(items: MenuItem[]): MenuItem[] {
  return items.filter((item) => item.route === null && item.parent === null)
}

/**
 * Sort une entrée de son groupe, ou l'y fait entrer.
 *
 * `parent` à `null` la remonte au premier niveau — c'est une **valeur choisie**,
 * pas une absence de choix, et l'appelant doit la distinguer de « ne rien
 * changer » en n'appelant pas cette fonction.
 *
 * L'entrée garde sa place dans la liste, et `flattenTree` la range ensuite là
 * où son nouveau parent l'appelle : une entrée promue reste donc juste après le
 * groupe qu'elle quitte, à l'endroit où l'œil la cherche.
 *
 * Un groupe ne se déplace pas — il n'a pas de niveau où descendre — et une
 * entrée ne se range pas dans elle-même : les deux demandes rendent la liste
 * inchangée plutôt que de casser l'arbre.
 */
export function reparent(items: MenuItem[], code: string, parent: string | null): MenuItem[] {
  const item = items.find((candidate) => candidate.code === code)

  if (item === undefined || !item.canReparent || parent === code) return items

  if (parent !== null && !groupsOf(items).some((group) => group.code === parent)) return items

  return flattenTree(items.map((candidate) => (candidate.code === code ? { ...candidate, parent } : candidate)))
}

/**
 * Renumérote la liste sur son ordre courant.
 *
 * Le rang est l'indice, sans trou : le résolveur trie sur `position`, et des
 * rangs hérités du catalogue — espacés de dix — se mêleraient à ceux qu'on
 * vient d'écrire.
 */
export function withPositions(items: MenuItem[]): MenuItem[] {
  return items.map((item, index) => ({ ...item, position: index }))
}
