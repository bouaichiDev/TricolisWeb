import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { Button } from '@/shared/components/ui/button'

import { ServiceSummaryCard } from '../components/ServiceSummaryCard'
import type { OrderDetail } from '../types'

/**
 * Les services de la commande. Documents, bon de livraison, preuve et
 * réclamations d'un service vivent sur sa propre page : chargés à la demande,
 * pas avec la commande.
 */
export function ServicesTab({ order }: { order: OrderDetail }) {
  const packageNumber = new Map(order.packages.map((pkg, index) => [pkg.id, index + 1]))

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {order.services.map((service) => (
        <ServiceSummaryCard
          key={service.id}
          service={service}
          packageNumber={packageNumber}
          footer={
            <Button asChild variant="link" className="h-auto px-0">
              <Link to={paths.orderService(order.id, service.id)}>
                Documents, livraison et réclamations <ChevronRight className="size-4" aria-hidden />
              </Link>
            </Button>
          }
        />
      ))}
    </div>
  )
}
