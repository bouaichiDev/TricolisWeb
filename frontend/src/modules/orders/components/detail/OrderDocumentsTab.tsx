import { Upload } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import {
  useOrderDocuments,
  useUploadOrderDocument,
} from '@/modules/documents/hooks/useOrderDocuments'
import { DocumentGallery } from '@/modules/documents/components/DocumentGallery'
import { OrderPodSection } from '@/modules/pod/components/OrderPodSection'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate } from '@/shared/utils/format'

/**
 * Documents rattachés à la commande.
 *
 * Le type et le statut sont saisis : ce sont des chaînes libres en base, sans
 * énumération côté serveur. Proposer une liste fermée inventerait un vocabulaire
 * que rien ne valide.
 *
 * `DocumentResource` n'expose ni URL ni chemin de stockage — le chemin interne
 * ne doit pas quitter le serveur. Le fichier passe par
 * `GET /documents/{document}/download`, qui est authentifiée : la galerie le
 * récupère avec ses en-têtes avant de l'afficher ou de le remettre.
 */
export function OrderDocumentsTab({ orderId }: { orderId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [file, setFile] = useState<File | null>(null)
  const [documentType, setDocumentType] = useState('')
  const [status, setStatus] = useState('received')

  const documents = useOrderDocuments(orderId, page)
  const upload = useUploadOrderDocument(orderId)

  const submit = () => {
    if (file === null || documentType.trim() === '') return

    upload.mutate(
      { file, documentType: documentType.trim(), status: status.trim() },
      {
        onSuccess: () => {
          setFile(null)
          setDocumentType('')
        },
      },
    )
  }

  // Les preuves sont des documents comme les autres, reconnus a leur type :
  // elles ont leur section, en tete, parce qu'on les cherche en premier.
  const all = documents.data?.data ?? []

  return (
    <div className="flex flex-col gap-6">
      <SectionCard title={t('documents.upload')}>
        <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="order-document-file">{t('documents.fields.fileName')}</Label>
            <Input
              id="order-document-file"
              type="file"
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          </div>

          <ControlledField
            label={t('documents.fields.documentType')}
            value={documentType}
            onChange={setDocumentType}
            required
          />

          <ControlledField label={t('orders.fields.status')} value={status} onChange={setStatus} required />

          <Button
            type="button"
            onClick={submit}
            disabled={file === null || documentType.trim() === '' || upload.isPending}
          >
            <Upload className="size-4" aria-hidden />
            {upload.isPending ? t('documents.uploading') : t('documents.upload')}
          </Button>
        </div>
      </SectionCard>

      <SectionCard title={t('pod.title')}>
        <OrderPodSection documents={all} isLoading={documents.isPending} />
      </SectionCard>

      <SectionCard title={t('documents.title')}>
        <DocumentGallery
          documents={all}
          isLoading={documents.isPending}
          meta={documents.data?.meta}
          onPageChange={setPage}
          details={(item) => (
            <>
              <span>{item.documentType}</span>
              <StatusBadge status={item.status} />
              <span>{formatDate(item.createdAt)}</span>
            </>
          )}
        />
      </SectionCard>
    </div>
  )
}
