import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import type { ProviderProfile } from '../api/profileApi'

/**
 * La fiche du fournisseur, en lecture seule : elle se modifie au back-office
 * du transporteur, jamais depuis le portail.
 */
export function ProviderSheet({ provider }: { provider: ProviderProfile['provider'] }) {
  const { address, contact } = provider
  const place = address ? [address.line1, [address.postalCode, address.city].filter(Boolean).join(' ')].filter(Boolean).join(' · ') : null
  const person = contact ? [`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim(), contact.email, contact.phone].filter(Boolean).join(' · ') : null

  return (
    <SectionCard title="Fournisseur" description="Ces informations sont tenues par votre transporteur.">
      <dl className="grid gap-x-6 sm:grid-cols-2">
        <DetailField label="Nom">{provider.name}</DetailField>
        <DetailField label="Code">{provider.code}</DetailField>
        <DetailField label="Adresse">{place}</DetailField>
        <DetailField label="Contact">{person}</DetailField>
      </dl>
    </SectionCard>
  )
}
