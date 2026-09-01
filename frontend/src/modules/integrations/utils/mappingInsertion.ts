/**
 * Insertion assistée d'un champ dans une correspondance JSON.
 *
 * Coller `services[].contacts[].phone` tel quel ne veut rien dire dans un
 * objet JSON. Ce qu'il faut, c'est la **structure** — et laquelle dépend de
 * l'endroit où l'on se trouve :
 *
 * ```text
 * curseur à la racine        → { "services": [ { "contacts": [ { "phone": "" } ] } ] }
 * curseur dans un service    → { "contacts": [ { "phone": "" } ] }
 * curseur dans un contact    → { "phone": "" }
 * ```
 *
 * Le travail se fait donc en trois temps : lire le chemin du curseur, retirer
 * ce qui est déjà ouvert autour de lui, puis fusionner ce qui reste dans le
 * document analysé. Passer par l'objet plutôt que par le texte garantit que le
 * résultat est toujours du JSON valide et correctement indenté — une insertion
 * à coups de découpage de chaîne produit une accolade en trop au premier cas
 * limite.
 */

export interface FieldSegment {
  key: string
  /** `services[]` : la valeur est un tableau dont on décrit un élément. */
  isArray: boolean
}

/** `services[].contacts[].phone` → trois segments, dont deux tableaux. */
export function parseFieldPath(path: string): FieldSegment[] {
  return path
    .split('.')
    .filter((part) => part !== '')
    .map((part) => ({
      key: part.replace('[]', ''),
      isArray: part.endsWith('[]'),
    }))
}

interface Frame {
  /** Clé sous laquelle ce conteneur est rangé ; `null` pour la racine et les
   *  objets d'un tableau, qui n'ajoutent pas de niveau au chemin. */
  ownKey: string | null
  /** Dernière clé lue dans ce conteneur, en attente de sa valeur. */
  currentKey: string | null
}

/**
 * Chemin des conteneurs ouverts à la position du curseur.
 *
 * Un analyseur à part entière serait excessif : il suffit de suivre les
 * accolades, les crochets et les clés, en sautant correctement les chaînes —
 * une accolade dans `"a{b"` n'ouvre rien.
 */
export function contextPathAt(text: string, cursor: number): string[] {
  const frames: Frame[] = []
  let pendingString: string | null = null
  let index = 0

  const limit = Math.min(cursor, text.length)

  while (index < limit) {
    const char = text[index]

    if (char === '"') {
      let scan = index + 1
      let value = ''

      while (scan < text.length && text[scan] !== '"') {
        if (text[scan] === '\\') {
          value += text[scan + 1] ?? ''
          scan += 2
          continue
        }

        value += text[scan]
        scan += 1
      }

      pendingString = value
      index = scan + 1
      continue
    }

    if (char === ':') {
      const frame = frames[frames.length - 1]
      if (frame !== undefined) frame.currentKey = pendingString
      pendingString = null
      index += 1
      continue
    }

    if (char === '{' || char === '[') {
      const parent = frames[frames.length - 1]
      frames.push({ ownKey: parent?.currentKey ?? null, currentKey: null })
      index += 1
      continue
    }

    if (char === '}' || char === ']') {
      frames.pop()
      const parent = frames[frames.length - 1]
      // La valeur est consommée : la clé du parent ne vaut plus.
      if (parent !== undefined) parent.currentKey = null
      index += 1
      continue
    }

    if (char === ',') {
      const frame = frames[frames.length - 1]
      if (frame !== undefined) frame.currentKey = null
      pendingString = null
      index += 1
      continue
    }

    index += 1
  }

  return frames
    .map((frame) => frame.ownKey)
    .filter((key): key is string => key !== null)
}

type JsonObject = Record<string, unknown>

/** Construit `{ contacts: [ { phone: "" } ] }` à partir des segments restants. */
function build(segments: FieldSegment[]): JsonObject {
  const [head, ...rest] = segments
  if (head === undefined) return {}

  const leaf = rest.length === 0 ? '' : build(rest)

  return { [head.key]: head.isArray ? [leaf] : leaf }
}

/**
 * Fusionne sans écraser.
 *
 * Une correspondance se construit par petites touches : ajouter
 * `lines[].quantity` après `lines[].articleCode` ne doit pas effacer le
 * premier. Un tableau existant garde donc son premier élément, dans lequel on
 * fusionne — un mapping décrit **un** modèle de ligne, pas des données.
 */
function merge(target: JsonObject, addition: JsonObject): void {
  for (const [key, value] of Object.entries(addition)) {
    const existing = target[key]

    if (Array.isArray(value) && Array.isArray(existing)) {
      const template = value[0]
      const current = existing[0]

      if (isObject(template) && isObject(current)) {
        merge(current, template)
      } else if (existing.length === 0) {
        existing.push(template)
      }

      continue
    }

    if (isObject(value) && isObject(existing)) {
      merge(existing, value)
      continue
    }

    // Une clé déjà renseignée n'est pas remplacée : on n'efface pas la
    // correspondance que quelqu'un vient d'écrire.
    if (existing === undefined) target[key] = value
  }
}

function isObject(value: unknown): value is JsonObject {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** Descend jusqu'au conteneur visé, en créant ce qui manque au passage. */
function containerAt(root: JsonObject, segments: FieldSegment[]): JsonObject {
  let current = root

  for (const segment of segments) {
    let next = current[segment.key]

    if (segment.isArray) {
      if (!Array.isArray(next)) {
        next = []
        current[segment.key] = next
      }

      const list = next as unknown[]
      if (!isObject(list[0])) list[0] = {}

      current = list[0] as JsonObject
      continue
    }

    if (!isObject(next)) {
      next = {}
      current[segment.key] = next
    }

    current = next as JsonObject
  }

  return current
}

export interface InsertionResult {
  text: string
  /** Position où placer le curseur : entre les guillemets de la valeur. */
  cursor: number
}

/**
 * Insère un champ dans la correspondance, au bon niveau.
 *
 * Rend `null` quand le texte n'est pas du JSON exploitable : l'éditeur affiche
 * déjà l'erreur de syntaxe avec sa position, et réparer le document à la place
 * de son auteur ferait plus de dégâts que de bien.
 *
 * Un champ vide donne `{}` : c'est le seul endroit où l'on décide à la place de
 * l'utilisateur, et il n'y a pas d'autre document possible.
 */
export function insertField(
  text: string,
  cursor: number,
  fieldPath: string,
): InsertionResult | null {
  const segments = parseFieldPath(fieldPath)
  if (segments.length === 0) return null

  let root: JsonObject

  if (text.trim() === '') {
    root = {}
  } else {
    try {
      const parsed: unknown = JSON.parse(text)
      if (!isObject(parsed)) return null
      root = parsed
    } catch {
      return null
    }
  }

  const context = contextPathAt(text, cursor)

  // Ce qui est déjà ouvert autour du curseur n'a pas à être réécrit — mais
  // seulement tant qu'il coïncide avec le chemin du champ. Un champ de racine
  // choisi depuis l'intérieur d'un contact s'insère bien à la racine.
  let common = 0
  while (
    common < context.length &&
    common < segments.length &&
    context[common] === segments[common].key
  ) {
    common += 1
  }

  const container = containerAt(root, segments.slice(0, common))
  merge(container, build(segments.slice(common)))

  const next = JSON.stringify(root, null, 2)
  const leafKey = segments[segments.length - 1].key
  const anchor = next.indexOf(`"${leafKey}": ""`)

  return {
    text: next,
    // Le curseur se pose entre les guillemets de la valeur : c'est là qu'on
    // écrit le nom de la colonne du fichier, tout de suite après.
    cursor: anchor === -1 ? next.length : anchor + `"${leafKey}": "`.length,
  }
}
