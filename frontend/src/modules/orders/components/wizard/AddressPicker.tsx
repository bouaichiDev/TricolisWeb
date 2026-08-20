import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'

import { useAddressOptions, useSiteOptions } from '../../hooks/useServiceScope'
import { NewServiceAddressDialog } from './NewServiceAddressDialog'

/**
 * Valeur désignant le client lui-même, par opposition à l'un de ses sites.
 *
 * Radix refuse une option de valeur vide ; les sites portant un ULID, ce
 * libellé ne peut entrer en collision avec aucun d'entre eux.
 */
const CUSTOMER_SOURCE = 'customer'

interface AddressPickerProps {
  /** Client de la commande — le donneur d'ordre, choisi à l'étape Général. */
  customerId: string
  value: string
  onChange: (addressId: string) => void
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
 * Une adresse absente du carnet se crée sur place, avec son contact.
 */
export function AddressPicker({
  customerId,
  value,
  onChange,
  error,
  required = false,
}: AddressPickerProps) {
  const { t } = useTranslation()
  const [source, setSource] = useState(CUSTOMER_SOURCE)
  const [creating, setCreating] = useState(false)

  const sites = useSiteOptions(customerId)
  const isSite = source !== CUSTOMER_SOURCE
  const addresses = useAddressOptions(
    isSite ? 'customer_site' : 'customer',
    isSite ? source : customerId,
  )

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
          entityType={isSite ? 'customer_site' : 'customer'}
          entityId={isSite ? source : customerId}
          open
          onOpenChange={setCreating}
          onCreated={onChange}
        />
      ) : null}
    </>
  )
}
