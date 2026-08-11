import { Alert, AlertDescription } from '@/shared/components/ui/alert'

/** Message d'erreur global d'un formulaire, au-dessus des champs. */
export function FormErrorSummary({ message }: { message: string | null }) {
  if (message === null) return null

  return (
    <Alert variant="destructive">
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  )
}
