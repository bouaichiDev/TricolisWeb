import { useWatch, type UseFormReturn } from 'react-hook-form'

import { Card, CardContent, CardHeader, CardTitle } from '@/shared/components/ui/card'

import type { OrderFormValues } from '../form/orderSchema'
import type { FormOptions } from '../types'

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="text-right">{value || '—'}</span>
    </div>
  )
}

/** Dernière étape : relire avant d’envoyer. Rien n’est encore enregistré. */
export function ReviewStep({ form, options, documents = [] }: { form: UseFormReturn<OrderFormValues>; options: FormOptions; documents?: File[] }) {
  const values = useWatch({ control: form.control }) as OrderFormValues
  const serviceName = (id: string) => options.services.find((service) => service.id === id)?.name ?? '—'
  const addressName = (id: string) => options.addresses.find((address) => address.id === id)?.name ?? 'Adresse existante'

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Commande</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <Row label="Votre référence" value={values.customerReference} />
          <Row label="Référence externe" value={values.externalReference} />
          <Row label="Lignes" value={String(values.lines?.length ?? 0)} />
          <Row label="Colis" value={String(values.packages?.length ?? 0)} />
          <Row label="Documents" value={documents.length === 0 ? 'Aucun' : documents.map((file) => file.name).join(', ')} />
          {values.workerRemark && <p className="text-muted-foreground pt-2 text-sm">{values.workerRemark}</p>}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Articles</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {values.lines?.map((line) => (
            <Row key={line.uid} label={line.articleLabel || line.name || 'Ligne'} value={`× ${line.quantity}`} />
          ))}
        </CardContent>
      </Card>
      {values.services?.map((service, index) => (
        <Card key={index} className="md:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">
              Service {index + 1} — {serviceName(service.serviceId)}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Row label="Date" value={[service.requestedDate, service.requestedFrom && `${service.requestedFrom}–${service.requestedTo}`].filter(Boolean).join(' ')} />
            <Row
              label="Adresse"
              value={service.addressMode === 'new'
                ? `${service.recipient.firstName} ${service.recipient.lastName}, ${service.recipient.addressLine1}, ${service.recipient.postalCode} ${service.recipient.city}`
                : addressName(service.addressId)}
            />
            <Row label="Contacts" value={service.contacts.map((contact) => `${contact.firstName} ${contact.lastName}`.trim()).join(', ')} />
            <Row label="Colis" value={String(service.packageUids.length)} />
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
