import type { ExportConfigurationPayload, ExportSettings } from '../types/export'
import { EXPORT_CONTENTS, type ExportContent } from '../types/exportContents'

/** Les champs de connexion, tels que le formulaire les tient : en texte. */
export interface ConnectionDraft {
  host: string
  port: string
  username: string
  password: string
  remoteDirectory: string
}

/** Un envoi manuel n'appelle personne, un courriel passe par notre relais. */
export function hasConnection(transport: string): boolean {
  return transport !== 'manual' && transport !== 'email'
}

/**
 * Le formulaire traduit en ce que l'API attend.
 *
 * **Un mot de passe vide est omis, pas envoyé vide.** Le serveur ne rend jamais
 * le secret (§124) : le champ est donc vide à l'ouverture, et l'omettre est la
 * seule façon de dire « inchangé ». L'envoyer vide l'effacerait.
 *
 * Le type d'export et la fréquence ne se saisissent pas : ils découlent du
 * contenu choisi — facture à la clôture, ou statut de commande à chaque
 * changement. Une destination configurée autrement ne partirait jamais.
 */
export function buildExportPayload(
  customerId: string,
  form: {
    name: string
    format: string
    transport: string
    fileNamePattern: string
    isActive: boolean
    connection: ConnectionDraft
    settings: ExportSettings
    content?: ExportContent
  },
): ExportConfigurationPayload {
  const content = EXPORT_CONTENTS[form.content ?? 'invoice']
  const { connection } = form

  return {
    customerId,
    name: form.name.trim(),
    exportType: content.exportType,
    format: form.format,
    transport: form.transport,
    host: connection.host.trim() || null,
    port: connection.port === '' ? null : Number.parseInt(connection.port, 10),
    username: connection.username.trim() || null,
    ...(connection.password === '' ? {} : { password: connection.password }),
    remoteDirectory: connection.remoteDirectory.trim() || null,
    fileNamePattern: form.fileNamePattern.trim() || null,
    frequency: content.frequency,
    settings: form.settings,
    isActive: form.isActive,
  }
}
