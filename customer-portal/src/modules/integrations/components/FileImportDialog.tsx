import { useState } from 'react'

import { errorMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'

import { integrationsApi } from '../api/integrationsApi'
import { useIntegrationMutation } from '../hooks/useIntegrations'
import type { ImportIntegration, ImportPreview, ImportResult } from '../types'
import { ImportPreviewView } from './ImportPreviewView'
import { ImportResultView } from './ImportResultView'

const ACCEPT: Record<string, string> = { xlsx: '.xlsx', excel: '.xlsx', csv: '.csv', json: '.json', xml: '.xml' }

interface FileImportDialogProps {
  flow: ImportIntegration | null
  onClose: () => void
}

/**
 * Importer un fichier de commandes (§50) : choisir le fichier, le
 * prévisualiser, lire la correspondance et les erreurs, confirmer, lire le
 * résumé. L'intégration est celle du client — il n'en choisit jamais une
 * autre, ni un autre client.
 */
export function FileImportDialog({ flow, onClose }: FileImportDialogProps) {
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const previewMutation = useIntegrationMutation(integrationsApi.preview)
  const executeMutation = useIntegrationMutation(integrationsApi.execute)

  const close = () => {
    setFile(null)
    setPreview(null)
    setResult(null)
    setError(null)
    onClose()
  }

  const run = async <T,>(work: () => Promise<T>, then: (value: T) => void) => {
    setError(null)
    try {
      then(await work())
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  const accept = ACCEPT[flow?.fileFormat.toLowerCase() ?? ''] ?? '.xlsx,.csv,.json,.xml'
  const importable = preview !== null && preview.summary.valid + preview.summary.duplicates > 0

  return (
    <Dialog open={flow !== null} onOpenChange={(open) => !open && close()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Importer des commandes — {flow?.name}</DialogTitle>
          <DialogDescription>
            {result ? 'Résultat de l’import.' : preview ? 'Vérifiez l’aperçu : rien n’est créé avant votre confirmation.' : `Fichier attendu : ${accept}.`}
          </DialogDescription>
        </DialogHeader>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {result ? (
          <ImportResultView result={result} />
        ) : preview ? (
          <ImportPreviewView preview={preview} />
        ) : (
          <div className="grid gap-2">
            <Label htmlFor="import-file">Fichier</Label>
            <input id="import-file" type="file" accept={accept} onChange={(event) => setFile(event.target.files?.[0] ?? null)} className="text-sm" />
          </div>
        )}

        <DialogFooter>
          {result ? (
            <Button onClick={close}>Fermer</Button>
          ) : preview ? (
            <>
              <Button variant="outline" onClick={() => setPreview(null)}>Changer de fichier</Button>
              <Button
                disabled={!importable || executeMutation.isPending}
                onClick={() => void run(() => executeMutation.mutateAsync({ configurationId: preview.configuration.id, token: preview.token }), setResult)}
              >
                Importer {preview.summary.valid} commande(s)
              </Button>
            </>
          ) : (
            <Button
              disabled={file === null || flow === null || previewMutation.isPending}
              onClick={() => file && flow && void run(() => previewMutation.mutateAsync({ configurationId: flow.id, file }), setPreview)}
            >
              Prévisualiser
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
