/**
 * Document — champs relevés sur `DocumentResource`.
 *
 * Ni `storagePath` ni URL de téléchargement : la ressource ne les expose pas,
 * et le chemin de stockage ne doit jamais quitter le serveur.
 *
 * `documentType` et `status` sont des **chaînes libres** en base — `varchar(64)`
 * et `varchar(20)` — sans énumération côté serveur. L'interface les laisse donc
 * saisir plutôt que de proposer une liste qui n'existe nulle part.
 */
export interface Document {
  id: string
  organizationId: string
  referenceNumber: string | null
  documentType: string
  status: string
  fileName: string
  mimeType: string
  size: number
  receivedAt: string | null
  createdBy: string | null
  createdAt: string
  updatedAt: string
}

export interface DocumentUpload {
  file: File
  documentType: string
  status: string
  referenceNumber?: string
  receivedAt?: string
}
