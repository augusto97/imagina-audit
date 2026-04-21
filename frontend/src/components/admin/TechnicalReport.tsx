import { useEffect, useState, useCallback, memo } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { Database, ArrowRight } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import type { AuditResult, ModuleResult } from '@/types/audit'

import { ReportHeader } from './report/ReportHeader'
import { ExecutiveSummary } from './report/ExecutiveSummary'
import { TechStackSummary } from './report/TechStackSummary'
import { ActionPlan } from './report/ActionPlan'
import { ModuleDetail } from './report/ModuleDetail'
import { getAllMetricsByLevel, type ChecklistState } from './report/helpers'

/**
 * Orquestador del reporte técnico.
 *
 * Responsabilidades:
 *  - Cargar la auditoría, el checklist y el snapshot (si existe).
 *  - Manejar el toggle del checklist con actualización optimista.
 *  - Componer las sub-secciones (header, resumen, action plan, detalles).
 *
 * Toda la presentación vive en subcomponentes bajo `report/`.
 */
function TechnicalReport() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { fetchLeadDetail, pinAudit } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [checklist, setChecklist] = useState<ChecklistState>({})
  const [loading, setLoading] = useState(true)
  const [snapshotModule, setSnapshotModule] = useState<ModuleResult | null>(null)
  const [snapshotReloadKey] = useState(0)
  const [isPinned, setIsPinned] = useState(false)

  useEffect(() => {
    if (!id) return
    api.get('/admin/snapshot.php', { params: { audit_id: id } })
      .then(res => setSnapshotModule(res.data?.data?.analysis || null))
      .catch(() => setSnapshotModule(null))
  }, [id, snapshotReloadKey])

  useEffect(() => {
    if (!id) return
    Promise.all([
      fetchLeadDetail(id),
      api.get('/admin/checklist.php', { params: { audit_id: id } }).then(r => r.data?.data).catch(() => [])
    ]).then(([audit, items]: [AuditResult & { isPinned?: boolean }, Array<{ metric_id: string; completed: number; notes: string | null; completed_at: string | null }>]) => {
      setResult(audit)
      setIsPinned(!!audit?.isPinned)
      const state: ChecklistState = {}
      for (const item of items || []) {
        state[item.metric_id] = { completed: item.completed === 1, notes: item.notes, completedAt: item.completed_at }
      }
      setChecklist(state)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [id, fetchLeadDetail, snapshotReloadKey])

  const toggleCheck = useCallback((metricId: string) => {
    if (!id) return
    let previousCompleted = false
    setChecklist(prev => {
      previousCompleted = prev[metricId]?.completed ?? false
      const newVal = !previousCompleted
      return { ...prev, [metricId]: { completed: newVal, notes: prev[metricId]?.notes ?? null, completedAt: newVal ? new Date().toISOString() : null } }
    })
    api.put('/admin/checklist.php', { auditId: id, metricId, completed: !previousCompleted }).catch(() => {
      // Revertir en caso de error
      setChecklist(prev => ({ ...prev, [metricId]: { completed: previousCompleted, notes: prev[metricId]?.notes ?? null, completedAt: null } }))
    })
  }, [id])

  const handleBack = useCallback(() => navigate('/admin/leads'), [navigate])

  const handleTogglePin = useCallback(async () => {
    if (!id) return
    const newVal = !isPinned
    setIsPinned(newVal) // optimista
    try {
      await pinAudit(id, newVal)
    } catch {
      setIsPinned(!newVal) // revertir si falla
    }
  }, [id, isPinned, pinAudit])

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
      <ReportHeader result={result} isPinned={isPinned} onBack={handleBack} onTogglePin={handleTogglePin} />
      <ExecutiveSummary result={result} criticalCount={criticalMetrics.length} warningCount={warningMetrics.length} snapshotModule={snapshotModule} />
      {result.techStack && <TechStackSummary techStack={result.techStack} scanDuration={result.scanDurationMs} />}
      {result.isWordPress && id && (
        <SnapshotAvailabilityBanner auditId={id} hasSnapshot={!!snapshotModule} />
      )}
      <ActionPlan critical={criticalMetrics} warning={warningMetrics} checklist={checklist} onToggle={toggleCheck} />
      {result.modules.map(m => (
        <ModuleDetail key={m.id} module={m} />
      ))}
      {snapshotModule && <ModuleDetail module={snapshotModule} />}
    </div>
  )
}

/**
 * Banner que invita a ir a la pestaña de análisis interno. Si ya hay
 * snapshot, ofrece ver el detalle; si no, explica cómo subirlo.
 */
function SnapshotAvailabilityBanner({ auditId, hasSnapshot }: { auditId: string; hasSnapshot: boolean }) {
  return (
    <Card className={hasSnapshot ? 'border-emerald-200 bg-emerald-50/50' : 'border-blue-200 bg-blue-50/50'}>
      <CardContent className="flex flex-wrap items-center gap-3 py-3">
        <Database className={`h-5 w-5 shrink-0 ${hasSnapshot ? 'text-emerald-600' : 'text-blue-600'}`} strokeWidth={1.5} />
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-[var(--text-primary)]">
            {hasSnapshot ? 'Análisis interno conectado' : '¿Necesitas datos internos?'}
          </p>
          <p className="text-xs text-[var(--text-secondary)]">
            {hasSnapshot
              ? 'Plugins con versiones reales, BD, cron, seguridad interna y más — en la pestaña Análisis interno.'
              : 'Sube el JSON del plugin wp-snapshot para ver plugins, vulnerabilidades, BD, cron y configuración interna.'}
          </p>
        </div>
        <Link
          to={`/admin/leads/${auditId}/internal`}
          className="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] shadow-sm hover:bg-[var(--bg-secondary)]"
        >
          {hasSnapshot ? 'Ver análisis' : 'Conectar snapshot'} <ArrowRight className="h-3 w-3" />
        </Link>
      </CardContent>
    </Card>
  )
}

export default memo(TechnicalReport)
