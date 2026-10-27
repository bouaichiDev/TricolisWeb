import { ShieldX } from 'lucide-react'
import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { Button } from '@/shared/components/ui/button'

function Message({ title, text }: { title: string; text: string }) {
  return (
    <div className="flex flex-col items-center gap-3 py-16 text-center">
      <ShieldX className="text-muted-foreground size-10" aria-hidden />
      <h1 className="text-xl font-semibold">{title}</h1>
      <p className="text-muted-foreground max-w-md text-sm">{text}</p>
      <Button asChild variant="outline">
        <Link to={paths.dashboard}>Retour au tableau de bord</Link>
      </Button>
    </div>
  )
}

export function AccessDeniedPage() {
  return (
    <Message
      title="Accès refusé"
      text="Votre rôle ne donne pas accès à cette page. Demandez à votre transporteur si vous en avez besoin."
    />
  )
}

export function NotFoundPage() {
  return <Message title="Page introuvable" text="Cette page n’existe pas ou n’est pas disponible pour votre entreprise." />
}
