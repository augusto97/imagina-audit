import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, ExternalLink, Printer } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { getLevelLabel, getLevelColor } from '@/lib/utils'
import type { AuditResult, ModuleResult, MetricResult } from '@/types/audit'

export default function TechnicalReport() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { fetchLeadDetail } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    fetchLeadDetail(id).then((data: AuditResult) => {
      setResult(data)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail])

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-48 rounded-2xl" /></div>
  }
  if (!result) {
    return <div className="text-center py-12 text-[var(--text-secondary)]">Auditoría no encontrada</div>
  }

  const criticalMetrics = getAllMetricsByLevel(result, 'critical')
  const warningMetrics = getAllMetricsByLevel(result, 'warning')
  const goodMetrics = getAllMetricsByLevel(result, 'good')

  return (
    <div className="space-y-8 max-w-4xl">
      <ReportHeader result={result} onBack={() => navigate('/admin/leads')} />
      <ExecutiveSummary result={result} criticalCount={criticalMetrics.length} warningCount={warningMetrics.length} />
      <ActionPlan critical={criticalMetrics} warning={warningMetrics} />
      {result.modules.map(m => (
        <ModuleDetail key={m.id} module={m} />
      ))}
    </div>
  )
}

/* === Helpers === */

interface MetricWithModule extends MetricResult {
  moduleName: string
  moduleId: string
}

function getAllMetricsByLevel(result: AuditResult, level: string): MetricWithModule[] {
  const items: MetricWithModule[] = []
  for (const mod of result.modules) {
    for (const metric of mod.metrics) {
      if (metric.level === level) {
        items.push({ ...metric, moduleName: mod.name, moduleId: mod.id })
      }
    }
  }
  return items
}

function levelBg(level: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-50 border-red-200',
    warning: 'bg-amber-50 border-amber-200',
    good: 'bg-emerald-50 border-emerald-200',
    excellent: 'bg-emerald-50 border-emerald-200',
  }
  return map[level] || 'bg-gray-50 border-gray-200'
}

function levelDot(level: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-500',
    warning: 'bg-amber-500',
    good: 'bg-emerald-500',
    excellent: 'bg-emerald-600',
  }
  return map[level] || 'bg-gray-400'
}

/* === Sub-components === */

function ReportHeader({ result, onBack }: { result: AuditResult; onBack: () => void }) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" strokeWidth={1.5} />
        </Button>
        <div>
          <h1 className="text-xl font-bold text-[var(--text-primary)]">Reporte Técnico</h1>
          <div className="flex items-center gap-2 mt-0.5">
            <a href={result.url} target="_blank" rel="noreferrer" className="text-sm text-[var(--accent-primary)] hover:underline flex items-center gap-1">
              {result.domain} <ExternalLink className="h-3 w-3" />
            </a>
            <span className="text-xs text-[var(--text-tertiary)]">
              {new Date(result.timestamp).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' })}
            </span>
          </div>
        </div>
      </div>
      <Button variant="outline" size="sm" onClick={() => window.print()}>
        <Printer className="h-4 w-4 mr-1" strokeWidth={1.5} /> Imprimir
      </Button>
    </div>
  )
}

function ExecutiveSummary({ result, criticalCount, warningCount }: { result: AuditResult; criticalCount: number; warningCount: number }) {
  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">Resumen Ejecutivo</h2>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <SummaryCard label="Score Global" value={`${result.globalScore}/100`} color={getLevelColor(result.globalLevel)} />
        <SummaryCard label="Críticos" value={String(criticalCount)} color="var(--color-critical)" />
        <SummaryCard label="Importantes" value={String(warningCount)} color="var(--color-warning)" />
        <SummaryCard label="WordPress" value={result.isWordPress ? 'Sí' : 'No'} color="var(--accent-primary)" />
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {result.modules.map(m => (
          <div key={m.id} className="flex items-center gap-2 text-sm">
            <div className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: getLevelColor(m.level) }} />
            <span className="text-[var(--text-secondary)] truncate">{m.name}</span>
            <span className="font-semibold text-[var(--text-primary)] ml-auto">{m.score ?? '—'}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

function SummaryCard({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3 text-center">
      <p className="text-xs text-[var(--text-tertiary)] mb-1">{label}</p>
      <p className="text-2xl font-bold" style={{ color }}>{value}</p>
    </div>
  )
}

function ActionPlan({ critical, warning }: { critical: MetricWithModule[]; warning: MetricWithModule[] }) {
  if (critical.length === 0 && warning.length === 0) {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <h2 className="text-lg font-bold text-emerald-700">Sin problemas detectados</h2>
        <p className="text-sm text-emerald-600 mt-1">El sitio está en buen estado. No se requieren acciones correctivas urgentes.</p>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <h2 className="text-lg font-bold text-[var(--text-primary)] mb-1">Plan de Acción</h2>
      <p className="text-sm text-[var(--text-secondary)] mb-4">Priorizado por severidad. Corregir primero los críticos.</p>

      {critical.length > 0 && (
        <div className="mb-4">
          <h3 className="text-sm font-bold text-red-600 mb-2 flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-red-500" /> Prioridad Alta — {critical.length} problemas críticos
          </h3>
          <div className="space-y-2">
            {critical.map((m, i) => (
              <ActionItem key={m.id + i} index={i + 1} metric={m} />
            ))}
          </div>
        </div>
      )}

      {warning.length > 0 && (
        <div>
          <h3 className="text-sm font-bold text-amber-600 mb-2 flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-amber-500" /> Prioridad Media — {warning.length} mejoras importantes
          </h3>
          <div className="space-y-2">
            {warning.map((m, i) => (
              <ActionItem key={m.id + i} index={critical.length + i + 1} metric={m} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

function ActionItem({ index, metric }: { index: number; metric: MetricWithModule }) {
  return (
    <div className={`rounded-xl border p-3 ${levelBg(metric.level)}`}>
      <div className="flex items-start gap-2">
        <span className="text-xs font-bold text-[var(--text-tertiary)] mt-0.5 shrink-0 w-5">#{index}</span>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm font-semibold text-[var(--text-primary)]">{metric.name}</span>
            <Badge variant="secondary" className="text-[10px]">{metric.moduleName}</Badge>
          </div>
          <p className="text-sm text-[var(--text-secondary)] mt-1">{metric.description}</p>
          {metric.recommendation && (
            <p className="text-sm font-medium text-[var(--text-primary)] mt-2">
              → {metric.recommendation}
            </p>
          )}
        </div>
      </div>
    </div>
  )
}

function ModuleDetail({ module }: { module: ModuleResult }) {
  const issues = module.metrics.filter(m => m.level === 'critical' || m.level === 'warning')
  const passed = module.metrics.filter(m => m.level === 'good' || m.level === 'excellent')
  const info = module.metrics.filter(m => m.level !== 'critical' && m.level !== 'warning' && m.level !== 'good' && m.level !== 'excellent')

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className="h-3 w-3 rounded-full" style={{ backgroundColor: getLevelColor(module.level) }} />
          <h2 className="text-lg font-bold text-[var(--text-primary)]">{module.name}</h2>
          <Badge variant={module.level === 'critical' ? 'destructive' : module.level === 'warning' ? 'warning' : 'success'}>
            {module.score}/100 — {getLevelLabel(module.level)}
          </Badge>
        </div>
      </div>

      {issues.length > 0 && (
        <div className="mb-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-red-500 mb-2">Problemas a corregir</h3>
          <div className="space-y-3">
            {issues.map(m => <MetricDetail key={m.id} metric={m} />)}
          </div>
        </div>
      )}

      {passed.length > 0 && (
        <div className="mb-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-500 mb-2">Aprobados</h3>
          <div className="space-y-1">
            {passed.map(m => (
              <div key={m.id} className="flex items-center gap-2 text-sm py-1">
                <div className={`h-2 w-2 rounded-full ${levelDot(m.level)}`} />
                <span className="text-[var(--text-secondary)]">{m.name}</span>
                <span className="ml-auto text-xs text-[var(--text-tertiary)]">{m.displayValue}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {info.length > 0 && (
        <div>
          <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Informativos</h3>
          <div className="space-y-1">
            {info.map(m => (
              <div key={m.id} className="flex items-center gap-2 text-sm py-1">
                <div className="h-2 w-2 rounded-full bg-gray-400" />
                <span className="text-[var(--text-secondary)]">{m.name}</span>
                <span className="ml-auto text-xs text-[var(--text-tertiary)]">{m.displayValue}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

function MetricDetail({ metric }: { metric: MetricResult }) {
  const details = metric.details || {}

  return (
    <div className={`rounded-xl border p-4 ${levelBg(metric.level)}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 mb-1">
            <div className={`h-2.5 w-2.5 rounded-full shrink-0 ${levelDot(metric.level)}`} />
            <span className="font-semibold text-sm text-[var(--text-primary)]">{metric.name}</span>
          </div>
          <p className="text-sm text-[var(--text-secondary)] mb-2">{metric.description}</p>

          {/* Detalles técnicos expandidos */}
          {renderTechnicalDetails(metric.id, details)}

          {metric.recommendation && (
            <div className="mt-3 rounded-lg bg-white/80 border border-[var(--border-default)] p-3">
              <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">CÓMO CORREGIR</p>
              <p className="text-sm text-[var(--text-primary)]">{metric.recommendation}</p>
            </div>
          )}
        </div>
        <span className="text-xs font-bold shrink-0 px-2 py-1 rounded-lg bg-white/60">{metric.score}/100</span>
      </div>
    </div>
  )
}

function renderTechnicalDetails(metricId: string, details: Record<string, unknown>) {
  if (!details || Object.keys(details).length === 0) return null

  // Plugins con vulnerabilidades o desactualizados
  if (metricId === 'wp_plugins' && Array.isArray(details.plugins)) {
    const plugins = details.plugins as Array<Record<string, unknown>>
    const outdated = plugins.filter(p => p.outdated)
    if (outdated.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] overflow-hidden">
        <table className="w-full text-xs">
          <thead><tr className="bg-[var(--bg-tertiary)]">
            <th className="text-left px-3 py-1.5 font-semibold">Plugin</th>
            <th className="text-left px-3 py-1.5 font-semibold">Instalada</th>
            <th className="text-left px-3 py-1.5 font-semibold">Actualizar a</th>
          </tr></thead>
          <tbody>
            {outdated.map((p, i) => (
              <tr key={i} className="border-t border-[var(--border-default)]">
                <td className="px-3 py-1.5 font-medium">{String(p.name)}</td>
                <td className="px-3 py-1.5 text-red-600">{String(p.detectedVersion || '?')}</td>
                <td className="px-3 py-1.5 text-emerald-600 font-semibold">{String(p.latestVersion || '?')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  // Archivos sensibles
  if (metricId === 'sensitive_files' && Array.isArray(details.files) && (details.files as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">ARCHIVOS EXPUESTOS PÚBLICAMENTE</p>
        <ul className="space-y-1">
          {(details.files as string[]).map((f, i) => (
            <li key={i} className="text-xs font-mono text-red-700">{f}</li>
          ))}
        </ul>
        <p className="text-xs text-[var(--text-secondary)] mt-2">Eliminar estos archivos del servidor o bloquear acceso vía .htaccess.</p>
      </div>
    )
  }

  // Security headers
  if (metricId === 'security_headers' && details.missing && Array.isArray(details.missing)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">HEADERS FALTANTES — Agregar en .htaccess o configuración del servidor</p>
        <div className="space-y-1.5 font-mono text-xs">
          {(details.missing as string[]).map((h, i) => (
            <div key={i} className="text-[var(--text-primary)]">
              Header set {h} {getHeaderExample(h)}
            </div>
          ))}
        </div>
      </div>
    )
  }

  // Vulnerabilidades
  if (metricId === 'plugin_vulnerabilities' && Array.isArray(details.vulnerabilities)) {
    const vulns = details.vulnerabilities as Array<Record<string, unknown>>
    if (vulns.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 overflow-hidden">
        <table className="w-full text-xs">
          <thead><tr className="bg-red-50">
            <th className="text-left px-3 py-1.5 font-semibold">Plugin</th>
            <th className="text-left px-3 py-1.5 font-semibold">CVE</th>
            <th className="text-left px-3 py-1.5 font-semibold">Severidad</th>
            <th className="text-left px-3 py-1.5 font-semibold">Fix</th>
          </tr></thead>
          <tbody>
            {vulns.map((v, i) => (
              <tr key={i} className="border-t border-red-100">
                <td className="px-3 py-1.5 font-medium">{String(v.pluginName || v.plugin)}</td>
                <td className="px-3 py-1.5 font-mono">{String(v.cveId || '—')}</td>
                <td className="px-3 py-1.5"><Badge variant={v.severity === 'critical' ? 'destructive' : 'warning'} className="text-[10px]">{String(v.severity)}</Badge></td>
                <td className="px-3 py-1.5 text-emerald-600 font-semibold">{String(v.fixedInVersion || 'Actualizar')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  // Open Graph / Twitter Cards tags
  if ((metricId === 'open_graph' || metricId === 'twitter_cards') && details.tags) {
    const tags = details.tags as Record<string, string | null>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">ETIQUETAS DETECTADAS</p>
        <div className="space-y-1 text-xs">
          {Object.entries(tags).map(([key, val]) => (
            <div key={key} className="flex gap-2">
              <span className="font-mono font-semibold w-36 shrink-0">{key}</span>
              <span className={val ? 'text-[var(--text-secondary)]' : 'text-red-500 font-medium'}>
                {val ? (String(val).length > 60 ? String(val).substring(0, 60) + '...' : String(val)) : '✗ Faltante'}
              </span>
            </div>
          ))}
        </div>
      </div>
    )
  }

  // PageSpeed opportunities
  if (metricId === 'pagespeed_opportunities' && Array.isArray(details.opportunities)) {
    const opps = details.opportunities as Array<Record<string, unknown>>
    if (opps.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">OPORTUNIDADES DE OPTIMIZACIÓN</p>
        <div className="space-y-1.5 text-xs">
          {opps.map((o, i) => (
            <div key={i} className="flex items-center justify-between gap-2">
              <span className="text-[var(--text-primary)]">{String(o.title)}</span>
              {Number(o.savings) > 0 && (
                <span className="text-amber-600 font-semibold shrink-0">-{(Number(o.savings) / 1000).toFixed(1)}s</span>
              )}
            </div>
          ))}
        </div>
      </div>
    )
  }

  // Heading hierarchy
  if (metricId === 'heading_hierarchy' && details.counts) {
    const counts = details.counts as Record<string, number>
    return (
      <div className="mt-2 flex gap-3 text-xs">
        {Object.entries(counts).filter(([, v]) => v > 0).map(([tag, count]) => (
          <span key={tag} className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)] font-mono">
            {tag.toUpperCase()}: {count}
          </span>
        ))}
      </div>
    )
  }

  return null
}

function getHeaderExample(header: string): string {
  const examples: Record<string, string> = {
    'X-Content-Type-Options': '"nosniff"',
    'X-Frame-Options': '"SAMEORIGIN"',
    'Content-Security-Policy': '"default-src \'self\'"',
    'Strict-Transport-Security': '"max-age=31536000; includeSubDomains"',
    'X-XSS-Protection': '"1; mode=block"',
    'Referrer-Policy': '"strict-origin-when-cross-origin"',
    'Permissions-Policy': '"camera=(), microphone=(), geolocation=()"',
  }
  return examples[header] || '"value"'
}
