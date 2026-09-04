import type { Status } from '../types/status'

/**
 * Valeurs de départ du formulaire, à plat et en chaînes.
 *
 * Les champs numériques restent des chaînes tant que la saisie dure : un champ
 * vidé donne `NaN` si on le convertit à chaque frappe, et l'écran afficherait
 * une valeur que personne n'a tapée.
 */
export function statusFormValues(status: Status | null): Record<string, string> {
  if (status === null) {
    return { source: '', status: '', code: '', label: '', icon: '', position: '' }
  }

  return {
    source: status.source,
    status: String(status.status),
    code: status.code,
    label: status.label,
    icon: status.icon ?? '',
    position: status.position === null ? '' : String(status.position),
  }
}

/** Champ texte du formulaire, hors entité et hors cases à cocher. */
export interface StatusFieldSpec {
  name: string
  labelKey: string
  type: 'text' | 'number'
  required?: boolean
  hintKey?: string
}

export const STATUS_FIELDS: StatusFieldSpec[] = [
  {
    name: 'status',
    labelKey: 'statuses.fields.status',
    type: 'number',
    required: true,
    hintKey: 'statuses.statusHint',
  },
  {
    name: 'code',
    labelKey: 'statuses.fields.code',
    type: 'text',
    required: true,
    hintKey: 'statuses.codeHint',
  },
  { name: 'label', labelKey: 'statuses.fields.label', type: 'text', required: true },
  { name: 'icon', labelKey: 'statuses.fields.icon', type: 'text', hintKey: 'statuses.iconHint' },
  { name: 'position', labelKey: 'statuses.fields.position', type: 'number' },
]
