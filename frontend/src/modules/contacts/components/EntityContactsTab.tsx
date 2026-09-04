import { useTranslation } from 'react-i18next'

import { EntityAddressesPanel } from '@/modules/addresses/components/EntityAddressesPanel'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Adresses d'un client, chacune avec ses contacts.
 *
 * Le modèle ne prévoit pas de « contacts du client » flottants : un client
 * porte des **adresses** — livraison, facturation — et chaque adresse porte les
 * contacts qui la concernent. Qui prévenir dépend du lieu, pas du client dans
 * l'absolu : le magasinier d'un entrepôt n'est pas le comptable du siège.
 *
 * Cet onglet affichait auparavant un message d'indisponibilité : `GET /contacts`
 * et `GET /addresses` n'acceptaient pas `entityType` / `entityId`, alors que la
 * création les acceptait déjà. Le filtre existe désormais des deux côtés.
 */
export function CustomerContactsTab({ entityId }: { entityId: string }) {
  const { t } = useTranslation()

  return (
    <SectionCard title={t('addresses.title')} description={t('addresses.customerHint')}>
      <EntityAddressesPanel
        entityType="customer"
        entityId={entityId}
        emptyMessage={t('addresses.emptyCustomer')}
      />
    </SectionCard>
  )
}
