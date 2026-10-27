/**
 * Le côté « exécution » d'une configuration d'import (Phase 6 portail) :
 * champs cibles tenus par le backend, état de la connexion, test, passe.
 * Aucun secret n'y figure — `hasCredentials` dit seulement s'il existe.
 */

export interface ImportTargetFieldsCatalog {
  groups: { key: string; fields: { path: string; requirement: 'required' | 'optional' | 'conditional'; constraint: string | null }[] }[]
  types: string[]
  validationRules: string[]
  duplicateStrategies: string[]
}

export type ImportChannel = 'upload' | 'rest_pull' | 'sftp' | 'ftp'

export interface RestPullSettings {
  path?: string | null
  healthPath?: string | null
  recordsPath?: string | null
  query?: Record<string, string | number | boolean> | null
  since?: { param?: string | null; format?: string | null } | null
  pagination?: { type?: 'none' | 'page' | 'offset' | 'cursor'; param?: string | null; sizeParam?: string | null; size?: number; start?: number; cursorPath?: string | null; maxPages?: number } | null
  actorUserId?: string | null
  apiKeyHeader?: string | null
}

export interface ImportConnectionState {
  channel: ImportChannel
  frequency: 'manual' | 'hourly'
  orderSource: string
  connection:
    | ({ kind: 'rest'; configured: boolean; hasCredentials: boolean; code: string } & Partial<{
        isActive: boolean
        baseUrl: string
        authType: 'none' | 'bearer' | 'api_key' | 'basic'
        timeoutSeconds: number
        headerNames: string[]
        settings: RestPullSettings
        lastUsedAt: string | null
      }>)
    | ({ kind: 'file'; configured: boolean; hasCredentials: boolean; problem?: string } & Partial<{
        driver: string
        host: string
        port: number | null
        username: string | null
        actorEmail: string | null
        directory: string
        filePattern: string
        afterImport: 'archive' | 'delete' | 'keep'
        archiveDirectory: string | null
      }>)
    | null
  mapping: { mapped: string[]; unknownTargets: string[]; missingRequired: string[]; problems: string[] }
  flags: {
    inactive: boolean
    mappingMissing: boolean
    mappingIncomplete: boolean
    connectionMissing: boolean
    credentialMissing: boolean
    testable: boolean
  }
  ready: boolean
}

export interface RestPullConnectionPayload {
  baseUrl: string
  authType: 'none' | 'bearer' | 'api_key' | 'basic'
  /** Absent : le secret existant est gardé. `null` : il est effacé. */
  credentials?: string | null
  timeoutSeconds?: number
  isActive?: boolean
  settings?: RestPullSettings
}

/** Connexion FTP/SFTP : le protocole découle du type de source, il ne se saisit pas. */
export interface FileConnectionPayload {
  host: string
  port?: number | null
  username?: string | null
  /** Absent : le mot de passe existant est gardé. `null` : il est effacé. */
  password?: string | null
  directory?: string | null
  filePattern?: string | null
  afterImport?: 'archive' | 'delete' | 'keep'
  archiveDirectory?: string | null
  actorEmail?: string | null
}

export type ImportConnectionPayload = RestPullConnectionPayload | FileConnectionPayload

export interface ConnectionTestResult {
  ok: boolean
  message: string
  details: Record<string, unknown>
}
