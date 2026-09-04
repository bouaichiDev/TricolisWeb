import { z } from 'zod'

/**
 * Ce qu'on s'autorise à envoyer.
 *
 * Deux champs, exactement les deux que le backend accepte : une clé, un rang.
 * Le schéma n'est **pas** la sécurité — le serveur revalide tout, confronte la
 * clé au catalogue et refuse un widget dont le rôle n'a pas la permission. Il
 * est la garantie qu'un champ ajouté ici par mégarde ne parte pas
 * silencieusement pour être ignoré à l'autre bout.
 *
 * `strict()` porte l'essentiel : un `label`, une `route` ou un `resolver`
 * glissés dans le corps échouent ici, à l'écriture du code, plutôt que d'être
 * envoyés puis filtrés sans que personne le remarque.
 *
 * `min(0)` reprend la borne du serveur. Les rangs sont de toute façon
 * renumérotés en bloc à l'enregistrement — mais une liste envoyée avec un rang
 * négatif serait refusée en 422, et l'écran n'aurait rien à en dire.
 */
export const roleDashboardWidgetSelectionSchema = z
  .object({
    key: z.string().min(1),
    position: z.number().int().min(0).max(9999),
  })
  .strict()

/**
 * La configuration entière.
 *
 * Une liste **vide est valide**, et c'est le point : c'est ainsi qu'un rôle dit
 * « aucun widget ». Exiger au moins une entrée aurait rendu ce choix
 * impossible à exprimer.
 */
export const roleDashboardConfigurationSchema = z.object({
  widgets: z.array(roleDashboardWidgetSelectionSchema).max(100),
})

export type RoleDashboardConfigurationInput = z.infer<typeof roleDashboardConfigurationSchema>
