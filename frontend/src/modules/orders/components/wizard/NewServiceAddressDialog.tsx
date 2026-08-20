import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { AddressFields } from '@/modules/addresses/components/AddressFields'
import { useCreateEntityAddress } from '@/modules/addresses/hooks/useEntityAddressMutations'
import { addressSchema, ADDRESS_FORM_DEFAULTS } from '@/modules/addresses/schemas/addressSchema'
import { ADDRESS_TYPES, CONTACT_ROLES, type AddressEntityType } from '@/modules/addresses/types/address'
import { contactsApi } from '@/modules/contacts/api/contacts.api'
import { addressesApi } from '@/modules/addresses/api/addresses.api'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { SelectField } from '@/shared/components/form/SelectField'
import { TextField } from '@/shared/components/form/TextField'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { useApiFormError } from '@/shared/hooks/useApiForm'

/**
 * Adresse et contact saisis d'un seul tenant.
 *
 * Le contact est facultatif : un prénom suffit à le créer, et son absence
 * n'empêche pas l'adresse d'exister.
 */
const schema = addressSchema.extend({
  addressType: z.string().min(1, 'validation.required'),
  contactFirstName: z.string().max(255, 'validation.max'),
  contactLastName: z.string().max(255, 'validation.max'),
  contactPhone: z.string().max(255, 'validation.max'),
  contactEmail: z.string().max(255, 'validation.max'),
  contactRole: z.string(),
})

type FormValues = z.infer<typeof schema>

const DEFAULTS: FormValues = {
  ...ADDRESS_FORM_DEFAULTS,
  addressType: 'delivery',
  contactFirstName: '',
  contactLastName: '',
  contactPhone: '',
  contactEmail: '',
  contactRole: 'delivery',
}

interface NewServiceAddressDialogProps {
  entityType: AddressEntityType
  entityId: string
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Reçoit l'identifiant de l'adresse créée, pour la sélectionner aussitôt. */
  onCreated: (addressId: string) => void
}

/**
 * Création d'une adresse de destination, sans quitter la commande.
 *
 * `StoreOrderRequest` exige un `addressId` existant : une adresse ne peut pas
 * voyager dans la charge utile de la commande. Elle est donc créée d'abord, par
 * sa propre route, puis désignée par le service.
 *
 * **Le contact est saisi dans la foulée** : sur un point de livraison, l'adresse
 * sans le nom de qui reçoit ne sert à rien, et les demander en deux temps fait
 * perdre le second. Il est rattaché à l'adresse, où le sélecteur de contacts du
 * service ira le chercher.
 *
 * L'adresse reste si la commande est abandonnée : c'est une adresse du client,
 * réutilisable, pas un brouillon.
 */
export function NewServiceAddressDialog({
  entityType,
  entityId,
  open,
  onOpenChange,
  onCreated,
}: NewServiceAddressDialogProps) {
  const { t } = useTranslation()
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: DEFAULTS })
  const { formError, handleError, clearError } = useApiFormError(form)
  const createAddress = useCreateEntityAddress({ entityType, entityId })

  const submit = form.handleSubmit(async (values) => {
    clearError()

    try {
      const address = await createAddress.mutateAsync({
        name: values.name,
        addressLine1: values.addressLine1,
        addressLine2: values.addressLine2,
        addressNumber: values.addressNumber,
        route: values.route,
        postalCode: values.postalCode,
        city: values.city,
        country: values.country,
        instructions: values.instructions,
        timeWindowFrom: values.timeWindowFrom,
        timeWindowTo: values.timeWindowTo,
        addressType: values.addressType,
        isDefault: false,
      })

      if (values.contactFirstName.trim() !== '') {
        const contact = await contactsApi.create({
          firstName: values.contactFirstName,
          lastName: values.contactLastName,
          phone: values.contactPhone || null,
          email: values.contactEmail || null,
          entityType,
          entityId,
          contactRole: values.contactRole,
          isPrimary: true,
        })

        await addressesApi.attachContact(address.id, {
          contactId: contact.id,
          contactRole: values.contactRole,
          isPrimary: true,
        })
      }

      onCreated(address.id)
      form.reset(DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('orders.services.newAddress')}</DialogTitle>
          <DialogDescription>{t('orders.services.newAddressHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={formError} />

        <div className="flex flex-col gap-5">
          <SelectField
            form={form}
            name="addressType"
            label={t('addresses.fields.addressType')}
            required
            options={ADDRESS_TYPES.map((type) => ({
              value: type,
              label: t(`addressTypes.${type}`, { defaultValue: type }),
            }))}
          />

          <AddressFields form={form} />

          <fieldset className="border-t pt-4">
            <legend className="mb-1 text-sm font-medium">{t('orders.services.contacts')}</legend>
            <p className="mb-3 text-xs text-muted-foreground">
              {t('orders.services.newContactHint')}
            </p>

            <div className="grid gap-5 sm:grid-cols-2">
              <TextField form={form} name="contactFirstName" label={t('contacts.fields.firstName')} />
              <TextField form={form} name="contactLastName" label={t('contacts.fields.lastName')} />
              <TextField form={form} name="contactPhone" label={t('contacts.fields.phone')} type="tel" />
              <TextField form={form} name="contactEmail" label={t('contacts.fields.email')} type="email" />
              <SelectField
                form={form}
                name="contactRole"
                label={t('orders.services.contactRole')}
                options={CONTACT_ROLES.map((role) => ({
                  value: role,
                  label: t(`contactRoles.${role}`),
                }))}
              />
            </div>
          </fieldset>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={() => void submit()} disabled={form.formState.isSubmitting}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
