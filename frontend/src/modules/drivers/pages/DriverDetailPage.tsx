import { Pencil } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { useState } from 'react'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { DriverActivityReport } from '../components/DriverActivityReport'
import { useDriver } from '../hooks/useDrivers'

/** Fiche d'un chauffeur. Disponibilités et compétences relèvent d'autres phases. */
export function DriverDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()
  const [tab, setTab] = useState('information')

  const { data: driver, isPending, error, refetch } = useDriver(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!driver) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={driver.name}
        description={driver.code}
        actions={
          <PermissionGuard permission="drivers.update">
            <Button asChild variant="outline">
              <Link to={`/drivers/${driver.id}/edit`}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="information">{t('drivers.tabs.information')}</TabsTrigger>
          <TabsTrigger value="report">{t('drivers.tabs.report')}</TabsTrigger>
        </TabsList>

        <TabsContent value="report" className="mt-6">
          <DriverActivityReport driverId={driver.id} />
        </TabsContent>

        <TabsContent value="information" className="mt-6">
      <SectionCard title={t('drivers.identity')}>
        <dl className="grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.code')}
            </dt>
            <dd className="mt-1 text-sm">{driver.code}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.provider')}
            </dt>
            <dd className="mt-1 text-sm">
              {/* Un chauffeur du transporteur n'a pas de fournisseur : le dire
                  vaut mieux qu'une case vide, qu'on prend pour un chargement
                  qui n'a pas abouti. */}
              {driver.providerId === null ? (
                <span className="text-muted-foreground">{t('drivers.ownDriver')}</span>
              ) : (
                <Link to={`/providers/${driver.providerId}`} className="text-primary hover:underline">
                  {driver.providerName ?? driver.providerId}
                </Link>
              )}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.status')}
            </dt>
            <dd className="mt-1">
              <StatusBadge status={driver.status} source="driver" />
            </dd>
          </div>

          {/* Le compte du chauffeur : c'est par lui qu'il ouvre l'application,
              et par lui qu'on saura plus tard qui a fait quoi. Le nom et
              l'adresse se corrigent sur sa fiche, pas ici. */}
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.account')}
            </dt>
            <dd className="mt-1 text-sm">
              {driver.user == null ? (
                <span className="text-muted-foreground">{t('drivers.noAccount')}</span>
              ) : driver.membershipId == null ? (
                // Le compte existe mais n'appartient plus a cette organisation :
                // l'afficher sans lien vaut mieux qu'un lien qui echoue.
                <span>
                  {`${driver.user.firstName} ${driver.user.lastName}`.trim()} · {driver.user.email}
                </span>
              ) : (
                <Link
                  to={`/users/${driver.membershipId}`}
                  className="text-primary hover:underline"
                >
                  {`${driver.user.firstName} ${driver.user.lastName}`.trim()} · {driver.user.email}
                </Link>
              )}
            </dd>
          </div>
        </dl>
      </SectionCard>
        </TabsContent>
      </Tabs>
    </div>
  )
}
