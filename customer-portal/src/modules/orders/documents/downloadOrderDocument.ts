import { toast } from 'sonner'

import { errorMessage } from '@/shared/api/errors'

import type { OrderDocument } from '../types'
import { fetchDocumentFile } from './useOrderDocumentFile'

/** Téléchargement authentifié : un lien direct partirait sans le jeton. */
export async function downloadOrderDocument(orderId: string | undefined, document: Pick<OrderDocument, 'id' | 'fileName'>): Promise<void> {
  try {
    const url = URL.createObjectURL(await fetchDocumentFile(orderId, document.id))
    const anchor = Object.assign(window.document.createElement('a'), { href: url, download: document.fileName })
    anchor.click()
    URL.revokeObjectURL(url)
  } catch (caught) {
    toast.error(errorMessage(caught))
  }
}
