import { useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { ApiError, refusalMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { toursApi } from '../api/toursApi'
import type { TourStop } from '../types'

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm'
const IMAGES = 'image/png,image/jpeg,image/webp'
type Field = 'orderServiceId' | 'recipientName' | 'signature' | 'photo' | 'remark' | 'form'

/**
 * Saisir une preuve de livraison à un arrêt atteint : le service livré, qui
 * a réceptionné, sa signature (une image) et, au besoin, une photo. L'heure
 * n'est pas demandée : c'est celle du serveur à l'enregistrement.
 */
export function ProofDialog({ tourId, stop, open, onOpenChange }: { tourId: string; stop: TourStop; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [chosen, setChosen] = useState('')
  // Le choix suit la liste : si elle change, on retombe sur le premier service.
  const serviceId = stop.services.some((service) => service.orderServiceId === chosen) ? chosen : (stop.services[0]?.orderServiceId ?? '')
  const [recipient, setRecipient] = useState('')
  const [remark, setRemark] = useState('')
  const [signature, setSignature] = useState<File | null>(null)
  const [photo, setPhoto] = useState<File | null>(null)
  const [errors, setErrors] = useState<Partial<Record<Field, string>>>({})
  const [pending, setPending] = useState(false)

  const close = (next: boolean) => {
    if (!next) setErrors({})
    onOpenChange(next)
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (!signature) return setErrors({ signature: 'La signature est obligatoire.' })

    const form = new FormData()
    form.append('orderServiceId', serviceId)
    form.append('recipientName', recipient.trim())
    form.append('signature', signature)
    if (photo) form.append('photo', photo)
    if (remark.trim()) form.append('remark', remark.trim())

    setPending(true)
    try {
      await toursApi.recordProof(tourId, stop.id, form)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tour', tourId, 'proofs', stop.id] }),
        queryClient.invalidateQueries({ queryKey: ['tour', tourId, 'documents'] }),
      ])
      toast.success('Preuve de livraison enregistrée.')
      setRecipient('')
      setRemark('')
      setSignature(null)
      setPhoto(null)
      close(false)
    } catch (caught) {
      const fields = caught instanceof ApiError ? caught.errors : {}
      const known = Object.fromEntries(Object.entries(fields).map(([key, messages]) => [key, messages[0]])) as Partial<Record<Field, string>>
      const placed = known.orderServiceId || known.recipientName || known.signature || known.photo || known.remark
      setErrors(placed ? known : { form: Object.values(known)[0] ?? refusalMessage(caught) })
    } finally {
      setPending(false)
    }
  }

  const error = (field: Field) => errors[field] && <p className="text-destructive text-sm">{errors[field]}</p>

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Preuve de livraison — arrêt {stop.sequence}</DialogTitle>
          <DialogDescription>L’heure enregistrée sera celle de la validation.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={(event) => void submit(event)}>
          {errors.form && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{errors.form}</AlertDescription>
            </Alert>
          )}
          <div className="grid gap-1.5">
            <Label htmlFor={`proof-service-${stop.id}`}>Service livré</Label>
            <select id={`proof-service-${stop.id}`} className={selectClass} value={serviceId} onChange={(event) => setChosen(event.target.value)}>
              {stop.services.map((service) => (
                <option key={service.id} value={service.orderServiceId}>{`${service.serviceNumber ?? ''} — ${service.serviceName ?? 'Service'}`}</option>
              ))}
            </select>
            {error('orderServiceId')}
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`proof-recipient-${stop.id}`}>Réceptionnaire</Label>
            <Input id={`proof-recipient-${stop.id}`} value={recipient} maxLength={120} onChange={(event) => setRecipient(event.target.value)} />
            {error('recipientName')}
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`proof-signature-${stop.id}`}>Signature (image)</Label>
            <Input id={`proof-signature-${stop.id}`} type="file" accept={IMAGES} onChange={(event) => setSignature(event.target.files?.[0] ?? null)} />
            {error('signature')}
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`proof-photo-${stop.id}`}>Photo (facultative)</Label>
            <Input id={`proof-photo-${stop.id}`} type="file" accept={IMAGES} onChange={(event) => setPhoto(event.target.files?.[0] ?? null)} />
            {error('photo')}
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`proof-remark-${stop.id}`}>Remarque (facultative)</Label>
            <Textarea id={`proof-remark-${stop.id}`} value={remark} maxLength={500} onChange={(event) => setRemark(event.target.value)} />
            {error('remark')}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => close(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={pending || recipient.trim().length < 2 || !serviceId}>
              Enregistrer la preuve
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
