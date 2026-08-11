import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { TextField } from '@/shared/components/form/TextField'

/**
 * Champs d'adresse, greffes sur le formulaire de l'entite porteuse.
 *
 * Le composant ne cree pas son propre `useForm` : une adresse se saisit
 * toujours dans le formulaire de ce qu'elle situe — un site, une agence — et un
 * second formulaire imbrique casserait la validation d'ensemble.
 */
export function AddressFields<T extends FieldValues>({ form }: { form: UseFormReturn<T> }) {
  const { t } = useTranslation()
  const field = (name: string) => name as Path<T>

  return (
    <div className="grid gap-5 sm:grid-cols-2">
      <TextField form={form} name={field('name')} label={t('addresses.fields.name')} />
      <TextField
        form={form}
        name={field('addressLine1')}
        label={t('addresses.fields.addressLine1')}
        required
      />
      <TextField
        form={form}
        name={field('addressLine2')}
        label={t('addresses.fields.addressLine2')}
      />
      <TextField
        form={form}
        name={field('addressNumber')}
        label={t('addresses.fields.addressNumber')}
      />
      <TextField form={form} name={field('route')} label={t('addresses.fields.route')} />
      <TextField form={form} name={field('postalCode')} label={t('addresses.fields.postalCode')} />
      <TextField form={form} name={field('city')} label={t('addresses.fields.city')} />
      <TextField
        form={form}
        name={field('country')}
        label={t('addresses.fields.country')}
        placeholder="FR"
        description={t('addresses.countryHint')}
      />
      <TextField
        form={form}
        name={field('timeWindowFrom')}
        label={t('addresses.fields.timeWindowFrom')}
        placeholder="08:00"
      />
      <TextField
        form={form}
        name={field('timeWindowTo')}
        label={t('addresses.fields.timeWindowTo')}
        placeholder="18:00"
      />
      <div className="sm:col-span-2">
        <TextField
          form={form}
          name={field('instructions')}
          label={t('addresses.fields.instructions')}
        />
      </div>
    </div>
  )
}
