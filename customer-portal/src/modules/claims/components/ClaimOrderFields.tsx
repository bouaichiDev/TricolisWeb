import { useState } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'

import { useOrder, useOrders } from '@/modules/orders/hooks/useOrders'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { SelectField } from '@/shared/components/form/SelectField'
import { DetailField } from '@/shared/components/layout/DetailField'

import type { ClaimFormValues } from '../form/claimSchema'

interface ClaimOrderFieldsProps {
  form: UseFormReturn<ClaimFormValues>
  /** Ouverte depuis une commande ou un service : les deux sont imposés par la page. */
  locked: boolean
}

/**
 * Commande puis service concerné (§9).
 *
 * Les commandes proposées sont celles du client, lues par l'API du portail ;
 * les services, ceux de la commande choisie — changer de commande vide le
 * service. Imposés par la page d'origine, ils s'affichent sans pouvoir être
 * changés ; le backend les revérifie de toute façon.
 */
export function ClaimOrderFields({ form, locked }: ClaimOrderFieldsProps) {
  const [search, setSearch] = useState('')
  const orderId = useWatch({ control: form.control, name: 'orderId' })
  const orderServiceId = useWatch({ control: form.control, name: 'orderServiceId' })
  const orders = useOrders({ search: search || undefined })
  const order = useOrder(orderId)
  const services = orderId ? (order.data?.services ?? []) : []

  if (locked) {
    const service = services.find((candidate) => candidate.id === orderServiceId)

    return (
      <dl className="grid gap-x-6 sm:grid-cols-2">
        <DetailField label="Commande">{order.data ? order.data.reference : null}</DetailField>
        <DetailField label="Service concerné">{service ? `${service.serviceNumber} — ${service.service?.name ?? 'Service'}` : null}</DetailField>
      </dl>
    )
  }

  const orderOptions = (orders.data?.data ?? []).map((item) => ({
    value: item.id,
    label: item.customerReference ? `${item.reference} · ${item.customerReference}` : item.reference,
  }))

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div className="grid gap-2">
        <SearchInput value={search} onChange={setSearch} placeholder="Chercher une commande…" />
        <SelectField
          form={form}
          name="orderId"
          label="Commande"
          placeholder="Aucune commande"
          options={orderOptions}
        />
      </div>
      <SelectField
        form={form}
        name="orderServiceId"
        label="Service concerné (facultatif)"
        placeholder={orderId ? 'Toute la commande' : 'Choisissez d’abord une commande'}
        options={services.map((service) => ({ value: service.id, label: `${service.serviceNumber} — ${service.service?.name ?? 'Service'}` }))}
      />
    </div>
  )
}
