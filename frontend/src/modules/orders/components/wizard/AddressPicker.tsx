import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { AddressEntityType } from '@/modules/addresses/types/address'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import { useAuth } from '@/shared/hooks/useAuth'

import { useAddressOptions, useSiteOptions } from '../../hooks/useServiceScope'
import type { ServiceContactDraft } from '../../schemas/orderDraft'
import { NewServiceAddressDialog } from './NewServiceAddressDialog'

/**
 * Sources d'adresses, par opposition aux sites du client.
 *
 * Radix refuse une option de valeur vide ; les sites portant un ULID, ces
 * libellés ne peuvent entrer en collision avec aucun d'entre eux.
 */
const CUSTOMER_SOURCE = 'customer'
const FREE_SOURCE = 'free'

interface AddressPickerProps {
  /** Client de la commande — le donneur d'ordre, choisi à l'étape Général. */
  customerId: string
  value: string
  onChange: (addressId: string) => void
  /**
   * Adresse créée sur place, avec le contact saisi dans la foulée.
   *
   * Sans ce rappel, l'écran retomberait sur `onChange` et le contact serait
   * perdu — donc à ressaisir dans la section Contacts.
   */
  onCreated?: (addressId: string, contact: ServiceContactDraft | null) => void
  error?: string
  required?: boolean
}

/**
 * Choix de l'adresse d'exécution d'un service.
 *
 * Le client n'est pas redemandé ici : c'est celui de la commande, choisi à
 * l'étape Général. **Le destinataire s'exprime par l'adresse**, pas par un
 * second client — un chargement chez le donneur d'ordre et une livraison chez
 * son client sont deux adresses du même carnet.
 *
 * L'API expose les adresses **par entité** : celles du client et celles de
 * chacun de ses sites sont des listes distinctes, d'où le choix de la source
 * avant celui de l'adresse. Tout charger d'un coup demanderait une requête par
 * site.
 *
 * Une adresse absente du carnet se crée sur place. Elle n'est **pas** rattachée
 * au donneur d'ordre : son carnet grossirait d'une ligne par livraison chez un
 * client final. Le lien avec la commande est ailleurs, et existe déjà au
 * diagramme — `order_services.address_id`. L'adresse est portée par
 * l'organisation, d'où la troisième source, hors carnet client.
 */
export function AddressPicker({
  customerId,
  value,
  onChange,
  onCreated,
  error,
  required = false,
}: AddressPickerProps) {
  const { t } = useTranslation()
  const { organizationId } = useAuth()
  const [source, setSource] = useState(CUSTOMER_SOURCE)
  const [creating, setCreating] = useState(false)

  const sites = useSiteOptions(customerId)
  const [entityType, entityId] = resolveEntity(source, customerId, organizationId)
  const addresses = useAddressOptions(entityType, entityId)

  const noCustomer = customerId === ''

  return (
    <>
      <AsyncSelect
        label={t('orders.services.addressSource')}
        value={source}
        onChange={(next) => {
          setSource(next)
          // L'adresse retenue appartenait à l'autre source : la garder
          // afficherait un identifiant sans libellé.
          onChange('')
        }}
        options={[
          { value: CUSTOMER_SOURCE, label: t('orders.services.sourceCustomer') },
          ...sites.options,
          { value: FREE_SOURCE, label: t('orders.services.sourceFree') },
        ]}
        isLoading={sites.isLoading}
        disabled={noCustomer}
        description={noCustomer ? t('orders.services.pickCustomerFirst') : undefined}
      />

      <div className="flex flex-col gap-2">
        <AsyncSelect
          label={t('orders.services.address')}
          value={value}
          onChange={onChange}
          options={addresses.options}
          isLoading={addresses.isLoading}
          disabled={noCustomer}
          required={required}
          description={
            noCustomer
              ? t('orders.services.pickCustomerFirst')
              : addresses.options.length === 0 && !addresses.isLoading
                ? t('orders.services.noAddress')
                : t('orders.services.addressHint')
          }
          error={error}
        />

        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-fit"
          disabled={noCustomer}
          onClick={() => setCreating(true)}
        >
          <Plus className="size-4" aria-hidden />
          {t('orders.services.newAddress')}
        </Button>
      </div>

      {creating ? (
        <NewServiceAddressDialog
          open
          onOpenChange={setCreating}
          onCreated={(addressId, contact) => {
            // L'adresse créée est hors carnet client : la source bascule, sinon
            // la liste affichée ne la contiendrait pas.
            setSource(FREE_SOURCE)
            if (onCreated) onCreated(addressId, contact)
            else onChange(addressId)
          }}
        />
      ) : null}
    </>
  )
}

/** Entité dont les adresses sont listées, selon la source choisie. */
function resolveEntity(
  source: string,
  customerId: string,
  organizationId: string | null,
): [AddressEntityType, string] {
  if (source === CUSTOMER_SOURCE) return ['customer', customerId]
  if (source === FREE_SOURCE) return ['organization', organizationId ?? '']

  return ['customer_site', source]
}
