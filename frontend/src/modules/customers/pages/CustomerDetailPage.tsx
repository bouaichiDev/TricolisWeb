import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { CustomerConfigurationTab } from '../components/CustomerConfigurationTab'
import { CustomerHeader } from '../components/CustomerHeader'
import { CustomerInformationTab } from '../components/CustomerInformationTab'
import { useCustomer } from '../hooks/useCustomers'
import { CustomerContactsTab } from '@/modules/contacts/components/EntityContactsTab'
import { CustomerDocumentsTab } from '@/modules/documents/components/EntityDocumentsTab'
import { CustomerSitesTab } from '@/modules/customerSites/components/CustomerSitesTab'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

/**
 * Fiche client à onglets.
 *
 * Les cinq onglets du §20 sont livrés. Les six autres — catalogues, commandes,
 * stock, factures, réclamations, intégrations — sont explicitement réservés aux
 * phases suivantes : les afficher vides annoncerait des fonctionnalités
 * absentes.
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
          <TabsTrigger value="contacts">{t('customers.tabs.contacts')}</TabsTrigger>
          <TabsTrigger value="documents">{t('customers.tabs.documents')}</TabsTrigger>
          <TabsTrigger value="configuration">{t('customers.tabs.configuration')}</TabsTrigger>
        </TabsList>

        <TabsContent value="information" className="mt-6">
          <CustomerInformationTab customer={customer} />
        </TabsContent>

        <TabsContent value="sites" className="mt-6">
          <CustomerSitesTab customerId={customer.id} />
        </TabsContent>

        <TabsContent value="contacts" className="mt-6">
          <CustomerContactsTab entityId={customer.id} />
        </TabsContent>

        <TabsContent value="documents" className="mt-6">
          <CustomerDocumentsTab entityId={customer.id} />
        </TabsContent>

        <TabsContent value="configuration" className="mt-6">
          <CustomerConfigurationTab customer={customer} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
