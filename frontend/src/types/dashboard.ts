/**
 * Tipos de la respuesta GET /api/admin/dashboard — mirror del payload
 * del endpoint PHP. Se usa en DashboardPage y en sus sub-componentes.
 */

export interface DashboardData {
  audits: {
    total: number
    today: number
    thisWeek: number
    thisMonth: number
    averageScore: number
    averageScore7d: number
    pinned: number
    wpCount: number
    nonWpCount: number
    wpRate: number
  }
  leads: {
    total: number
    conversionRate: number
  }
  scoreDistribution: {
    critical: number
    deficient: number
    regular: number
    good: number
    excellent: number
  }
  trend30d: Array<{
    date: string
    count: number
    avgScore: number | null
  }>
  recentAudits: Array<{
    id: string
    url: string
    domain: string
    leadName: string | null
    leadEmail: string | null
    leadWhatsapp: string | null
    leadCompany: string | null
    globalScore: number
    globalLevel: string
    isWordPress: boolean
    isPinned: boolean
    createdAt: string
    hasContactInfo: boolean
  }>
  recurringDomains: Array<{
    domain: string
    totalAudits: number
    bestScore: number
    worstScore: number
    lastAuditId: string
    trend: 'improving' | 'stable' | 'declining'
  }>
  queue: {
    running: number
    queued: number
    failedLastHour: number
    completedLastHour: number
    maxConcurrent: number
  }
  snapshots: {
    connected: number
    percentageOfWp: number
  }
  vulnerabilities: {
    total: number
    lastUpdate: string | null
  }
  security: {
    twoFaEnabled: boolean
    recoveryCodesLeft: number
  }
  pluginVault: {
    cached: boolean
    version: string | null
    checkedAt: string | null
  }
  retention: {
    enabled: boolean
    months: number
  }
  cronHealth: {
    overallOk: boolean
    counts: { ok: number; warning: number; critical: number; never: number }
  } | null
}
