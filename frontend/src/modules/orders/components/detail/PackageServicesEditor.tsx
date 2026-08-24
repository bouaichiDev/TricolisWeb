import { Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import { usePermission } from '@/shared/hooks/usePermission'

import {
  useAttachPackageToService,
  useDetachPackageFromService,
} from '../../hooks/useServicePackages'
import type { OrderService } from '../../types/orderDetail'

interface PackageServicesEditorProps {
  orderId: string
  packageId: string
  services: OrderService[]
  editable: boolean
}

/**
 * Services qui prennent ce colis en charge.
 *
 * C'est la relation `OrderServicePackage`, lue **à l'envers** : la fiche d'un
 * service dit quels colis il transporte, celle d'un colis dit par quels
 * services il passe. Un même colis est souvent chargé par l'un et livré par
 * l'autre, et cette liste-là ne se déduisait d'aucun écran.
 *
 * Aucune route n'expose les liaisons d'un colis — elles sont imbriquées sous le
 * service. La liste est donc **dérivée** de `services[].packages[]`, que le
 * détail de la commande porte déjà : inventer une route pour retrouver ce qui
 * est là serait un appel de plus pour la même donnée.
 *
 * Le détacher passe en revanche par le service propriétaire du lien, seul
 * chemin que l'API accepte.
 *
 * On ne peut y relier que des services **déjà dans la commande** : un
 * `OrderService` exige une adresse, une séquence, une durée et des montants, et
 * les demander depuis la fiche d'un colis dupliquerait le formulaire de
 * l'onglet Services. Quand ils sont tous liés, l'écran le dit plutôt que de
 * retirer le sélecteur en silence.
 */
export function PackageServicesEditor({
  orderId,
  packageId,
  services,
  editable,
}: PackageServicesEditorProps) {
  const { t } = useTranslation()
  const mayUpdate = usePermission('order_services.update')
  const canEdit = editable && mayUpdate

  const [adding, setAdding] = useState('')
  const [error, setError] = useState<string | null>(null)

  const attach = useAttachPackageToService(orderId)
  const detach = useDetachPackageFromService(orderId)

  const onError = (cause: unknown) =>
    setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))

  /** Le lien de ce colis dans chaque service, quand il existe. */
  const linked = services
    .map((service) => ({
      service,
      link: (service.packages ?? []).find((item) => item.packageId === packageId),
    }))
    .filter((entry) => entry.link !== undefined)

  const available = services.filter(
    (service) => !linked.some((entry) => entry.service.id === service.id),
  )

  const label = (service: OrderService) =>
    service.service?.name ?? service.serviceNumber

  return (
    <div className="flex flex-col gap-3">
      <div>
        <p className="text-sm font-medium">{t('orders.packages.services')}</p>
        <p className="text-xs text-muted-foreground">{t('orders.packages.servicesHint')}</p>
      </div>

      {error !== null ? <p className="text-sm text-destructive">{error}</p> : null}

      {linked.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('orders.packages.noService')}</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {linked.map(({ service, link }) => (
            <li
              key={service.id}
              className="flex items-center gap-3 rounded-md border px-3 py-2"
            >
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm">{label(service)}</p>
                <p className="text-xs text-muted-foreground">
                  {t('orders.services.position', { position: service.sequence })} ·{' '}
                  {service.serviceNumber}
                  {link?.quantity != null ? ` · ${String(link.quantity)}` : ''}
                </p>
              </div>

              {canEdit ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  disabled={detach.isPending}
                  onClick={() => {
                    setError(null)
                    detach.mutate(
                      { serviceId: service.id, linkId: link?.id ?? '' },
                      { onError },
                    )
                  }}
                  title={t('orders.packages.detachService')}
                  aria-label={t('orders.packages.detachService')}
                >
                  <X className="size-4" aria-hidden />
                </Button>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {canEdit && available.length === 0 ? (
        // Le selecteur disparaissait sans rien dire : on ne savait pas si lier
        // un service etait impossible, ou seulement introuvable.
        <p className="text-xs text-muted-foreground">{t('orders.packages.allServicesLinked')}</p>
      ) : null}

      {canEdit && available.length > 0 ? (
        <div className="flex items-end gap-2">
          <div className="flex-1">
            <AsyncSelect
              label={t('orders.packages.attachService')}
              value={adding}
              onChange={setAdding}
              options={available.map((service) => ({
                value: service.id,
                label: label(service),
                hint: service.serviceNumber,
              }))}
            />
          </div>

          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={adding === '' || attach.isPending}
            onClick={() => {
              setError(null)
              attach.mutate(
                { serviceId: adding, packageId },
                { onSuccess: () => setAdding(''), onError },
              )
            }}
          >
            <Plus className="size-4" aria-hidden />
            {t('orders.packages.attach')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
