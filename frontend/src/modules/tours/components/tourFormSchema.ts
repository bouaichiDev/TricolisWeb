import { z } from 'zod'

/**
 * Sentinelle du choix « aucun ».
 *
 * Radix refuse une option de valeur vide, et le serveur attend `null` pour un
 * moyen non affecté : cette valeur fait le pont entre les deux.
 */
export const NONE = 'none'

/**
 * Contraintes reprises de `StoreTourRequest`.
 *
 * Le numéro n'y figure pas : le serveur l'attribue, un entier qui avance de un.
 * Le laisser saisir produisait des doublons que la contrainte d'unicité
 * refusait une fois le formulaire rempli.
 */
export const tourSchema = z.object({
  agencyId: z.string().min(1, 'validation.required'),
  depotId: z.string(),
  providerId: z.string(),
  vehicleId: z.string(),
  driverId: z.string(),
  tourType: z.string().max(64, 'validation.max'),
  instructions: z.string(),
  // Obligatoire, et seul porteur de la date : le formulaire demandait la date
  // de la tournée puis le début prévu puis la fin, soit trois champs pour deux
  // informations. Une tournée qui commence le 2 n'est pas datée du 3.
  plannedStartAt: z.string().min(1, 'validation.required'),
  plannedEndAt: z.string(),
})

export type TourFormValues = z.infer<typeof tourSchema>

/** Un champ vide ou la sentinelle valent « non renseigné », soit `null`. */
export function optional(value: string): string | null {
  return value === NONE || value === '' ? null : value
}

/**
 * La date de la tournée : celle de son début.
 *
 * `2026-09-01T06:30` → `2026-09-01`. Le serveur exige toujours `tourDate` ; il
 * n'y a simplement plus de raison de le demander deux fois à l'utilisateur.
 */
export function tourDateOf(plannedStartAt: string): string {
  return plannedStartAt.slice(0, 10)
}

/**
 * La fin retenue quand on n'en donne pas : 20 h 00 le jour du départ.
 *
 * Une tournée sans fin ne se compare à aucune autre — c'est pourtant à cela que
 * servent les horaires prévus. Vingt heures ferme la journée d'exploitation.
 *
 * **Un départ après 20 h finit le lendemain.** Le serveur refuse une fin
 * antérieure au début (`after_or_equal`) : sans ce report, une tournée de nuit
 * serait rejetée sans que rien à l'écran n'explique pourquoi.
 */
export function defaultEndAt(plannedStartAt: string): string {
  const evening = `${plannedStartAt.slice(0, 10)}T20:00`

  return plannedStartAt < evening ? evening : `${nextDay(plannedStartAt.slice(0, 10))}T20:00`
}

/** Le lendemain, mois et années franchis. */
function nextDay(day: string): string {
  const date = new Date(`${day}T00:00:00`)
  date.setDate(date.getDate() + 1)

  const pad = (value: number) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
