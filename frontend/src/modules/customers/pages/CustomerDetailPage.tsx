import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { CustomerConfigurationTab } from '../components/CustomerConfigurationTab'
import { CustomerHeader } from '../components/CustomerHeader'
import { CustomerInformationTab } from '../components/CustomerInformationTab'
import { useCustomer } from '../hooks/useCustomers'
import { CustomerCatalogsTab } from '@/modules/catalogs/components/CustomerCatalogsTab'
import { CustomerContactsTab } from '@/modules/contacts/components/EntityContactsTab'
import { EntityDocumentsTab } from '@/modules/documents/components/EntityDocumentsTab'
import { CustomerSitesTab } from '@/modules/customerSites/components/CustomerSitesTab'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

/**
 * Fiche client à onglets.
 *
 * L'onglet « Contacts » du §20 est devenu « Adresses » : le modèle rattache les
 * contacts à une adresse, pas au client. Un client porte plusieurs adresses —
 * livraison, facturation — et chacune porte les contacts qui la concernent.
 *
 * Les six autres onglets — catalogues, commandes, stock, factures,
 * réclamations, intégrations — sont explicitement réservés aux phases
 * suivantes : les afficher vides annoncerait des fonctionnalités absentes.
 */
export function CustomerDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()

  const { data: customer, isPending, error, refetch } = useCustomer(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!customer) return null

  return (
    <div className="flex flex-col gap-6">
      <CustomerHeader customer={customer} />

      <Tabs defaultValue="information">
        <TabsList className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="information">{t('customers.tabs.information')}</TabsTrigger>
          <TabsTrigger value="sites">{t('customers.tabs.sites')}</TabsTrigger>
          <TabsTrigger value="addresses">{t('customers.tabs.addresses')}</TabsTrigger>
          <TabsTrigger value="documents">{t('customers.tabs.documents')}</TabsTrigger>
          <TabsTrigger value="catalogs">{t('customers.tabs.catalogs')}</TabsTrigger>
          <TabsTrigger value="configuration">{t('customers.tabs.configuration')}</TabsTrigger>
        </TabsList>

        <TabsContent value="information" className="mt-6">
          <CustomerInformationTab customer={customer} />
        </TabsContent>

        <TabsContent value="sites" className="mt-6">
          <CustomerSitesTab customerId={customer.id} />
        </TabsContent>

        <TabsContent value="addresses" className="mt-6">
          <CustomerContactsTab entityId={customer.id} />
        </TabsContent>

        <TabsContent value="documents" className="mt-6">
          <EntityDocumentsTab entityType="customer" entityId={customer.id} />
        </TabsContent>

        {/* Le catalogue est facultatif : l'onglet existe toujours, et dit
            lui-même quand la capacité est désactivée. Le masquer laisserait
            croire que la fonction n'existe pas. */}
        <TabsContent value="catalogs" className="mt-6">
          <CustomerCatalogsTab
            customerId={customer.id}
            catalogEnabled={customer.catalogEnabled}
          />
        </TabsContent>

        <TabsContent value="configuration" className="mt-6">
          <CustomerConfigurationTab customer={customer} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
