import type { CSSProperties } from 'react'

/**
 * Teinte de repli, par code, quand le référentiel ne porte pas de couleur —
 * celle du back-office. Un code inconnu prend la teinte neutre.
 */
const TONES: Record<string, string> = {
  active: 'bg-success/12 text-success border-success/25',
  confirmed: 'bg-success/12 text-success border-success/25',
  completed: 'bg-success/12 text-success border-success/25',
  inactive: 'bg-muted text-muted-foreground border-border',
  draft: 'bg-muted text-muted-foreground border-border',
  pending: 'bg-warning/15 text-warning border-warning/30',
  invited: 'bg-warning/15 text-warning border-warning/30',
  ready: 'bg-primary/10 text-primary border-primary/25',
  planned: 'bg-primary/10 text-primary border-primary/25',
  partially_planned: 'bg-primary/10 text-primary border-primary/25',
  in_progress: 'bg-primary/10 text-primary border-primary/25',
  suspended: 'bg-destructive/12 text-destructive border-destructive/25',
  blocked: 'bg-destructive/12 text-destructive border-destructive/25',
  cancelled: 'bg-destructive/12 text-destructive border-destructive/25',
  disabled: 'bg-muted text-muted-foreground border-border',
}

const HEX = /^#[0-9A-Fa-f]{6}$/

export interface StatusAppearance {
  className: string
  style?: CSSProperties
}

/**
 * L'apparence d'un statut, pour le badge, la liste et le calendrier.
 *
 * **La couleur du référentiel l'emporte** (`statuses.color`, réglée par
 * l'administrateur) : texte dans la couleur, fond et bordure dans ses
 * versions translucides. Sans elle, la teinte de repli par code.
 */
export function statusAppearance(code: string | null | undefined, color?: string | null): StatusAppearance {
  if (color && HEX.test(color)) {
    return { className: '', style: { color, backgroundColor: `${color}1f`, borderColor: `${color}55` } }
  }

  return { className: (code && TONES[code]) || TONES.inactive }
}
