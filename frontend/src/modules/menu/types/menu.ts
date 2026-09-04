/**
 * Entrée de menu, telle que la renvoie `GET /menu`.
 *
 * Le catalogue vit côté backend (`App\Shared\Menu\MenuCatalogue`) : c'est lui
 * la source. Le frontend ne décide plus quelles entrées existent — il résout
 * l'icône et la clé i18n, et affiche ce qu'on lui donne.
 *
 * `icon` et `parent` arrivent **déjà arbitrés** : si l'organisation les a
 * choisis, ce sont les siens. `label` reste distinct de `labelKey` — null
 * signifie « traduis la clé », et les confondre perdrait la traduction.
 *
 * `route` absente signifie un groupe repliable ; ses enfants portent son `code`
 * dans `parent`.
 */
export interface MenuItem {
  code: string
  labelKey: string
  /** Libellé choisi par l'organisation ; null pour suivre `labelKey`. */
  label: string | null
  icon: string
  route: string | null
  permission: string | null
  parent: string | null
  section: string
  position: number
  isVisible: boolean
  /** Faux pour les entrées que l'organisation ne peut pas masquer. */
  canHide: boolean
  /**
   * Faux pour un groupe : il n'a pas de niveau où descendre.
   *
   * La barre latérale en rend deux. Ranger un groupe dans un groupe placerait
   * ses entrées au troisième, où rien ne les affiche — elles disparaîtraient
   * sans qu'aucune erreur ne soit levée.
   */
  canReparent: boolean
  /**
   * Vrai pour un groupe que l'organisation s'est créé.
   *
   * Lui seul se supprime : un groupe livré par le catalogue ne lui appartient
   * pas, il se masque. La distinction vient du serveur plutôt que d'un préfixe
   * de code relu ici — l'invariant n'a pas à vivre à deux endroits.
   */
  isCustom: boolean
}

/**
 * Libellé à afficher : celui de l'organisation, sinon la traduction livrée.
 *
 * Passer par cette fonction plutôt que par `t(item.labelKey)` est ce qui rend
 * le renommage effectif partout — barre latérale, fil d'Ariane, écran de
 * réglage. Un appel oublié afficherait l'ancien nom à un seul endroit, et
 * l'organisation croirait le réglage à moitié pris en compte.
 */
export function menuLabel(item: MenuItem, translate: (key: string) => string): string {
  return item.label ?? translate(item.labelKey)
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
