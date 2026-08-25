import type { Claim, ClaimPayload, ClaimUpdatePayload } from '../types/claim'

/**
 * Valeurs du formulaire, toutes en chaînes.
 *
 * Les nombres et les dates sont saisis en texte puis convertis : un champ
 * nombre vide donne `NaN` avec `valueAsNumber`, et `NaN` passerait une
 * validation `min(0)` sans être un nombre.
 */
export interface ClaimFormValues {
  title: string
  claimType: string
  status: string
  description: string
  cause: string
  orderServiceId: string
  decision: string
  followUp: string
  result: string
  cost: string
  closedAt: string
}

/** Valeur désignant « toute la commande », Radix refusant une option vide. */
export const NO_SERVICE = 'none'

export const CLAIM_FORM_DEFAULTS: ClaimFormValues = {
  title: '',
  claimType: '',
  status: '',
  description: '',
  cause: '',
  orderServiceId: NO_SERVICE,
  decision: '',
  followUp: '',
  result: '',
  cost: '',
  closedAt: '',
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

const amount = (value: string): number | null => {
  const parsed = Number(value.trim())

  return value.trim() === '' || !Number.isFinite(parsed) ? null : parsed
}

const moment = (value: string): string | null =>
  value === '' ? null : new Date(value).toISOString()

/**
 * Charge utile de création.
 *
 * Le traitement en est **absent** : `StoreClaimRequest` ne l'accepte pas, et
 * l'envoyer produirait un 422 sur des champs que l'écran n'affiche même pas.
 */
export function toClaimPayload(values: ClaimFormValues): Omit<ClaimPayload, 'customerId'> {
  return {
    title: values.title.trim(),
    claimType: values.claimType.trim(),
    status: values.status,
    description: blank(values.description),
    cause: blank(values.cause),
    orderServiceId: values.orderServiceId === NO_SERVICE ? null : values.orderServiceId,
  }
}

/** Charge utile de modification : la création, plus le traitement. */
export function toClaimUpdatePayload(values: ClaimFormValues): ClaimUpdatePayload {
  return {
    ...toClaimPayload(values),
    decision: blank(values.decision),
    followUp: blank(values.followUp),
    result: blank(values.result),
    cost: amount(values.cost),
    closedAt: moment(values.closedAt),
  }
}

const text = (value: string | null | undefined): string => value ?? ''

export function toClaimFormValues(claim: Claim): ClaimFormValues {
  return {
    title: claim.title,
    claimType: claim.claimType,
    status: claim.status,
    description: text(claim.description),
    cause: text(claim.cause),
    orderServiceId: claim.orderServiceId ?? NO_SERVICE,
    decision: text(claim.decision),
    followUp: text(claim.followUp),
    result: text(claim.result),
    cost: claim.cost === null ? '' : String(claim.cost),
    // `datetime-local` n'accepte pas le suffixe de fuseau.
    closedAt: claim.closedAt === null ? '' : claim.closedAt.slice(0, 16),
  }
}
