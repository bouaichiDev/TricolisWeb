import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { claimsApi } from '../api/claimsApi'
import { ClaimOrderFields } from '../components/ClaimOrderFields'
import { CLAIM_FIELDS, claimSchema, toClaimPayload, type ClaimFormValues } from '../form/claimSchema'
import { useClaimMutation } from '../hooks/useClaims'

/**
 * Nouvelle réclamation (§9) — depuis la liste, ou depuis une commande ou un
 * service, qui imposent alors `orderId` et `orderServiceId` par l'URL.
 */
export function ClaimCreatePage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const locked = params.has('orderId')
  const [formError, setFormError] = useState<string | null>(null)
  const create = useClaimMutation(claimsApi.create)

  const form = useForm<ClaimFormValues>({
    resolver: zodResolver(claimSchema),
    defaultValues: { orderId: params.get('orderId') ?? '', orderServiceId: params.get('orderServiceId') ?? '', claimType: '', title: '', description: '' },
  })

  // Changer de commande vide le service : il appartenait à la précédente.
  const orderId = useWatch({ control: form.control, name: 'orderId' })
  const previous = useRef(orderId)
  useEffect(() => {
    if (!locked && previous.current !== orderId) form.setValue('orderServiceId', '')
    previous.current = orderId
  }, [orderId, locked, form])

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      const claim = await create.mutateAsync(toClaimPayload(values))
      toast.success('Réclamation envoyée à votre transporteur.')
      navigate(paths.claim(claim.id))
    } catch (caught) {
      // Erreurs de champ sur les champs ; le reste (409, 403…) en tête du formulaire.
      const orphans = applyServerErrors(form, caught, CLAIM_FIELDS)
      if (!(caught instanceof ApiError && caught.isValidation)) setFormError(errorMessage(caught))
      else if (orphans.length > 0) setFormError(orphans[0])
    }
  })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Nouvelle réclamation" description="Décrivez le problème : votre transporteur la prend en charge et vous tient informé de son traitement." />

      <form onSubmit={(event) => void submit(event)} className="flex flex-col gap-6" noValidate>
        {formError && (
          <Alert variant="destructive">
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        )}

        <SectionCard title="Concerne">
          <ClaimOrderFields form={form} locked={locked} />
        </SectionCard>

        <SectionCard title="Réclamation">
          <div className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field form={form} name="claimType" label="Type de réclamation" placeholder="Ex. : colis endommagé, retard…" maxLength={64} />
              <Field form={form} name="title" label="Titre" maxLength={255} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="claim-description">Description</Label>
              <Textarea id="claim-description" rows={5} maxLength={5000} {...form.register('description')} />
            </div>
          </div>
        </SectionCard>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={() => navigate(-1)}>
            Annuler
          </Button>
          <Button type="submit" disabled={create.isPending}>
            Envoyer la réclamation
          </Button>
        </div>
      </form>
    </div>
  )
}
