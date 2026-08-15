/**
 * Entrée de menu, telle que la renvoie `GET /menu`.
 *
 * Le catalogue vit côté backend (`App\Shared\Menu\MenuCatalogue`) : c'est lui
 * la source. Le frontend ne décide plus quelles entrées existent — il résout
 * l'icône et la clé i18n, et affiche ce qu'on lui donne.
 *
 * `route` absente signifie un groupe repliable ; ses enfants portent son `code`
 * dans `parent`.
 */
export interface MenuItem {
  code: string
  labelKey: string
  icon: string
  route: string | null
  permission: string | null
  parent: string | null
  section: string
  position: number
  isVisible: boolean
  /** Faux pour les entrées que l'organisation ne peut pas masquer. */
  canHide: boolean
}

/** Entrée racine avec ses enfants, forme attendue par la barre latérale. */
export interface MenuTree {
  item: MenuItem
  children: MenuItem[]
}

/**
 * Reconstruit l'arbre à partir de la liste plate.
 *
 * L'API renvoie une liste ordonnée : la hiérarchie tient dans `parent`, ce qui
 * évite d'imbriquer la réponse et de compliquer le tri. Un enfant dont le
 * parent est absent — masqué, ou sans permission — remonte à la racine plutôt
 * que de disparaître : une entrée autorisée doit rester atteignable.
 */
export function buildMenuTree(items: MenuItem[]): MenuTree[] {
  const roots = items.filter((item) => item.parent === null)
  const rootCodes = new Set(roots.map((item) => item.code))

  const tree = roots.map((item) => ({
    item,
    children: items.filter((child) => child.parent === item.code),
  }))

  const orphans = items
    .filter((item) => item.parent !== null && !rootCodes.has(item.parent))
    .map((item) => ({ item, children: [] as MenuItem[] }))

  return [...tree, ...orphans].sort((a, b) => a.item.position - b.item.position)
}
