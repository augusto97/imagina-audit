// === TIPOS DEL PANEL ADMIN ===

import type { SemaphoreLevel } from './audit'

export interface DashboardStats {
  totalAudits: number
  totalLeads: number
  auditsToday: number
  auditsThisWeek: number
  auditsThisMonth: number
  averageScore: number
  scoreDistribution: {
    critical: number
    deficient: number
    regular: number
    good: number
    excellent: number
  }
  recentAudits: LeadSummary[]
}

export interface LeadSummary {
  id: string
  url: string
  domain: string
  leadName: string | null
  leadEmail: string | null
  leadWhatsapp: string | null
  leadCompany: string | null
  globalScore: number
  globalLevel: SemaphoreLevel
  timestamp: string
  hasContactInfo: boolean
}

export interface AppSettings {
  companyName: string
  companyUrl: string
  companyWhatsapp: string
  companyEmail: string
  companyPlansUrl: string
  logoUrl: string
  googlePagespeedApiKey: string
  moduleWeights: Record<string, number>
  thresholds: {
    excellent: number
    good: number
    warning: number
    critical: number
  }
  salesMessages: Record<string, string>
  ctaTitle: string
  ctaDescription: string
  ctaButtonWhatsappText: string
  ctaButtonPlansText: string
  plans: {
    name: string
    price: string
    currency: string
  }[]
}

export interface VulnerabilityEntry {
  id: number
  pluginSlug: string
  pluginName: string
  affectedVersions: string
  severity: 'low' | 'medium' | 'high' | 'critical'
  cveId: string
  description: string
  fixedInVersion: string
  dateAdded: string
}
