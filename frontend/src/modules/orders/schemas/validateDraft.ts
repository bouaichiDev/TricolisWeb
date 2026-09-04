import type { OrderDraft } from './orderDraft'
import type { OrderErrorReport, OrderIssue, OrderStep } from './orderErrors'

const REQUIRED = 'validation.required'
const POSITIVE = 'validation.positive'

const STEP_ORDER: OrderStep[] = ['general', 'lines', 'packages', 'services', 'review']

const filled = (value: string): boolean => value.trim() !== ''
const positive = (value: string): boolean => filled(value) && Number(value) > 0

/**
 * Contrôle du brouillon avant envoi, calqué sur `StoreOrderRequest`.
 *
 * Aucune règle n'est inventée : chaque contrôle correspond à une règle du
 * serveur. Le but n'est pas de remplacer la validation — c'est le serveur qui
 * fait foi — mais d'éviter un aller-retour pour un champ manifestement vide, et
 * de désigner l'étape fautive avec le même vocabulaire que le 422.
 */
export function validateDraft(draft: OrderDraft): OrderErrorReport {
  const issues: OrderIssue[] = [
    ...headerIssues(draft),
    ...lineIssues(draft),
    ...packageIssues(draft),
    ...serviceIssues(draft),
  ]

  const steps = new Set(issues.map((issue) => issue.step))

  return { issues, stepsInError: STEP_ORDER.filter((step) => steps.has(step)), message: null }
}

function issue(
  step: OrderStep,
  path: string,
  field: string,
  message: string,
  entityKey: string | null = null,
  sub: OrderIssue['sub'] = null,
): OrderIssue {
  return { path, message, step, entityKey, sub, field }
}

function headerIssues(draft: OrderDraft): OrderIssue[] {
  const found: OrderIssue[] = []

  if (!filled(draft.customerId)) found.push(issue('general', 'customerId', 'customerId', REQUIRED))
  if (!filled(draft.agencyId)) found.push(issue('general', 'agencyId', 'agencyId', REQUIRED))
  if (!filled(draft.orderDate)) found.push(issue('general', 'orderDate', 'orderDate', REQUIRED))

  return found
}

function lineIssues(draft: OrderDraft): OrderIssue[] {
  if (draft.lines.length === 0) {
    return [issue('lines', 'lines', 'lines', 'orders.wizard.requiredLines')]
  }

  return draft.lines.flatMap((line, index) => {
    const found: OrderIssue[] = []

    // `name` n'est requis qu'en l'absence d'article : le backend reprend alors
    // le libellé du catalogue.
    if (line.catalogItemId === null && !filled(line.name)) {
      found.push(issue('lines', `lines.${index}.name`, 'name', REQUIRED, line.key))
    }

    if (!positive(line.quantity)) {
      found.push(issue('lines', `lines.${index}.quantity`, 'quantity', POSITIVE, line.key))
    }

    return found
  })
}

function packageIssues(draft: OrderDraft): OrderIssue[] {
  return draft.packages.flatMap((item, index) =>
    item.lines
      .filter((link) => !positive(link.quantity))
      .map((link, position) =>
        issue(
          'packages',
          `packages.${index}.lines.${position}.quantity`,
          'quantity',
          POSITIVE,
          item.key,
          { kind: 'lines', index: draft.lines.findIndex((line) => line.key === link.lineKey) },
        ),
      ),
  )
}

/** Champs de service `required` côté serveur, y compris les quatre montants. */
const SERVICE_REQUIRED = [
  'serviceId',
  'addressId',
  'serviceNumber',
  'requestedDate',
  'unit',
  'status',
  'requiredTimeMinutes',
  'remainingTimeMinutes',
  'weight',
  'volume',
  'packageCount',
  'customerUnitPrice',
  'customerTotalPrice',
  'providerUnitCost',
  'providerTotalCost',
] as const

function serviceIssues(draft: OrderDraft): OrderIssue[] {
  if (draft.services.length === 0) {
    return [issue('services', 'services', 'services', 'orders.wizard.requiredServices')]
  }

  return draft.services.flatMap((service, index) => {
    const found: OrderIssue[] = []
    const at = (field: string, message: string, sub?: OrderIssue['sub']) =>
      found.push(
        issue('services', `services.${index}.${field}`, field, message, service.key, sub ?? null),
      )

    for (const field of SERVICE_REQUIRED) {
      if (!filled(service[field])) at(field, REQUIRED)
    }

    if (!positive(service.quantity)) at('quantity', POSITIVE)
    if (!positive(service.sequence)) at('sequence', POSITIVE)

    service.contacts.forEach((contact, position) => {
      if (contact.contactId === null && !filled(contact.firstName)) {
        found.push(
          issue(
            'services',
            `services.${index}.contacts.${position}.firstName`,
            'firstName',
            REQUIRED,
            service.key,
            { kind: 'contacts', index: position },
          ),
        )
      }
    })

    return found
  })
}
