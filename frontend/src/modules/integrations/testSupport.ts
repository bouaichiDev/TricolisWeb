import { HttpResponse, http } from 'msw'

import { paginated } from '@/test/fixtures'
import { API, server } from '@/test/server'

/**
 * Jeux de données des intégrations client, à la forme réelle des ressources.
 *
 * Ils reproduisent surtout leurs **absences**, qui sont ici le contrat :
 * `ApiConfigurationResource` ne porte pas `apiKeyHash`,
 * `ExportConfigurationResource` remplace `encryptedPassword` par `hasPassword`,
 * et `ExportJobResource` remplace `storagePath` par `hasFile`. Un jeu de test
 * plus généreux que l'API ferait passer des tests que la production mettrait en
 * échec — et masquerait précisément la fuite de secret qu'on veut interdire.
 */
export const CUSTOMER_ID = '01JQZ00000000000000CUST1'
export const IMPORT_CONFIG_ID = '01JQZ0000000000000IMPRT1'
export const API_CONFIG_ID = '01JQZ00000000000000APIC1'
export const EXPORT_CONFIG_ID = '01JQZ0000000000000EXPCF1'
export const EXPORT_JOB_ID = '01JQZ0000000000000EXPJB1'

export const importConfiguration = (overrides: Record<string, unknown> = {}) => ({
  id: IMPORT_CONFIG_ID,
  customerId: CUSTOMER_ID,
  name: 'Commandes ERP',
  sourceType: 'sftp',
  fileFormat: 'csv',
  mapping: { orderNumber: 'REF', quantity: 'QTE' },
  validationRules: { orderNumber: 'required' },
  isActive: true,
  ...overrides,
})

/** Sans `apiKeyHash` : la ressource ne l'expose pas, et ne doit pas. */
export const apiConfiguration = (overrides: Record<string, unknown> = {}) => ({
  id: API_CONFIG_ID,
  customerId: CUSTOMER_ID,
  name: 'Portail client',
  allowedIps: ['10.0.0.0/24'],
  permissions: ['orders.view'],
  isActive: true,
  lastUsedAt: '2026-08-30T09:00:00+00:00',
  ...overrides,
})

/** Forme de `ApiKeyIssuedResource` : la seule qui porte une clé en clair. */
export const apiKeyIssued = (apiKey = 'trk_live_0123456789abcdef') => ({
  configuration: apiConfiguration(),
  apiKey,
  warning: 'Cette clé n’est affichée qu’une seule fois.',
})

/** `hasPassword` remplace `encryptedPassword` : le secret ne revient jamais. */
export const exportConfiguration = (overrides: Record<string, unknown> = {}) => ({
  id: EXPORT_CONFIG_ID,
  customerId: CUSTOMER_ID,
  name: 'Factures SFTP',
  exportType: 'invoice',
  format: 'xml',
  transport: 'sftp',
  host: 'sftp.client.test',
  port: 22,
  username: 'tricolis',
  hasPassword: true,
  remoteDirectory: '/in',
  fileNamePattern: '{invoice_number}.xml',
  encoding: 'UTF-8',
  frequency: 'on_invoice_closed',
  settings: null,
  isActive: true,
  ...overrides,
})

/** `hasFile` remplace `storagePath` : le chemin interne ne sort pas. */
export const exportJob = (overrides: Record<string, unknown> = {}) => ({
  id: EXPORT_JOB_ID,
  customerId: CUSTOMER_ID,
  configurationId: EXPORT_CONFIG_ID,
  entityType: 'invoice',
  entityId: '01JQZ0000000000000INVOI1',
  fileName: 'FA-2026-0001.xml',
  hasFile: true,
  status: 'failed',
  attemptCount: 2,
  generatedAt: '2026-08-30T09:00:00+00:00',
  sentAt: null,
  errorMessage: 'Connexion SFTP refusée.',
  configuration: {
    id: EXPORT_CONFIG_ID,
    name: 'Factures SFTP',
    format: 'xml',
    transport: 'sftp',
  },
  ...overrides,
})

const statusRow = (code: string, label: string, rank: number) => ({
  id: `01JQZ000000000000EXST0${rank}`,
  source: 'export_job',
  status: rank,
  code,
  label,
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: false,
  requiresReason: false,
  position: rank * 10,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
})

/** Les quatre codes que `StatusSeeder` sème pour `export_job`, dans l'ordre. */
export function serveExportJobStatuses() {
  server.use(
    http.get(`${API}/statuses`, () =>
      HttpResponse.json(
        paginated([
          statusRow('pending', 'En attente', 1),
          statusRow('processing', 'En cours d’envoi', 2),
          statusRow('sent', 'Envoyé', 3),
          statusRow('failed', 'Échoué', 4),
        ]),
      ),
    ),
  )
}

export function serveCustomers() {
  server.use(
    http.get(`${API}/customers`, () =>
      HttpResponse.json(
        paginated([
          {
            id: CUSTOMER_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'CLI01',
            name: 'Client Nord',
            status: 'active',
          },
        ]),
      ),
    ),
  )
}

/** Le référentiel RBAC, tel que `GET /permissions` le rend. */
export function servePermissions() {
  server.use(
    http.get(`${API}/permissions`, () =>
      HttpResponse.json({
        data: [
          {
            id: '01JQZ0000000000000PERM01',
            code: 'orders.view',
            name: 'Voir les commandes',
            module: 'orders',
            menuSection: 'operations',
            action: 'view',
          },
          {
            id: '01JQZ0000000000000PERM02',
            code: 'orders.create',
            name: 'Créer une commande',
            module: 'orders',
            menuSection: 'operations',
            action: 'create',
          },
        ],
        meta: [],
      }),
    ),
  )
}
