/**
 * Tipos, umbrales y formateadores del Laboratorio de rendimiento.
 * Umbrales = los de Google (web.dev / Lighthouse) para que los colores
 * coincidan con lo que el usuario ve en PageSpeed Insights.
 */
import type {
  NetworkRequest, CruxData, ResourceBreakdownItem, LighthouseAudit,
} from '../waterfall/helpers'
import { formatSize } from '../waterfall/helpers'

export type Strategy = 'mobile' | 'desktop'
export type StrategyChoice = Strategy | 'both'
export type RunStatus = 'pending' | 'measuring' | 'completed' | 'failed'

export interface RunSummary {
  score: number | null
  fcp: number | null
  lcp: number | null
  cls: number | null
  tbt: number | null
  si: number | null
  tti: number | null
  ttfb: number | null
  requests: number
  bytes: number
  isMedian?: boolean
}

export interface PerfRun {
  id: number
  url: string
  strategy: StrategyChoice
  runsRequested: number
  runsDone: number
  status: RunStatus
  note: string | null
  scoreMobile: number | null
  scoreDesktop: number | null
  error: string | null
  createdAt: string
  completedAt: string | null
}

export interface Opportunity { id: string; title: string; displayValue: string; savings: number }

export interface StrategyResult {
  error?: string
  summary?: RunSummary
  runs?: RunSummary[]
  meta?: { finalUrl?: string; lighthouseVersion?: string | null; warnings?: string[]; measuredAt?: string } | null
  data?: {
    networkRequests: NetworkRequest[]
    resourceBreakdown: ResourceBreakdownItem[]
    lighthouseAudits: LighthouseAudit[]
    opportunities: Opportunity[]
    lcpElement: Record<string, string> | null
    clsElements: Array<Record<string, unknown>>
    mainThreadWork: Array<{ group: string; duration: number }>
    crux: CruxData | null
  }
}

export interface PerfRunDetail extends PerfRun {
  result: {
    url: string
    strategy: StrategyChoice
    runsRequested: number
    strategies: Partial<Record<Strategy, StrategyResult>>
    errors: Array<{ round: number; strategy: Strategy; message: string }>
  } | null
  previous: (PerfRun & { summaries: Partial<Record<Strategy, RunSummary>> }) | null
}

export type MetricKey = 'score' | 'lcp' | 'fcp' | 'cls' | 'tbt' | 'si' | 'ttfb' | 'tti' | 'requests' | 'bytes'

export type Rating = 'good' | 'average' | 'poor' | 'neutral'

/** [bueno ≤ a, mejorable ≤ b]. score es al revés (más alto = mejor). */
const THRESHOLDS: Partial<Record<MetricKey, [number, number]>> = {
  lcp: [2500, 4000],
  fcp: [1800, 3000],
  cls: [0.1, 0.25],
  tbt: [200, 600],
  si: [3400, 5800],
  ttfb: [800, 1800],
  tti: [3800, 7300],
}

export function rate(key: MetricKey, value: number | null | undefined): Rating {
  if (value === null || value === undefined) return 'neutral'
  if (key === 'score') return value >= 90 ? 'good' : value >= 50 ? 'average' : 'poor'
  const th = THRESHOLDS[key]
  if (!th) return 'neutral'
  return value <= th[0] ? 'good' : value <= th[1] ? 'average' : 'poor'
}

export const RATING_COLOR: Record<Rating, string> = {
  good: '#0c9d58',
  average: '#e67700',
  poor: '#e5322d',
  neutral: 'var(--text-primary)',
}

export const RATING_BG: Record<Rating, string> = {
  good: 'rgba(12,206,107,0.10)',
  average: 'rgba(255,164,0,0.12)',
  poor: 'rgba(255,78,66,0.10)',
  neutral: 'var(--bg-tertiary)',
}

export function formatMs(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return '—'
  return ms < 1000 ? `${Math.round(ms)} ms` : `${(ms / 1000).toFixed(1)} s`
}

export function formatMetric(key: MetricKey, value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  if (key === 'score' || key === 'requests') return String(Math.round(value))
  if (key === 'cls') return value.toFixed(3)
  if (key === 'bytes') return formatSize(value)
  return formatMs(value)
}

/**
 * Delta entre dos valores. `better` indica si el cambio es una mejora
 * (score sube / tiempos y peso bajan). Cambios despreciables → neutral,
 * para no pintar de verde/rojo el ruido normal de PSI.
 */
export function delta(key: MetricKey, current: number | null | undefined, previous: number | null | undefined):
  { text: string; better: boolean | null } | null {
  if (current === null || current === undefined || previous === null || previous === undefined) return null
  const d = current - previous
  const noise: Partial<Record<MetricKey, number>> = { score: 0, cls: 0.005, requests: 0, bytes: 1024 }
  const minChange = noise[key] ?? 50 // 50 ms para métricas de tiempo
  if (Math.abs(d) <= minChange) return { text: '=', better: null }
  const higherIsBetter = key === 'score'
  const better = higherIsBetter ? d > 0 : d < 0
  const sign = d > 0 ? '+' : '−'
  const abs = Math.abs(d)
  let text: string
  if (key === 'score' || key === 'requests') text = `${sign}${Math.round(abs)}`
  else if (key === 'cls') text = `${sign}${abs.toFixed(3)}`
  else if (key === 'bytes') text = `${sign}${formatSize(abs)}`
  else text = `${sign}${formatMs(abs)}`
  return { text, better }
}

/** Fechas del servidor: 'Y-m-d H:i:s' en UTC (env.php fuerza UTC). */
export function parseServerDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const iso = s.includes('T') ? s : s.replace(' ', 'T') + 'Z'
  const d = new Date(iso)
  return isNaN(d.getTime()) ? null : d
}

export function formatDateTime(s: string | null | undefined, lang: string): string {
  const d = parseServerDate(s)
  if (!d) return '—'
  return d.toLocaleString(lang, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

export const METRIC_ORDER: MetricKey[] = ['score', 'lcp', 'fcp', 'tbt', 'cls', 'si', 'ttfb', 'requests', 'bytes']
