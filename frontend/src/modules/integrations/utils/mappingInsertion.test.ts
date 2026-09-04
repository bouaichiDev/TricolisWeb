import { describe, expect, it } from 'vitest'

import { contextPathAt, insertField, parseFieldPath } from './mappingInsertion'

/** Position du curseur, marquée par `|` dans le texte du test. */
function at(marked: string): { text: string; cursor: number } {
  const cursor = marked.indexOf('|')

  return { text: marked.replace('|', ''), cursor }
}

const insert = (marked: string, path: string) => {
  const { text, cursor } = at(marked)

  return insertField(text, cursor, path)
}

describe('chemin d’un champ', () => {
  it('distingue les tableaux des objets', () => {
    expect(parseFieldPath('services[].contacts[].phone')).toEqual([
      { key: 'services', isArray: true },
      { key: 'contacts', isArray: true },
      { key: 'phone', isArray: false },
    ])
  })

  it('lit un champ de racine', () => {
    expect(parseFieldPath('orderDate')).toEqual([{ key: 'orderDate', isArray: false }])
  })
})

describe('contexte au curseur', () => {
  it('rend un chemin vide à la racine', () => {
    const { text, cursor } = at('{\n  |\n}')

    expect(contextPathAt(text, cursor)).toEqual([])
  })

  it('reconnaît l’intérieur d’un élément de tableau', () => {
    const { text, cursor } = at('{\n  "services": [\n    {\n      |\n    }\n  ]\n}')

    expect(contextPathAt(text, cursor)).toEqual(['services'])
  })

  it('descend dans les tableaux imbriqués', () => {
    const { text, cursor } = at(
      '{\n "services": [\n  {\n   "contacts": [\n    {\n     |\n    }\n   ]\n  }\n ]\n}',
    )

    expect(contextPathAt(text, cursor)).toEqual(['services', 'contacts'])
  })

  /** Une accolade dans une chaîne n'ouvre rien. */
  it('ignore la ponctuation à l’intérieur des chaînes', () => {
    const { text, cursor } = at('{\n  "a": "va{leur",\n  |\n}')

    expect(contextPathAt(text, cursor)).toEqual([])
  })

  /** Une fois la valeur fermée, sa clé ne vaut plus pour la suite. */
  it('oublie la clé dont la valeur est refermée', () => {
    const { text, cursor } = at('{\n  "services": [{ "unit": "U" }],\n  |\n}')

    expect(contextPathAt(text, cursor)).toEqual([])
  })
})

describe('insertion d’un champ', () => {
  /** Un éditeur vide n'a pas d'objet : il faut bien le créer. */
  it('crée le document quand le champ est vide', () => {
    const result = insertField('', 0, 'orderDate')

    expect(result?.text).toBe('{\n  "orderDate": ""\n}')
  })

  it('pose le curseur entre les guillemets de la valeur', () => {
    const result = insertField('', 0, 'orderDate')

    expect(result?.text.slice(result.cursor)).toBe('"\n}')
  })

  /**
   * Le cas qui compte : à la racine, un chemin imbriqué s'écrit en entier.
   * Coller `services[].contacts[].phone` tel quel ne produirait rien de lisible.
   */
  it('déploie toute la structure depuis la racine', () => {
    const result = insert('{\n  |\n}', 'services[].contacts[].phone')

    expect(JSON.parse(result?.text ?? '')).toEqual({
      services: [{ contacts: [{ phone: '' }] }],
    })
  })

  /** Dans un service, seul ce qui manque est ajouté. */
  it('n’ajoute que le reste depuis l’intérieur d’un service', () => {
    const result = insert(
      '{\n  "services": [\n    {\n      "unit": "U"|\n    }\n  ]\n}',
      'services[].contacts[].phone',
    )

    expect(JSON.parse(result?.text ?? '')).toEqual({
      services: [{ unit: 'U', contacts: [{ phone: '' }] }],
    })
  })

  /** Dans un contact, il ne reste que la feuille. */
  it('n’ajoute que la feuille depuis l’intérieur d’un contact', () => {
    const result = insert(
      '{\n "services": [\n  {\n   "contacts": [\n    {\n     "email": "MAIL"|\n    }\n   ]\n  }\n ]\n}',
      'services[].contacts[].phone',
    )

    expect(JSON.parse(result?.text ?? '')).toEqual({
      services: [{ contacts: [{ email: 'MAIL', phone: '' }] }],
    })
  })

  /**
   * Un champ de racine choisi depuis l'intérieur d'un contact s'insère à la
   * racine : le contexte ne compte que s'il coïncide avec le chemin du champ.
   */
  it('replace un champ de racine à la racine, où que soit le curseur', () => {
    const result = insert(
      '{\n "services": [\n  {\n   "contacts": [\n    {\n     |\n    }\n   ]\n  }\n ]\n}',
      'orderDate',
    )

    expect(JSON.parse(result?.text ?? '')).toEqual({
      services: [{ contacts: [{}] }],
      orderDate: '',
    })
  })

  /** Ajouter un second champ ne doit pas effacer le premier. */
  it('conserve ce qui est déjà écrit', () => {
    const result = insert(
      '{\n  "lines": [\n    {\n      "articleCode": "ARTICLE"|\n    }\n  ]\n}',
      'lines[].quantity',
    )

    expect(JSON.parse(result?.text ?? '')).toEqual({
      lines: [{ articleCode: 'ARTICLE', quantity: '' }],
    })
  })

  it('ne remplace pas une correspondance déjà renseignée', () => {
    const result = insert('{\n  "orderDate": "DATE"|\n}', 'orderDate')

    expect(JSON.parse(result?.text ?? '')).toEqual({ orderDate: 'DATE' })
  })

  /**
   * Un JSON cassé n'est pas réparé à la place de son auteur : l'éditeur affiche
   * déjà l'erreur avec sa position.
   */
  it('renonce sur un JSON invalide', () => {
    expect(insertField('{ "a": ', 7, 'orderDate')).toBeNull()
  })

  it('renonce sur un document qui n’est pas un objet', () => {
    expect(insertField('[1, 2]', 3, 'orderDate')).toBeNull()
  })
})
