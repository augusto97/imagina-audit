import { useEffect, useState, useCallback, memo } from 'react'
import { useParams, Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Database, ArrowRight, LayoutDashboard, ListChecks, Boxes } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { useAdmin } from '@/hooks/useAdmin'
import api from '@/lib/api'
import LeadReportNav from './LeadReportNav'
import type { AuditResult, ModuleResult } from '@/types/audit'

import { ReportHeader } from './report/ReportHeader'
import { ExecutiveSummary } from './report/ExecutiveSummary'
import { TechStackSummary } from './report/TechStackSummary'
import { ActionPlan } from './report/ActionPlan'
import { ModulesAccordion } from './report/ModulesAccordion'
import { ModuleScoreGrid } from './report/ModuleScoreGrid'
import { getAllMetricsByLevel, type ChecklistState } from './report/helpers'

/**
 * Orquestador del reporte técnico. Estructura de 3 tabs:
 *   - Resumen: vista de un vistazo (executive + tech + banner snapshot)
 *   - Plan de acción: checklist de críticos + importantes
 *   - Detalles por módulo: deep-dive técnico por cada módulo auditado
 *
 * El estado del tab activo se guarda en el query param ?tab=, así el
 * operador puede compartir links a un tab específico (se implementa en
 * fase E; por ahora se mantiene solo en state local).
 */
function TechnicalReport() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const { fetchLeadDetail, pinAudit } = useAdmin()
  const [result, setResult] = useState<AuditResult | null>(null)
  const [checklist, setChecklist] = useState<ChecklistState>({})
  const [loading, setLoading] = useState(true)
  const [snapshotModule, setSnapshotModule] = useState<ModuleResult | null>(null)
  const [isPinned, setIsPinned] = useState(false)
  const [focusedModuleId, setFocusedModuleId] = useState<string | null>(null)

  // Tab activo sincronizado con ?tab= en la URL. Permite compartir links
  // al tab específico y usar back/forward del navegador.
  const [searchParams, setSearchParams] = useSearchParams()
  const rawTab = searchParams.get('tab')
  const activeTab: 'summary' | 'plan' | 'modules' =
    rawTab === 'plan' || rawTab === 'modules' ? rawTab : 'summary'

  const setActiveTab = useCallback((tab: 'summary' | 'plan' | 'modules') => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (tab === 'summary') next.delete('tab')
      else next.set('tab', tab)
      return next
    }, { replace: true })
  }, [setSearchParams])

  // Click en un mini-gauge del Resumen: saltar al tab Detalles y marcar
  // el módulo como focused para que el acordeón lo expanda.
  const handleModuleClick = useCallback((moduleId: string) => {
    setFocusedModuleId(moduleId)
    setActiveTab('modules')
  }, [setActiveTab])

  useEffect(() => {
    if (!id) return
    api.get('/admin/snapshot.php', { params: { audit_id: id } })
      .then(res => setSnapshotModule(res.data?.data?.analysis || null))
      .catch(() => setSnapshotModule(null))
  }, [id])

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
  }, [id, fetchLeadDetail])

  const toggleCheck = useCallback((metricId: string) => {
    if (!id) return
    let previousCompleted = false
    setChecklist(prev => {
      previousCompleted = prev[metricId]?.completed ?? false
      const newVal = !previousCompleted
      return { ...prev, [metricId]: { completed: newVal, notes: prev[metricId]?.notes ?? null, completedAt: newVal ? new Date().toISOString() : null } }
    })
    api.put('/admin/checklist.php', { auditId: id, metricId, completed: !previousCompleted }).catch(() => {
      setChecklist(prev => ({ ...prev, [metricId]: { completed: previousCompleted, notes: prev[metricId]?.notes ?? null, completedAt: null } }))
    })
  }, [id])

  const handleTogglePin = useCallback(async () => {
    if (!id) return
    const newVal = !isPinned
    setIsPinned(newVal)
    try { await pinAudit(id, newVal) }
    catch { setIsPinned(!newVal) }
  }, [id, isPinned, pinAudit])

  if (loading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-48 rounded-2xl" /></div>
  }
  if (!result) {
    return <div className="text-center py-12 text-[var(--text-secondary)]">{t('report.not_found')}</div>
  }

  const criticalMetrics = getAllMetricsByLevel(result, 'critical')
  const warningMetrics = getAllMetricsByLevel(result, 'warning')
  const totalPlanItems = criticalMetrics.length + warningMetrics.length
  const donePlanItems = [...criticalMetrics, ...warningMetrics].filter(m => checklist[m.id]?.completed).length

  return (
    <div className="space-y-5">
      {id && <LeadReportNav auditId={id} domain={result.domain} />}
      <ReportHeader result={result} isPinned={isPinned} onTogglePin={handleTogglePin} />

      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as typeof activeTab)}>
        <TabsList>
          <TabsTrigger value="summary">
            <LayoutDashboard className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.tab_summary')}
          </TabsTrigger>
          <TabsTrigger value="plan">
            <ListChecks className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.tab_plan')}
            {totalPlanItems > 0 && (
              <Badge variant="secondary" className="ml-1.5 text-[10px]">
                {donePlanItems}/{totalPlanItems}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="modules">
            <Boxes className="h-4 w-4 mr-1" strokeWidth={1.5} /> {t('report.tab_modules')}
            <Badge variant="secondary" className="ml-1.5 text-[10px]">
              {result.modules.length + (snapshotModule ? 1 : 0)}
            </Badge>
          </TabsTrigger>
        </TabsList>

        {/* ─── Tab 1: Resumen ───────────────────────────────────── */}
        <TabsContent value="summary" className="mt-4 space-y-5">
          <ExecutiveSummary
            result={result}
            criticalCount={criticalMetrics.length}
            warningCount={warningMetrics.length}
            snapshotModule={snapshotModule}
          />
          <ModuleScoreGrid
            modules={snapshotModule ? [...result.modules, snapshotModule] : result.modules}
            onModuleClick={handleModuleClick}
          />
          {result.techStack && (
            <TechStackSummary techStack={result.techStack} scanDuration={result.scanDurationMs} />
          )}
          {result.isWordPress && id && (
            <SnapshotAvailabilityBanner auditId={id} hasSnapshot={!!snapshotModule} />
          )}
          {totalPlanItems > 0 && (
            <Card className="cursor-pointer border-[var(--accent-primary)]/20 bg-[var(--accent-primary)]/5 transition-colors hover:bg-[var(--accent-primary)]/10" onClick={() => setActiveTab('plan')}>
              <CardContent className="flex items-center justify-between gap-3 py-4">
                <div>
                  <p className="font-semibold text-[var(--text-primary)]">
                    {t('report.plan_cta_title', { total: totalPlanItems, critical: criticalMetrics.length, warning: warningMetrics.length })}
                  </p>
                  <p className="text-xs text-[var(--text-secondary)]">
                    {t('report.plan_cta_subtitle')}
                  </p>
                </div>
                <ArrowRight className="h-5 w-5 text-[var(--accent-primary)]" />
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* ─── Tab 2: Plan de acción ────────────────────────────── */}
        <TabsContent value="plan" className="mt-4">
          <ActionPlan
            critical={criticalMetrics}
            warning={warningMetrics}
            checklist={checklist}
            onToggle={toggleCheck}
          />
        </TabsContent>

        {/* ─── Tab 3: Detalles por módulo ────────────────────────── */}
        <TabsContent value="modules" className="mt-4">
          <ModulesAccordion
            modules={snapshotModule ? [...result.modules, snapshotModule] : result.modules}
            focusedModuleId={focusedModuleId}
          />
        </TabsContent>
      </Tabs>
    </div>
  )
}

/**
 * Banner que invita a ir a la pestaña de análisis interno.
 */
function SnapshotAvailabilityBanner({ auditId, hasSnapshot }: { auditId: string; hasSnapshot: boolean }) {
  const { t } = useTranslation()
  return (
    <Card className={hasSnapshot ? 'border-emerald-200 bg-emerald-50/50' : 'border-blue-200 bg-blue-50/50'}>
      <CardContent className="flex flex-wrap items-center gap-3 py-3">
        <Database className={`h-5 w-5 shrink-0 ${hasSnapshot ? 'text-emerald-600' : 'text-blue-600'}`} strokeWidth={1.5} />
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-[var(--text-primary)]">
            {hasSnapshot ? t('report.snapshot_connected_title') : t('report.snapshot_missing_title')}
          </p>
          <p className="text-xs text-[var(--text-secondary)]">
            {hasSnapshot ? t('report.snapshot_connected_body') : t('report.snapshot_missing_body')}
          </p>
        </div>
        <Link
          to={`/admin/leads/${auditId}/internal`}
          className="inline-flex items-center gap-1 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] shadow-sm hover:bg-[var(--bg-secondary)]"
        >
          {hasSnapshot ? t('report.snapshot_view_analysis') : t('report.snapshot_connect')} <ArrowRight className="h-3 w-3" />
        </Link>
      </CardContent>
    </Card>
  )
}

export default memo(TechnicalReport)
