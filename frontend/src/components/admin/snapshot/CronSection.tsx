import { Clock, AlertCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * WP Cron: KPIs + lista de overdue + próximos + hooks más frecuentes
 * (para detectar scheduling leak).
 */
export default function CronSection({ report }: { report: SnapshotReport }) {
  const { t } = useTranslation()
  const s = report.cron.summary
  const subtitle = t('report.snap_cron_subtitle', { total: s.total, hooks: s.uniqueHooks })
    + (s.wpCronDisabled ? t('report.snap_cron_subtitle_disabled') : '')

  return (
    <SectionCard
      title={t('report.snap_cron_title')}
      subtitle={subtitle}
      icon={<Clock className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label={t('report.snap_cron_kpi_total')} value={Number(s.total ?? 0)} />
        <KpiTile
          label={t('report.snap_cron_kpi_overdue')}
          value={Number(s.overdue ?? 0)}
          tone={Number(s.overdue) > 0 ? (Number(s.overdue) > 10 ? 'critical' : 'warning') : 'good'}
        />
        <KpiTile
          label={t('report.snap_cron_kpi_wpcron')}
          value={s.wpCronDisabled ? t('report.snap_cron_wpcron_server') : t('report.snap_cron_wpcron_internal')}
          hint={s.wpCronDisabled ? t('report.snap_cron_wpcron_hint_external') : t('report.snap_cron_wpcron_hint_traffic')}
          tone={s.wpCronDisabled ? 'info' : 'neutral'}
        />
        <KpiTile label={t('report.snap_cron_kpi_hooks')} value={Number(s.uniqueHooks ?? 0)} />
      </div>

      {/* Overdue */}
      {report.cron.overdue.length > 0 && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
          <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-red-900">
            <AlertCircle className="h-3.5 w-3.5" /> {t('report.snap_cron_overdue_heading', { count: report.cron.overdue.length })}
          </p>
          <div className="space-y-1 text-[11px]">
            {report.cron.overdue.slice(0, 10).map((e, i) => (
              <div key={i} className="flex items-center gap-2 text-red-900">
                <code className="font-mono">{String(e.hook)}</code>
                <span className="text-red-700">{String(e.diff || '')}</span>
                <span className="ml-auto text-[10px] text-red-600">{String(e.schedule || '')}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Próximos */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_cron_upcoming_heading')}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_hook')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_col_when')}</th>
                  <th className="px-2 py-1.5">{t('report.snap_cron_col_freq')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {report.cron.upcoming.slice(0, 12).map((e, i) => (
                  <tr key={i}>
                    <td className="px-2 py-1 font-mono text-[10px] max-w-[180px] truncate" title={String(e.hook)}>{String(e.hook)}</td>
                    <td className="px-2 py-1 text-[var(--text-secondary)]">{String(e.diff)}</td>
                    <td className="px-2 py-1 text-[10px] text-[var(--text-tertiary)]">{String(e.schedule || '—')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Hooks más frecuentes */}
        <div>
          <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
            {t('report.snap_cron_tophooks_heading')}
          </h4>
          <div className="overflow-hidden rounded-lg border border-[var(--border-default)]">
            <table className="w-full text-[11px]">
              <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
                <tr>
                  <th className="px-2 py-1.5">{t('report.snap_col_hook')}</th>
                  <th className="px-2 py-1.5 text-right">{t('report.snap_col_instances')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-default)]">
                {report.cron.topHooks.map((h, i) => (
                  <tr key={i}>
                    <td className="px-2 py-1 font-mono text-[10px] max-w-[220px] truncate" title={h.hook}>{h.hook}</td>
                    <td className="px-2 py-1 text-right tabular-nums">
                      <span className={h.count >= 10 ? 'font-bold text-amber-600' : ''}>{h.count}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div className="mt-4">
        <IssueList issues={report.cron.issues} />
      </div>
    </SectionCard>
  )
}
