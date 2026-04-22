import { LayoutDashboard, Globe, Server, Database as DbIcon, Users as UsersIcon, Gauge, ShieldAlert } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Vista general: primera card del dashboard. Muestra el estado del sitio
 * de un vistazo — identidad + KPIs de cada dimensión para que el operador
 * sepa dónde mirar más a fondo.
 */
export default function OverviewSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const site = report.overview.site
  const k = report.overview.kpis

  const outdated = Number(k.pluginsOutdated ?? 0)
  const vulnerable = Number((report.plugins.summary.vulnerable as number) ?? 0)
  const cacheActive = Number(k.cacheActive ?? 0)
  const cacheStack = (k.cacheStack as { page: boolean; object: boolean; opcache: boolean }) || { page: false, object: false, opcache: false }
  const critical = Number(k.securityCritical ?? 0)
  const warnings = Number(k.securityWarning ?? 0)

  return (
    <SectionCard
      title={t('report.snap_overview_title')}
      subtitle={t('report.snap_overview_subtitle')}
      icon={<LayoutDashboard className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* Stack técnico del sitio */}
      <div className="mb-4 grid grid-cols-2 gap-2 rounded-lg bg-[var(--bg-secondary)] p-3 text-xs sm:grid-cols-4">
        <StackLine icon={<Globe className="h-3.5 w-3.5" />} label={t('report.snap_overview_stack_wp')} value={String(site.wpVersion || '—')} />
        <StackLine icon={<Server className="h-3.5 w-3.5" />} label={t('report.snap_overview_stack_php')} value={String(site.phpVersion || '—')} />
        <StackLine icon={<DbIcon className="h-3.5 w-3.5" />} label={t('report.snap_overview_stack_db')} value={String(site.db || '—')} />
        <StackLine icon={<Server className="h-3.5 w-3.5" />} label={t('report.snap_overview_stack_server')} value={String(site.server || '—')} />
      </div>

      {/* KPIs por dimensión */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <KpiTile
          label={t('report.snap_overview_kpi_plugins_active')}
          value={Number(k.pluginsActive ?? 0)}
          suffix={`/ ${k.pluginsTotal ?? 0}`}
          hint={Number(k.pluginsInactive ?? 0) > 0 ? t('report.snap_overview_hint_inactive', { count: k.pluginsInactive }) : undefined}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_plugins_outdated')}
          value={outdated}
          tone={outdated > 0 ? (outdated >= 5 ? 'critical' : 'warning') : 'good'}
          hint={outdated === 0 ? t('report.snap_overview_hint_all_updated') : t('report.snap_overview_hint_pending_updates')}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_vulnerable')}
          value={vulnerable}
          tone={vulnerable > 0 ? 'critical' : 'good'}
          hint={t('report.snap_overview_hint_per_wpvuln')}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_cache_stack')}
          value={`${cacheActive}/3`}
          tone={cacheActive === 3 ? 'good' : cacheActive >= 1 ? 'warning' : 'critical'}
          hint={[
            cacheStack.page ? 'page' : null,
            cacheStack.object ? 'object' : null,
            cacheStack.opcache ? 'opcache' : null,
          ].filter(Boolean).join(' · ') || t('report.snap_overview_hint_none_active')}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_database')}
          value={String(k.dbSizeHuman || '—')}
          hint={t('report.snap_overview_hint_tables', { count: k.dbTables ?? 0 })}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_users')}
          value={Number(k.users ?? 0)}
          tone={Number(k.administrators ?? 0) > 3 ? 'warning' : 'neutral'}
          hint={t('report.snap_overview_hint_admins', { count: k.administrators })}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_security_critical')}
          value={critical}
          tone={critical > 0 ? 'critical' : 'good'}
          hint={warnings > 0 ? t('report.snap_overview_hint_warnings', { count: warnings }) : t('report.snap_overview_hint_internal_checks')}
        />
        <KpiTile
          label={t('report.snap_overview_kpi_theme')}
          value={String(k.activeTheme || '—')}
          hint={k.activeThemeHasUpdate ? t('report.snap_overview_hint_update_available') : undefined}
          tone={k.activeThemeHasUpdate ? 'warning' : 'neutral'}
        />
      </div>

      {/* Mini leyenda para el lector técnico */}
      <div className="mt-4 flex flex-wrap items-center gap-4 text-[10px] text-[var(--text-tertiary)]">
        <span className="inline-flex items-center gap-1"><Gauge className="h-3 w-3" /> {t('report.snap_overview_legend_perf')}</span>
        <span className="inline-flex items-center gap-1"><ShieldAlert className="h-3 w-3" /> {t('report.snap_overview_legend_security')}</span>
        <span className="inline-flex items-center gap-1"><UsersIcon className="h-3 w-3" /> {t('report.snap_overview_legend_access')}</span>
      </div>
    </SectionCard>
  )
}

function StackLine({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="flex items-center gap-1.5 text-[var(--text-secondary)]">
      <span className="text-[var(--text-tertiary)]">{icon}</span>
      <span className="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">{label}</span>
      <span className="ml-auto truncate font-mono text-[var(--text-primary)]" title={value}>{value}</span>
    </div>
  )
}
