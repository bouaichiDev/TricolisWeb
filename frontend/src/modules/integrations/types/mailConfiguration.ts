/**
 * La boîte d'envoi de l'organisation — `OrganizationMailConfigurationResource`.
 *
 * **Le mot de passe n'y figure pas**, et n'y figurera jamais : `hasPassword`
 * dit seulement qu'il en existe un. Un secret SMTP qui traverse une réponse
 * JSON finit dans un journal de requêtes, puis dans une capture d'écran.
 */
export interface MailConfiguration {
  id: string
  organizationId: string
  host: string
  port: number
  /** `tls`, `ssl`, ou nul pour aucun chiffrement. */
  encryption: string | null
  username: string | null
  hasPassword: boolean
  fromAddress: string
  fromName: string | null
  replyTo: string | null
  isActive: boolean
  lastUsedAt: string | null
  createdAt: string
  updatedAt: string
}

/**
 * Ce qu'on envoie pour régler la boîte.
 *
 * `password` absent conserve celui en place ; à `null` il l'efface. Rouvrir
 * l'écran pour changer un port ne doit pas obliger à ressaisir un secret qu'on
 * n'a plus sous la main.
 */
export interface MailConfigurationPayload {
  host: string
  port: number
  encryption: string | null
  username: string | null
  password?: string | null
  fromAddress: string
  fromName: string | null
  replyTo: string | null
  isActive: boolean
}
