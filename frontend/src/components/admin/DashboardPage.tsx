import { useEffect, useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { FileSearch, UserCheck, Gauge, Blocks, RefreshCw, Loader2 } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { useAdmin } from '@/hooks/useAdmin'
import { KPICard } from './dashboard/KPICard'
import { SystemStatusBanner } from './dashboard/SystemStatusBanner'
import { TrendChart } from './dashboard/TrendChart'
import { ScoreDistribution } from './dashboard/ScoreDistribution'
import { RecurringDomains } from './dashboard/RecurringDomains'
import { RecentAuditsTable } from './dashboard/RecentAuditsTable'
import { IntegrationsCard } from './dashboard/IntegrationsCard'
import type { DashboardData } from '@/types/dashboard'

/**
 * Dashboard admin — vista consolidada del estado del sistema y de la
 * actividad de auditorías. Una sola request a /admin/dashboard trae
 * todos los datos necesarios (11 bloques agregados en el backend).
 *
 * Layout (grid vertical):
 *   - Header con refresh
 *   - Row 1: 4 KPIs primarios
 *   - Row 2: Status banner (5 sub-sistemas)
 *   - Row 3: Trend 30 días (2/3) + Integrations (1/3)
 *   - Row 4: Score distribution (1/2) + Recurring domains (1/2)
 *   - Row 5: Recent audits (full width)
 */
export default function DashboardPage() {
  const { t } = useTranslation()
  const { fetchDashboard } = useAdmin()
  const [data, setData] = useState<DashboardData | null>(null)
  const [refreshing, setRefreshing] = useState(false)
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null)

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true)
    const d = await fetchDashboard()
    if (d) setData(d as DashboardData)
    setLastUpdated(new Date())
    setRefreshing(false)
  }, [fetchDashboard])

  useEffect(() => { load() }, [load])

  if (!data) return <DashboardSkeleton />

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">{t('dashboard.title')}</h1>
          <p className="mt-1 text-sm text-[var(--text-secondary)]">{t('dashboard.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2 text-xs text-[var(--text-tertiary)]">
          {lastUpdated && <span>{t('dashboard.updated_at', { time: lastUpdated.toLocaleTimeString() })}</span>}
          <Button variant="outline" size="sm" onClick={() => load(true)} disabled={refreshing}>
            {refreshing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" strokeWidth={1.5} />}
            {t('common.refresh')}
          </Button>
        </div>
      </div>

      {/* Row 1: KPIs primarios */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KPICard
          label={t('dashboard.kpi_total_audits')}
          value={data.audits.total}
          icon={<FileSearch className="h-5 w-5 text-white" strokeWidth={1.5} />}
          gradient="from-[#0CC0DF] to-[#0A9DB8]"
          bgGlow="bg-[#0CC0DF]/8"
          delay={0}
          subtext={
            <div className="flex gap-3">
              <span><b className="text-[var(--text-primary)]">{data.audits.today}</b> {t('dashboard.kpi_today')}</span>
              <span><b className="text-[var(--text-primary)]">{data.audits.thisWeek}</b> {t('dashboard.kpi_7d')}</span>
              <span><b className="text-[var(--text-primary)]">{data.audits.thisMonth}</b> {t('dashboard.kpi_30d')}</span>
            </div>
          }
        />
        <KPICard
          label={t('dashboard.kpi_avg_score')}
          value={data.audits.averageScore}
          suffix="/100"
          icon={<Gauge className="h-5 w-5 text-white" strokeWidth={1.5} />}
          gradient="from-violet-400 to-violet-600"
          bgGlow="bg-violet-500/8"
          delay={0.08}
          subtext={<TrendDelta current={data.audits.averageScore7d} overall={data.audits.averageScore} label={t('dashboard.kpi_avg_last_7d')} noDataLabel={t('dashboard.kpi_avg_no_data_7d')} />}
        />
        <KPICard
          label={t('dashboard.kpi_conversion')}
          value={data.leads.conversionRate}
          suffix="%"
          icon={<UserCheck className="h-5 w-5 text-white" strokeWidth={1.5} />}
          gradient="from-emerald-400 to-emerald-600"
          bgGlow="bg-emerald-500/8"
          delay={0.16}
          subtext={
            <>
              <b className="text-[var(--text-primary)]">{data.leads.total}</b> {t('dashboard.kpi_with_contact')}
              <span className="text-[var(--text-tertiary)]"> · {t('dashboard.kpi_of')} {data.audits.total}</span>
            </>
          }
        />
        <KPICard
          label={t('dashboard.kpi_wordpress_rate')}
          value={data.audits.wpRate}
          suffix="%"
          icon={<Blocks className="h-5 w-5 text-white" strokeWidth={1.5} />}
          gradient="from-amber-400 to-amber-600"
          bgGlow="bg-amber-500/8"
          delay={0.24}
          subtext={
            <>
              <b className="text-[var(--text-primary)]">{data.audits.wpCount}</b> {t('dashboard.kpi_wordpress_count')}
              <span className="text-[var(--text-tertiary)]"> · {data.audits.nonWpCount} {t('dashboard.kpi_external_count')}</span>
            </>
          }
        />
      </div>

      {/* Row 2: Status banner (5 sub-sistemas) */}
      <SystemStatusBanner data={data} />

      {/* Row 3: Trend 30d + Integrations */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <TrendChart data={data.trend30d} />
        </div>
        <div>
          <IntegrationsCard
            snapshots={data.snapshots}
            vulnerabilities={data.vulnerabilities}
            pinned={data.audits.pinned}
          />
        </div>
      </div>

      {/* Row 4: Score distribution + Recurring domains */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <ScoreDistribution data={data.scoreDistribution} />
        <RecurringDomains domains={data.recurringDomains} />
      </div>

      {/* Row 5: Recent audits table */}
      <RecentAuditsTable audits={data.recentAudits} />
    </div>
  )
}

/**
 * Delta textual: muestra el score 7d comparado con el histórico.
 * Si son (casi) iguales, muestra neutro; si difiere >0.5 muestra flecha.
 */
function TrendDelta({ current, overall, label, noDataLabel }: { current: number; overall: number; label: string; noDataLabel: string }) {
  const diff = current - overall
  const abs = Math.abs(diff)
  if (current === 0) {
    return <span className="text-[var(--text-tertiary)]">{noDataLabel}</span>
  }
  if (abs < 0.5) {
    return <span><b className="text-[var(--text-primary)]">{current}</b> {label}</span>
  }
  const up = diff > 0
  const color = up ? 'text-emerald-600' : 'text-red-600'
  const arrow = up ? '▲' : '▼'
  return (
    <span>
      <b className="text-[var(--text-primary)]">{current}</b> {label}{' '}
      <span className={color}>{arrow} {abs.toFixed(1)}</span>
    </span>
  )
}

function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-8 w-48" />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-32 rounded-2xl" />)}
      </div>
      <Skeleton className="h-20 rounded-2xl" />
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Skeleton className="h-72 rounded-2xl lg:col-span-2" />
        <Skeleton className="h-72 rounded-2xl" />
      </div>
      <Skeleton className="h-80 rounded-2xl" />
    </div>
  )
}
