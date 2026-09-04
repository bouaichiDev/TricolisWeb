import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { ExportJob, ExportJobFilters, ExportJobPayload } from '../types/export'

/**
 * Envois — l'historique de ce qui est parti chez les clients.
 *
 * `Route::apiResource(...)->only(['index', 'store', 'show'])` plus `retry` : il
 * n'existe **ni `PATCH`, ni `DELETE`**. Un envoi est un fait daté ; le corriger
 * reviendrait à réécrire ce qui s'est passé, et le §50 l'interdit.
 *
 * **Aucune méthode de téléchargement**, et ce n'est pas un oubli : la route
 * `GET /export-jobs/{id}/download` n'existe pas côté serveur, pas plus que la
 * permission `export_jobs.download`. La ressource expose `hasFile`, mais rien ne
 * sert le fichier. Construire une URL depuis `storagePath` serait la seule autre
 * voie — le §58 l'interdit, et le serveur ne renvoie de toute façon pas ce
 * chemin.
 */
export const exportJobsApi = {
  list: (filters: ExportJobFilters) =>
    api.get<ApiCollection<ExportJob>>('/export-jobs', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<ExportJob>>(`/export-jobs/${id}`).then((r) => r.data),

  /**
   * Déclenche un envoi.
   *
   * `entityType` n'accepte que les alias de `MorphMap::registered()` — jamais
   * un nom de classe PHP (§51). La génération et la transmission restent au
   * serveur : `ProcessExportJob` s'en charge, React ne produit aucun fichier.
   */
  create: (payload: ExportJobPayload) =>
    api.post<ApiResource<ExportJob>>('/export-jobs', payload).then((r) => r.data),

  /**
   * Relancer un envoi manqué.
   *
   * Le statut est fourni par l'appelant : le diagramme n'en énumère aucun pour
   * un envoi, et le référentiel les porte. Un envoi déjà transmis est refusé en
   * 409 — le renvoyer donnerait au client deux fois la même facture. Le job est
   * **réutilisé**, jamais dupliqué.
   */
  retry: (id: string, status = 'pending') =>
    api.post<ApiResource<ExportJob>>(`/export-jobs/${id}/retry`, { status }).then((r) => r.data),
}
