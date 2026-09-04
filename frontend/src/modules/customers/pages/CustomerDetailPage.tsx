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
import { CustomerPricingTab } from '@/modules/pricing/components/CustomerPricingTab'
import { CustomerIntegrationsTab } from '@/modules/integrations/components/CustomerIntegrationsTab'
import { CustomerStockTab } from '@/modules/stock/components/CustomerStockTab'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

/**
 * Fiche client à onglets.
 *
 * L'onglet « Contacts » du §20 est devenu « Adresses » : le modèle rattache les
 * contacts à une adresse, pas au client. Un client porte plusieurs adresses —
 * livraison, facturation — et chacune porte les contacts qui la concernent.
 *
 * L'onglet « Tarification » montre les barèmes propres au client et ceux qui
 * s'appliquent à défaut : le §169S veut que le repli sur le barème global se
 * lise, plutôt qu'une liste vide laisse croire à l'absence de tarif.
 *
 * L'onglet « Stock » montre la marchandise du client entreposée chez le
 * transporteur. Le stock est physiquement celui du transporteur, mais reste
 * séparé métier par client, et deux routes dédiées le servent — c'est ici que
 * la séparation se voit.
 *
 * L'onglet « Intégrations » rassemble les quatre volets du modèle : imports,
 * accès API, exports et historique des envois. Les configurations d'export de
 * facture posées en Phase 6 y figurent — ce sont les mêmes, et le §34 interdit
 * une seconde table.
 *
 * Les onglets restants — commandes, réclamations — sont réservés aux phases
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
          <TabsTrigger value="pricing">{t('customers.tabs.pricing')}</TabsTrigger>
          <TabsTrigger value="stock">{t('customers.tabs.stock')}</TabsTrigger>
          <TabsTrigger value="integrations">{t('customers.tabs.integrations')}</TabsTrigger>
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

        <TabsContent value="pricing" className="mt-6">
          <CustomerPricingTab customerId={customer.id} />
        </TabsContent>

        <TabsContent value="stock" className="mt-6">
          <CustomerStockTab customerId={customer.id} />
        </TabsContent>

        <TabsContent value="integrations" className="mt-6">
          <CustomerIntegrationsTab customerId={customer.id} />
        </TabsContent>

        <TabsContent value="configuration" className="mt-6">
          <CustomerConfigurationTab customer={customer} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
