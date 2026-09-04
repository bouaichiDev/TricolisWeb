import { z } from 'zod'

import type {
  StockReservationPayload,
  StockReservationStatusPayload,
} from '../api/stock-reservations.api'

/**
 * Réservation de stock.
 *
 * Les trois références sont obligatoires : réserver, c'est promettre **une**
 * quantité d'**un** article dans **un** emplacement à **une** ligne de commande.
 * Le serveur vérifie en plus que la ligne appartient au même client que
 * l'article — un contrôle que le frontend ne peut pas faire seul, et qu'il ne
 * doit donc pas prétendre faire.
 *
 * Le disponible n'est pas vérifié ici : il change entre l'affichage et l'envoi.
 * `CreateStockReservationAction` le lit sous verrou, et répond 409.
 */
export const stockReservationSchema = z.object({
  stockItemId: z.string().min(1, 'validation.required'),
  stockLocationId: z.string().min(1, 'validation.required'),
  orderLineId: z.string().min(1, 'validation.required'),
  quantity: z
    .string()
    .min(1, 'validation.required')
    .refine((value) => Number(value) > 0, 'stock.quantityMustBePositive'),
  status: z.string().min(1, 'validation.required'),
})

export type StockReservationFormValues = z.infer<typeof stockReservationSchema>

export const STOCK_RESERVATION_FORM_DEFAULTS: StockReservationFormValues = {
  stockItemId: '',
  stockLocationId: '',
  orderLineId: '',
  quantity: '',
  status: 'active',
}

export function toStockReservationPayload(
  values: StockReservationFormValues,
): StockReservationPayload {
  return {
    stockItemId: values.stockItemId,
    stockLocationId: values.stockLocationId,
    orderLineId: values.orderLineId,
    quantity: Number(values.quantity),
    status: values.status,
  }
}

/**
 * Libération : un seul champ, et c'est tout ce que le serveur accepte.
 *
 * `ReleaseStockReservationRequest` ne connaît que `status`. La date de
 * libération et le retour de la quantité au disponible sont décidés par
 * l'action, pas par l'appelant — les laisser saisir permettrait d'antidater une
 * libération, ou de rendre plus que ce qui avait été promis.
 */
export const releaseStockReservationSchema = z.object({
  status: z.string().min(1, 'validation.required'),
})

export type ReleaseStockReservationFormValues = z.infer<typeof releaseStockReservationSchema>

export function toReleasePayload(
  values: ReleaseStockReservationFormValues,
): StockReservationStatusPayload {
  return { status: values.status }
}
