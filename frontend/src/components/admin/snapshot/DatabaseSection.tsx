import { Database as DbIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { SectionCard, KpiTile, IssueList } from './ui'
import type { SnapshotReport } from '@/types/snapshotReport'

/**
 * Peso de la DB + top 15 tablas + MyISAM + stats de housekeeping
 * (autoload, revisiones, transients, orphaned meta).
 */
export default function DatabaseSection({ report }: { report: SnapshotReport }) {
  const { t, i18n } = useTranslation()
  const s = report.database.summary
  const localeTag = i18n.language || 'en'
  const formatRows = (n: number) => Number.isFinite(n) ? n.toLocaleString(localeTag) : String(n)

  return (
    <SectionCard
      title={t('report.snap_db_title')}
      subtitle={t('report.snap_db_subtitle', {
        size: s.sizeHuman,
        tables: s.tables,
        rows: s.rows?.toLocaleString?.(localeTag) ?? s.rows,
      })}
      icon={<DbIcon className="h-4 w-4 text-[var(--accent-primary)]" strokeWidth={1.75} />}
    >
      {/* KPIs */}
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <KpiTile label={t('report.snap_db_kpi_size')} value={String(s.sizeHuman || '—')} hint={t('report.snap_db_hint_tables', { count: s.tables })} />
        <KpiTile
          label={t('report.snap_db_kpi_autoload')}
          value={String(s.autoloadHuman || '—')}
          tone={Number(s.autoloadBytes) > 1048576 ? 'warning' : 'good'}
          hint={t('report.snap_db_hint_options', { used: s.autoloadOptions, total: s.totalOptions })}
        />
        <KpiTile
          label={t('report.snap_db_kpi_revisions')}
          value={Number(s.revisions ?? 0)}
          tone={Number(s.revisions) > 500 ? 'warning' : 'good'}
        />
        <KpiTile
          label={t('report.snap_db_kpi_transients')}
          value={Number(s.transients ?? 0)}
          tone={Number(s.transients) > 1000 ? 'warning' : 'neutral'}
        />
      </div>

      {/* Info DB */}
      <div className="mb-4 grid gap-2 sm:grid-cols-3 text-xs">
        <InfoBox label={t('report.snap_db_info_prefix')} value={String(s.prefix || '—')} mono />
        <InfoBox label={t('report.snap_db_info_charset')} value={String(s.charset || '—')} mono />
        <InfoBox label={t('report.snap_db_info_collation')} value={String(s.collation || 'default')} mono />
      </div>

      {/* Top tablas */}
      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        {t('report.snap_db_top_tables')}
      </h4>
      <div className="mb-4 overflow-x-auto rounded-lg border border-[var(--border-default)]">
        <table className="w-full text-xs">
          <thead className="bg-[var(--bg-secondary)] text-left text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">
            <tr>
              <th className="px-3 py-2">{t('report.snap_col_table')}</th>
              <th className="px-3 py-2 text-right">{t('report.snap_col_rows')}</th>
              <th className="px-3 py-2 text-right">{t('report.snap_col_size')}</th>
              <th className="px-3 py-2">{t('report.snap_col_engine')}</th>
              <th className="px-3 py-2">{t('report.snap_col_collation')}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border-default)]">
            {report.database.topTables.map((tbl) => (
              <tr key={String(tbl.name)} className="hover:bg-[var(--bg-secondary)]">
                <td className="px-3 py-1.5 font-mono text-[11px]">{String(tbl.name)}</td>
                <td className="px-3 py-1.5 text-right tabular-nums">{formatRows(Number(tbl.rows ?? 0))}</td>
                <td className="px-3 py-1.5 text-right tabular-nums font-medium">{Number(tbl.sizeMb ?? 0)} MB</td>
                <td className="px-3 py-1.5">
                  <span className={`font-mono text-[10px] ${String(tbl.engine).toLowerCase() === 'myisam' ? 'text-amber-600' : 'text-[var(--text-secondary)]'}`}>
                    {String(tbl.engine || '—')}
                  </span>
                </td>
                <td className="px-3 py-1.5 text-[10px] text-[var(--text-tertiary)]">{String(tbl.collation || '—')}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Tablas MyISAM */}
      {report.database.myisamTables.length > 0 && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
          <p className="mb-1 text-xs font-semibold text-amber-900">
            {t('report.snap_db_myisam_title', { count: report.database.myisamTables.length })}
          </p>
          <p className="mb-2 text-[11px] text-amber-900/80">
            {t('report.snap_db_myisam_intro')}
          </p>
          <div className="flex flex-wrap gap-1">
            {report.database.myisamTables.map((tbl) => (
              <code key={tbl.name} className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px]">{tbl.name}</code>
            ))}
          </div>
        </div>
      )}

      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
        {t('report.snap_db_actionable')}
      </h4>
      <IssueList issues={report.database.issues} />
    </SectionCard>
  )
}

function InfoBox({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="rounded-lg border border-[var(--border-default)] px-3 py-2">
      <div className="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">{label}</div>
      <div className={`text-sm font-medium text-[var(--text-primary)] ${mono ? 'font-mono' : ''}`}>{value}</div>
    </div>
  )
}
