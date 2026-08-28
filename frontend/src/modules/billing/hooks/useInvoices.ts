import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { invoicesApi } from '../api/invoices.api'
import type {
  BillableServiceFilters,
  InvoiceFilters,
  InvoiceLinePayload,
  InvoicePayload,
} from '../types/invoice'

export const invoiceKeys = {
  all: ['invoices'] as const,
  list: (filters: InvoiceFilters) => [...invoiceKeys.all, 'list', filters] as const,
  detail: (id: string) => [...invoiceKeys.all, 'detail', id] as const,
  closure: (id: string) => [...invoiceKeys.all, 'closure', id] as const,
  billable: (customerId: string, filters: BillableServiceFilters) =>
    ['billable-services', customerId, filters] as const,
  suggestions: (customerId: string, field: string, term: string) =>
    ['billable-suggestions', customerId, field, term] as const,
}

export function useInvoiceList(filters: InvoiceFilters) {
  return useQuery({
    queryKey: invoiceKeys.list(filters),
    queryFn: () => invoicesApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useInvoice(id: string | null) {
  return useQuery({
    queryKey: invoiceKeys.detail(id ?? ''),
    queryFn: () => invoicesApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

/**
 * Ce que la clôture déclenchera.
 *
 * Rechargé à chaque ouverture du dialogue : une destination peut avoir été
 * activée ou coupée entre-temps, et annoncer un envoi qui n'aura pas lieu est
 * pire que de ne rien annoncer.
 */
export function useInvoiceClosurePreview(id: string | null, enabled: boolean) {
  return useQuery({
    queryKey: invoiceKeys.closure(id ?? ''),
    queryFn: () => invoicesApi.closurePreview(id as string),
    enabled: enabled && id !== null && id !== '',
    staleTime: 0,
  })
}

/**
 * Les prestations facturables d'un client.
 *
 * Sans client choisi, aucune requête : la route n'existe que sous un client, et
 * demander « toutes les prestations facturables » n'aurait pas de sens.
 */
export function useBillableServices(customerId: string, filters: BillableServiceFilters) {
  return useQuery({
    queryKey: invoiceKeys.billable(customerId, filters),
    queryFn: () => invoicesApi.billableServices(customerId, filters),
    enabled: customerId !== '',
    placeholderData: (previous) => previous,
  })
}

/**
 * Les valeurs proposées pour compléter un filtre.
 *
 * Gardées une minute : la liste des numéros facturables ne bouge qu'à la
 * création d'une facture, et retourner au serveur à chaque frappe ferait dix
 * requêtes pour un numéro de commande.
 */
export function useBillableSuggestions(
  customerId: string,
  field: 'service' | 'order',
  term: string,
) {
  return useQuery({
    queryKey: invoiceKeys.suggestions(customerId, field, term),
    queryFn: () => invoicesApi.billableSuggestions(customerId, field, term),
    enabled: customerId !== '',
    staleTime: 60 * 1000,
    placeholderData: (previous) => previous,
  })
}

export function useCreateInvoice() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: InvoicePayload) => invoicesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      // Les prestations retenues ne sont plus facturables : le selecteur doit
      // cesser de les proposer.
      void queryClient.invalidateQueries({ queryKey: ['billable-services'] })
      toast.success(t('toast.created'))
    },
  })
}

export function useDeleteInvoice() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => invoicesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['billable-services'] })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Corriger l'en-tête d'une facture au brouillon.
 *
 * Le détail **et** la liste sont invalidés : le numéro et la date s'affichent
 * dans les deux, et n'en rafraîchir qu'un laisserait deux vérités à l'écran.
 */
export function useUpdateInvoice(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<InvoicePayload>) => invoicesApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

/** Verser plusieurs prestations dans une facture existante. */
export function useAddInvoiceLines(invoiceId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payloads: InvoiceLinePayload[]) => invoicesApi.addLines(invoiceId, payloads),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['billable-services'] })
      toast.success(t('toast.created'))
    },
  })
}

export function useAddInvoiceLine(invoiceId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: InvoiceLinePayload) => invoicesApi.addLine(invoiceId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoiceId) })
      void queryClient.invalidateQueries({ queryKey: ['billable-services'] })
      toast.success(t('toast.created'))
    },
  })
}

export function useRemoveInvoiceLine(invoiceId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (lineId: string) => invoicesApi.removeLine(invoiceId, lineId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoiceId) })
      void queryClient.invalidateQueries({ queryKey: ['billable-services'] })
      toast.success(t('toast.deleted'))
    },
  })
}

/**
 * Clôturer la facture.
 *
 * La liste est invalidée comme le détail : le statut change, et une facture
 * clôturée ne se modifie plus — l'écran doit cesser d'en proposer les actions.
 */
export function useCloseInvoice(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: () => invoicesApi.close(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      toast.success(t('billing.invoices.closed'))
    },
  })
}
