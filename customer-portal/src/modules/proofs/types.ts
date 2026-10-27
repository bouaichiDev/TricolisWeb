/** Une pièce d'une preuve : métadonnées seulement, le contenu passe par `/documents/{id}/download`. */
export interface ProofFile {
  id: string
  fileName: string
  mimeType: string
  size: number
}

/** Une preuve de livraison, en lecture seule (§17). */
export interface ProofOfDelivery {
  id: string
  orderId: string
  orderServiceId: string | null
  service: { id: string; serviceNumber: string; name: string | null } | null
  recipientName: string
  remark: string | null
  deliveredAt: string | null
  signature: ProofFile | null
  photo: ProofFile | null
}
