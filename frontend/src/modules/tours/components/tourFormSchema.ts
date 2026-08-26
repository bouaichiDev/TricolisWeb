import { z } from 'zod'

/**
 * Sentinelle du choix « aucun ».
 *
 * Radix refuse une option de valeur vide, et le serveur attend `null` pour un
 * moyen non affecté : cette valeur fait le pont entre les deux.
 */
export const NONE = 'none'

/** Contraintes reprises de `StoreTourRequest`. */
export const tourSchema = z.object({
  tourNumber: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  tourDate: z.string().min(1, 'validation.required'),
  agencyId: z.string().min(1, 'validation.required'),
  depotId: z.string(),
  providerId: z.string(),
  vehicleId: z.string(),
  driverId: z.string(),
  tourType: z.string().max(64, 'validation.max'),
  instructions: z.string(),
  plannedStartAt: z.string(),
  plannedEndAt: z.string(),
})

export type TourFormValues = z.infer<typeof tourSchema>

/** Un champ vide ou la sentinelle valent « non renseigné », soit `null`. */
export function optional(value: string): string | null {
  return value === NONE || value === '' ? null : value
}
