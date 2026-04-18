import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, ExternalLink, Printer } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import { getLevelLabel, getLevelColor } from '@/lib/utils'
import api from '@/lib/api'
import type { AuditResult, ModuleResult, MetricResult } from '@/types/audit'

interface ChecklistState {
  [metricId: string]: { completed: boolean; notes: string | null; completedAt: string | null }
}

export default function TechnicalReport() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { fetchLeadDetail } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [checklist, setChecklist] = useState<ChecklistState>({})
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    Promise.all([
      fetchLeadDetail(id),
      api.get('/admin/checklist.php', { params: { audit_id: id } }).then(r => r.data?.data).catch(() => [])
    ]).then(([audit, items]: [AuditResult, Array<{ metric_id: string; completed: number; notes: string | null; completed_at: string | null }>]) => {
      setResult(audit)
      const state: ChecklistState = {}
      for (const item of items || []) {
        state[item.metric_id] = { completed: item.completed === 1, notes: item.notes, completedAt: item.completed_at }
      }
      setChecklist(state)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail])

  const toggleCheck = useCallback((metricId: string) => {
    if (!id) return
    const current = checklist[metricId]?.completed ?? false
    const newVal = !current
    setChecklist(prev => ({ ...prev, [metricId]: { completed: newVal, notes: prev[metricId]?.notes ?? null, completedAt: newVal ? new Date().toISOString() : null } }))
    api.put('/admin/checklist.php', { auditId: id, metricId, completed: newVal }).catch(() => {
      // Revert on error
      setChecklist(prev => ({ ...prev, [metricId]: { completed: current, notes: prev[metricId]?.notes ?? null, completedAt: null } }))
    })
  }, [id, checklist])

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-48 rounded-2xl" /></div>
  }
  if (!result) {
    return <div className="text-center py-12 text-[var(--text-secondary)]">Auditoría no encontrada</div>
  }

  const criticalMetrics = getAllMetricsByLevel(result, 'critical')
  const warningMetrics = getAllMetricsByLevel(result, 'warning')

  return (
    <div className="space-y-8">
      <ReportHeader result={result} onBack={() => navigate('/admin/leads')} />
      <ExecutiveSummary result={result} criticalCount={criticalMetrics.length} warningCount={warningMetrics.length} />
      {result.techStack && <TechStackSummary techStack={result.techStack} scanDuration={result.scanDurationMs} />}
      <ActionPlan critical={criticalMetrics} warning={warningMetrics} checklist={checklist} onToggle={toggleCheck} />
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

function TechStackSummary({ techStack, scanDuration }: { techStack: NonNullable<AuditResult['techStack']>; scanDuration: number }) {
  const hosting = techStack.hostingInfo
  const domain = techStack.domainInfo

  const techItems: Array<{ label: string; value: string }> = []
  if (techStack.server) techItems.push({ label: 'Servidor', value: techStack.server })
  if (techStack.phpVersion) techItems.push({ label: 'PHP', value: techStack.phpVersion })
  if (techStack.httpProtocol) techItems.push({ label: 'Protocolo', value: techStack.httpProtocol })
  if (techStack.cms) techItems.push({ label: 'CMS', value: techStack.cms })
  if (techStack.pageBuilder?.length) techItems.push({ label: 'Page Builder', value: techStack.pageBuilder.join(', ') })
  if (techStack.ecommerce?.length) techItems.push({ label: 'Ecommerce', value: techStack.ecommerce.join(', ') })
  if (techStack.cachePlugin?.length) techItems.push({ label: 'Cache', value: techStack.cachePlugin.join(', ') })
  if (techStack.seoPlugin?.length) techItems.push({ label: 'SEO Plugin', value: techStack.seoPlugin.join(', ') })
  if (techStack.securityPlugin?.length) techItems.push({ label: 'Seguridad', value: techStack.securityPlugin.join(', ') })
  if (techStack.cdn) techItems.push({ label: 'CDN', value: techStack.cdn })
  if (techStack.analytics?.length) techItems.push({ label: 'Analytics', value: techStack.analytics.join(', ') })

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6 space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold text-[var(--text-primary)]">Stack Tecnológico e Infraestructura</h2>
        {scanDuration > 0 && <span className="text-xs text-[var(--text-tertiary)]">Escaneo: {(scanDuration / 1000).toFixed(1)}s</span>}
      </div>

      {/* Hosting & Domain info */}
      {(hosting || domain) && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {hosting && (
            <div className="rounded-xl bg-[var(--bg-secondary)] p-4">
              <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Hosting</h3>
              <div className="space-y-1.5 text-sm">
                {hosting.ip && <div><span className="text-[var(--text-tertiary)]">IP:</span> <span className="font-mono font-medium">{hosting.ip}</span></div>}
                {hosting.provider && <div><span className="text-[var(--text-tertiary)]">Proveedor:</span> <span className="font-medium">{hosting.provider}</span></div>}
                {(hosting.city || hosting.country) && <div><span className="text-[var(--text-tertiary)]">Ubicación:</span> <span className="font-medium">{[hosting.city, hosting.country].filter(Boolean).join(', ')}</span></div>}
                {hosting.nameservers && hosting.nameservers.length > 0 && <div><span className="text-[var(--text-tertiary)]">NS:</span> <span className="font-mono text-xs">{hosting.nameservers.slice(0, 2).join(', ')}</span></div>}
              </div>
            </div>
          )}
          {domain && (
            <div className="rounded-xl bg-[var(--bg-secondary)] p-4">
              <h3 className="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] mb-2">Dominio</h3>
              <div className="space-y-1.5 text-sm">
                {domain.domain && <div><span className="text-[var(--text-tertiary)]">Dominio:</span> <span className="font-medium">{domain.domain}</span></div>}
                {domain.registrar && <div><span className="text-[var(--text-tertiary)]">Registrar:</span> <span className="font-medium">{domain.registrar}</span></div>}
                {domain.createdDate && <div><span className="text-[var(--text-tertiary)]">Registrado:</span> <span className="font-medium">{domain.createdDate}</span></div>}
                {domain.expiryDate && (
                  <div>
                    <span className="text-[var(--text-tertiary)]">Expira:</span>{' '}
                    <span className={`font-medium ${domain.daysUntilExpiry !== null && domain.daysUntilExpiry !== undefined && domain.daysUntilExpiry < 60 ? 'text-red-600' : ''}`}>
                      {domain.expiryDate}
                      {domain.daysUntilExpiry !== null && domain.daysUntilExpiry !== undefined && (
                        <span className="text-xs ml-1">({domain.daysUntilExpiry} días)</span>
                      )}
                    </span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Tech stack grid */}
      {techItems.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2">
          {techItems.map(({ label, value }) => (
            <div key={label} className="text-sm">
              <span className="text-[var(--text-tertiary)]">{label}: </span>
              <span className="font-medium text-[var(--text-primary)]">{value}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function ActionPlan({ critical, warning, checklist, onToggle }: { critical: MetricWithModule[]; warning: MetricWithModule[]; checklist: ChecklistState; onToggle: (metricId: string) => void }) {
  const allItems = [...critical, ...warning]
  const total = allItems.length
  const doneCount = allItems.filter(m => checklist[m.id]?.completed).length

  if (total === 0) {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <h2 className="text-lg font-bold text-emerald-700">Sin problemas detectados</h2>
        <p className="text-sm text-emerald-600 mt-1">El sitio está en buen estado. No se requieren acciones correctivas urgentes.</p>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <div className="flex items-center justify-between mb-1">
        <h2 className="text-lg font-bold text-[var(--text-primary)]">Plan de Acción</h2>
        <span className="text-xs font-medium text-[var(--text-tertiary)]">
          {doneCount}/{total} completados
          {doneCount > 0 && <span className="ml-2 text-emerald-500">({Math.round(doneCount / total * 100)}%)</span>}
        </span>
      </div>
      {doneCount < total && (
        <div className="w-full bg-gray-100 rounded-full h-1.5 mb-4">
          <div className="bg-emerald-500 h-1.5 rounded-full transition-all" style={{ width: `${(doneCount / total) * 100}%` }} />
        </div>
      )}
      {doneCount === total && <p className="text-sm text-emerald-600 mb-4 font-medium">Todas las correcciones completadas.</p>}

      {critical.length > 0 && (
        <div className="mb-4">
          <h3 className="text-sm font-bold text-red-600 mb-2 flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-red-500" /> Prioridad Alta — {critical.length} problemas críticos
          </h3>
          <div className="space-y-2">
            {critical.map((m, i) => (
              <ActionItem key={m.id} index={i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
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
              <ActionItem key={m.id} index={critical.length + i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

function ActionItem({ index, metric, checked, onToggle }: { index: number; metric: MetricWithModule; checked: boolean; onToggle: () => void }) {
  return (
    <div className={`rounded-xl border p-3 ${checked ? 'bg-emerald-50/50 border-emerald-200 opacity-70' : levelBg(metric.level)} transition-all`}>
      <div className="flex items-start gap-2">
        <button onClick={onToggle} className="mt-0.5 shrink-0 cursor-pointer">
          <div className={`h-5 w-5 rounded-md border-2 flex items-center justify-center transition-colors ${checked ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 hover:border-gray-400'}`}>
            {checked && <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>}
          </div>
        </button>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className={`text-sm font-semibold ${checked ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'}`}>{metric.name}</span>
            <Badge variant="secondary" className="text-[10px]">{metric.moduleName}</Badge>
            <span className="text-[10px] text-[var(--text-tertiary)]">#{index}</span>
          </div>
          {!checked && (
            <>
              <p className="text-sm text-[var(--text-secondary)] mt-1">{metric.description}</p>
              {metric.recommendation && (
                <p className="text-sm font-medium text-[var(--text-primary)] mt-2">
                  → {metric.recommendation}
                </p>
              )}
            </>
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
          {renderTechnicalDetails(metric.id, details, metric)}

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

function renderTechnicalDetails(metricId: string, details: Record<string, unknown>, metric?: MetricResult) {
  if (!details || Object.keys(details).length === 0) return null

  // SSL certificate details
  if (metricId === 'ssl_valid' && (details.issuer || details.validTo)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1">
        {details.issuer != null && <div><span className="font-semibold">Emisor:</span> {String(details.issuer)}</div>}
        {details.protocol != null && <div><span className="font-semibold">Protocolo:</span> {String(details.protocol)}</div>}
        {details.validFrom != null && <div><span className="font-semibold">Válido desde:</span> {String(details.validFrom)}</div>}
        {details.validTo != null && <div><span className="font-semibold">Expira:</span> <span className={Number(details.daysRemaining) < 30 ? 'text-red-600 font-bold' : ''}>{String(details.validTo)} ({String(details.daysRemaining)} días restantes)</span></div>}
      </div>
    )
  }

  // Exposed headers — show what to remove
  if (metricId === 'exposed_headers' && Array.isArray(details.exposed) && (details.exposed as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3">
        <p className="text-xs font-bold text-amber-700 mb-1">HEADERS QUE EXPONEN INFORMACIÓN DEL SERVIDOR</p>
        <div className="space-y-1 text-xs">
          {(details.exposed as string[]).map((h, i) => (
            <div key={i} className="font-mono text-amber-800">{h}</div>
          ))}
        </div>
        <p className="text-xs text-[var(--text-secondary)] mt-2">
          En .htaccess agregar: <code className="font-mono bg-gray-100 px-1 rounded">Header unset X-Powered-By</code> y <code className="font-mono bg-gray-100 px-1 rounded">ServerTokens Prod</code> en la config de Apache.
        </p>
      </div>
    )
  }

  // WordPress version — show upgrade target
  if (metricId === 'wp_version' && details.latestVersion) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs">
        <span className="font-semibold">Versión instalada:</span> {String(metric?.value || '?')} →{' '}
        <span className="font-semibold text-emerald-600">Actualizar a: {String(details.latestVersion)}</span>
        <p className="text-[var(--text-tertiary)] mt-1">Actualizar desde Dashboard → Actualizaciones. Hacer backup previo.</p>
      </div>
    )
  }

  // Theme info
  if (metricId === 'wp_theme' && (details.themeName || details.childTheme !== undefined)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1">
        {details.themeName != null && <div><span className="font-semibold">Tema:</span> {String(details.themeName)}</div>}
        {details.themeVersion != null && <div><span className="font-semibold">Versión:</span> {String(details.themeVersion)}</div>}
        <div><span className="font-semibold">Child theme:</span> {details.childTheme ? <span className="text-emerald-600">Sí</span> : <span className="text-amber-600">No — Las personalizaciones se perderán al actualizar el tema</span>}</div>
      </div>
    )
  }

  // REST API exposed users
  if (metricId === 'rest_api_exposed' && details.users && Array.isArray(details.users)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">USUARIOS EXPUESTOS VÍA REST API</p>
        <div className="flex flex-wrap gap-2">
          {(details.users as string[]).map((u, i) => (
            <span key={i} className="text-xs font-mono px-2 py-0.5 bg-red-100 rounded text-red-700">{u}</span>
          ))}
        </div>
        <p className="text-xs text-[var(--text-secondary)] mt-2">Bloquear en functions.php o con plugin de seguridad (Wordfence, iThemes).</p>
      </div>
    )
  }

  // User enumeration
  if (metricId === 'user_enumeration' && details.username) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs">
        <span className="font-semibold">Username detectado:</span>{' '}
        <span className="font-mono text-amber-700">{String(details.username)}</span>
        <p className="text-[var(--text-secondary)] mt-1">Bloquear /?author=N con regla en .htaccess:<br/>
          <code className="font-mono bg-gray-100 px-1 rounded">RewriteRule ^/?author= - [F,L]</code>
        </p>
      </div>
    )
  }

  // Images missing alt — show file names
  if (metricId === 'images_alt' && Array.isArray(details.missingExamples) && (details.missingExamples as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">IMÁGENES SIN TEXTO ALT ({String(details.withoutAlt)} total)</p>
        <div className="flex flex-wrap gap-1.5">
          {(details.missingExamples as string[]).map((f, i) => (
            <span key={i} className="text-[10px] font-mono px-2 py-0.5 bg-gray-100 rounded text-[var(--text-secondary)] break-all">{f}</span>
          ))}
        </div>
      </div>
    )
  }

  // Internal links stats
  if (metricId === 'internal_links' && (details.internal !== undefined || details.external !== undefined)) {
    return (
      <div className="mt-2 flex flex-wrap gap-3 text-xs">
        <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">Internos: <b>{String(details.internal ?? 0)}</b></span>
        <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">Externos: <b>{String(details.external ?? 0)}</b></span>
        {Number(details.nofollow) > 0 && <span className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)]">Nofollow: <b>{String(details.nofollow)}</b></span>}
        {Number(details.emptyAnchors) > 0 && <span className="px-2 py-1 rounded-lg bg-amber-50 border border-amber-200">Sin anchor text: <b>{String(details.emptyAnchors)}</b></span>}
      </div>
    )
  }

  // Directory listing
  if (metricId === 'directory_listing' && Array.isArray(details.exposed)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">DIRECTORIOS CON LISTADO PÚBLICO</p>
        <ul className="space-y-1">
          {(details.exposed as string[]).map((d, i) => (
            <li key={i} className="text-xs font-mono text-red-700">{d}</li>
          ))}
        </ul>
        <p className="text-xs text-[var(--text-secondary)] mt-2">Agregar <code className="font-mono bg-gray-100 px-1 rounded">Options -Indexes</code> en .htaccess de cada directorio.</p>
      </div>
    )
  }

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

    const cvssColor = (score: number) => {
      if (score >= 9) return 'bg-red-600 text-white'
      if (score >= 7) return 'bg-red-500 text-white'
      if (score >= 4) return 'bg-amber-500 text-white'
      return 'bg-yellow-400 text-gray-900'
    }

    return (
      <div className="mt-2 space-y-2">
        {vulns.map((v, i) => (
          <div key={i} className="rounded-lg border border-red-200 bg-red-50/50 p-3">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-semibold text-sm text-gray-900">{String(v.pluginName || v.plugin)}</span>
                  {v.cveId != null && <span className="text-[10px] font-mono text-gray-500">{String(v.cveId)}</span>}
                </div>
                {v.name != null && <p className="text-xs text-gray-600 mt-1">{String(v.name)}</p>}
                <div className="flex items-center gap-3 mt-2 text-[11px] text-gray-500">
                  {v.fixedInVersion != null && !v.unfixed && <span>Fix: <span className="font-semibold text-emerald-600">v{String(v.fixedInVersion)}</span></span>}
                  {v.unfixed === true && <span className="font-semibold text-red-600">Sin corrección disponible</span>}
                  {v.affectedVersions != null && <span>Afecta: {String(v.affectedVersions)}</span>}
                </div>
              </div>
              {v.cvssScore != null && Number(v.cvssScore) > 0 && (
                <div className="shrink-0 text-center">
                  <div className={`inline-block px-2.5 py-1 rounded-md text-sm font-bold ${cvssColor(Number(v.cvssScore))}`}>
                    {Number(v.cvssScore).toFixed(1)}
                  </div>
                  <div className="text-[9px] text-gray-400 mt-0.5">CVSS</div>
                </div>
              )}
            </div>
          </div>
        ))}
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
  // SERP preview
  if (metricId === 'serp_preview' && details.title) {
    const title = String(details.title)
    const desc = String(details.description || '')
    const domain = String(details.domain || '')
    return (
      <div className="mt-2 space-y-3">
        <div className="rounded-lg border border-gray-200 bg-white p-4 max-w-lg">
          <p className="text-xs text-gray-500 mb-1">Vista previa en escritorio</p>
          <div className="text-xs text-emerald-700 mb-0.5">{domain}</div>
          <div className="text-blue-700 text-base hover:underline cursor-pointer leading-snug">{title.length > 70 ? title.substring(0, 67) + '...' : title}</div>
          <div className="text-xs text-gray-600 mt-1 leading-relaxed">{desc.length > 160 ? desc.substring(0, 157) + '...' : desc || 'Sin meta description'}</div>
        </div>
        <div className="flex gap-4 text-[10px] text-gray-400">
          <span>Title: {String(details.titleLength)} car.</span>
          <span>Description: {String(details.descriptionLength)} car.</span>
        </div>
      </div>
    )
  }

  // Link stats with detail table
  if (metricId === 'link_stats' && Array.isArray(details.links)) {
    const links = details.links as Array<{ href: string; anchor: string; type: string; follow: string }>
    if (links.length === 0) return null
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-2 max-h-56 overflow-y-auto space-y-1">
        {links.map((l, i) => (
          <div key={i} className="text-[11px] border-b border-gray-100 pb-1 last:border-0">
            <div className="flex items-center gap-1.5 flex-wrap">
              <span className={`text-[9px] px-1.5 py-0.5 rounded ${l.type === 'internal' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'}`}>{l.type === 'internal' ? 'Int' : 'Ext'}</span>
              <span className={`text-[9px] ${l.follow === 'nofollow' ? 'text-red-500' : 'text-gray-400'}`}>{l.follow}</span>
              <span className="text-gray-700 font-medium">{l.anchor}</span>
            </div>
            <div className="text-gray-400 font-mono text-[10px] break-all">{l.href}</div>
          </div>
        ))}
      </div>
    )
  }

  // Keyword density
  if (metricId === 'keyword_density' && (details.topWords || details.topPhrases)) {
    const words = (details.topWords || {}) as Record<string, number>
    const phrases = (details.topPhrases || {}) as Record<string, number>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-2">
        {Object.keys(words).length > 0 && (
          <div>
            <p className="font-bold text-[var(--text-tertiary)] mb-1">PALABRAS MÁS FRECUENTES</p>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(words).map(([w, c]) => (
                <span key={w} className="px-2 py-0.5 rounded bg-gray-100 text-gray-700">{w} <b>{String(c)}</b></span>
              ))}
            </div>
          </div>
        )}
        {Object.keys(phrases).length > 0 && (
          <div>
            <p className="font-bold text-[var(--text-tertiary)] mb-1">FRASES FRECUENTES</p>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(phrases).map(([p, c]) => (
                <span key={p} className="px-2 py-0.5 rounded bg-blue-50 text-blue-700">{p} <b>{String(c)}</b></span>
              ))}
            </div>
          </div>
        )}
      </div>
    )
  }

  // URL resolution
  if (metricId === 'url_resolution' && Array.isArray(details.results)) {
    const results = details.results as Array<{ variant: string; redirectsTo: string; matches: boolean; status: number }>
    return (
      <div className="mt-2 space-y-1.5">
        {results.map((r, i) => (
          <div key={i} className="rounded-lg bg-white/60 border border-[var(--border-default)] p-2 text-xs">
            <div className="font-mono text-gray-600 break-all">{r.variant}</div>
            <div className="font-mono text-gray-500 break-all mt-0.5">→ {r.redirectsTo}</div>
            <span className={`text-[10px] font-medium ${r.matches ? 'text-emerald-600' : 'text-red-500'}`}>{r.matches ? '✓ Correcto' : '✗ No coincide'}</span>
          </div>
        ))}
      </div>
    )
  }

  if (metricId === 'heading_hierarchy') {
    const counts = (details.counts || {}) as Record<string, number>
    const headings = (details.headings || []) as Array<{ level: number; tag: string; text: string }>
    return (
      <div className="mt-2 space-y-2">
        <div className="flex gap-3 text-xs">
          {Object.entries(counts).filter(([, v]) => v > 0).map(([tag, count]) => (
            <span key={tag} className="px-2 py-1 rounded-lg bg-white/60 border border-[var(--border-default)] font-mono">
              {tag.toUpperCase()}: {count}
            </span>
          ))}
        </div>
        {headings.length > 0 && (
          <div className="rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-1 max-h-64 overflow-y-auto">
            {headings.map((h, i) => (
              <div key={i} className="flex items-start gap-2" style={{ paddingLeft: `${(h.level - 1) * 16}px` }}>
                <span className="shrink-0 font-mono font-bold text-[var(--accent-primary)]">{h.tag}</span>
                <span className="text-gray-700">{h.text || '(vacío)'}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    )
  }

  // Broken resources
  if (metricId === 'broken_resources' && Array.isArray(details.broken) && (details.broken as Array<Record<string, unknown>>).length > 0) {
    const broken = details.broken as Array<{ url: string; type: string; status: number }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-red-200 p-3">
        <p className="text-xs font-bold text-red-600 mb-1">RECURSOS ROTOS ({broken.length} de {String(details.checked)} verificados)</p>
        {broken.map((b, i) => (
          <div key={i} className="text-xs flex items-center gap-2 py-0.5">
            <span className="text-red-600 font-mono">{b.status}</span>
            <span className="text-gray-500">[{b.type}]</span>
            <span className="text-gray-700 truncate">{b.url}</span>
          </div>
        ))}
      </div>
    )
  }

  // HTML errors
  if (metricId === 'html_errors' && Array.isArray(details.errors) && (details.errors as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3">
        <p className="text-xs font-bold text-amber-700 mb-1">PROBLEMAS DETECTADOS</p>
        <ul className="space-y-0.5">
          {(details.errors as string[]).map((e, i) => <li key={i} className="text-xs text-gray-700">- {e}</li>)}
        </ul>
        {Number(details.inlineStyles) > 20 && <p className="text-xs text-gray-500 mt-1">{String(details.inlineStyles)} estilos inline detectados</p>}
      </div>
    )
  }

  // Oversize headings
  if (metricId === 'oversize_headings' && Array.isArray(details.oversized) && (details.oversized as Array<Record<string, unknown>>).length > 0) {
    const items = details.oversized as Array<{ tag: string; text: string; length: number; maxLength: number }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs space-y-1">
        {items.map((h, i) => (
          <div key={i}><span className="font-mono font-bold text-amber-600">{h.tag}</span> <span className="text-gray-500">({h.length} car., máx {h.maxLength})</span>: <span className="text-gray-700">{h.text}</span></div>
        ))}
      </div>
    )
  }

  // Oversized alt
  if (metricId === 'oversized_alt' && Array.isArray(details.oversized) && (details.oversized as Array<Record<string, unknown>>).length > 0) {
    const items = details.oversized as Array<{ file: string; altLength: number; altPreview: string }>
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-amber-200 p-3 text-xs space-y-1">
        {items.map((a, i) => (
          <div key={i}><span className="font-medium text-gray-700">{a.file}</span> <span className="text-amber-600">({a.altLength} car.)</span>: <span className="text-gray-500">{a.altPreview}</span></div>
        ))}
      </div>
    )
  }

  // Exposed emails
  if (metricId === 'exposed_email' && Array.isArray(details.emails) && (details.emails as string[]).length > 0) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.emails as string[]).map((e, i) => (
          <span key={i} className="text-xs font-mono px-2 py-0.5 bg-amber-50 border border-amber-200 rounded text-amber-700">{e}</span>
        ))}
      </div>
    )
  }

  // DMARC value
  if (metricId === 'dmarc' && details.value) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3">
        <p className="text-xs font-bold text-[var(--text-tertiary)] mb-1">REGISTRO DMARC</p>
        <code className="text-[10px] text-gray-600 font-mono break-all">{String(details.value)}</code>
      </div>
    )
  }

  // Structured data types
  if (metricId === 'structured_data' && Array.isArray(details.types)) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.types as string[]).map((t, i) => (
          <span key={i} className="text-xs px-2 py-0.5 bg-blue-50 border border-blue-200 rounded text-blue-700">{t}</span>
        ))}
      </div>
    )
  }

  // Cache details
  if (metricId === 'cache_headers' && Array.isArray(details.details)) {
    return (
      <div className="mt-2 rounded-lg bg-white/60 border border-[var(--border-default)] p-3 text-xs space-y-0.5">
        {(details.details as string[]).map((d, i) => <div key={i} className="text-gray-600">{d}</div>)}
      </div>
    )
  }

  // Text/code ratio
  if (metricId === 'text_code_ratio' && details.ratio != null) {
    const ratio = Number(details.ratio)
    return (
      <div className="mt-2 flex items-center gap-3 text-xs">
        <div className="flex-1 bg-gray-100 rounded-full h-3 max-w-xs">
          <div className="h-full rounded-full transition-all" style={{ width: `${Math.min(ratio, 100)}%`, backgroundColor: ratio >= 15 ? '#10B981' : ratio >= 10 ? '#F59E0B' : '#EF4444' }} />
        </div>
        <span className="text-gray-500">Texto: {String(details.textSize)}B / HTML: {String(details.htmlSize)}B</span>
      </div>
    )
  }

  // Safe Browsing threats
  if (metricId === 'safe_browsing' && Array.isArray(details.threatTypes) && (details.threatTypes as string[]).length > 0) {
    return (
      <div className="mt-2 rounded-lg bg-red-100 border border-red-300 p-3">
        <p className="text-xs font-bold text-red-700 mb-1">AMENAZAS DETECTADAS POR GOOGLE</p>
        <div className="flex flex-wrap gap-1.5">
          {(details.threatTypes as string[]).map((t, i) => (
            <span key={i} className="text-xs px-2 py-0.5 bg-red-200 rounded text-red-800 font-medium">{t}</span>
          ))}
        </div>
      </div>
    )
  }

  // Theme/core vulnerabilities (same format as plugin vulns)
  if ((metricId === 'theme_vulnerabilities' || metricId === 'core_vulnerabilities') && Array.isArray(details.vulnerabilities)) {
    const vulns = details.vulnerabilities as Array<Record<string, unknown>>
    if (vulns.length === 0) return null
    return (
      <div className="mt-2 space-y-1.5">
        {vulns.map((v, i) => (
          <div key={i} className="rounded-lg border border-red-200 bg-red-50/50 p-2 text-xs">
            <span className="font-medium text-gray-900">{String(v.name || v.cve || 'Vulnerabilidad')}</span>
            {v.cvssScore != null && Number(v.cvssScore) > 0 && <span className="ml-2 px-1.5 py-0.5 rounded bg-red-500 text-white text-[10px] font-bold">{Number(v.cvssScore).toFixed(1)}</span>}
            {v.fixedInVersion != null && <span className="ml-2 text-emerald-600">Fix: v{String(v.fixedInVersion)}</span>}
          </div>
        ))}
      </div>
    )
  }

  // Sitemap details
  if (metricId === 'sitemap' && (details.url || details.count)) {
    return (
      <div className="mt-2 text-xs text-gray-600 space-y-0.5">
        {details.url != null && <div>URL: <span className="font-mono">{String(details.url)}</span></div>}
        {details.isIndex === true && <div>Tipo: Sitemap Index</div>}
        {Number(details.count) > 0 && <div>{details.isIndex ? 'Sub-sitemaps' : 'URLs'}: <b>{String(details.count)}</b></div>}
      </div>
    )
  }

  // Robots.txt details
  if (metricId === 'robots' && (details.lineCount || details.disallowCount)) {
    return (
      <div className="mt-2 flex gap-3 text-xs">
        <span className="px-2 py-1 rounded bg-gray-50 border border-gray-200">Directivas: <b>{String(details.lineCount)}</b></span>
        <span className="px-2 py-1 rounded bg-gray-50 border border-gray-200">Disallow: <b>{String(details.disallowCount)}</b></span>
        {details.hasSitemap === true && <span className="px-2 py-1 rounded bg-emerald-50 border border-emerald-200 text-emerald-700">Incluye Sitemap</span>}
      </div>
    )
  }

  // Hreflang languages
  if (metricId === 'hreflang' && Array.isArray(details.languages)) {
    return (
      <div className="mt-2 flex flex-wrap gap-1.5">
        {(details.languages as string[]).map((l, i) => (
          <span key={i} className="text-xs px-2 py-0.5 bg-indigo-50 border border-indigo-200 rounded text-indigo-700 font-mono">{l}</span>
        ))}
      </div>
    )
  }

  // Mixed content count
  if (metricId === 'mixed_content' && Number(details.count) > 0) {
    return (
      <div className="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
        {Number(details.count)} recursos cargados por HTTP inseguro. Buscar <code className="font-mono bg-amber-100 px-1 rounded">src="http://</code> en el código y cambiar a <code className="font-mono bg-amber-100 px-1 rounded">https://</code>
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
