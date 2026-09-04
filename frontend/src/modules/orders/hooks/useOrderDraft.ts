import { useCallback, useState } from 'react'

import {
  emptyDraft,
  emptyLine,
  emptyPackage,
  emptyService,
  type LineDraft,
  type OrderDraft,
  type PackageDraft,
  type ServiceDraft,
} from '../schemas/orderDraft'
import { descendantKeys } from '../schemas/packageOrder'

/**
 * État du brouillon de commande et ses opérations.
 *
 * Tout passe par des clés stables, jamais par des index : retirer une ligne ne
 * doit pas décaler les affectations déjà faites vers les colis. Supprimer un
 * élément retire aussi les références qui pointaient vers lui — une affectation
 * orpheline produirait un 422 sur un chemin devenu invisible.
 */
export function useOrderDraft(initial?: OrderDraft) {
  const [draft, setDraft] = useState<OrderDraft>(() => initial ?? emptyDraft())

  const patch = useCallback((values: Partial<OrderDraft>) => {
    setDraft((current) => ({ ...current, ...values }))
  }, [])

  /* ----------------------------------------------------------- lignes -- */

  const addLine = useCallback(() => {
    setDraft((current) => ({ ...current, lines: [...current.lines, emptyLine()] }))
  }, [])

  const patchLine = useCallback((key: string, values: Partial<LineDraft>) => {
    setDraft((current) => ({
      ...current,
      lines: current.lines.map((line) => (line.key === key ? { ...line, ...values } : line)),
    }))
  }, [])

  const removeLine = useCallback((key: string) => {
    setDraft((current) => ({
      ...current,
      lines: current.lines.filter((line) => line.key !== key),
      packages: current.packages.map((item) => ({
        ...item,
        lines: item.lines.filter((link) => link.lineKey !== key),
      })),
    }))
  }, [])

  /* ------------------------------------------------------------ colis -- */

  /**
   * Ajoute un colis, et y met la ligne quand il n'y en a qu'une.
   *
   * Le contenu d'un colis naît **ici**, avec la commande : `CreateOrderPackages`
   * lit `packages[].lines[]` et rien d'autre ne le renseigne avant l'exécution.
   * Avec une seule ligne de commande, tout y va — proposer autre chose serait
   * faire recopier un nombre déjà à l'écran.
   *
   * C'est une valeur de **brouillon**, modifiable et détachable avant l'envoi :
   * rien n'est écrit tant que la commande n'est pas créée.
   */
  const addPackage = useCallback((parentKey: string | null = null) => {
    setDraft((current) => {
      const sole = current.lines.length === 1 ? current.lines[0] : undefined
      const alreadyAssigned = current.packages.some((item) => item.lines.length > 0)

      const lines =
        sole !== undefined && !alreadyAssigned && current.packages.length === 0
          ? [{ lineKey: sole.key, quantity: sole.quantity }]
          : []

      return {
        ...current,
        packages: [...current.packages, { ...emptyPackage(), parentKey, lines }],
      }
    })
  }, [])

  const patchPackage = useCallback((key: string, values: Partial<PackageDraft>) => {
    setDraft((current) => ({
      ...current,
      packages: current.packages.map((item) => (item.key === key ? { ...item, ...values } : item)),
    }))
  }, [])

  /** Retire un colis et toute sa descendance, ainsi que les services qui les citaient. */
  const removePackage = useCallback((key: string) => {
    setDraft((current) => {
      const removed = new Set([key, ...descendantKeys(current.packages, key)])

      return {
        ...current,
        packages: current.packages.filter((item) => !removed.has(item.key)),
        services: current.services.map((service) => ({
          ...service,
          packages: service.packages.filter((link) => !removed.has(link.packageKey)),
        })),
      }
    })
  }, [])

  /** Affecte une ligne à un colis, ou met à jour la quantité déjà affectée. */
  const assignLine = useCallback((packageKey: string, lineKey: string, quantity: string) => {
    setDraft((current) => ({
      ...current,
      packages: current.packages.map((item) => {
        if (item.key !== packageKey) return item

        const exists = item.lines.some((link) => link.lineKey === lineKey)

        return {
          ...item,
          lines: exists
            ? item.lines.map((link) => (link.lineKey === lineKey ? { ...link, quantity } : link))
            : [...item.lines, { lineKey, quantity }],
        }
      }),
    }))
  }, [])

  const detachLine = useCallback((packageKey: string, lineKey: string) => {
    setDraft((current) => ({
      ...current,
      packages: current.packages.map((item) =>
        item.key === packageKey
          ? { ...item, lines: item.lines.filter((link) => link.lineKey !== lineKey) }
          : item,
      ),
    }))
  }, [])

  /* --------------------------------------------------------- services -- */

  const addService = useCallback(() => {
    setDraft((current) => ({
      ...current,
      // La séquence suit le dernier rang utilisé : elle est unique dans la
      // commande, une collision serait refusée par le serveur.
      services: [
        ...current.services,
        emptyService(Math.max(0, ...current.services.map((s) => Number(s.sequence) || 0)) + 1),
      ],
    }))
  }, [])

  const patchService = useCallback((key: string, values: Partial<ServiceDraft>) => {
    setDraft((current) => ({
      ...current,
      services: current.services.map((service) =>
        service.key === key ? { ...service, ...values } : service,
      ),
    }))
  }, [])

  const removeService = useCallback((key: string) => {
    setDraft((current) => ({
      ...current,
      services: current.services.filter((service) => service.key !== key),
    }))
  }, [])

  return {
    draft,
    setDraft,
    patch,
    addLine,
    patchLine,
    removeLine,
    addPackage,
    patchPackage,
    removePackage,
    assignLine,
    detachLine,
    addService,
    patchService,
    removeService,
  }
}

export type OrderDraftController = ReturnType<typeof useOrderDraft>
