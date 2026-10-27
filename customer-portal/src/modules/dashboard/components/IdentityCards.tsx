import type { CustomerContext } from '@/shared/api/types'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'

const FEATURE_LABELS: Record<keyof CustomerContext['customer']['features'], string> = {
  catalog: 'Catalogue',
  stock: 'Stock',
  tracking: 'Suivi',
  packages: 'Colis',
  appointments: 'Rendez-vous',
}

/** Qui travaille, pour quel client, avec quels rôles — en sections du back-office. */
export function IdentityCards({ context }: { context: CustomerContext }) {
  const { customer, carrier, user, roles, membership } = context
  const features = Object.entries(customer.features).filter(([, enabled]) => enabled)

  return (
    <div className="grid gap-6 md:grid-cols-2">
      <SectionCard title="Votre entreprise">
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label="Client">{customer.name}</DetailField>
          <DetailField label="Raison sociale">{customer.legalName}</DetailField>
          <DetailField label="Transporteur">{carrier.name}</DetailField>
          <DetailField label="Modules ouverts">
            {features.length > 0 && (
              <span className="flex flex-wrap gap-1">
                {features.map(([key]) => (
                  <Badge key={key} variant="secondary" className="font-normal">
                    {FEATURE_LABELS[key as keyof typeof FEATURE_LABELS]}
                  </Badge>
                ))}
              </span>
            )}
          </DetailField>
        </dl>
      </SectionCard>
      <SectionCard title="Votre accès">
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label="Nom">{`${user.firstName} ${user.lastName}`.trim()}</DetailField>
          <DetailField label="E-mail">{user.email}</DetailField>
          <DetailField label="Rôles">
            {(roles.length > 0 || membership.isOwner) && (
              <span className="flex flex-wrap gap-1">
                {membership.isOwner && <Badge>Propriétaire</Badge>}
                {roles.map((role) => (
                  <Badge key={role.id} variant="outline" className="font-normal">
                    {role.name}
                  </Badge>
                ))}
              </span>
            )}
          </DetailField>
        </dl>
      </SectionCard>
    </div>
  )
}
