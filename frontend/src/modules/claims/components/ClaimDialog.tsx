import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { OrderService } from '@/modules/orders/types/orderDetail'
import { ApiError } from '@/shared/api/errors'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { ClaimForm } from './ClaimForm'
import { useClaim, useCreateClaim, useUpdateClaim } from '../hooks/useClaims'
import {
  CLAIM_FORM_DEFAULTS,
  toClaimFormValues,
  toClaimPayload,
  toClaimUpdatePayload,
  type ClaimFormValues,
} from '../schemas/claimForm'
import type { Claim } from '../types/claim'

interface ClaimDialogProps {
  /** Client de la commande : jamais choisi, toujours hérité. */
  customerId: string
  orderId: string
  services: OrderService[]
  /** `null` pour une création. */
  claim: Claim | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Création et modification d'une réclamation, depuis une commande.
 *
 * Le client et la commande viennent du contexte : la création passe par
 * `POST /customers/{customer}/claims`, où le client est dans l'URL. Aucun
 * sélecteur de client n'existe donc — le §15 interdit d'en choisir un autre, et
 * la façon la plus sûre de l'interdire est de ne pas le demander.
 *
 * La section Traitement n'apparaît qu'en modification, parce que le serveur ne
 * l'accepte pas à la création.
 */
export function ClaimDialog({
  customerId,
  orderId,
  services,
  claim,
  open,
  onOpenChange,
}: ClaimDialogProps) {
  const { t } = useTranslation()
  const isEdit = claim !== null

  // La ligne de liste ne porte ni description, ni cause, ni traitement :
  // construire le formulaire depuis elle les afficherait vides, et enregistrer
  // les effacerait. Le detail est donc recharge avant d'ouvrir la saisie.
  const detail = useClaim(claim?.id ?? null)
  const loaded = detail.data

  const [values, setValues] = useState<ClaimFormValues | null>(
    claim === null ? CLAIM_FORM_DEFAULTS : null,
  )
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (loaded === undefined || values !== null) return

    setValues(toClaimFormValues(loaded))
  }, [loaded, values])

  const create = useCreateClaim(customerId)
  const update = useUpdateClaim()

  const incomplete =
    values === null ||
    values.title.trim() === '' ||
    values.claimType.trim() === '' ||
    values.status === ''

  const submit = async () => {
    if (values === null) return
    setError(null)

    try {
      if (isEdit) await update.mutateAsync({ id: claim.id, ...toClaimUpdatePayload(values) })
      else await create.mutateAsync({ ...toClaimPayload(values), orderId })

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('claims.edit') : t('claims.create')}</DialogTitle>
          <DialogDescription>
            {isEdit ? t('claims.editHint') : t('claims.createHint')}
          </DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        {values === null ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : (
          <ClaimForm
            values={values}
            onChange={(patch) =>
              setValues((current) => (current === null ? current : { ...current, ...patch }))
            }
            services={services}
            showTreatment={isEdit}
          />
        )}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={incomplete || create.isPending || update.isPending}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
