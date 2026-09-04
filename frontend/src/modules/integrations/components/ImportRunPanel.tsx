import { CircleCheck, Play } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'

import { orderKeys } from '@/modules/orders/hooks/useOrders'
import { useAgencyOptions, useDepotOptions } from '@/modules/orders/hooks/useOrderScope'
import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'

import { customerImportConfigurationsApi } from '../api/customer-import-configurations.api'
import type { ImportResult } from '../types/customerIntegration'

interface ImportRunPanelProps {
  configurationId: string
  hasMapping: boolean
  isActive: boolean
}

/**
 * Importer réellement le fichier.
 *
 * Le pendant écrivant de l'essai : même lecture, même correspondance, mais les
 * commandes sont créées.
 *
 * **L'agence est obligatoire**, et ce n'est pas un choix d'interface :
 * `orders.agency_id` est `NOT NULL`, une commande sans agence n'existe pas en
 * base. Le fichier d'un client ne la porte pas — il ne connaît pas notre
 * organisation — donc elle se choisit ici. Le dépôt reste facultatif, et c'est
 * précisément ce qui reste à faire ensuite sur les commandes importées.
 *
 * **Tout ou rien.** Une seule commande invalide fait refuser le fichier entier,
 * sans rien écrire. Un import à moitié abouti laisserait un état que personne
 * ne saurait reprendre.
 */
export function ImportRunPanel({ configurationId, hasMapping, isActive }: ImportRunPanelProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const input = useRef<HTMLInputElement>(null)

  const [agencyId, setAgencyId] = useState('')
  const [depotId, setDepotId] = useState('')

  const agencies = useAgencyOptions()
  const depots = useDepotOptions(agencyId)

  const run = useMutation({
    mutationFn: (file: File) =>
      customerImportConfigurationsApi.import(configurationId, file, agencyId, depotId),
    onSuccess: () => {
      // Les commandes viennent d'apparaître : la liste doit les montrer.
      void queryClient.invalidateQueries({ queryKey: orderKeys.all })
    },
  })

  const ready = hasMapping && isActive && agencyId !== ''
  const result: ImportResult | undefined = run.data

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <AsyncSelect
          label={t('orders.fields.agency')}
          value={agencyId}
          onChange={(next) => {
            setAgencyId(next)
            // Le dépôt appartient à l'agence : en changer invalide le choix.
            setDepotId('')
          }}
          options={agencies.options}
          isLoading={agencies.isLoading}
          required
          description={t('integrations.imports.run.agencyHint')}
        />

        <AsyncSelect
          label={t('orders.fields.depot')}
          value={depotId}
          onChange={setDepotId}
          options={depots.options}
          isLoading={depots.isLoading}
          disabled={agencyId === ''}
          description={t('integrations.imports.run.depotHint')}
        />
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <input
          ref={input}
          type="file"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            if (file !== undefined) run.mutate(file)
            event.target.value = ''
          }}
        />

        <Button
          type="button"
          disabled={!ready || run.isPending}
          onClick={() => input.current?.click()}
        >
          <Play className="size-4" aria-hidden />
          {run.isPending ? t('common.loading') : t('integrations.imports.run.choose')}
        </Button>

        <span className="text-xs text-muted-foreground">
          {!isActive
            ? t('integrations.imports.run.inactive')
            : !hasMapping
              ? t('integrations.imports.preview.noMapping')
              : agencyId === ''
                ? t('integrations.imports.run.pickAgency')
                : t('integrations.imports.run.hint')}
        </span>
      </div>

      {run.error === null || run.error === undefined ? null : (
        <div className="flex flex-col gap-2">
          <FormErrorSummary
            message={
              run.error instanceof ApiError ? run.error.message : t('errors.unexpected')
            }
          />

          {/* Les erreurs sont préfixées du rang de la commande dans le fichier :
              sans cela, on saurait qu'une unité manque sans savoir laquelle des
              trente commandes la réclame. */}
          {run.error instanceof ApiError && Object.keys(run.error.errors).length > 0 ? (
            <ul className="flex flex-col gap-1 rounded-md border border-destructive/30 bg-destructive/5 p-3">
              {Object.entries(run.error.errors).map(([field, messages]) => (
                <li key={field} className="text-xs">
                  <span className="font-mono">{field}</span>
                  <span className="text-muted-foreground"> — {messages[0]}</span>
                </li>
              ))}
            </ul>
          ) : null}

          <p className="text-xs text-muted-foreground">
            {t('integrations.imports.run.nothingWritten')}
          </p>
        </div>
      )}

      {result === undefined ? null : (
        <div className="flex flex-col gap-3 rounded-md border border-success/30 bg-success/5 p-3">
          <p className="flex items-center gap-1.5 text-sm text-success">
            <CircleCheck className="size-4" aria-hidden />
            {t('integrations.imports.run.created', { count: result.orders.length })}
          </p>

          <ul className="flex flex-wrap gap-2">
            {result.orders.map((order) => (
              <li key={order.id}>
                <Link
                  to={`/orders/${order.id}`}
                  className="rounded-md border bg-card px-2.5 py-1 text-xs hover:bg-muted"
                >
                  {order.orderNumber}
                  {order.externalReference === null ? null : (
                    <span className="text-muted-foreground"> · {order.externalReference}</span>
                  )}
                </Link>
              </li>
            ))}
          </ul>

          <Link to="/orders?withoutDepot=1" className="text-xs underline">
            {t('integrations.imports.run.seeImported')}
          </Link>
        </div>
      )}
    </div>
  )
}
