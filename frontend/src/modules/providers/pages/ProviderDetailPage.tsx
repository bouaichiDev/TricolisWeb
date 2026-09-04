import { Pencil } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { EntityDocumentsTab } from '@/modules/documents/components/EntityDocumentsTab'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { ProviderSettlementsTab } from '@/modules/providerSettlements/components/ProviderSettlementsTab'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { ProviderResourcesTab } from '../components/ProviderResourcesTab'
import { useProvider } from '../hooks/useProviders'

/**
 * Fiche d'un fournisseur.
 *
 * Trois onglets seulement : informations, chauffeurs, véhicules. Contrats,
 * disponibilités et grilles tarifaires relèvent d'autres phases — les afficher
 * vides annoncerait des fonctions absentes.
 *
 * Les listes des onglets ne se chargent qu'une fois l'onglet ouvert : une fiche
 * consultée pour son adresse n'a pas à interroger deux listes que personne ne
 * regarde.
 */
export function ProviderDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const [tab, setTab] = useState('information')

  const { data: provider, isPending, error, refetch } = useProvider(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!provider) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={provider.name}
        description={provider.code}
        actions={
          <PermissionGuard permission="providers.update">
            <Button asChild variant="outline">
              <Link to={`/providers/${provider.id}/edit`}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="information">{t('providers.tabs.information')}</TabsTrigger>
          <TabsTrigger value="drivers">{t('providers.tabs.drivers')}</TabsTrigger>
          <TabsTrigger value="vehicles">{t('providers.tabs.vehicles')}</TabsTrigger>
          <TabsTrigger value="settlements">{t('providers.tabs.settlements')}</TabsTrigger>
          <TabsTrigger value="documents">{t('providers.tabs.documents')}</TabsTrigger>
        </TabsList>

        <TabsContent value="information" className="mt-6">
          <SectionCard title={t('providers.identity')}>
            <dl className="grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('providers.fields.code')}
                </dt>
                <dd className="mt-1 text-sm">{provider.code}</dd>
              </div>
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('providers.fields.name')}
                </dt>
                <dd className="mt-1 text-sm">{provider.name}</dd>
              </div>
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('providers.fields.status')}
                </dt>
                <dd className="mt-1">
                  <StatusBadge status={provider.status} source="provider" />
                </dd>
              </div>

              {/* Un fournisseur porte **une** adresse et **un** contact, par
                  colonne directe : ce ne sont pas des liaisons polymorphes, et
                  un panneau de liaisons afficherait un modele qui n'est pas le
                  sien. Ils se lisent ici, et se changent au formulaire. */}
              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('providers.fields.address')}
                </dt>
                <dd className="mt-1 text-sm">
                  {provider.address
                    ? [provider.address.name, provider.address.postalCode, provider.address.city]
                        .filter(Boolean)
                        .join(' · ')
                    : t('providers.noAddress')}
                </dd>
              </div>

              <div>
                <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('providers.fields.contact')}
                </dt>
                <dd className="mt-1 text-sm">
                  {provider.contact
                    ? [
                        `${provider.contact.firstName ?? ''} ${provider.contact.lastName ?? ''}`.trim(),
                        provider.contact.email,
                        provider.contact.phone,
                      ]
                        .filter(Boolean)
                        .join(' · ')
                    : t('providers.noContact')}
                </dd>
              </div>
            </dl>
          </SectionCard>
        </TabsContent>

        <TabsContent value="drivers" className="mt-6">
          <ProviderResourcesTab
            providerId={provider.id}
            kind="drivers"
            active={tab === 'drivers'}
          />
        </TabsContent>

        <TabsContent value="vehicles" className="mt-6">
          <ProviderResourcesTab
            providerId={provider.id}
            kind="vehicles"
            active={tab === 'vehicles'}
          />
        </TabsContent>

        <TabsContent value="settlements" className="mt-6">
          <ProviderSettlementsTab providerId={provider.id} active={tab === 'settlements'} />
        </TabsContent>

        <TabsContent value="documents" className="mt-6">
          <EntityDocumentsTab entityType="provider" entityId={provider.id} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
