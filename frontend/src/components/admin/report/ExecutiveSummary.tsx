import { memo } from 'react'
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
  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">Resumen Ejecutivo</h2>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <SummaryCard
          label="Score Global"
          value={`${result.globalScore}/100`}
          color={getLevelColor(result.globalLevel)}
        />
        <SummaryCard
          label="Críticos"
          value={String(criticalCount)}
          color={criticalCount > 0 ? 'var(--color-critical)' : 'var(--text-tertiary)'}
        />
        <SummaryCard
          label="Importantes"
          value={String(warningCount)}
          color={warningCount > 0 ? 'var(--color-warning)' : 'var(--text-tertiary)'}
        />
        <SummaryCard
          label="Tipo de sitio"
          value={result.isWordPress ? 'WordPress' : 'Externo'}
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
