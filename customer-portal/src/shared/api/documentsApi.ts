import { api } from './client'

/**
 * Un document du portail par son identifiant : le backend revérifie le
 * chemin métier (commande, service, réclamation, preuve de livraison)
 * jusqu'au client courant. Jamais d'URL de stockage directe.
 */
export const documentsApi = {
  download: (documentId: string) => api.blob(`/documents/${documentId}/download`),
}
