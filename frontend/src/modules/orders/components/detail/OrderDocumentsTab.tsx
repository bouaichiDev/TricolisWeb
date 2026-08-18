import { Upload } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import {
  useOrderDocuments,
  useUploadOrderDocument,
} from '@/modules/documents/hooks/useOrderDocuments'
import type { Document } from '@/modules/documents/types/document'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate } from '@/shared/utils/format'

/** Taille lisible ; le serveur renvoie des octets. */
function formatSize(size: number): string {
  if (size < 1024) return `${size} o`
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} Ko`

  return `${(size / (1024 * 1024)).toFixed(1)} Mo`
}

/**
 * Documents rattachés à la commande.
 *
 * Le type et le statut sont saisis : ce sont des chaînes libres en base, sans
 * énumération côté serveur. Proposer une liste fermée inventerait un vocabulaire
 * que rien ne valide.
 *
 * Aucun lien de téléchargement : `DocumentResource` n'expose ni URL ni chemin
 * de stockage, et il n'existe pas de route de téléchargement à ce jour.
 */
export function OrderDocumentsTab({ orderId }: { orderId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [file, setFile] = useState<File | null>(null)
  const [documentType, setDocumentType] = useState('')
  const [status, setStatus] = useState('received')

  const documents = useOrderDocuments(orderId, page)
  const upload = useUploadOrderDocument(orderId)

  const columns: Column<Document>[] = [
    { key: 'fileName', header: t('documents.fields.fileName'), cell: (row) => row.fileName },
    {
      key: 'documentType',
      header: t('documents.fields.documentType'),
      cell: (row) => row.documentType,
    },
    { key: 'status', header: t('orders.fields.status'), cell: (row) => <StatusBadge status={row.status} /> },
    {
      key: 'size',
      header: t('documents.fields.size'),
      hideOnMobile: true,
      cell: (row) => formatSize(row.size),
    },
    {
      key: 'createdAt',
      header: t('documents.fields.createdAt'),
      hideOnMobile: true,
      cell: (row) => formatDate(row.createdAt),
    },
  ]

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

      <SectionCard title={t('documents.title')}>
        <DataTable
          columns={columns}
          rows={documents.data?.data ?? []}
          isLoading={documents.isPending}
          meta={documents.data?.meta}
          onPageChange={setPage}
          emptyMessage={t('documents.empty')}
          rowKey={(row) => row.id}
        />
      </SectionCard>
    </div>
  )
}
