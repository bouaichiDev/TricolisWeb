import { ordersApi } from './ordersApi'

/**
 * Dépose les fichiers choisis dans le wizard sur la commande enregistrée.
 *
 * Un à un, et sans tout annuler si l'un échoue : la commande existe, elle ne
 * doit pas disparaître parce qu'un fichier a été refusé. Les noms en échec
 * sont rendus pour que l'écran les signale.
 */
export async function uploadDocuments(orderId: string, files: File[]): Promise<string[]> {
  const failed: string[] = []

  for (const file of files) {
    try {
      await ordersApi.uploadDocument(orderId, file)
    } catch {
      failed.push(file.name)
    }
  }

  return failed
}
