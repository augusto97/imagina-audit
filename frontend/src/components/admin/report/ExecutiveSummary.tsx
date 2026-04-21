import { memo } from 'react'
import { useTranslation } from 'react-i18next'
import { getLevelColor } from '@/lib/utils'
import type { AuditResult, ModuleResult } from '@/types/audit'

/**
 * Resumen ejecutivo con 4 KPIs: score global, críticos, importantes,
 * tipo de sitio. La lista de módulos ya NO vive aquí — ahora hay un
 * `ModuleScoreGrid` con mini-gauges en el tab Resumen.
 */
export const ExecutiveSummary = memo(function ExecutiveSummary({
  result,
  criticalCount,
  warningCount,
}: {
  result: AuditResult
  criticalCount: number
  warningCount: number
  /** Mantenido por compatibilidad hacia atrás; ya no se renderiza. */
  snapshotModule?: ModuleResult | null
}) {
  const { t } = useTranslation()
  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">{t('report.executive_summary')}</h2>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <SummaryCard
          label={t('report.executive_global_score')}
          value={`${result.globalScore}/100`}
          color={getLevelColor(result.globalLevel)}
        />
        <SummaryCard
          label={t('report.executive_critical')}
          value={String(criticalCount)}
          color={criticalCount > 0 ? 'var(--color-critical)' : 'var(--text-tertiary)'}
        />
        <SummaryCard
          label={t('report.executive_important')}
          value={String(warningCount)}
          color={warningCount > 0 ? 'var(--color-warning)' : 'var(--text-tertiary)'}
        />
        <SummaryCard
          label={t('report.executive_site_type')}
          value={result.isWordPress ? t('report.executive_site_wordpress') : t('report.executive_site_external')}
          color="var(--accent-primary)"
        />
      </div>
    </div>
  )
})

function SummaryCard({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <div className="rounded-xl bg-[var(--bg-secondary)] p-3 text-center">
      <p className="text-xs text-[var(--text-tertiary)] mb-1">{label}</p>
      <p className="text-2xl font-bold" style={{ color }}>{value}</p>
    </div>
  )
}
