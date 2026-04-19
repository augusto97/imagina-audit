// === TIPOS PRINCIPALES DE AUDITORÍA ===

export type SemaphoreLevel = 'critical' | 'warning' | 'good' | 'excellent' | 'info' | 'unknown'

export interface MetricResult {
  /** Identificador único: "ssl_valid", "pagespeed_mobile", etc. */
  id: string
  /** Nombre legible: "Certificado SSL" */
  name: string
  /** Valor detectado */
  value: string | number | boolean | null
  /** Valor formateado para mostrar: "Válido hasta 2025-12-01" */
  displayValue: string
  /** 0-100 para esta métrica individual */
  score: number
  /** Semáforo visual */
  level: SemaphoreLevel
  /** Explicación para el usuario */
  description: string
  /** Qué debería hacer */
  recommendation: string
  /** Cómo Imagina WP lo resuelve */
  imaginaSolution: string
  /** Datos adicionales opcionales */
  details?: Record<string, unknown>
}

export interface ModuleResult {
  /** 'wordpress' | 'security' | 'performance' | 'seo' | 'mobile' | 'infrastructure' | 'conversion' | 'backups' */
  id: string
  /** Nombre legible: "Seguridad" */
  name: string
  /** Nombre del icono Lucide: "shield", "gauge", etc. */
  icon: string
  /** 0-100 promedio ponderado del módulo */
  score: number
  /** Nivel de semáforo */
  level: SemaphoreLevel
  /** Peso en el score global (0.05 - 0.25) */
  weight: number
  /** Métricas individuales */
  metrics: MetricResult[]
  /** Resumen del módulo */
  summary: string
  /** Mensaje de venta */
  salesMessage: string
}

export interface SolutionItem {
  /** Descripción del problema */
  problem: string
  /** Nivel de severidad */
  level: SemaphoreLevel
  /** Solución que ofrece Imagina WP */
  solution: string
  /** Plan que incluye la solución: "Basic" | "Pro" | "Custom" */
  includedInPlan: string
}

export interface AuditResult {
  /** UUID del audit */
  id: string
  /** URL escaneada */
  url: string
  /** Dominio limpio */
  domain: string
  /** Fecha ISO 8601 */
  timestamp: string
  /** Duración total en milisegundos */
  scanDurationMs: number
  /** 0-100 score ponderado global */
  globalScore: number
  /** Nivel global */
  globalLevel: SemaphoreLevel
  /** Conteo de problemas por tipo */
  totalIssues: {
    critical: number
    warning: number
    good: number
  }
  /** Resultados de cada módulo */
  modules: ModuleResult[]
  /** ¿Es WordPress? */
  isWordPress: boolean
  /** Estimación de impacto económico */
  economicImpact: {
    estimatedMonthlyLoss: number
    currency: string
    explanation: string
  }
  /** Mapeo problema → solución */
  solutionMap: SolutionItem[]
  /** Stack tecnológico detectado (informativo, no afecta score) */
  techStack?: {
    server?: string | null
    cms?: string | null
    pageBuilder?: string[]
    ecommerce?: string[]
    cachePlugin?: string[]
    seoPlugin?: string[]
    securityPlugin?: string[]
    jsLibraries?: string[]
    cssFramework?: string[]
    fonts?: string[]
    cdn?: string | null
    analytics?: string[]
    phpVersion?: string | null
    httpProtocol?: string | null
    hostingInfo?: {
      ip?: string | null
      provider?: string | null
      country?: string | null
      city?: string | null
      reverseDns?: string | null
      nameservers?: string[]
    } | null
    domainInfo?: {
      domain?: string | null
      registrar?: string | null
      createdDate?: string | null
      expiryDate?: string | null
      daysUntilExpiry?: number | null
    } | null
  }
}

export interface AuditRequest {
  url: string
  leadName?: string
  leadEmail?: string
  leadWhatsapp?: string
  leadCompany?: string
  /** Forzar nuevo escaneo ignorando cache */
  forceRefresh?: boolean
}

/** Estado de la auditoría en curso */
export type AuditStatus = 'idle' | 'scanning' | 'completed' | 'error'

/**
 * Progreso reportado por el backend durante un audit en background.
 * Se lee vía GET /api/scan-progress?id=X con polling cada 1.5s.
 */
export interface AuditProgress {
  status: 'running' | 'completed' | 'failed'
  /** init | fetch | wordpress | security | performance | seo | mobile | infrastructure | conversion | page_health | wp_internal | techstack | compile */
  currentStep: string
  /** Texto legible: "Analizando seguridad..." */
  currentLabel: string
  completedSteps: number
  totalSteps: number
  progress: number
  startedAt: number
  /** Presente cuando status=completed */
  auditId?: string
  /** Presente cuando status=failed */
  error?: string
}
