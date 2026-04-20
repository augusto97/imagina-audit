/**
 * Tipos y utilidades compartidas por los componentes del waterfall.
 */

export interface NetworkRequest {
  url: string
  resourceType: string
  startTime: number
  endTime: number
  transferSize: number
  resourceSize: number
  statusCode: number
  mimeType: string
  protocol: string
}

export interface CruxMetric {
  id: string
  label: string
  percentile: number | null
  category: string | null
  distributions: Array<{ min: number; max?: number; proportion: number }>
}

export interface CruxData {
  overallCategory: string | null
  metrics: CruxMetric[]
}

export interface ResourceBreakdownItem {
  resourceType: string
  label: string
  requestCount: number
  transferSize: number
}

export interface LighthouseAudit {
  id: string
  title: string
  description: string
  score: number | null
  impact: string
  displayValue: string
  group: string
  weight: number
}

/** Colores Pingdom-style por tipo de recurso. */
export const TYPE_COLORS: Record<string, string> = {
  Document: '#4CAF50',
  Stylesheet: '#2196F3',
  Script: '#FFC107',
  Image: '#9C27B0',
  Font: '#E91E63',
  XHR: '#00BCD4',
  Fetch: '#00BCD4',
  Media: '#FF5722',
  Other: '#9E9E9E',
}

/** Etiqueta visible para filtros de tipo de recurso. */
export const TYPE_LABELS: Record<string, string> = {
  Document: 'HTML',
  Stylesheet: 'CSS',
  Script: 'JS',
  Image: 'Images',
  Font: 'Fonts',
  XHR: 'XHR',
  Fetch: 'XHR',
  Media: 'Media',
  Other: 'Other',
}

/** Formatea bytes como B/KB/MB. */
export function formatSize(bytes: number): string {
  if (bytes === 0) return '0'
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / (1024 * 1024)).toFixed(2) + 'MB'
}

/** Extrae el nombre de archivo del path de una URL (truncado a 45 chars). */
export function extractFilename(url: string): string {
  try {
    const u = new URL(url)
    const path = u.pathname
    const file = path.split('/').pop() || path
    return file.length > 45 ? file.substring(0, 42) + '...' : file
  } catch {
    return url.substring(0, 45)
  }
}
